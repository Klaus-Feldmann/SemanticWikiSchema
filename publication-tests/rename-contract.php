<?php
require __DIR__.'/bootstrap.php';
$root=dirname(__DIR__);
$manifest=json_decode(file_get_contents($root.'/extension.json'),true,512,JSON_THROW_ON_ERROR);
$composer=json_decode(file_get_contents($root.'/composer.json'),true,512,JSON_THROW_ON_ERROR);
$bridge=json_decode(file_get_contents($root.'/SchemaBaseBridge/extension.json'),true,512,JSON_THROW_ON_ERROR);
function renameCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
renameCheck($manifest['name']==='SemanticWikiSchema','registry identity');
renameCheck($manifest['namemsg']==='sws-name','localized name for Special:Version');
foreach (['en', 'de'] as $language) {
 $messages=json_decode(file_get_contents($root.'/i18n/'.$language.'.json'),true,512,JSON_THROW_ON_ERROR);
 foreach (['sws-name','specialpages-group-sws','sws-page-tools-label'] as $key) {
  renameCheck($messages[$key]==='Semantic WikiSchema',"$language display label $key");
 }
}
renameCheck($manifest['ResourceFileModulePaths']['remoteExtPath']==='SemanticWikiSchema','ResourceLoader install path');
renameCheck($composer['name']==='aufeinwort/semantic-wikischema' && $composer['extra']['installer-name']==='SemanticWikiSchema','Composer installation identity');
renameCheck(isset($bridge['requires']['extensions']['SemanticWikiSchema']),'bridge registry dependency');
renameCheck($bridge['name']==='SchemaBaseBridge','bridge registry identity');
renameCheck($bridge['namemsg']==='swsbasebridge-name','bridge name for Special:Version');
foreach (['en', 'de'] as $language) {
 $messages=json_decode(file_get_contents($root.'/SchemaBaseBridge/i18n/'.$language.'.json'),true,512,JSON_THROW_ON_ERROR);
 renameCheck($messages[$bridge['namemsg']]==='SchemaBaseBridge',"$language bridge display label");
}
$badge=ProfessionalWiki\SWS\SWSExtension::poweredByBadge('/w/extensions','1.47.0-wmf.24');
renameCheck($badge['alt']==='Powered by Semantic WikiSchema','badge display name');
foreach(array_merge([$badge['src']],array_column($badge['sources']??[],'srcset'))as$url){
 renameCheck(str_starts_with($url,'/w/extensions/SemanticWikiSchema/'),'new badge path');
 renameCheck(is_file($root.substr($url,strlen('/w/extensions/SemanticWikiSchema'))),'badge asset exists');
}
renameCheck(isset($manifest['config']['SWSRdfBaseUri']),'existing RDF config identity retained');
foreach(glob($root.'/maintenance/*.php')as$file){
 renameCheck(!str_contains(file_get_contents($file),"requireExtension( 'SWS' )"),'maintenance registry identity');
}
echo "SemanticWikiSchema registry, ResourceLoader, Composer, bridge, assets and retained data/config identities verified.\n";
