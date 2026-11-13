<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use ProfessionalWiki\SWS\Application\IdentityMigration\{MigrationWriteScope,ReferenceRewriter};
use SWSBaseBridge\SchemaPropertyBindings;
$old=['propertyDefinitions'=>['Ort'=>['type'=>'text','externalProperty'=>['source'=>'wikidata','id'=>'P19']]]];
$new=$old+['formatVersion'=>2,'id'=>'S2','identityLanguage'=>'de','labels'=>(object)['de'=>'Person'],'descriptions'=>(object)[]];
$json=json_encode($new,JSON_THROW_ON_ERROR);$calls=0;$lookup=function()use(&$calls){$calls++;throw new RuntimeException('Unexpected source request');};
if(MigrationWriteScope::originalSchema($json)!==null)throw new RuntimeException('Unscoped original schema');
MigrationWriteScope::run(function()use($new,$json,$lookup){SchemaPropertyBindings::validate($new,MigrationWriteScope::originalSchema($json),null,$lookup);},[ReferenceRewriter::fingerprint($json)=>$old]);
if($calls!==0)throw new RuntimeException('Migration requested unchanged property datatype');
$changed=$new;$changed['propertyDefinitions']['Ort']['externalProperty']['id']='P20';$caught=false;
MigrationWriteScope::run(function()use($changed,$json,$lookup,&$caught){
 if(MigrationWriteScope::originalSchema(json_encode($changed))!==null)throw new LogicException('Changed payload got an attestation');
 try{SchemaPropertyBindings::validate($changed,[],null,$lookup);}catch(RuntimeException){$caught=true;}
},[ReferenceRewriter::fingerprint($json)=>$old]);
if(!$caught||$calls!==1||MigrationWriteScope::originalSchema($json)!==null)throw new RuntimeException('Schema validation scope leaked');
echo "4 D6 exact-schema-proof/unchanged-datatype/no-source-query/scope checks passed (actual binding validator).\n";
