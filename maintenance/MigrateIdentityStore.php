<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\IdentityMigration\{InventoryPlanner,MigrationCoordinator,MigrationJournal,DurableCheckpoint,MigrationFieldAdditionLog,ReferenceRewriter};
use ProfessionalWiki\SWS\Persistence\MediaWiki\IdentityMigration\MediaWikiMigrationHost;
use ProfessionalWiki\SWS\SWSExtension;

$basePath=getenv('MW_INSTALL_PATH')!==false?getenv('MW_INSTALL_PATH'):__DIR__.'/../../..';
require_once $basePath.'/maintenance/Maintenance.php';

/** Default is a read-only plan. --apply/--resume operate only on that signed journal. */
class MigrateIdentityStore extends Maintenance {
    public function __construct() {
        parent::__construct();$this->requireExtension('SemanticWikiSchema');
        $this->addDescription('Preview and resume the SWS identity migration. Pause all web edits and job runners before applying.');
        $this->addOption('journal','Private writable absolute journal path',true,true);
        $this->addOption('user','Registered operator with schema, page and content-model edit rights',true,true);
        $this->addOption('apply','Apply the previously generated signed plan');
        $this->addOption('resume','Resume the same signed plan after interruption');
        $this->addOption('maintenance-window','Acknowledge that web writes and job runners are paused');
    }
    public function execute():bool {
        $context=\MediaWiki\Context\RequestContext::getMain(); $previousUser=$context->getUser();
        try {
            $services=$this->getServiceContainer();$user=$services->getUserFactory()->newFromName($this->getOption('user'));
            if($user===null||!$user->isRegistered()||!$user->isAllowed('sws-schema-edit')||!$user->isAllowed('editcontentmodel')) { throw new \RuntimeException('A registered, authorized migration operator is required'); }
            $context->setUser($user);
            $authority=$user;
            $normalizer=static fn(string $name):string => SWSExtension::getInstance()->getSchemaReferenceParser()->localName($name)->name->getText();
            $path=$this->getOption('journal');
            if(!is_string($path)||!str_starts_with($path,'/')) { throw new \RuntimeException('Use an absolute private journal path'); }
            $journal=new MigrationJournal($path,$services->getMainConfig()->get('SecretKey'));
            $host=new MediaWikiMigrationHost($authority,$normalizer);$registry=SWSExtension::getInstance()->newIdentityRegistry();
            if(!$this->hasOption('apply')&&!$this->hasOption('resume')) {
                if(is_file($path)) { throw new \RuntimeException('Journal already exists; choose another path or resume it'); }
                $plan=(new InventoryPlanner($registry,$normalizer))->plan($host->inventory());$journal->write($plan);
                $this->output('Preview only: '.count($plan['schemas']).' schemas, '.count($plan['entities']).' entities, '.count($plan['operations'])." planned operations. No wiki writes.\n");
                return true;
            }
            if($services->getReadOnlyMode()->getReason()!==false) { throw new \RuntimeException('The CLI must be writable; pause web writes and jobs separately before applying'); }
            if(!$this->hasOption('maintenance-window')) { throw new \RuntimeException('Pause web edits and job runners, then supply --maintenance-window'); }
            $data=$journal->read();$plan=$data['plan'];
            if($plan['wiki']!==SWSExtension::getInstance()->getSubjectIdParser()->getLocalSourceKey()||$plan['owner']!==SWSExtension::getInstance()->getIdentityOwner($authority)) { throw new \RuntimeException('Journal belongs to another wiki or operator'); }
            $host->usePlan($plan);
            $fieldLog=new MigrationFieldAdditionLog($path.'.fields.json',$services->getMainConfig()->get('SecretKey'),hash('sha256',ReferenceRewriter::encode($plan)));
            $host->enableFieldRepair($fieldLog,fn():bool=>$this->commitTransactionRound(__METHOD__),fn(string $line)=>$this->output($line."\n"));
            $checkpoint=new DurableCheckpoint(fn():bool=>$this->commitTransactionRound(__METHOD__),function(array $state)use($journal,$plan):void {
                $journal->checkpoint($state);
                $this->output('Reservations '.$state['reservation'].'/'.count($plan['reservations']).'; operations '.$state['operation'].'/'.count($plan['operations']).'; projections '.($state['rebuilt']?'complete':'pending')."\n");
            });
            $state=(new MigrationCoordinator($registry,$host))->apply($plan,$data['state'],$checkpoint->save(...));
            $this->output('Automatic schema fields: '.count($fieldLog->entries()).'; report: '.$fieldLog->path."\n");
            foreach($fieldLog->entries()as$entry){$this->output($entry['schemaId'].' ['.$entry['schemaLabel'].'] / '.$entry['field'].' | '.($entry['sourceSchema']===null?'text placeholder; REVIEW':'definition from '.$entry['sourceSchema']).' | '.$entry['status']."\n");}
            return $state['rebuilt'];
        } catch(\Throwable $error) { $this->rollbackTransactionRound(__METHOD__);$this->error($error->getMessage());return false; }
        finally { $context->setUser($previousUser); }
    }
}
$maintClass=MigrateIdentityStore::class;
require_once RUN_MAINTENANCE_IF_MAIN;
