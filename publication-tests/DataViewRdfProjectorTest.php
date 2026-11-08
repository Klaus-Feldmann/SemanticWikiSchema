<?php
use PHPUnit\Framework\TestCase;
use SWSBaseBridge\DataViewRdfProjector;
use ProfessionalWiki\SWS\Domain\Rdf\{Iri, Literal, RdfNamespaces, RdfFormat};
use ProfessionalWiki\SWS\Infrastructure\Rdf\HardfRdfSerializer;
use pietercolpaert\hardf\{TriGParser, Util};
use Psr\Log\NullLogger;

final class DataViewRdfProjectorTest extends TestCase {
 private const BASE='https://example.org';
 private function field(string $name,string $type,array $own=[],?array $external=null):array {
  return ['name'=>$name,'type'=>$type,'ownValues'=>$own,'external'=>$external,'effectiveOrigin'=>$own!==[]?'own':'external','effectiveValues'=>$own!==[]?$own:($external['values']??[]),'status'=>'resolved'];
 }
 public function view():array {
  return ['version'=>1,'readOnly'=>true,'complete'=>true,'scope'=>'current','retrievedAt'=>'2026-11-03T12:00:00Z','language'=>'de',
   'page'=>['id'=>42,'title'=>'Person','url'=>'https://example.org/wiki/Person'],'mainSubjectId'=>'sF1FCQPNcwQdw4B',
   'identities'=>['wikidata'=>['id'=>'Q5','url'=>'https://www.wikidata.org/wiki/Q5'],'wikibase-local'=>['id'=>'Q5','url'=>'https://example.org/wiki/Q5']],
   'displayDescription'=>['text'=>'Eigene Beschreibung','language'=>'de','sources'=>[['source'=>'page','id'=>'42','url'=>'https://example.org/wiki/Person']]],
   'snapshotJson'=>'unrelated custom root attribute',
   'extra'=>['empty'=>[],'null'=>null,'number'=>1.0,'nested'=>['text'=>"Zeile 1\n\"Zweite Zeile\""]],
   'subjects'=>[['id'=>'sF1FCQPNcwQdw4B','displayName'=>'Person','schema'=>'Person','isMainSubject'=>true,'fields'=>[
    $this->field('Ort','text',[['kind'=>'text','value'=>'Eigener Ort']],['source'=>'wikidata','propertyId'=>'P19','values'=>[['kind'=>'entity','id'=>'Q2','label'=>'Quellenort','url'=>'https://www.wikidata.org/wiki/Q2']]]),
    $this->field('Klassenzuordnung','text',[],['source'=>'wikidata','values'=>[['kind'=>'entity','id'=>'Q5','label'=>'Mensch','url'=>'https://www.wikidata.org/wiki/Q5']]]),
    $this->field('Lokale Klasse','text',[],['source'=>'wikibase-local','values'=>[['kind'=>'entity','id'=>'Q5','label'=>'Lokaler Mensch','url'=>'https://example.org/wiki/Q5']]]),
    $this->field('Zahl','number',[['kind'=>'number','value'=>0]]),$this->field('Wahr','boolean',[['kind'=>'boolean','value'=>false]]),
    $this->field('Quelle','url',[['kind'=>'url','value'=>['url'=>'https://example.net/?a=1&b=2','label'=>'Titel','caption'=>'Quelle','captionPage'=>'Vermerk:Quelle']]]),
    $this->field('Sterbedatum','historicalDate',[['kind'=>'historicalDate','value'=>'-0347','precision'=>'year','calendar'=>'julian']]),
    $this->field('Zeit','dateTime',[['kind'=>'dateTime','value'=>'2026-11-03T14:00:00+02:00']]),
    $this->field('Auswahl','select',[['kind'=>'select','value'=>'o123','id'=>'o123','label'=>'Abhandlung']]),
    $this->field('Bild','image',[['kind'=>'image','value'=>'commons:Portrait.jpg','repository'=>'commons','filename'=>'Portrait.jpg']]),
    $this->field('Mehrsprachig','monolingualText',[['kind'=>'monolingualText','value'=>['language'=>'de','text'=>'Mensch']]]),
    $this->field('Notiz','wikitext',[['kind'=>'wikitext','value'=>'{{Nicht ausführen}}']]),
   ]]]];
 }
 private function serializer():HardfRdfSerializer {return new HardfRdfSerializer((new RdfNamespaces(self::BASE))->prefixMap(),new NullLogger());}
 public function testBothRdfFormatsAreValidAndRecoverIdenticalFullSnapshot():void {
  $view=$this->view();$quads=(new DataViewRdfProjector(new RdfNamespaces(self::BASE)))->project($view);$sets=[];
  foreach([RdfFormat::Turtle,RdfFormat::TriG]as$format){
   $text=$this->serializer()->serialize($quads,$format);$parsed=(new TriGParser())->parse($text);self::assertNotEmpty($parsed);$triples=[];
   foreach($parsed as$t){$triples[]=$t['subject']."\t".$t['predicate']."\t".$t['object'];
    if($t['predicate']===self::BASE.'/ontology/data-view/snapshotJson')self::assertSame($view,json_decode(Util::getLiteralValue($t['object']),true,512,JSON_THROW_ON_ERROR));
   }
   $triples=array_values(array_unique($triples));sort($triples);$sets[]=$triples;
  }
  self::assertSame($sets[0],$sets[1]);
 }
 public function testOnlyEffectiveValuesAreDirectAssertions():void {
  $quads=(new DataViewRdfProjector(new RdfNamespaces(self::BASE)))->project($this->view())->asArray();
  $direct=array_values(array_filter($quads,static fn($q)=>$q->subject->value===self::BASE.'/entity/sF1FCQPNcwQdw4B'&&$q->predicate->value===self::BASE.'/prop/Ort'));
  self::assertCount(1,$direct);self::assertSame('Eigener Ort',$direct[0]->object->lexicalForm);
  self::assertNotEmpty(array_filter($quads,static fn($q)=>$q->predicate->value===RdfNamespaces::RDF.'object'&&$q->object instanceof Iri&&$q->object->value==='http://www.wikidata.org/entity/Q2'));
 }
 public function testTypesLabelsDatesAndDistinctSourceIds():void {
  $quads=(new DataViewRdfProjector(new RdfNamespaces(self::BASE)))->project($this->view())->asArray();$values=[];
  foreach($quads as$q)if($q->subject->value===self::BASE.'/entity/sF1FCQPNcwQdw4B')$values[$q->predicate->value]=$q->object;
  self::assertSame('0',$values[self::BASE.'/prop/Zahl']->lexicalForm);self::assertSame('false',$values[self::BASE.'/prop/Wahr']->lexicalForm);
  self::assertSame('-0347',$values[self::BASE.'/prop/Sterbedatum']->lexicalForm);self::assertSame('2026-11-03T14:00:00+02:00',$values[self::BASE.'/prop/Zeit']->lexicalForm);
  self::assertSame('Abhandlung',$values[self::BASE.'/prop/Auswahl']->lexicalForm);self::assertSame('http://www.wikidata.org/entity/Q5',$values[self::BASE.'/prop/Klassenzuordnung']->value);
  self::assertSame('https://example.org/wiki/Q5',$values[self::BASE.'/prop/Lokale_Klasse']->value);
 }

 public function testLanguageTaggedSourceTextRemainsAnRdfLanguageLiteral():void {
  $view=$this->view();
  $view['subjects'][0]['fields'][]=$this->field('Geburtsname','text',[],[
   'source'=>'wikidata','propertyId'=>'P1477','values'=>[['kind'=>'text','value'=>'Avram Noam Chomsky','language'=>'en','rank'=>'normal']]
  ]);
  $quads=(new DataViewRdfProjector(new RdfNamespaces(self::BASE)))->project($view)->asArray();
  $direct=array_values(array_filter($quads,static fn($q)=>$q->subject->value===self::BASE.'/entity/sF1FCQPNcwQdw4B'&&$q->predicate->value===self::BASE.'/prop/Geburtsname'));
  self::assertCount(1,$direct);
  self::assertInstanceOf(Literal::class,$direct[0]->object);
  self::assertSame('Avram Noam Chomsky',$direct[0]->object->lexicalForm);
  self::assertSame('en',$direct[0]->object->languageTag);
 }

 public function testRelationsAndSeveralSubjectsRemainDistinct():void {
  $view=$this->view();$target='sF16iHXBMTQdHDY';
  $view['subjects'][]=['id'=>$target,'displayName'=>'Ziel','schema'=>'Person','isMainSubject'=>false,'fields'=>[]];
  $view['subjects'][0]['fields'][]=$this->field('Bezug','relation',[[ 'kind'=>'relation','value'=>['id'=>'rF16iHXBMTQdHDY','target'=>$target], 'target'=>['id'=>$target,'label'=>'Ziel','status'=>'resolved'] ]]);
  $quads=(new DataViewRdfProjector(new RdfNamespaces(self::BASE)))->project($view)->asArray();
  $links=array_filter($quads,static fn($q)=>$q->subject->value===self::BASE.'/entity/sF1FCQPNcwQdw4B' && $q->predicate->value===self::BASE.'/prop/Bezug');
  self::assertCount(1,$links);self::assertSame(self::BASE.'/entity/'.$target,array_values($links)[0]->object->value);
  $graphs=array_unique(array_map(static fn($q)=>$q->graph->value,$quads));
  self::assertTrue(count(array_filter($graphs,static fn($g)=>str_ends_with($g,'/graph/own')))===1);
  self::assertTrue(count(array_filter($graphs,static fn($g)=>str_ends_with($g,'/graph/wikidata')))===1);
  self::assertTrue(count(array_filter($graphs,static fn($g)=>str_ends_with($g,'/graph/wikibase-local')))===1);
 }

 public function testLocalConceptUrisComeFromTheRepositoryCallback():void {
  $projector=new DataViewRdfProjector(new RdfNamespaces(self::BASE),static fn(string $source,string $id):?string=>$source==='wikibase-local'?'https://local-repo.example/concept/'.$id:null);
  $quads=$projector->project($this->view())->asArray();
  $direct=array_values(array_filter($quads,static fn($q)=>$q->subject->value===self::BASE.'/entity/sF1FCQPNcwQdw4B' && $q->predicate->value===self::BASE.'/prop/Lokale_Klasse'));
  self::assertSame('https://local-repo.example/concept/Q5',$direct[0]->object->value);
 }
}
