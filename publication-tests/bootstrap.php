<?php
$root=dirname(__DIR__).'/';
$vendor=getenv('SWS_TEST_VENDOR') ?: $root.'vendor/autoload.php';
if(is_file($vendor))require $vendor;
spl_autoload_register(static function(string $class)use($root): void {
 foreach(['ProfessionalWiki\\SWS\\Tests\\'=>'tests/phpunit/','ProfessionalWiki\\SWS\\'=>'src/','SWSBaseBridge\\'=>'SchemaBaseBridge/src/']as $prefix=>$directory){
  if(str_starts_with($class,$prefix)){
   $file=$root.$directory.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
   if(is_file($file))require $file;
  }
 }
},true,true);

// Use the exact target-core encoder in standalone tests; never replace a loaded core function.
if ( !function_exists( 'wfEscapeWikiText' ) ) { require __DIR__ . '/mediawiki-wikitext-escape.php'; }
