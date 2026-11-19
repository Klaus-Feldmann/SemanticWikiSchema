<?php
// Every current declared runtime class reference must resolve through its registered prefix.
$root=dirname(__DIR__); require __DIR__.'/bootstrap.php';
if (!class_exists('AEWI\\SWS\\Domain\\Subject\\SubjectId') || class_exists('ProfessionalWiki\\SWS\\Domain\\Subject\\SubjectId')) { throw new RuntimeException('Unexpected class ownership or legacy alias'); }
$extension=json_decode(file_get_contents($root.'/extension.json'),true);$composer=json_decode(file_get_contents($root.'/composer.json'),true);
if (!isset($extension['AutoloadNamespaces']['AEWI\\SWS\\'],$composer['autoload']['psr-4']['AEWI\\SWS\\'])) { throw new RuntimeException('Namespace registrations differ'); }
$count=0;
$check=function(mixed $value)use(&$check,&$count,$root,$extension):void {
    if (is_array($value)) { foreach($value as$key=>$part){$check($key);$check($part);}return; }
    if (!is_string($value)||!preg_match('/\AAEWI\\\\(?:SWS|RedHerb)\\\\/',$value))return;
    [$class,$method]=array_pad(explode('::',$value,2),2,null);
    if (isset($extension['AutoloadClasses'][$class])) { $file=$root.'/'.$extension['AutoloadClasses'][$class]; }
    else {
        $file=null;
        foreach (array_merge($extension['AutoloadNamespaces'],$extension['TestAutoloadNamespaces']??[])as$prefix=>$dir) {
            if (str_starts_with($class,$prefix)) { $file=$root.'/'.$dir.'/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';break; }
        }
    }
    if ($file===null||!is_file($file)) { throw new RuntimeException('Unresolved registration: '.$value); }
    if ($method!==null&&!preg_match('/\bfunction\s+'.preg_quote($method,'/').'\s*\(/',file_get_contents($file))) { throw new RuntimeException('Unresolved method: '.$value); }
    $count++;
};
foreach(['callback','Hooks','JobClasses','ContentHandlers','SearchMappings','SpecialPages','RestRoutes','APIModules']as$key)$check($extension[$key]??[]);
foreach(['src','maintenance','SchemaBaseBridge/src','tests/RedHerb/src']as$dir) {
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir))as$file) {
        if (!$file->isFile()||$file->getExtension()!=='php')continue;
        if (preg_match('/\bProfessionalWiki\\\\|\bprofessionalwiki\\\\/',file_get_contents($file->getPathname()))) { throw new RuntimeException('Active old namespace: '.$file->getPathname()); }
    }
}
echo "$count runtime class/method registrations and active namespace boundaries passed.\n";
