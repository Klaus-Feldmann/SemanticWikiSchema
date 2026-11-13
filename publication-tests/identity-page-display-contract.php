<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use ProfessionalWiki\SWS\Application\Queries\GetPageSubjects\GetPageSubjectsQuery;
use ProfessionalWiki\SWS\Domain\Identity\{SchemaId,LocalizedIdentity};
use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,SchemaReference,PropertyDefinitions};
use ProfessionalWiki\SWS\Domain\Subject\{Subject,SubjectId,SubjectLabel,SubjectMap,StatementList};
use ProfessionalWiki\SWS\Domain\Page\{PageId,PageSubjects};
use ProfessionalWiki\SWS\Tests\TestDoubles\{InMemorySubjectRepository,InMemorySchemaLookup,InMemorySubjectLookup,InMemoryPageIdentifiersLookup,StubPageReadAuthorizer};
use ProfessionalWiki\SWS\Tests\Data\TestNaming;
use ProfessionalWiki\SWS\Presentation\{RestGetPageSubjectsPresenter,SchemaPresentationSerializer};
$checks=0;
function pageCheck(bool $ok,string $why):void{global $checks;if(!$ok)throw new RuntimeException($why);$checks++;}
$lookup=new InMemorySchemaLookup(
 new Schema(new SchemaName('S10'),'',new PropertyDefinitions([]),false,new SchemaId('S10'),new LocalizedIdentity('de',['de'=>'Personendaten'])),
 new Schema(new SchemaName('Legacy'),'',new PropertyDefinitions([]),false)
);
$references=[SchemaReference::identified(new SchemaId('S10')),SchemaReference::local(new SchemaName('Legacy')),SchemaReference::identified(new SchemaId('S10'),'remote'),SchemaReference::sourced('remote',new SchemaName('Legacy'))];
foreach($references as $i=>$ref){
 $repository=new InMemorySubjectRepository();
 $subject=new Subject(new SubjectId('E44'),new SubjectLabel('Kurt Gödel'),$ref,new StatementList());
 $repository->savePageSubjects(new PageSubjects($subject,new SubjectMap()),new PageId(28657));
 $presenter=new RestGetPageSubjectsPresenter();$auth=new StubPageReadAuthorizer(true);
 $query=new GetPageSubjectsQuery($presenter,$repository,TestNaming::newResponseItemFactory($repository,$lookup),new InMemorySubjectLookup(),$lookup,new SchemaPresentationSerializer(),new InMemoryPageIdentifiersLookup(),$auth);
 $query->execute(28657,includeSchemas:true,includeReferencedSubjects:true);$data=$presenter->getJsonArray();
 pageCheck(isset($data['subjects']['E44']),'Stored entity survives display query');
 pageCheck($data['subjects']['E44']['schema']===$ref->toJson(),'Reference shape remains unchanged');
 pageCheck(array_keys($data['schemas'])===($i===0?['S10']:($i===1?['Legacy']:[])),'Local ID and legacy schemas embedded; remote schemas never read locally');
 if($i===0){pageCheck($data['schemas']['S10']['labels']['de']==='Personendaten','Display includes own schema label');if(getenv('SWS_DISPLAY_FIXTURE'))file_put_contents(getenv('SWS_DISPLAY_FIXTURE'),json_encode($data,JSON_THROW_ON_ERROR));}
 $query->execute(28657);pageCheck(!isset($presenter->getJsonArray()['schemas']),'Non-expanded endpoint unchanged');
 $auth->allowed=false;$query->execute(28657,includeSchemas:true);$denied=$presenter->getJsonArray();
 pageCheck($denied['subjects']===[]&&$denied['schemas']===[],'Denied page discloses neither entity nor schema');
}
echo "$checks actual page-query/presenter display checks passed (in-memory persistence, no live writes).\n";
