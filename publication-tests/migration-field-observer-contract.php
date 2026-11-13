<?php
declare(strict_types=1);
require __DIR__.'/identity-direct-fields-contract.php';
use ProfessionalWiki\SWS\Application\IdentityMigration\MigrationFieldRepairScope as RepairScope;
use ProfessionalWiki\SWS\EntryPoints\EffectiveFieldLookup;
$nObserved=0;function observed(bool $ok,string $why):void{global$nObserved;$nObserved++;if(!$ok)throw new RuntimeException($why);}
$probe=RepairScope::attempt(fn()=>(new EffectiveFieldLookup(new MediaWiki\Context\TestContext()))->read(new MediaWiki\Title\Title(),['field'=>'Fehlend']));
observed($probe['error'] instanceof InvalidArgumentException&&$probe['missing'][0]['schemaId']==='S9'&&$probe['missing'][0]['field']==='Fehlend','Actual authorized field read identifies exact missing schema field');
$probe=RepairScope::attempt(fn()=>(new EffectiveFieldLookup(new MediaWiki\Context\TestContext()))->read(new MediaWiki\Title\Title(),['field'=>'Notiz']));
observed($probe['error']===null&&$probe['missing']===[],'Existing field is not an automatic addition request');
try{(new EffectiveFieldLookup(new MediaWiki\Context\TestContext()))->read(new MediaWiki\Title\Title(),['field'=>'Outside']);}catch(InvalidArgumentException){}
observed(RepairScope::attempt(static fn()=>null)['missing']===[],'Normal calls cannot retain a repair request');
echo "$nObserved actual EffectiveFieldLookup migration-observation/read-only checks passed.\n";
