<?php
declare( strict_types = 1 );
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/SqliteIdentityConnection.php';
use IdentityHarness\SqliteIdentityConnection;
use ProfessionalWiki\SWS\Application\Identity\IdentityBoundaryResolver;
use ProfessionalWiki\SWS\Application\Rdf\{RdfPageProjector, SubjectIriResolver, OntologyMappingProjector};
use ProfessionalWiki\SWS\Application\Source\SchemaResolver;
use ProfessionalWiki\SWS\Domain\Identity\{IdentityKind, LegacyIdentity, SchemaId, LocalizedIdentity};
use ProfessionalWiki\SWS\Domain\Subject\{Subject, SubjectId, SubjectIdParser, SubjectLabel, StatementList, SubjectMap, SubjectDisplayName};
use ProfessionalWiki\SWS\Domain\Schema\{Schema, SchemaName, SchemaReference, PropertyDefinitions, PropertyName};
use ProfessionalWiki\SWS\Domain\Page\{Page, PageId, PageProperties, PageSubjects};
use ProfessionalWiki\SWS\Domain\Source\SourceRegistry;
use ProfessionalWiki\SWS\Domain\Rdf\{RdfNamespaces, RdfValueMapperRegistry, Literal, RdfFormat};
use ProfessionalWiki\SWS\Domain\Relation\{Relation, RelationId};
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Value\{StringValue, RelationValue};
use ProfessionalWiki\SWS\Persistence\Identity\SqlIdentityRegistry;
use ProfessionalWiki\SWS\Persistence\MediaWiki\MappingPersistenceDeserializer;
use ProfessionalWiki\SWS\Infrastructure\Rdf\HardfRdfSerializer;
use ProfessionalWiki\SWS\Presentation\JsonSchemaSerializer;
use ProfessionalWiki\SWS\EntryPoints\Scribunto\SchemaLuaSerializer;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySource;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence\{Neo4jSubjectUpdater, Neo4jPageSubject, Neo4jValueBuilderRegistry};

$count = 0;
function d4check( bool $ok, string $why ): void { global $count; if ( !$ok ) { throw new RuntimeException( $why ); } $count++; }
function d4refused( Closure $operation, string $why ): void { try { $operation(); } catch ( InvalidArgumentException ) { d4check( true, $why ); return; } throw new RuntimeException( $why ); }
$db = new SqliteIdentityConnection( ':memory:' );
foreach ( ['sws_id_counter','sws_identity','sws_identity_alias'] as $table ) { $db->pdo->exec( str_replace( '/*_*/', 'test_', file_get_contents( __DIR__ . '/../sql/sqlite/' . $table . '.sql' ) ) ); }
foreach ( IdentityKind::cases() as $kind ) { $db->insertIgnore( 'sws_id_counter', ['swsic_kind'=>$kind->value,'swsic_value'=>0] ); }
$registry = new SqlIdentityRegistry( $db );
$s1 = $registry->reserve( IdentityKind::Schema, 'schema-one' ); $s2 = $registry->reserve( IdentityKind::Schema, 'schema-two' );
$e1 = $registry->reserve( IdentityKind::Entity, 'entity-one' ); $e2 = $registry->reserve( IdentityKind::Entity, 'entity-two' );
$old = 's11111111111111'; $oldTarget = 's22222222222222';
$registry->linkLegacy( new LegacyIdentity( IdentityKind::Entity, 'aewi', $old ), $e1 );
$registry->linkLegacy( new LegacyIdentity( IdentityKind::Entity, 'aewi', $oldTarget ), $e2 );
$registry->linkLegacy( new LegacyIdentity( IdentityKind::Schema, 'aewi', 'S1' ), $s2 );
$before = $db->pdo->query( 'SELECT SUM(swsic_value) FROM test_sws_id_counter' )->fetchColumn();
$boundary = new IdentityBoundaryResolver( $registry, 'aewi' );
$raw = new SubjectIdParser( 'aewi' ); $canonical = new SubjectIdParser( 'aewi', $boundary->entity(...) );
d4check( $raw->parseOrThrow( $old )->text === $old, 'Persistence parser retains old revision IDs' );
d4check( $canonical->parseOrThrow( $old )->text === 'E1', 'Current boundary resolves old ID' );
d4check( $canonical->parseOrThrow( 'aewi:' . $old )->text === 'E1', 'Explicit local source resolves same identity' );
d4check( $canonical->parseOrThrow( 'remote:' . $old )->text === 'remote:' . $old, 'Foreign identity never resolves through local alias' );
d4check( $boundary->schema( SchemaReference::local( new SchemaName( 'S1' ) ) )->id?->text === 'S2', 'Old schema name S1 has its own alias' );
d4check( $boundary->schema( SchemaReference::identified( new SchemaId( 'S1' ) ) )->id?->text === 'S1', 'Explicit S1 never follows legacy name alias' );
d4check( $canonical->parseOrThrow( 'E1' )->text === 'E1', 'Canonical entity unchanged' );
d4check( $db->pdo->query( 'SELECT SUM(swsic_value) FROM test_sws_id_counter' )->fetchColumn() === $before, 'Read resolution never allocates or changes counters' );
$normalizer = new class implements \ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceNormalizer { public function normalize(SchemaReference $reference):SchemaReference{return $reference;} };
$schemaParser = new \ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceParser('aewi',$normalizer);
$types = \ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeRegistry::withCoreTypes($schemaParser);
$generator = new class implements \ProfessionalWiki\SWS\Infrastructure\IdGenerator { public function generate():string{return '11111111111111';} };
$incomingRelation = ['propertyType'=>'relation','value'=>[['id'=>'r11111111111111','target'=>$oldTarget]]];
$written = (new \ProfessionalWiki\SWS\Application\StatementListBuilder($types,$generator,$canonical))->build(['Kennt'=>$incomingRelation]);
d4check($written->getTypedRelations()->relations[0]->targetId->text==='E2','New writes canonicalize incoming legacy relation targets');
$historical = (new \ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\StatementDeserializer($types,$raw))->deserialize('Kennt',$incomingRelation);
d4check($historical->getValue()->relations[0]->targetId->text===$oldTarget,'Historical relation deserialization retains stored target identity');
$terms = new LocalizedIdentity( 'de', ['de'=>'Gödel','en'=>'Goedel'], ['de'=>'Mathematiker','en'=>'Mathematician'] );
$schemaTerms = new LocalizedIdentity( 'de', ['de'=>'Person','en'=>'Human'], ['de'=>'Personendaten'] );
$schema = new Schema( new SchemaName( 'S1' ), '', new PropertyDefinitions([]), false, new SchemaId( 'S1' ), $schemaTerms );
$otherSchema = new Schema( new SchemaName( 'S2' ), '', new PropertyDefinitions([]), false, new SchemaId( 'S2' ), $schemaTerms );
$source = new InMemorySource(); $source->addSchema( $schema ); $source->addSchema( $otherSchema );
$sources = new SourceRegistry( 'aewi' ); $sources->registerSource( 'aewi', $source ); $sources->registerSource( 'remote', $source );
$logger = new Psr\Log\NullLogger(); $resolver = new SchemaResolver( $sources, $logger, $boundary->schema(...) );
d4check( $resolver->getSchema( SchemaReference::local( new SchemaName( 'S1' ) ) )?->getId()?->text === 'S2', 'Production schema resolver follows name alias' );
d4check( $resolver->getSchema( $schema->getReference() )?->getId()?->text === 'S1', 'Production schema resolver retains explicit ID' );
$relation = new Relation( new RelationId( 'r11111111111111' ), new SubjectId( $oldTarget ) );
$subject = new Subject( new SubjectId( 'E1' ), new SubjectLabel( 'Gödel' ), $schema->getReference(), new StatementList([
 new Statement( new PropertyName( 'Notiz' ), 'text', new StringValue( 'Forschungsstand unverändert' ) ),
 new Statement( new PropertyName( 'sws_labels' ), 'text', new StringValue( 'Eigener Feldwert' ) ),
 new Statement( new PropertyName( 'Kennt' ), 'relation', new RelationValue( $relation ) ),
]), identity: $terms );
$page = new Page( new PageId( 12 ), new PageProperties( ['name'=>'Entität:E1'] ), new PageSubjects( $subject, new SubjectMap() ) );
d4check( SubjectDisplayName::mayBeTitledBySubjectId( 'Entität:E1' ), 'E-prefixed entity page is recognized' );
d4check( SubjectDisplayName::isTitledBySubjectOnIt( 'Entität:E1', $page->getSubjects() ), 'E title matches actual entity on page' );
d4check( SubjectDisplayName::inPlaceOfPageTitle( $page->getSubjects(), 'Entität:E1' ) === $subject, 'Entity label replaces its technical page title' );
d4check( SubjectDisplayName::mayBeTitledBySubjectId( 'Subject:S11111111111111' ), 'Legacy capitalized page titles remain supported' );
$ns = new RdfNamespaces( 'https://wiki.example/' );
$iris = new SubjectIriResolver( $ns, $sources, $logger, $boundary->entity(...) );
$native = new RdfPageProjector( RdfValueMapperRegistry::withCoreMappers(), $ns, $resolver, $iris, $logger );
$quads = $native->projectPage( $page );
$find = static function ( $list, string $subject, string $predicate ): array { return array_values( array_filter( $list->asArray(), static fn ( $q ) => $q->subject->value === $subject && $q->predicate->value === $predicate ) ); };
$labels = $find( $quads, 'https://wiki.example/entity/E1', RdfNamespaces::RDFS . 'label' );
d4check( count( $labels ) === 2, 'Native RDF contains every own entity label' );
d4check( $labels[0]->object->languageTag === 'de' && $labels[1]->object->languageTag === 'en', 'Native labels carry language tags' );
d4check( count( $find( $quads, 'https://wiki.example/entity/E1', RdfNamespaces::RDFS . 'comment' ) ) === 2, 'Own descriptions are RDF comments in both languages' );
d4check( count( $find( $quads, 'https://wiki.example/schema-id/S1', RdfNamespaces::RDFS . 'label' ) ) === 2, 'Stable schema class carries its own multilingual labels' );
$links = $find( $quads, 'https://wiki.example/entity/E1', 'https://wiki.example/schema-id/S1/field/Kennt' );
d4check( $links[0]->object->value === 'https://wiki.example/entity/E2', 'Relation target aliases use canonical entity IRI' );
$relationLabel = $find( $quads, 'https://wiki.example/relation/r11111111111111', RdfNamespaces::RDFS . 'label' );
d4check( count( $relationLabel ) === 1 && str_contains( $relationLabel[0]->object->lexicalForm, 'Gödel — Kennt' ), 'Reified relation has readable source and predicate' );
d4check( $ns->schemaClass( new SchemaName( 'S1' ), 'remote' )->value !== $ns->schemaClass( new SchemaName( 'remote:S1' ) )->value, 'Foreign schema class cannot collide with local colon-bearing name' );
$foreign = new Subject( new SubjectId( 'E2' ), null, SchemaReference::fromJson( ['source'=>'remote','id'=>'S1'], 'aewi' ), new StatementList(), identity: $terms );
$foreignPage = new Page( new PageId( 13 ), new PageProperties( ['name'=>'Foreign'] ), new PageSubjects( $foreign, new SubjectMap() ) );
$foreignTypes = $find( $native->projectPage( $foreignPage ), 'https://wiki.example/entity/E2', RdfNamespaces::RDF . 'type' );
d4check( $foreignTypes[0]->object->value === 'https://wiki.example/schema-source-id/remote/S1', 'Foreign schema ID retains its source in RDF typing' );
d4check( $ns->identifiedSchemaClass(new SchemaId('S1'))->value !== $ns->schemaClass(new SchemaName('S1'))->value, 'Old schema named S1 and new ID S1 never share an RDF identity' );
$legacyNamedS1 = new Schema(new SchemaName('S1'),'',new PropertyDefinitions([]),false);
d4check( \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSchemaIdentity::label($schema,$schema->getReference()) !== \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSchemaIdentity::label($legacyNamedS1,$legacyNamedS1->getReference()), 'Neo4j typing keeps old names and explicit IDs disjoint' );
$unavailable = new Subject(new SubjectId('E3'),null,SchemaReference::identified(new SchemaId('S999')),new StatementList());
$unavailablePage = new Page(new PageId(14),new PageProperties(['name'=>'Unavailable schema']),new PageSubjects($unavailable,new SubjectMap()));
d4check($find($native->projectPage($unavailablePage),'https://wiki.example/entity/E3',RdfNamespaces::RDF.'type') === [],'Unavailable schema keeps existing projection exclusion policy');
$renamedSchema = new Schema( new SchemaName( 'S1' ), '', new PropertyDefinitions([]), false, new SchemaId( 'S1' ), new LocalizedIdentity( 'de', ['de'=>'Persönlichkeit'], [] ) );
$source->addSchema( $renamedSchema );
$renamedTypes = $find( $native->projectPage( $page ), 'https://wiki.example/entity/E1', RdfNamespaces::RDF . 'type' );
d4check( $renamedTypes[0]->object->value === 'https://wiki.example/schema-id/S1', 'Schema label edit never changes class identity' );
$source->addSchema( $schema );
$mapping = (new MappingPersistenceDeserializer())->deserialize( new \ProfessionalWiki\SWS\Domain\Mapping\MappingName( 'Test' ), json_encode([
 'version'=>1,'prefixes'=>['ex'=>'https://ontology.example/'], 'schemas'=>['S1'=>['subject'=>['class'=>'ex:Legacy']]],
 'schemaIds'=>['S1'=>['subject'=>['class'=>'ex:Human','labelPredicate'=>'ex:name']]],
], JSON_THROW_ON_ERROR) );
d4check( $mapping->forSchema( new SchemaName( 'S1' ) )?->subject?->class === 'ex:Legacy', 'Mapping legacy key remains a legacy name' );
d4check( $mapping->forSchema( $schema->getReference() )?->subject?->class === 'ex:Human', 'Mapping explicit ID key is separate' );
$ontology = new OntologyMappingProjector( $mapping, $ns, RdfValueMapperRegistry::withCoreMappers(), $iris, $logger );
$mapped = $ontology->projectPage( $page );
d4check( $find( $mapped, 'https://wiki.example/entity/E1', RdfNamespaces::RDF . 'type' )[0]->object->value === 'https://ontology.example/Human', 'Ontology projection applies the ID mapping' );
d4check( count( $find( $mapped, 'https://wiki.example/entity/E1', 'https://ontology.example/name' ) ) === 2, 'Ontology label predicate retains both language labels' );
$lua = (new SchemaLuaSerializer())->toLuaTable( $schema );
d4check( $lua['id'] === 'S1' && $lua['reference'] === ['id'=>'S1'] && $lua['labels']['en'] === 'Human', 'Lua schema result retains ID and language maps as arrays' );
$jsonSchema = json_decode( (new JsonSchemaSerializer('https://wiki.example/schema-id/S1/json-schema'))->serialize($schema), true, 512, JSON_THROW_ON_ERROR );
d4check( $jsonSchema['title'] === 'Person' && $jsonSchema['properties']['schema']['const'] === ['id'=>'S1'], 'JSON Schema uses readable title and stable reference' );
// Actual Neo4j projection methods; no Neo4j server or transaction is simulated as a real server.
$reflection = new ReflectionClass( Neo4jSubjectUpdater::class ); $updater = $reflection->newInstanceWithoutConstructor();
foreach ( ['schemaResolver'=>$resolver,'wikiId'=>'aewi','pageId'=>new PageId(12),'valueBuilderRegistry'=>Neo4jValueBuilderRegistry::withCoreBuilders(),'logger'=>$logger,'canonicalize'=>$boundary->entity(...)] as $key=>$value ) { $reflection->getProperty($key)->setValue($updater,$value); }
$properties = $reflection->getMethod('nodeProperties')->invoke($updater,new Neo4jPageSubject($subject,true,false),$page);
d4check( $properties['sws#schemaId'] === 'S1' && $properties['id'] === 'E1', 'Neo4j properties keep stable schema/entity identity' );
d4check( json_decode( $properties['sws#labels'], true ) === $terms->labels, 'Neo4j labels survive its scalar-only property storage' );
d4check( $properties['sws_labels'] === ['Eigener Feldwert'], 'Metadata does not overwrite a legal research field called sws_labels' );
$relations = $reflection->getMethod('withoutForeignTargets')->invoke($updater,$subject->getTypedRelations());
d4check( $relations->relations[0]->targetId->text === 'E2', 'Neo4j relation target uses the canonical ID, avoiding an old-ID stub' );
// Complete-view JSON/RDF preserve the same own metadata, typed schema and effective values.
$viewSubject = \SWSBaseBridge\DataViewAssembler::subject( $subject, $schema, ['fields'=>[]], static fn($id)=>['id'=>$id,'status'=>'unavailable'] );
d4check( (array)$viewSubject['labels'] === $terms->labels && $viewSubject['schemaIdentity']['id'] === 'S1', 'Complete JSON view preserves entity and schema metadata' );
$view = ['page'=>['id'=>12,'title'=>'Test','url'=>'https://wiki.example/wiki/Test'],'subjects'=>[$viewSubject]];
$complete = (new \SWSBaseBridge\DataViewRdfProjector($ns))->project($view);
d4check( count( $find( $complete, 'https://wiki.example/entity/E1', RdfNamespaces::RDFS.'label' ) ) === 2, 'Complete RDF view contains own language-tagged labels' );
d4check( $find( $complete, 'https://wiki.example/entity/E1', RdfNamespaces::RDF.'type' )[0]->object->value === 'https://wiki.example/schema-id/S1', 'Complete RDF view handles object-shaped schema ID references' );
$foreignViewSubject = \SWSBaseBridge\DataViewAssembler::subject( $foreign, $schema, ['fields'=>[]], static fn($id)=>['id'=>$id,'status'=>'unavailable'] );
$foreignComplete = (new \SWSBaseBridge\DataViewRdfProjector($ns))->project(['page'=>['id'=>13,'title'=>'Foreign','url'=>'https://wiki.example/wiki/Foreign'],'subjects'=>[$foreignViewSubject]]);
d4check( $find( $foreignComplete, 'https://wiki.example/entity/E2', RdfNamespaces::RDF.'type' )[0]->object->value === 'https://wiki.example/schema-source-id/remote/S1', 'Complete export keeps foreign source even with resolved schema identity metadata' );
$labelResult = \ProfessionalWiki\SWS\Presentation\IdentityLabelResult::serialize(new \ProfessionalWiki\SWS\Application\SubjectLabelLookupResult('E1','Gödel','Article'),$subject,'en');
d4check($labelResult['label']==='Goedel'&&$labelResult['description']==='Mathematician','Label lookup presentation uses requested own language and description');
d4check((array)$labelResult['labels']===$terms->labels,'Label results retain all own language maps');
$serializer = new HardfRdfSerializer($ns->prefixMap(),$logger);
foreach ( [RdfFormat::Turtle,RdfFormat::TriG] as $format ) {
 $serialized = $serializer->serialize($quads,$format);
 d4check( str_contains($serialized,'@de') && str_contains($serialized,'@en'), 'Real RDF serializer retains language tags in '.$format->name );
}
d4refused( fn()=>new LocalizedIdentity('abcdefghijk'), 'Metadata language accepted by RDF must satisfy the RDF language-tag grammar' );
echo "$count D4 identity/alias/Lua/JSON/native-RDF/ontology/Neo4j/complete-view checks passed (actual SQLite and projection classes; no live MediaWiki/graph server).\n";
