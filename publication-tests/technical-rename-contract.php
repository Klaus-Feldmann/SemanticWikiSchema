<?php
require __DIR__.'/bootstrap.php';
$root=dirname(__DIR__);
$main=json_decode(file_get_contents($root.'/extension.json'),true,512,JSON_THROW_ON_ERROR);
$bridge=json_decode(file_get_contents($root.'/SchemaBaseBridge/extension.json'),true,512,JSON_THROW_ON_ERROR);
$composer=json_decode(file_get_contents($root.'/composer.json'),true,512,JSON_THROW_ON_ERROR);
function swsRenameCheck(bool $pass,string $message):void {if(!$pass)throw new RuntimeException($message);}
swsRenameCheck($main['name']==='SemanticWikiSchema','Public extension name');
swsRenameCheck(isset($composer['autoload']['psr-4']['ProfessionalWiki\\SWS\\']),'PHP namespace');
foreach($main['ContentHandlers']as$model=>$class){swsRenameCheck(str_starts_with($model,'SWS'),'Content model '.$model);}
swsRenameCheck(\ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository::SLOT_NAME==='sws','Subject slot');
foreach($main['RestRoutes']as$route){swsRenameCheck(str_starts_with($route['path'],'/sws/'),'REST route '.$route['path']);}
foreach($bridge['RestRoutes']as$route){swsRenameCheck(str_starts_with($route['path'],'/swsbase/'),'Bridge route');}
foreach(['sws_subject','sws_relation','sws_subject_page','sws_rebuild_runs']as$table){
 swsRenameCheck(is_file($root.'/sql/'.$table.'.json'),'Abstract schema '.$table);
 $prefix=['sws_subject'=>'swss_','sws_relation'=>'swsr_','sws_subject_page'=>'swssp_','sws_rebuild_runs'=>'swsrr_'][$table];
 foreach(json_decode(file_get_contents($root.'/sql/'.$table.'.json'),true) as $definition)foreach($definition['columns']as$column)swsRenameCheck(str_starts_with($column['name'],$prefix),'Column '.$column['name']);
 foreach(['mysql','sqlite','postgres']as$db){swsRenameCheck(str_contains(file_get_contents($root.'/sql/'.$db.'/'.$table.'.sql'),$table),'SQL '.$table);}
}
swsRenameCheck(array_column($main['namespaces'],'id')===[7474,7475,7476,7477,7478,7479,7480,7481],'Existing numeric namespaces');
swsRenameCheck(is_file($root.'/resources/ext.sws/dist/sws.js'),'Built frontend');
swsRenameCheck(!is_dir($root.'/resources/ext.neowiki'),'Obsolete frontend directory');
swsRenameCheck(str_contains(file_get_contents($root.'/integrations/smw/Wikiseiten/Modul-WikiSchema.lua'),"require('mw.sws')"),'Public query module');
foreach([$main,$bridge]as$manifest){
 foreach($manifest['AutoloadNamespaces']as$prefix=>$directory){
  foreach(($manifest['Hooks']??[])as$handler){
   foreach((array)$handler as$entry){
    if(!is_string($entry)||!str_starts_with($entry,$prefix))continue;
    $class=explode('::',$entry)[0];$path=str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    $base=$manifest['name']==='SchemaBaseBridge'?$root.'/SchemaBaseBridge/':$root.'/';
    swsRenameCheck(is_file($base.$directory.$path),'Hook class '.$class);
   }
  }
 }
}
echo "SWS technical rename: slot, models, SQL definitions, namespaces, REST routes, hook classes and built frontend verified.\n";
