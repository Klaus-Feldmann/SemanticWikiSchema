<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\Semantic;
use InvalidArgumentException;

/** Config is supplied by the validated WikiSchemaSmwMapping server settings. */
final class AnnotationPlanner {
    public function __construct(private readonly array $config) {}

    public function build(array $result): AnnotationPlan {
        if ($result['missing'] ?? false) { return new AnnotationPlan([],[]); }
        if (!is_array($this->config['properties'] ?? null)) { throw new InvalidArgumentException('WikiSchemaSmwMapping: properties must be a table'); }
        $names=$this->config['properties'][$result['schema']['name']] ?? [];
        if (!is_array($names)) { throw new InvalidArgumentException('WikiSchemaSmwMapping: schema mapping must be a table'); }
        $rows=[];$objects=[];$seen=[];$formatter=new SmwValueFormatter();
        foreach($result['fields'] as $field) {
            if ($field['type'] === 'image') { continue; }
            if ($field['type'] === 'page' && ($field['assignCategories'] ?? false)) { continue; }
            if ($field['type'] === 'relation' && ($field['status'] ?? '') === 'deferred-relation') {
                continue;
            }
            $occupied=$field['values']!==[] || $field['hasStoredValues'];
            if($occupied && !in_array($field['type'],['page','text','select','url','number','boolean','historicalDate','date','dateTime'],true)) { throw new InvalidArgumentException('Unsupported occupied field: '.$field['name']); }
            if($field['values']===[]) { continue; }
            $property=$names[$field['name']] ?? $field['name'];
            if(!is_string($property)) { throw new InvalidArgumentException('Invalid property mapping'); }
            $property=trim($property);
            $this->validateName($property,'property');
            foreach($field['values'] as $value) {
                $stored=$formatter->format($field,$value);
                $entry=$value['metadata']['urlEntry'] ?? null;
                $caption=$entry['caption'] ?? null;
                $target=$caption!==null && trim($caption)!=='' ? trim($caption) : $property;
                $this->validateName($target,'target');
                if($entry!==null) {
                    $mapping=$this->config['urlEntries'] ?? null;
                    if(!is_array($mapping)) { throw new InvalidArgumentException('URL mapping missing'); }
                    $args=['sws-url-'.sha1($result['subject']['id']."\0".$field['name']."\0".(count($objects)+1))];
                    $data=['url'=>$stored,'label'=>$entry['label'] ?? null,'caption'=>$entry['caption'] ?? null,'captionPage'=>$entry['captionPage'] ?? null,'field'=>$field['name'],'subject'=>$result['subject']['id']];
                    foreach(['url','label','caption','captionPage','field','subject'] as $key) {
                        $item=$data[$key];
                        if($item!==null && $item!=='') {
                            $mapped=$mapping[$key] ?? null;
                            if(!is_string($mapped)) { throw new InvalidArgumentException('Invalid URL mapping'); }
                            $this->validateName($mapped,'subobject');
                            $args[]=$mapped.'='.$item;
                        }
                    }
                    $objects[]=$args;
                }
                $key=json_encode([$target,$stored],JSON_THROW_ON_ERROR);
                if(!isset($seen[$key])) { $seen[$key]=true;$rows[]=$target.'='.$stored; }
            }
        }
        return new AnnotationPlan($rows,$objects);
    }
    private function validateName(string $name,string $context): void {
        // Match the current Lua rules, rather than silently tightening legal names.
        if($name==='' || in_array($name,['template','@json'],true) || str_starts_with($name,'+') || str_contains($name,'::') || preg_match('/[=|\[\]{}<>\r\n]/u',$name) || $context==='target' && preg_match('/[\x00-\x1f\x7f#]/u',$name)) {
            throw new InvalidArgumentException('Invalid property name');
        }
    }
}
