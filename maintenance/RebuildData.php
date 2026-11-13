<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use ProfessionalWiki\SWS\Application\DataRebuild\DataRebuildCoordinator;
use ProfessionalWiki\SWS\Persistence\MediaWiki\IdentityMigration\MediaWikiDataRebuilder;

$basePath=getenv('MW_INSTALL_PATH')!==false?getenv('MW_INSTALL_PATH'):__DIR__.'/../../..';
require_once $basePath.'/maintenance/Maintenance.php';

/** Repairs current derived data. Existing receipts avoid new store-source requests unless explicitly requested. */
class RebuildData extends Maintenance {
    public function __construct() {
        parent::__construct();$this->requireExtension('SemanticWikiSchema');
        $this->addDescription('Rebuild current SWS hosting/search indices, categories, SMW and graph projections. Does not restore old revisions or allocate new identities.');
        $this->addOption('after','Resume after the last successfully reported page ID',false,true);
        $this->addOption('page','Rebuild one page ID',false,true);
        $this->addOption('refresh-sources','Explicitly recollect configured store sources instead of reusing a valid stored source basis');
        $this->setBatchSize(100);
    }
    public function execute():bool {
        try {
            $after=$this->integerOption('after',0);$batch=$this->getBatchSize();
            $host=new MediaWikiDataRebuilder();$refresh=$this->hasOption('refresh-sources');
            if($this->hasOption('page')) {
                $page=$this->integerOption('page',0);if($page<1) { throw new \InvalidArgumentException('Page ID must be positive'); }
                $host->rebuildPage($page,$refresh);if(!$this->commitTransactionRound(__METHOD__)) { throw new \RuntimeException('Rebuild commit acknowledgement failed'); }$this->output('Rebuilt page '.$page."\n");return true;
            }
            $result=(new DataRebuildCoordinator($host->pageIdsAfter(...),$host->rebuildPage(...)))->run($after,$batch,$refresh,function(array $state):void {
                if(!$this->commitTransactionRound(__METHOD__)) { throw new \RuntimeException('Rebuild commit acknowledgement failed'); }
                $this->output('Completed page '.$state['after'].'; processed '.$state['processed'].'; resume with --after '.$state['after']."\n");
            });
            $this->output('Done: '.$result['processed']." pages rebuilt.\n");return true;
        } catch(\Throwable $error) { $this->rollbackTransactionRound(__METHOD__);$this->error($error->getMessage());return false; }
    }
    private function integerOption(string $name,int $default):int {
        $value=$this->getOption($name,(string)$default);
        if(!is_scalar($value)||!preg_match('/^(0|[1-9][0-9]*)\z/',(string)$value)||(float)$value>PHP_INT_MAX) { throw new \InvalidArgumentException('Invalid --'.$name); }
        return (int)$value;
    }
}
$maintClass=RebuildData::class;
require_once RUN_MAINTENANCE_IF_MAIN;
