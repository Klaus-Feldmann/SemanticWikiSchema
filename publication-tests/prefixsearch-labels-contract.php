<?php
namespace MediaWiki {
    class MediaWikiServices {
        public static $instance;
        public $values;
        public static function getInstance() { return self::$instance; }
        public function getMainConfig() { return $this; }
        public function get($key) { return $this->values[$key]; }
        public function getContentLanguage() { return $this; }
        public function getNamespaces() { return [-2=>'Medium',-1=>'Spezial',0 => '',1=>'Diskussion', 14 => 'Kategorie', 10 => 'Vorlage',828=>'Modul']; }
    }
}
namespace MediaWiki\Output {
    class OutputPage {
        public $vars = []; public $modules = []; public $styles = [];
        public function addJsConfigVars($k,$v) { $this->vars[$k]=$v; }
        public function addModules($m) { $this->modules[]=$m; }
        public function addModuleStyles($m) { $this->styles[]=$m; }
    }
}
namespace {
    function wfMessage($k) { check($k === 'article'); return new class { public function text() { return 'Artikel'; } }; }
    function check($value) { if (!$value) { throw new \RuntimeException('Hook test failed'); } }
    require dirname(__DIR__) . '/src/EntryPoints/PrefixSearchHooks.php';
    $manifest=json_decode(file_get_contents(dirname(__DIR__).'/extension.json'),true);
    check($manifest['config']['SWSPrefixSearchEnabled']['value'] === true);
    check($manifest['config']['SWSPrefixSearchNamespaces']['value'] === null);
    check($manifest['config']['SWSAdditionalSubjectNamespaces']['value'] === []);
    check(!isset($manifest['config']['SWSPrefixSearchNamespaceLabels']));
    $s = new \MediaWiki\MediaWikiServices(); \MediaWiki\MediaWikiServices::$instance = $s;
    $s->values=['SWSPrefixSearchEnabled'=>true,'SWSPrefixSearchSubstringFallback'=>true,'SWSPrefixSearchNamespaces'=>[14,0,14,-1,999,'10',10]];
    $out = new \MediaWiki\Output\OutputPage();
    \ProfessionalWiki\SWS\EntryPoints\PrefixSearchHooks::onBeforePageDisplay($out,null);
    check(array_column($out->vars['wgSWSPrefixSearchNamespaces'],'id') === [14,0,10]);
    check(array_column($out->vars['wgSWSPrefixSearchNamespaces'],'label') === ['Kategorie','Artikel','Vorlage']);
    check($out->modules === ['ext.sws.prefixSearch']);
    $c=['search'=>true]; \ProfessionalWiki\SWS\EntryPoints\PrefixSearchHooks::onSkinPageReadyConfig(null,$c); check($c['search']===false);
    $s->values['SWSPrefixSearchNamespaces']=null; $out=new \MediaWiki\Output\OutputPage();
    \ProfessionalWiki\SWS\EntryPoints\PrefixSearchHooks::onBeforePageDisplay($out,null);
    check(array_column($out->vars['wgSWSPrefixSearchNamespaces'],'id') === [0,1,14,10,828]);
    $s->values['SWSPrefixSearchEnabled']=false; $out=new \MediaWiki\Output\OutputPage();
    \ProfessionalWiki\SWS\EntryPoints\PrefixSearchHooks::onBeforePageDisplay($out,null); check($out->modules === []);
    $s->values['SWSPrefixSearchEnabled']=true; $s->values['SWSPrefixSearchNamespaces']=[];
    $out=new \MediaWiki\Output\OutputPage(); \ProfessionalWiki\SWS\EntryPoints\PrefixSearchHooks::onBeforePageDisplay($out,null); check($out->modules === []);
    echo "PASS PHP hook contract: all namespaces including discussions/modules, article message, explicit ordering, namespace 0, duplicate/invalid filtering, enable/disable, empty list.\n";
}
