<?php

declare( strict_types = 1 );

require __DIR__ . '/source-refresh/CollectorHost.php';
require __DIR__ . '/bootstrap.php';

use CollectorHarness\State as C;
use MediaWiki\Context\TestContext;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\Source\StoredSourceValues;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\{ PropertyDefinition, PropertyDefinitions, Schema, SchemaName, SchemaReference };
use ProfessionalWiki\SWS\Domain\Source\SourceSnapshot;
use ProfessionalWiki\SWS\Domain\Subject\{ StatementList, Subject, SubjectId, SubjectMap };
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingContext;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\Data\TestSources;
use SWSBaseBridge\{ EntityGateway, ExternalValuesLookup, SourceSnapshotCollector };

$checks = 0;
function ccheck( bool $ok, string $message ): void { global $checks; $checks++; if ( !$ok ) { throw new RuntimeException( $message ); } }
function claimD2( string $type, mixed $value, string $snaktype = 'value' ): array {
	return [ 'rank' => 'normal', 'mainsnak' => [ 'snaktype' => $snaktype, 'datatype' => $type,
		'datavalue' => [ 'type' => $type === 'commonsMedia' || $type === 'string' ? 'string' : $type, 'value' => $value ] ] ];
}
function resetCollector(): void {
	C::$requests = []; C::$cache = []; C::$cacheCalls = 0; C::$failIds = []; C::$denied = []; C::$localEnabled = true;
	C::$cachedQ = [ 943 => 'Q1' ];
	C::$entities = [
		'wikidata' => [
			'Q41390' => [ 'id' => 'Q41390', 'labels' => [ 'de' => [ 'value' => 'Kurt Gödel' ] ], 'descriptions' => [ 'de' => [ 'value' => 'Mathematiker' ] ],
				'claims' => [ 'P19' => [ claimD2( 'wikibase-item', [ 'id' => 'Q14960' ] ) ],
					'P1477' => [ claimD2( 'string', 'Quellenname' ) ],
					'P569' => [ claimD2( 'time', [ 'time' => '+1906-04-28T00:00:00Z', 'precision' => 11, 'calendarmodel' => 'http://www.wikidata.org/entity/Q1985727' ] ) ],
					'P18' => [ claimD2( 'commonsMedia', 'Gödel.jpg' ) ], 'P43' => [ claimD2( 'quantity', [ 'amount' => '+1' ] ) ] ] ],
			'Q14960' => [ 'id' => 'Q14960', 'labels' => [ 'de' => [ 'value' => 'Brünn' ] ] ],
		],
		'wikibase-local' => [ 'Q500' => [ 'id' => 'Q500', 'labels' => [ 'de' => [ 'value' => 'Lokale Entität' ] ], 'claims' => [ 'P19' => [ claimD2( 'string', 'Lokaler Wert' ) ] ] ],
			'P19' => [ 'id' => 'P19', 'datatype' => 'string' ] ],
	];
	foreach ( [ 'P19' => 'wikibase-item', 'P1477' => 'string', 'P569' => 'time', 'P18' => 'commonsMedia', 'P43' => 'quantity' ] as $id => $datatype ) {
		C::$entities['wikidata'][$id] = [ 'id' => $id, 'datatype' => $datatype, 'labels' => [ 'de' => [ 'value' => 'Bezeichnung ' . $id ] ] ];
	}
}
$registry = TestSources::newPropertyTypeRegistry();
$properties = [];
foreach ( [ 'Ort' => [ 'text', 'wikidata', 'P19' ], 'Name' => [ 'text', 'wikidata', 'P1477' ],
	'Datum' => [ 'historicalDate', 'wikidata', 'P569' ], 'Bild' => [ 'image', 'wikidata', 'P18' ],
	'Ungeeignet' => [ 'text', 'wikidata', 'P43' ], 'Lokal' => [ 'text', 'wikibase-local', 'P19' ] ] as $name => [ $type, $source, $id ] ) {
	$properties[$name] = PropertyDefinition::fromJson( [ 'type' => $type, 'externalProperty' => [ 'source' => $source, 'id' => $id ] ], $registry );
}
$schema = new Schema( new SchemaName( 'Person' ), '', new PropertyDefinitions( $properties ), false );
SWSExtension::$schema = $schema;
$context = new TestContext(); $title = new Title();
function collectD2( Schema $schema ): array {
	$processing = new ProcessingContext( new Title() );
	$processing->output = new ParserOutput( [ 'unlinkedwikibase_id' => 'Q41390' ] );
	return $processing->run( static fn () => ( new SourceSnapshotCollector( new TestContext() ) )->collect( new Title(), $schema, 'de' ) );
}

resetCollector(); $data = collectD2( $schema ); $fields = array_column( $data['fields'], null, 'name' );
ccheck( count( $fields ) === 6, 'Actual collector covers every binding' );
ccheck( $data['identities']['wikidata']['id'] === 'Q41390', 'Fresh parse Q takes precedence over old pageprops Q' );
ccheck( $data['identities']['wikidata']['description'] === 'Mathematiker', 'Identity description included in persisted data' );
ccheck( $fields['Ort']['values'][0]['label'] === 'Brünn' && $fields['Ort']['propertyLabel'] === 'Bezeichnung P19', 'Property and referenced-entity labels collected' );
ccheck( $fields['Name']['values'][0]['value'] === 'Quellenname', 'Source text collected' );
ccheck( $fields['Datum']['values'][0]['sourceTime']['precision'] === 11 && isset( $fields['Datum']['candidates'] ), 'Date precision, calendar and candidate data retained' );
ccheck( $fields['Bild']['values'][0]['reference'] === 'commons:Gödel.jpg', 'Image reference normalized' );
ccheck( $fields['Ungeeignet']['status'] === 'unsupported' && $fields['Name']['status'] === 'resolved', 'Unsupported datatype is one field result' );
ccheck( $fields['Lokal']['values'][0]['value'] === 'Lokaler Wert' && $fields['Lokal']['entityId'] === 'Q500', 'Same P number in local repository remains source-qualified' );
ccheck( C::$cacheCalls === 0 && count( C::$requests ) === 3, 'Fresh collection bypasses WAN cache and batches item/properties/target labels' );

$gateway = new EntityGateway( $context, refresh: true );
$before = count( C::$requests ); $gateway->entities( 'wikidata', [ 'Q41390', 'P19' ], 'de', true );
$gateway->entities( 'wikidata', [ 'P19', 'Q41390' ], 'de', false );
ccheck( count( C::$requests ) === $before + 1, 'Within-refresh memoization reuses claims for label-only reads' );
$gateway->entities( 'wikidata', [ 'Q41390' ], 'en', true );
ccheck( count( C::$requests ) === $before + 2, 'Different language is separately collected' );
( new EntityGateway( $context, refresh: true ) )->entities( 'wikidata', [ 'Q41390' ], 'de', true );
ccheck( count( C::$requests ) === $before + 3, 'New logical refresh performs new source request' );

resetCollector();
$live = new EntityGateway( $context ); $old = $live->entities( 'wikidata', [ 'Q41390' ], 'de', true );
C::$entities['wikidata']['Q41390']['claims']['P1477'] = [ claimD2( 'string', 'Verändert' ) ];
$cached = ( new EntityGateway( $context ) )->entities( 'wikidata', [ 'Q41390' ], 'de', true );
$fresh = ( new EntityGateway( $context, refresh: true ) )->entities( 'wikidata', [ 'Q41390' ], 'de', true );
ccheck( $old === $cached && $fresh !== $cached && C::$cacheCalls === 2 && count( C::$requests ) === 2, 'Live keeps cached behavior while store collection gets current data' );

resetCollector(); C::$failIds = [ 'Q14960' ]; $failedTarget = array_column( collectD2( $schema )['fields'], null, 'name' );
ccheck( $failedTarget['Ort']['status'] === 'unavailable' && $failedTarget['Name']['status'] === 'resolved' && $failedTarget['Datum']['status'] === 'resolved', 'Target-label failure does not discard unrelated string/date fields' );
resetCollector(); C::$entities['wikidata']['Q41390']['claims']['P1477'][0]['mainsnak']['datavalue']['value'] = [ 'malformed' ];
$malformed = array_column( collectD2( $schema )['fields'], null, 'name' );
ccheck( $malformed['Name']['status'] === 'unavailable' && $malformed['Ort']['status'] === 'resolved', 'Malformed field is isolated' );
resetCollector(); C::$entities['wikidata']['Q14960']['labels'] = [];
$noLabel = array_column( collectD2( $schema )['fields'], null, 'name' );
ccheck( $noLabel['Ort']['status'] === 'unavailable' && $noLabel['Name']['status'] === 'resolved', 'Missing target label is rejected before final annotation planning' );
resetCollector(); C::$failIds = [ 'Q41390' ]; $outage = collectD2( $schema );
$outageFields = array_column( $outage['fields'], null, 'name' );
ccheck( $outageFields['Ort']['status'] === 'unavailable' && $outageFields['Lokal']['status'] === 'resolved', 'External outage does not halt local repository collection' );

resetCollector(); $good = collectD2( $schema );
$id = new SubjectId( 's11111111111111' );
$first = SourceSnapshot::recordAttempt( $id, 943, 101, [ 901 => 100 ], 'de', '2026-11-11T19:00:00Z', $good['fields'], $good['identities'] );
unset( C::$entities['wikidata']['Q41390']['claims']['P19'] ); $missing = collectD2( $schema );
$second = SourceSnapshot::recordAttempt( $id, 943, 102, [ 901 => 100 ], 'de', '2026-11-11T19:01:00Z', $missing['fields'], $missing['identities'], $first );
$subject = new Subject( $id, null, SchemaReference::local( new SchemaName( 'Person' ) ), new StatementList(), sourceSnapshot: $second );
$local = StoredSourceValues::read( $subject, $schema, 'de', 943 ); $localFields = array_column( $local['fields'], null, 'name' );
ccheck( $localFields['Ort']['values'][0]['label'] === 'Brünn' && $localFields['Ort']['sourceStatus'] === 'missing' && $localFields['Ort']['retained'], 'Usable stored value remains readable alongside missing-source status' );
$worker = new ProcessingContext( $title ); $worker->useStoredSources = true; $worker->subjects = new PageSubjects( $subject, new SubjectMap() );
$before = count( C::$requests );
$localRead = $worker->run( static fn () => ( new ExternalValuesLookup( new TestContext() ) )->read( new Title(),
	[ 'pageid' => 943, 'subject' => 's11111111111111', 'language' => 'de' ], null, $subject ) );
ccheck( count( C::$requests ) === $before && $localRead['mainSubjectId'] === $local['mainSubjectId'] &&
	array_column( $localRead['fields'], 'values', 'name' ) === array_column( $local['fields'], 'values', 'name' ), 'Actual Bridge reader uses network-free stored snapshot inside store worker' );

resetCollector(); C::$localEnabled = false; $disabled = array_column( collectD2( $schema )['fields'], null, 'name' );
ccheck( $disabled['Lokal']['status'] === 'unavailable' && $disabled['Ort']['status'] === 'resolved', 'Absent optional local repository remains a field outcome' );
resetCollector(); C::$denied = [ 'Q500' ];
$denied = collectD2( $schema );
ccheck( $denied['identities']['wikibase-local']['status'] === 'unavailable' && array_column( $denied['fields'], null, 'name' )['Lokal']['status'] === 'unavailable', 'Unreadable local entity is not persisted as source data' );

resetCollector(); C::$failIds = [ 'Q41390' ]; $failedGateway = new EntityGateway( $context, refresh: true );
for ( $i = 0; $i < 2; $i++ ) {
	try { $failedGateway->entities( 'wikidata', [ 'Q41390' ], 'de', true ); throw new LogicException( 'Outage ignored' ); }
	catch ( RuntimeException ) { ccheck( true, 'Source outage is explicit' ); }
}
ccheck( count( C::$requests ) === 1, 'Same failure is reused within one refresh rather than repeating a timeout per Subject' );

resetCollector(); C::$entities['wikidata']['Q41390']['descriptions']['de'] = [ 'value' => 'Malformed language', 'language' => '../../' ];
$badDescription = collectD2( $schema );
ccheck( $badDescription['identities']['wikidata']['descriptionStatus'] === 'unavailable' && array_column( $badDescription['fields'], null, 'name' )['Ort']['status'] === 'resolved', 'Invalid description metadata does not abort unrelated source fields' );
SourceSnapshot::recordAttempt( $id, 943, 103, [ 901 => 100 ], 'de', '2026-11-11T19:02:00Z', $badDescription['fields'], $badDescription['identities'], $first );
ccheck( true, 'Invalid description becomes a persistable result instead of throwing during capture' );

echo "$checks D2 actual collector/gateway/cache/typed-value/failure/local-read checks passed (controlled source APIs).\n";
