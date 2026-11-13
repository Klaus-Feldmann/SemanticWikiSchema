<?php
declare(strict_types=1);
require __DIR__.'/source-store-read-contract.php';
use CollectorHarness\State as C;
use ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity;
use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,PropertyDefinitions,PropertyDefinition};
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\SWSExtension;
C::$mode='cache';C::$cache=[];C::$requests=[];C::$failIds=[];C::$denied=[];C::$cachedQ=[943=>'Q41390'];
SWSExtension::$schema=new Schema(new SchemaName('Person'),'',new PropertyDefinitions(['Ort'=>PropertyDefinition::fromJson(['type'=>'text','externalProperty'=>['source'=>'wikidata','id'=>'P19']],$registry)]),false);
$own=readStoreSnapshot($first);$own->setIdentity(new LocalizedIdentity('de',['de'=>'Eigene Benennung'],['de'=>'Eigene Forschung']));$own->setLabel(new SubjectLabel('Eigene Benennung'));setStorePage($own);
C::$entities['wikidata']=[
 'Q41390'=>['id'=>'Q41390','labels'=>['de'=>['value'=>'Quellenname']], 'claims'=>['P19'=>[['rank'=>'normal','mainsnak'=>['snaktype'=>'value','datatype'=>'wikibase-item','datavalue'=>['value'=>['id'=>'Q14960']]]]]]],
 'P19'=>['id'=>'P19','datatype'=>'wikibase-item','labels'=>['de'=>['value'=>'Geburtsort']]],
 'Q14960'=>['id'=>'Q14960','labels'=>['de'=>['value'=>'Brünn']]],
];
$lookup=new SWSBaseBridge\DataViewLookup($context);$before=C::$revision->getContent('sws')->getText();
$v1=$lookup->read($title,'de');scheck($v1['subjects'][0]['fields'][0]['effectiveValues'][0]['label']==='Brünn','Cache source label is initially displayed');
C::$entities['wikidata']['Q14960']['labels']['de']['value']='Brno';
$v2=$lookup->read($title,'de');scheck($v2['subjects'][0]['fields'][0]['effectiveValues'][0]['label']==='Brünn','Existing cache entry retains source label until renewal');
C::$cache=[];$v3=$lookup->read($title,'de');scheck($v3['subjects'][0]['fields'][0]['effectiveValues'][0]['label']==='Brno','Renewed cache displays changed Wikidata label');
scheck($v3['subjects'][0]['label']==='Eigene Benennung'&&(array)$v3['subjects'][0]['labels']===['de'=>'Eigene Benennung'],'Changed external label never replaces own SWS identity label');
scheck(C::$revision->getContent('sws')->getText()===$before,'Source-label cache renewal never rewrites own persisted identity or source snapshot');
echo "5 D5 source-label renewal and own-label precedence checks passed (actual cache gateway/data view with controlled sources).\n";
