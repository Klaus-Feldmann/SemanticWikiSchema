<?php
require __DIR__.'/bootstrap.php';
use ProfessionalWiki\SWS\Domain\Mapping\MappingName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Domain\Identity\SchemaId;
use ProfessionalWiki\SWS\Persistence\MediaWiki\MappingPersistenceDeserializer;
$script=__DIR__.'/../examples/hdto/build_mapping.py';
$command=['python3',$script,'--artwork','S21','--artist','S22','--museum','S23','--creator','F1','--keeper','F2','--image','F3'];
$process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes);
if(!is_resource($process))throw new RuntimeException('Cannot start local mapping generator');
$json=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
if(proc_close($process)!==0)throw new RuntimeException($err);
$mapping=(new MappingPersistenceDeserializer())->deserialize(new MappingName('HDTO'),$json);
$checks=0;function hcheck($ok,$why){global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
$art=$mapping->forSchema(SchemaReference::identified(new SchemaId('S21')));
hcheck($art!==null,'Generated ID-based mapping deserializes');
hcheck($art->subject->class==='hdto:HC3_Tangible_Heritage_Entity','HDTO class preserved');
hcheck($art->properties->get('F1')->predicate==='crm:P14_carried_out_by','Creator predicate preserved');
hcheck($art->properties->get('F1')->node==='production','Production relation preserved');
hcheck($art->properties->get('F3')->predicate==='hdto:HP9i_has_visual_representation','Image predicate preserved');
hcheck($mapping->forSchema(SchemaReference::identified(new SchemaId('S22')))->subject->class==='crm:E21_Person','Artist class');
hcheck($mapping->forSchema(SchemaReference::identified(new SchemaId('S23')))->subject->class==='crm:E74_Group','Institution class');
hcheck($mapping->forSchema(SchemaReference::identified(new SchemaId('S24')))===null,'Unmapped schema not reassigned');
echo "$checks HDTO generator/domain-deserializer checks passed; no wiki writes.\n";
