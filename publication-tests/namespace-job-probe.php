<?php
namespace {
    interface GenericParameterJob {}
    class Job {
        public const JOB_NO_EXPLICIT_TRX_ROUND=1;
        public bool $removeDuplicates=false; public int $executionFlags=0;
        public string $type; public array $params;
        public function __construct(string $type,mixed $titleOrParams=[],array $params=[]) {
            $this->type=$type;$this->params=is_array($titleOrParams)?$titleOrParams:$params;
        }
    }
}
namespace MediaWiki\Title { class Title {} }
namespace {
    $root=$argv[1]??dirname(__DIR__);require $root.'/publication-tests/bootstrap.php';
    $namespace=array_key_first(json_decode(file_get_contents($root.'/composer.json'),true)['autoload']['psr-4']);
    $extension=json_decode(file_get_contents($root.'/extension.json'),true);$jobs=[];
    foreach ($extension['JobClasses'] as $type=>$registration) {
        $class=is_array($registration)?$registration['class']:$registration;
        $params=match($type){'swsGraphRebuild'=>['runId'=>17],'swsRebuildLastEditorPages'=>['userId'=>7,'userName'=>'Sophos'],'swsSchemaWikitextRefresh'=>['page'=>true,'after'=>123],default=>throw new RuntimeException('Unexpected job type')};
        $job=$type==='swsSchemaWikitextRefresh'?new $class(new \MediaWiki\Title\Title(),$params):new $class($params);
        if ($job->type!==$type||$job->params!==$params) { throw new RuntimeException('Job payload changed'); }
        $jobs[$type]=['type'=>$job->type,'params'=>$job->params,'removeDuplicates'=>$job->removeDuplicates,'executionFlags'=>$job->executionFlags];
    }
    echo json_encode($jobs,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}
