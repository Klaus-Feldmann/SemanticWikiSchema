<?php

declare( strict_types = 1 );

require __DIR__ . '/bootstrap.php';

use ProfessionalWiki\SWS\Application\DependentSubjectBuilder;
use ProfessionalWiki\SWS\Application\NewSubjectIdResolver;
use ProfessionalWiki\SWS\Application\Source\SourceSnapshotEditGuard;
use ProfessionalWiki\SWS\Application\StatementListBuilder;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Domain\Source\SourceSnapshot;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\StatementDeserializer;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SubjectContentDataSerializer;
use ProfessionalWiki\SWS\Tests\Data\{ TestNaming, TestRelation, TestSchema, TestSources, TestSubjectIds };
use ProfessionalWiki\SWS\Tests\TestDoubles\{ InMemoryPageIdentifiersLookup, InMemorySchemaLookup, StubIdGenerator };
use SWSBaseBridge\TimeClaimView;

$checks = 0;
function checkSource( bool $condition, string $message ): void {
	global $checks;
	$checks++;
	if ( !$condition ) { throw new RuntimeException( $message ); }
}
function refusesSource( callable $operation, string $message ): void {
	try { $operation(); } catch ( InvalidArgumentException ) { checkSource( true, $message ); return; }
	throw new RuntimeException( $message );
}
function sourceField( array $replace = [] ): array {
	return array_replace( [ 'name' => 'Geburtsort', 'source' => 'wikidata', 'entityId' => 'Q41390',
		'propertyId' => 'P19', 'propertyType' => 'text', 'propertyLabel' => 'Geburtsort',
		'propertyUrl' => 'https://www.wikidata.org/wiki/Property:P19', 'datatype' => 'wikibase-item',
		'status' => 'resolved', 'values' => [ [ 'kind' => 'entity', 'id' => 'Q14960', 'label' => 'Brünn',
			'url' => 'https://www.wikidata.org/wiki/Q14960', 'rank' => 'normal' ] ],
	], $replace );
}
function attemptSource( array $fields, ?SourceSnapshot $previous = null, int $revision = 101,
	string $at = '2026-11-11T18:00:00Z', string $language = 'de', array $identities = [] ): SourceSnapshot {
	return SourceSnapshot::recordAttempt( new SubjectId( 's11111111111111' ), 943, $revision, [ 901 => 100 ],
		$language, $at, $fields, $identities, $previous );
}
function subjectSource( ?SourceSnapshot $snapshot = null ): Subject {
	return new Subject( new SubjectId( 's11111111111111' ), new SubjectLabel( 'Eigener Name' ),
		SchemaReference::local( new SchemaName( 'Person' ) ), new StatementList( [
			new Statement( new PropertyName( 'Geburtsort' ), 'text', new StringValue( 'Eigene Forschungsangabe' ) ),
		] ), [ 'Geburtsort' => '[[Brno]] gemäß Quelle' ], $snapshot );
}

$identity = [ 'wikidata' => [ 'source' => 'wikidata', 'id' => 'Q41390', 'status' => 'resolved',
	'label' => 'Kurt Gödel', 'description' => 'Mathematiker' ] ];
$first = attemptSource( [ sourceField() ], identities: $identity );
$originalSuccess = $first->forLanguage( 'de' )['fields']['Geburtsort']['lastSuccess'];
checkSource( $originalSuccess['result']['entityId'] === 'Q41390', 'Source identity is stored with the value' );
checkSource( $originalSuccess['result']['values'][0]['label'] === 'Brünn', 'Target label retained locally' );
checkSource( $first->forLanguage( 'de' )['identities']['wikidata']['lastSuccess']['result']['description'] === 'Mathematiker', 'Description stored' );

foreach ( [ 'missing', 'unavailable', 'unsupported', 'manual' ] as $status ) {
	$failed = attemptSource( [ sourceField( [ 'status' => $status, 'values' => [], 'reason' => 'test-state' ] ) ],
		$first, 102, '2026-11-11T18:01:00Z', identities: [ 'wikidata' => [ 'source' => 'wikidata', 'status' => 'unavailable' ] ] );
	$entry = $failed->forLanguage( 'de' )['fields']['Geburtsort'];
	checkSource( $entry['lastSuccess'] === $originalSuccess, "$status preserves last successful value and provenance" );
	checkSource( $entry['lastAttempt']['status'] === $status, "$status retained as distinct outcome" );
	checkSource( $failed->forLanguage( 'de' )['attemptedAt'] === '2026-11-11T18:01:00Z', 'Last attempt time independent from last success' );
	checkSource( $failed->forLanguage( 'de' )['identities']['wikidata']['lastSuccess'] === $first->forLanguage( 'de' )['identities']['wikidata']['lastSuccess'], 'Identity outage preserves description' );
	$initialFailure = attemptSource( [ sourceField( [ 'status' => $status, 'values' => [] ] ) ] );
	checkSource( $initialFailure->forLanguage( 'de' )['fields']['Geburtsort']['lastSuccess'] === null, 'First failure does not fabricate a value' );
}
foreach ( [ [], [ [ 'kind' => 'novalue' ] ], [ [ 'kind' => 'somevalue' ] ] ] as $values ) {
	$empty = attemptSource( [ sourceField( [ 'values' => $values ] ) ], $first, 102, '2026-11-11T18:01:00Z' );
	checkSource( $empty->forLanguage( 'de' )['fields']['Geburtsort']['lastSuccess'] === $originalSuccess, 'Empty/unknown/no-value response preserves actual data' );
	checkSource( $empty->forLanguage( 'de' )['fields']['Geburtsort']['lastAttempt']['status'] === 'missing', 'Empty result becomes a note-capable state' );
}
$newField = sourceField( [ 'values' => [ [ 'kind' => 'text', 'value' => 'Neuer verwendbarer Wert' ] ] ] );
$recovered = attemptSource( [ $newField ], $failed, 103, '2026-11-11T18:02:00Z' );
checkSource( $recovered->forLanguage( 'de' )['fields']['Geburtsort']['lastSuccess']['result'] === $newField, 'Recovery replaces old source data' );
checkSource( $first->forLanguage( 'de' )['fields']['Geburtsort']['lastSuccess'] === $originalSuccess, 'Merge leaves earlier snapshot immutable' );

$twoFields = attemptSource( [ sourceField( [ 'status' => 'unavailable', 'values' => [] ] ),
	sourceField( [ 'name' => 'Name', 'propertyId' => 'P1477', 'datatype' => 'string', 'values' => [ [ 'kind' => 'text', 'value' => 'Gödel' ] ] ] ) ],
	$first, 102, '2026-11-11T18:01:00Z' );
checkSource( $twoFields->forLanguage( 'de' )['fields']['Name']['lastSuccess']['result']['values'][0]['value'] === 'Gödel', 'One field failure does not suppress another success' );
$english = attemptSource( [ sourceField( [ 'values' => [ [ 'kind' => 'text', 'value' => 'Brno' ] ] ] ) ], $first, 102, '2026-11-11T18:01:00Z', 'en' );
checkSource( $english->forLanguage( 'de' ) === $first->forLanguage( 'de' ), 'Updating another language preserves its own old revision context' );
checkSource( $english->forLanguage( 'fr' ) === null, 'Missing language is local absence, no fetch' );
checkSource( attemptSource( [], $first, 102, '2026-11-11T18:01:00Z' )->forLanguage( 'de' )['fields'] === [], 'Removed schema binding does not remain an active field' );

$time = TimeClaimView::result( [ [ 'rank' => 'normal', 'mainsnak' => [ 'snaktype' => 'value', 'datatype' => 'time',
	'datavalue' => [ 'value' => [ 'time' => '+1906-04-28T00:00:00Z', 'precision' => 11,
		'calendarmodel' => 'http://www.wikidata.org/entity/Q1985727' ] ] ] ] ], 'wikidata', 'de', 'de' );
$timeField = sourceField( [ 'name' => 'Geburtsdatum', 'propertyId' => 'P569', 'propertyType' => 'historicalDate',
	'datatype' => 'time', 'values' => $time['values'], 'candidates' => $time['candidates'] ] );
$typed = attemptSource( [ $timeField, sourceField( [ 'name' => 'Bild', 'propertyId' => 'P18', 'propertyType' => 'image',
	'values' => [ [ 'kind' => 'image', 'reference' => 'commons:Kurt Gödel.jpg', 'repository' => 'commons', 'filename' => 'Kurt Gödel.jpg' ] ] ] ) ] );
checkSource( $typed->forLanguage( 'de' )['fields']['Geburtsdatum']['lastSuccess']['result']['values'] === $time['values'], 'Actual date formatter result retains calendar/precision/rank/presentation' );
checkSource( $typed->forLanguage( 'de' )['fields']['Bild']['lastSuccess']['result']['values'][0]['reference'] === 'commons:Kurt Gödel.jpg', 'Image reference stays typed' );

$subject = subjectSource( $first );
$copy = $subject->withStatements( new StatementList() );
checkSource( $copy->getSourceSnapshot() === $first && $copy->getFieldNotes() === $subject->getFieldNotes(), 'Statement replacement preserves snapshot and author notes' );
$subject->setLabel( new SubjectLabel( 'Anderer eigener Name' ) );
$subject->setStatements( new StatementList( [ new Statement( new PropertyName( 'Geburtsort' ), 'text', new StringValue( 'Manuell geändert' ) ) ] ) );
checkSource( $subject->getSourceSnapshot()->toJson() === $first->toJson(), 'Manual changes do not overwrite sources' );

$serializer = new SubjectContentDataSerializer();
$deserializer = new SubjectContentDataDeserializer( new StatementDeserializer( TestSources::newPropertyTypeRegistry(), TestSubjectIds::newParser() ),
	new Psr\Log\NullLogger(), TestSources::newSchemaReferenceParser() );
$page = new PageSubjects( $subject, new SubjectMap() );
$json = $serializer->serialize( $page );
$round = $deserializer->deserialize( $json );
checkSource( $round->getMainSubject()->getSourceSnapshot()->toJson() === $first->toJson(), 'Complete source snapshot survives actual slot round-trip' );
checkSource( $round->getMainSubject()->getStatements()->asArray()['Geburtsort']->getValue()->toScalars() === [ 'Manuell geändert' ], 'Own field stays distinct from cached value' );
checkSource( $round->getMainSubject()->getFieldNotes() === [ 'Geburtsort' => '[[Brno]] gemäß Quelle' ], 'Author note is not a source status' );
checkSource( $serializer->serialize( $round ) === $json, 'Stable serialization' );
$legacy = json_decode( $json, true ); unset( $legacy['subjects']['s11111111111111']['sourceSnapshot'] );
$legacyJson = json_encode( $legacy );
$legacyPage = $deserializer->deserialize( $legacyJson );
checkSource( $legacyPage->getMainSubject()->getSourceSnapshot() === null, 'Old slots without source data remain readable' );
checkSource( !str_contains( $serializer->serialize( $legacyPage ), 'sourceSnapshot' ), 'No empty metadata is added to old slots' );
SourceSnapshotEditGuard::preserve( $legacyPage, $round );
checkSource( $legacyPage->getMainSubject()->getSourceSnapshot()->toJson() === $first->toJson(), 'Raw JSON edit omitting source data preserves it' );
SourceSnapshotEditGuard::preserve( $deserializer->deserialize( $json ), $round );
checkSource( true, 'Raw JSON showing unchanged source data remains editable' );
$forgedPage = new PageSubjects( subjectSource( $recovered ), new SubjectMap() );
refusesSource( fn () => SourceSnapshotEditGuard::preserve( $forgedPage, $round ), 'Forged source result rejected' );
refusesSource( fn () => SourceSnapshotEditGuard::preserve( $round, PageSubjects::newEmpty() ), 'New JSON Subject cannot inject a source result' );
$reassigned = new Subject( $subject->id, null, SchemaReference::local( new SchemaName( 'Andere' ) ), new StatementList() );
refusesSource( fn () => SourceSnapshotEditGuard::preserve( new PageSubjects( $reassigned, new SubjectMap() ), $round ), 'Raw editor cannot silently reassign cached provenance' );
$moved = new PageSubjects( null, new SubjectMap( $subject ) );
checkSource( $deserializer->deserialize( $serializer->serialize( $moved ) )->getOtherSubjects()->getSubject( $subject->id )->getSourceSnapshot()->toJson() === $first->toJson(), 'Moving/order changes preserve snapshot on the same Subject object' );

$lookup = new InMemorySchemaLookup( TestSchema::build( name: 'Birth', subjectsAreDependent: true ), TestSchema::build( name: 'Person' ) );
$builder = new DependentSubjectBuilder( new NewSubjectIdResolver( TestSubjectIds::newParser(), new StubIdGenerator( '11111111111111' ), new InMemoryPageIdentifiersLookup() ),
	TestSources::newSchemaReferenceParser(), TestSources::newSchemaResolver( $lookup ),
	new StatementListBuilder( TestSources::newPropertyTypeRegistry(), new StubIdGenerator( '11111111111111' ), TestSubjectIds::newParser() ), TestNaming::newDependentSubjectRules( $lookup ) );
$dependentId = new SubjectId( 'sBirth111111111' );
$dependentSnapshotData = $first->toJson(); $dependentSnapshotData['subjectId'] = $dependentId->text;
$dependent = new Subject( $dependentId, null, TestSchema::reference( 'Birth' ), new StatementList(), sourceSnapshot: SourceSnapshot::fromJson( $dependentSnapshotData ) );
$host = new Subject( new SubjectId( 'sPerson11111111' ), null, TestSchema::reference( 'Person' ), new StatementList( [
	new Statement( new PropertyName( 'Birth' ), 'relation', new \ProfessionalWiki\SWS\Domain\Value\RelationValue( TestRelation::build( targetId: $dependentId->text ) ) ),
] ) );
$item = [ 'id' => $dependentId->text, 'schema' => 'Birth', 'statements' => [] ];
$rebuilt = $builder->build( [ $item ], $host->id, new PageSubjects( $host, new SubjectMap( $dependent ) ) )->getSubject( $dependentId );
checkSource( $rebuilt->getSourceSnapshot()->toJson() === $dependent->getSourceSnapshot()->toJson(), 'Actual dependent rebuild preserves server snapshot' );
refusesSource( fn () => $builder->build( [ $item + [ 'sourceSnapshot' => $first->toJson() ] ], $host->id, new PageSubjects( $host, new SubjectMap( $dependent ) ) ), 'Nested edit request cannot inject a source snapshot' );

refusesSource( fn () => attemptSource( [ sourceField() ], $first, 100 ), 'Older revision attempt rejected' );
refusesSource( fn () => attemptSource( [ sourceField() ], $first, 102, '2026-11-11T17:00:00Z' ), 'Older timestamp attempt rejected' );
refusesSource( fn () => attemptSource( [ sourceField(), sourceField() ] ), 'Duplicate field rejected' );
refusesSource( fn () => attemptSource( [ sourceField( [ 'propertyId' => 'Q19' ] ) ] ), 'Malformed binding rejected' );
refusesSource( fn () => attemptSource( [ sourceField( [ 'entityId' => null ] ) ] ), 'Resolved value must carry source entity identity' );
refusesSource( fn () => attemptSource( [ sourceField( [ 'values' => [ [ 'kind' => 'html', 'value' => '<b>forged</b>' ] ] ] ) ] ), 'Unknown value shape rejected' );
refusesSource( fn () => attemptSource( [ sourceField() ], language: 'de<script>' ), 'Malformed language rejected' );
refusesSource( fn () => attemptSource( [ sourceField() ], at: '2026-02-30T12:00:00Z' ), 'Invalid calendar timestamp rejected' );
refusesSource( fn () => SourceSnapshot::fromJson( array_replace( $first->toJson(), [ 'version' => 99 ] ) ), 'Unknown storage version refused rather than silently dropped' );
refusesSource( fn () => new Subject( new SubjectId( 's11111111111112' ), null, TestSchema::reference( 'Person' ), new StatementList(), sourceSnapshot: $first ), 'Snapshot cannot be attached to another identity' );
$tampered = $first->toJson(); $tampered['languages']['de']['fields']['Geburtsort']['lastSuccess']['result']['status'] = 'unavailable';
refusesSource( fn () => SourceSnapshot::fromJson( $tampered ), 'Failure cannot masquerade as last success' );
$reference = $first->toJson(); $at =& $reference['languages']['de']['attemptedAt'];
$immutable = SourceSnapshot::fromJson( $reference ); $at = '2030-01-01T00:00:00Z';
checkSource( $immutable->forLanguage( 'de' )['attemptedAt'] === '2026-11-11T18:00:00Z', 'Caller references cannot mutate stored object' );
refusesSource( fn () => SourceSnapshot::assertJsonData( [ 'value' => new stdClass() ] ), 'Object payload rejected' );
refusesSource( fn () => SourceSnapshot::assertJsonData( [ 'value' => INF ] ), 'Non-finite payload rejected' );

echo "$checks source snapshot domain/persistence/edit-boundary checks passed.\n";
