<?php

declare( strict_types = 1 );

require __DIR__ . '/source-refresh/CollectorHost.php';
require __DIR__ . '/source-refresh/StoreReadHost.php';
require __DIR__ . '/bootstrap.php';

use CollectorHarness\State as C;
use MediaWiki\Context\TestContext;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\{ PropertyDefinition, PropertyDefinitions, PropertyName, Schema, SchemaName, SchemaReference };
use ProfessionalWiki\SWS\Domain\Source\SourceSnapshot;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\{ StatementList, Subject, SubjectId, SubjectMap };
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\{ StatementDeserializer, SubjectContentDataDeserializer };
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\Data\{ TestSources, TestSubjectIds };
use SWSBaseBridge\{ ApiExternalValues, ApiIdentity, DataViewLookup, ExportPageDataViewApi, StoredSourceLookup };

$checks = 0;
function scheck( bool $ok, string $message ): void { global $checks; $checks++; if ( !$ok ) { throw new RuntimeException( $message ); } }
function refusesRead( callable $call, string $message ): void {
	try { $call(); } catch ( RuntimeException ) { scheck( true, $message ); return; }
	throw new LogicException( $message );
}
function storeField( string $name = 'Ort', string $source = 'wikidata', string $id = 'Q41390' ): array {
	return [ 'name' => $name, 'source' => $source, 'propertyType' => 'text', 'propertyId' => 'P19', 'entityId' => $id,
		'propertyLabel' => 'Geburtsort', 'status' => 'resolved', 'values' => [ [ 'kind' => 'text', 'value' => 'Gespeicherter Ort' ] ] ];
}
function setStorePage( Subject $subject, int $revision = 102 ): void {
	C::$revision = new RevisionRecord( $revision, 943, [ 'sws' => SubjectContent::newFromData( new PageSubjects( $subject, new SubjectMap() ) ) ] );
	C::$revisions[$revision] = C::$revision;
}
function readStoreSnapshot( SourceSnapshot $snapshot ): Subject {
	$subject = new Subject( new SubjectId( 's11111111111111' ), null, SchemaReference::local( new SchemaName( 'Person' ) ),
		new StatementList( [ new Statement( new PropertyName( 'Name' ), 'text', new StringValue( 'Eigene Angabe' ) ) ] ), [ 'Ort' => 'Forschungsnotiz' ], $snapshot );
	setStorePage( $subject ); return $subject;
}
function storedAttempt( array $fields, array $identities, ?SourceSnapshot $previous = null, int $rev = 101 ): SourceSnapshot {
	return SourceSnapshot::recordAttempt( new SubjectId( 's11111111111111' ), 943, $rev, [ 901 => 100 ], 'de',
		$rev === 101 ? '2026-11-11T19:00:00Z' : '2026-11-11T19:01:00Z', $fields, $identities, $previous );
}
$registry = TestSources::newPropertyTypeRegistry();
SWSExtension::$deserializer = new SubjectContentDataDeserializer( new StatementDeserializer( $registry, TestSubjectIds::newParser() ), new Psr\Log\NullLogger(), TestSources::newSchemaReferenceParser() );
$properties = [ 'Ort' => PropertyDefinition::fromJson( [ 'type' => 'text', 'externalProperty' => [ 'source' => 'wikidata', 'id' => 'P19' ] ], $registry ),
	'Name' => PropertyDefinition::fromJson( [ 'type' => 'text' ], $registry ) ];
SWSExtension::$schema = new Schema( new SchemaName( 'Person' ), '', new PropertyDefinitions( $properties ), false );
C::$mode = 'store'; C::$requests = []; C::$cachedQ = [ 943 => 'Q999' ]; C::$identityLookups = 0;
C::$failIds = [ 'Q41390', 'Q999', 'P19', 'Q500' ];
$identity = [ 'source' => 'wikidata', 'status' => 'resolved', 'id' => 'Q41390', 'url' => 'https://www.wikidata.org/wiki/Q41390', 'description' => 'Gespeicherte Beschreibung' ];
$first = storedAttempt( [ storeField() ], [ 'wikidata' => $identity ] );
$subject = readStoreSnapshot( $first );
$context = new TestContext(); $title = new Title();
$params = [ 'pageid' => 943, 'subject' => $subject->id->text, 'language' => 'de', 'presentation' => false ];
$values = new ApiExternalValues( $params, $context ); $values->execute();
scheck( $values->output['result']['fields'][0]['values'][0]['value'] === 'Gespeicherter Ort', 'Real public field API reads stored value while source API is unavailable' );
scheck( C::$requests === [] && C::$identityLookups === 0, 'Store field read invokes no source API or page identity resolution' );
$fieldsBefore = $subject->getSourceSnapshot()->toJson();
$view = ( new DataViewLookup( $context ) )->read( $title, 'de' );
scheck( $view['complete'] && $view['sourceDataMode'] === 'store', 'Local data view is complete in store mode' );
scheck( $view['identities']['wikidata']['id'] === 'Q41390' && $view['displayDescription']['text'] === 'Gespeicherte Beschreibung', 'Identity/description use stored source rather than conflicting pageprops' );
scheck( $view['subjects'][0]['fields'][1]['effectiveValues'][0]['value'] === 'Eigene Angabe' && $view['subjects'][0]['fieldNotes']['Ort'] === 'Forschungsnotiz', 'Own values and author notes are retained in read model' );
$identityApi = new ApiIdentity( [ 'pageid' => 943, 'descriptions' => false, 'language' => 'de' ], $context ); $identityApi->execute();
scheck( $identityApi->output['result']['identities'][$subject->id->text]['wikidata']['id'] === 'Q41390' && C::$identityLookups === 0, 'Identity API without descriptions still avoids automatic identity resolution' );
$oldContent = C::$revision->getContent( 'sws' )->getText();
$fallback = ( new StoredSourceLookup( $context ) )->fields( $title, array_replace( $params, [ 'language' => 'en' ] ) );
scheck( $fallback['storage']['languageFallback'] && $fallback['storage']['language'] === 'de' && $fallback['fields'][0]['languageFallback'], 'Missing stored language uses explicit local fallback' );
scheck( ( new StoredSourceLookup( $context ) )->identities( $title, 'en' )['wikidata']['description']['language'] === 'de', 'Description fallback retains actual language' );
scheck( C::$revision->getContent( 'sws' )->getText() === $oldContent && $subject->getSourceSnapshot()->toJson() === $fieldsBefore, 'Public reads do not change slot or entity data' );
$presented = ( new StoredSourceLookup( $context ) )->fields( $title, array_replace( $params, [ 'presentation' => true ] ) );
scheck( $presented['presentation']['version'] === 1 && count( $presented['presentation']['definitions'] ) === 2, 'Graph presentation remains available without source requests' );

$failed = storeField(); $failed['status'] = 'unavailable'; $failed['values'] = [];
$second = storedAttempt( [ $failed ], [ 'wikidata' => [ 'source' => 'wikidata', 'status' => 'unavailable' ] ], $first, 103 );
readStoreSnapshot( $second, );
$failedView = ( new DataViewLookup( $context ) )->read( $title, 'de' );
$remote = $failedView['subjects'][0]['fields'][0]['external'];
scheck( $remote['status'] === 'resolved' && $remote['sourceStatus'] === 'unavailable' && $remote['retained'], 'Last good value remains distinct from failed last request' );
scheck( $failedView['complete'] && $failedView['identities']['wikidata']['retained'] && $failedView['displayDescription']['text'] === 'Gespeicherte Beschreibung', 'Stored source failure does not make retained local data incomplete' );
foreach ( [ 'json', 'turtle', 'trig' ] as $format ) {
	$api = new ExportPageDataViewApi( [ 'format' => $format, 'language' => 'de' ], $context->getAuthority() );
	$response = $api->run( 943 );
	scheck( $response->status === 200 && str_contains( (string)$response->body, 'Gespeicherter Ort' ), "Retained value exports in $format" );
	if ( $format !== 'json' ) { scheck( count( ( new pietercolpaert\hardf\TriGParser() )->parse( (string)$response->body ) ) > 0, "Valid RDF $format" ); }
}
$emptyDescription = $identity; $emptyDescription['description'] = '';
$empty = storedAttempt( [ storeField() ], [ 'wikidata' => $emptyDescription ], $first, 103 );
readStoreSnapshot( $empty );
$idView = ( new StoredSourceLookup( $context ) )->identities( $title, 'de' )['wikidata'];
scheck( $idView['description']['value'] === 'Gespeicherte Beschreibung' && $idView['descriptionSourceStatus'] === 'missing' && $idView['descriptionRetained'], 'Successful empty description preserves its last good text and records missing state' );
$firstFailure = storedAttempt( [ $failed ], [ 'wikidata' => [ 'source' => 'wikidata', 'status' => 'unavailable' ] ] );
readStoreSnapshot( $firstFailure );
$firstFailedView = ( new DataViewLookup( $context ) )->read( $title, 'de' );
scheck( $firstFailedView['complete'] && $firstFailedView['subjects'][0]['fields'][0]['external']['values'] === [], 'A persisted failed result is complete local state, not a fabricated value' );

$withoutStore = new Subject( $subject->id, null, $subject->getSchemaReference(), $subject->getStatements() ); setStorePage( $withoutStore );
$pending = ( new DataViewLookup( $context ) )->read( $title, 'de' );
scheck( !$pending['complete'] && !$pending['subjects'][0]['fields'][0]['external']['recorded'], 'Not-yet-processed legacy entity is explicitly pending' );
scheck( C::$requests === [] && C::$identityLookups === 0, 'Missing snapshot never silently falls back to source APIs' );
scheck( ( new StoredSourceLookup( $context ) )->fields( $title, array_replace( $params, [ 'subject' => 'bad' ] ) )['mainSubjectId'] === null, 'Invalid subject selector cannot select another entity' );
readStoreSnapshot( $first );
$hidden = new Subject( $subject->id, null, $subject->getSchemaReference(), new StatementList( [ new Statement( new PropertyName( 'Name' ), 'text', new StringValue( 'Privater Entwurf' ) ) ] ) );
setStorePage( $hidden, 104 ); C::$publishedId = 102;
$published = ( new DataViewLookup( $context ) )->read( $title, 'de' );
scheck( $published['subjects'][0]['fields'][1]['effectiveValues'][0]['value'] === 'Eigene Angabe', 'Read follows published revision and not a newer draft' );
C::$publish = false; refusesRead( fn () => ( new StoredSourceLookup( $context ) )->page( $title ), 'Unpublished page is not exposed' ); C::$publish = true;
C::$revisions[102]->readable = false; refusesRead( fn () => ( new StoredSourceLookup( $context ) )->page( $title ), 'Suppressed/unreadable published revision is not exposed' ); C::$revisions[102]->readable = true;
C::$publishedId = null; C::$denied = [ 'Artikel' ];
refusesRead( fn () => ( new StoredSourceLookup( $context ) )->page( $title ), 'Page read permission is required' ); C::$denied = [];

SWSExtension::$schema = new Schema( new SchemaName( 'Person' ), '', new PropertyDefinitions( [ 'Ort' => PropertyDefinition::fromJson( [ 'type' => 'text', 'externalProperty' => [ 'source' => 'wikibase-local', 'id' => 'P19' ] ], $registry ) ] ), false );
$localField = storeField( source: 'wikibase-local', id: 'Q500' );
$localSnapshot = storedAttempt( [ $localField ], [ 'wikibase-local' => [ 'source' => 'wikibase-local', 'status' => 'resolved', 'id' => 'Q500', 'url' => 'https://local.example/wiki/Q500', 'description' => 'Lokale Beschreibung' ] ] );
readStoreSnapshot( $localSnapshot ); C::$denied = [ 'Q500' ];
$restricted = ( new DataViewLookup( $context ) )->read( $title, 'de' );
scheck( $restricted['subjects'][0]['fields'][0]['external']['values'] === [] && !isset( $restricted['identities']['wikibase-local']['id'] ), 'Unreadable local source entity is redacted from fields and identity' );
scheck( !str_contains( json_encode( $restricted ), 'Lokale Beschreibung' ) && !str_contains( json_encode( $restricted ), 'Gespeicherter Ort' ), 'No private source label or text leaks through export metadata' );
C::$denied = [ 'P19' ];
scheck( ( new StoredSourceLookup( $context ) )->fields( $title, $params )['fields'][0]['reason'] === 'source-not-readable', 'Local source property permission is rechecked' );
C::$denied = [];
scheck( C::$requests === [] && C::$identityLookups === 0, 'All store/API/identity/description/export/permission reads made zero source data requests' );

// Old D2 snapshots have no separate description entry; they must remain readable.
SWSExtension::$schema = new Schema( new SchemaName( 'Person' ), '', new PropertyDefinitions( $properties ), false );
$legacyData = $second->toJson(); unset( $legacyData['languages']['de']['identities']['wikidata']['description'] );
readStoreSnapshot( SourceSnapshot::fromJson( $legacyData ) );
scheck( ( new StoredSourceLookup( $context ) )->identities( $title, 'de' )['wikidata']['description']['value'] === 'Gespeicherte Beschreibung', 'D2 identity snapshot without separate description state is readable' );

$changedIdentity = $identity; $changedIdentity['id'] = 'Q999'; $changedIdentity['url'] = 'https://www.wikidata.org/wiki/Q999'; $changedIdentity['description'] = 'Neue Identität';
$missingChanged = storeField(); $missingChanged['entityId'] = 'Q999'; $missingChanged['status'] = 'missing'; $missingChanged['values'] = [];
$changed = storedAttempt( [ $missingChanged ], [ 'wikidata' => $changedIdentity ], $first, 103 );
readStoreSnapshot( $changed );
$changedView = ( new DataViewLookup( $context ) )->read( $title, 'de' );
scheck( $changedView['identities']['wikidata']['id'] === 'Q999' && $changedView['subjects'][0]['fields'][0]['external']['entityUrl'] === 'https://www.wikidata.org/wiki/Q41390', 'Retained field provenance keeps its original entity after identity change' );
$quads = ( new SWSBaseBridge\DataViewRdfProjector( SWSExtension::getInstance()->getRdfNamespaces() ) )->project( $changedView )->asArray();
$provenance = array_values( array_filter( $quads, static fn ( $q ) => $q->predicate->value === 'http://www.w3.org/ns/prov#wasDerivedFrom' ) );
scheck( count( array_filter( $provenance, static fn ( $q ) => $q->object->value === 'https://www.wikidata.org/wiki/Q41390' ) ) === 1, 'RDF source statement points to old value owner rather than new page identity' );

// Cache mode is still an explicit read path and does not rewrite the persisted snapshot.
SWSExtension::$schema = new Schema( new SchemaName( 'Person' ), '', new PropertyDefinitions( [ 'Ort' => PropertyDefinition::fromJson( [ 'type' => 'text', 'externalProperty' => [ 'source' => 'wikibase-local', 'id' => 'P19' ] ], $registry ) ] ), false );
readStoreSnapshot( $localSnapshot );
C::$entities['wikibase-local'] = [ 'Q500' => [ 'id' => 'Q500', 'claims' => [ 'P19' => [ [ 'mainsnak' => [ 'snaktype' => 'value', 'datatype' => 'string', 'datavalue' => [ 'value' => 'Cache-Abfrage' ] ] ] ] ] ], 'P19' => [ 'id' => 'P19', 'datatype' => 'string' ] ];
C::$mode = 'cache'; C::$failIds = []; $beforeSlot = C::$revision->getContent( 'sws' )->getText();
$cachedApi = new ApiExternalValues( $params, $context ); $cachedApi->execute();
scheck( count( C::$requests ) > 0 && $cachedApi->output['result']['fields'][0]['values'][0]['value'] === 'Cache-Abfrage', 'Explicit cache mode uses previous source resolution' );
C::$mode = 'store'; $requests = count( C::$requests );
$storedApi = new ApiExternalValues( $params, $context ); $storedApi->execute();
scheck( count( C::$requests ) === $requests && $storedApi->output['result']['fields'][0]['values'][0]['value'] === 'Gespeicherter Ort', 'Switching to store reads persisted data without fetching' );
scheck( C::$revision->getContent( 'sws' )->getText() === $beforeSlot, 'Both read modes leave existing source storage untouched' );
C::$mode = 'live'; refusesRead( fn () => ProfessionalWiki\SWS\Application\Source\SourceDataMode::current(), 'Renamed mode accepts cache/store and rejects former live name' ); C::$mode = 'store';

echo "$checks D3 store/read/API/identity/description/language/export/permission checks passed (controlled MediaWiki host and unavailable sources).\n";
