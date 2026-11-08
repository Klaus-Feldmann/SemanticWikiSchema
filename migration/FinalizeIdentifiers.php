<?php
/** Run only after source, LocalSettings and query module replacement. */
declare(strict_types=1);
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Title\Title;
use MediaWiki\Registration\ExtensionRegistry;
use ProfessionalWiki\SWS\EntryPoints\Jobs\SchemaWikitextRefreshJob;

require_once (getenv('MW_INSTALL_PATH')?:getcwd()).'/maintenance/Maintenance.php';

final class FinalizeSwsIdentifiers extends Maintenance {
    private bool $maintenanceWasReadOnly = false;
    public function finalSetup(\MediaWiki\Settings\SettingsBuilder $settingsBuilder) {
        parent::finalSetup($settingsBuilder);
        $this->maintenanceWasReadOnly = (bool)$settingsBuilder->getConfig()->get('ReadOnly');
        if (!$this->hasOption('help')) {
            if (!$this->maintenanceWasReadOnly) throw new \RuntimeException('Set wgReadOnly before this maintenance operation.');
            // Process-local override before service initialization. LocalSettings and web requests stay read-only.
            $settingsBuilder->overrideConfigValue('ReadOnly', false);
        }
    }

    public function __construct(){parent::__construct();$this->addDescription('Invalidate only migrated pages and enqueue their normal SWS processing.');$this->addOption('journal','Completed migration VORGANG.json',true,true);}
    public function execute(){
        $services=$this->getServiceContainer();
        $version=ExtensionRegistry::getInstance()->getAllThings()['SemanticWikiSchema']['version']??'';
        if(!str_starts_with($version,'0.1.22'))throw new RuntimeException('Activate 0.1.22 before finalization.');
        $state=json_decode(file_get_contents($this->getOption('journal')),true,512,JSON_THROW_ON_ERROR);
        $db=$services->getConnectionProvider()->getPrimaryDatabase();
        if($state['state']!=='complete'||$state['database']!==$db->getDomainID())throw new RuntimeException('Not a completed migration of this wiki.');
        $old=Title::makeTitle(NS_MEDIAWIKI,'NeoWiki');
        if($this->getConfig()->get('SWSEnableInWikiConfig')&&$old->exists())throw new RuntimeException('Move MediaWiki:NeoWiki to MediaWiki:SWS through MediaWiki before finalization.');
        $module=Title::newFromText('Module:WikiSchema');
        if($module&&$module->exists()){
            $content=$services->getRevisionLookup()->getRevisionByTitle($module)?->getContent('main');
            if($content&&method_exists($content,'getText')&&preg_match('/mw\\.neowiki\\b/',$content->getText()))throw new RuntimeException('Replace Modul:WikiSchema with the supplied mw.sws version first.');
        }
        $queued=0;$purged=0;
        foreach($state['plan']['affectedPageIds']as$id){
            $title=Title::newFromID((int)$id);if(!$title)continue;
            $page=$services->getWikiPageFactory()->newFromTitle($title);
            if(!$page->doPurge())throw new RuntimeException('Page cache purge refused for page ID '.$id);
            $purged++;
            if($page->getRevisionRecord()?->hasSlot('sws')){
                $services->getJobQueueGroup()->push(new SchemaWikitextRefreshJob($title,['page'=>true]));$queued++;
            }
        }
        $this->output("Affected pages purged: $purged; normal SWS processing jobs queued: $queued. No article revisions created. Resume the job runner and verify live data/SMW/graph output.\n");
    }
}
$maintClass=FinalizeSwsIdentifiers::class;
require_once RUN_MAINTENANCE_IF_MAIN;
