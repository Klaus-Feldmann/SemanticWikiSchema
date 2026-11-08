<?php
/** Run from the existing MediaWiki root with maintenance/run.php and this absolute script path. */
declare(strict_types=1);
use MediaWiki\Maintenance\Maintenance;
use SWSMigration\{IdentifierMigration,MediaWikiDatabase};

require_once (getenv('MW_INSTALL_PATH')?:getcwd()).'/maintenance/Maintenance.php';
require_once __DIR__.'/IdentifierMigration.php';
require_once __DIR__.'/MediaWikiDatabase.php';

final class MigrateSwsIdentifiers extends Maintenance {
    private bool $maintenanceWasReadOnly = false;
    public function finalSetup(\MediaWiki\Settings\SettingsBuilder $settingsBuilder) {
        parent::finalSetup($settingsBuilder);
        $this->maintenanceWasReadOnly = (bool)$settingsBuilder->getConfig()->get('ReadOnly');
        if (!$this->hasOption('help') && ($this->hasOption('apply') || $this->hasOption('rollback'))) {
            if (!$this->maintenanceWasReadOnly) throw new \RuntimeException('Set wgReadOnly before this maintenance operation.');
            // Process-local override before service initialization. LocalSettings and web requests stay read-only.
            $settingsBuilder->overrideConfigValue('ReadOnly', false);
        }
    }

    public function __construct(){
        parent::__construct();
        $this->addDescription('Inspect or explicitly migrate NeoWiki/SWS 0.1.20 identifiers to SWS 0.1.22 without rewriting article/subject content. Default: report only.');
        $this->addOption('output','Report directory',false,true);
        $this->addOption('apply','Apply a previously reviewed plan');
        $this->addOption('plan','Absolute path to PLAN.json',false,true);
        $this->addOption('rollback','Absolute path to VORGANG.json',false,true);
        $this->addOption('jobs-stopped','Confirms external job workers and automatic runners are stopped');
        $this->addOption('backup-confirmed','Confirms an independent complete database/files backup exists');
    }
    private function assertQuiescent():void {
        if(!$this->maintenanceWasReadOnly)throw new RuntimeException('Set wgReadOnly during the coordinated switch before applying changes.');
        if(!$this->hasOption('jobs-stopped')||!$this->hasOption('backup-confirmed'))throw new RuntimeException('Explicit --jobs-stopped and --backup-confirmed are required.');
        $group=$this->getServiceContainer()->getJobQueueGroup();
        foreach(['neowikiGraphRebuild','neowikiRebuildLastEditorPages','neowikiSchemaWikitextRefresh','swsGraphRebuild','swsRebuildLastEditorPages','swsSchemaWikitextRefresh']as$type){
            if(!in_array($type,$group->getQueueTypes(),true))continue;
            $queue=$group->get($type);
            if($queue->getSize()+$queue->getDelayedCount()+$queue->getAcquiredCount()+$queue->getAbandonedCount()>0)throw new RuntimeException('Finish or resolve extension jobs before switching: '.$type);
        }
    }
    private function refreshNameCaches():void {
        $services=$this->getServiceContainer();
        $services->getSlotRoleStore()->reloadMap();$services->getContentModelStore()->reloadMap();
        $services->getConnectionProvider()->getPrimaryDatabase()->commit(__METHOD__);
    }
    public function execute(){
        $services=$this->getServiceContainer();
        $db=new MediaWikiDatabase($services->getConnectionProvider()->getPrimaryDatabase());
        $engine=new IdentifierMigration($db);
        $out=$this->getOption('output',sys_get_temp_dir().'/SWS-Wechsel-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)));
        if($this->hasOption('apply')&&$this->hasOption('rollback'))throw new RuntimeException('Choose apply or rollback.');
        if($this->hasOption('rollback')){
            $this->assertQuiescent();$engine->rollback($this->getOption('rollback'));$this->refreshNameCaches();
            $this->output("Original identifiers restored. Keep maintenance mode until old code/configuration is restored.\n");return;
        }
        if($this->hasOption('apply')){
            $this->assertQuiescent();$db->assertDdlSupported();
            if(!$this->hasOption('plan'))throw new RuntimeException('--plan is required.');
            $plan=json_decode(file_get_contents($this->getOption('plan')),true,512,JSON_THROW_ON_ERROR);
            $journal=rtrim($out,'/').'/VORGANG.json';$engine->apply($plan,$journal);$this->refreshNameCaches();
            $this->output('Migration complete; normalized data and numeric IDs unchanged. Journal: '.$journal."\nKeep maintenance mode and job workers stopped until code/configuration/MCP are switched.\n");return;
        }
        $plan=$engine->plan();
        $registry=\MediaWiki\Registration\ExtensionRegistry::getInstance()->getAllThings();
        $plan['sourceExtensionVersions']=array_intersect_key(array_map(static fn($item)=>$item['version']??'', $registry),array_flip(['NeoWiki','SemanticNeoWiki','SemanticWikiSchema']));
        IdentifierMigration::save(rtrim($out,'/').'/PLAN.json',$plan);
        IdentifierMigration::save(rtrim($out,'/').'/BESTAND.json',$engine->snapshot());
        $this->output(json_encode(['report'=>$out,'sourceTables'=>$plan['tables'],'operations'=>count($plan['operations']),'fingerprint'=>$plan['beforeHash'],'changed'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\nReport only: no database changes requested.\n");
    }
}
$maintClass=MigrateSwsIdentifiers::class;
require_once RUN_MAINTENANCE_IF_MAIN;
