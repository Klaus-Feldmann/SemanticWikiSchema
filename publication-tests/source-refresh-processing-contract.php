<?php

declare( strict_types = 1 );

require __DIR__ . '/source-snapshot/MediaWikiDoubles.php';
require __DIR__ . '/source-refresh/ProcessingHost.php';
require __DIR__ . '/bootstrap.php';

use D2Harness\State as S;
use MediaWiki\Content\JsonContent;
use MediaWiki\Revision\{ RevisionLookup, RevisionRecord };
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\{ ExternalProperty, PropertyCore, PropertyDefinitions, PropertyName, Schema, SchemaName, SchemaReference };
use ProfessionalWiki\SWS\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\{ StatementList, Subject, SubjectId, SubjectMap };
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\EntryPoints\Content\{ SchemaContent, SubjectContent };
use ProfessionalWiki\SWS\EntryPoints\Processing\{ InternalPageProcessor, InternalSourceSnapshotWriter, ProcessingContext, ProcessingHooks, ProcessingIdentity, ProcessingIncomplete, ProcessingResult, SourceRefreshReceipt, SourceWriteScope };
use ProfessionalWiki\SWS\EntryPoints\SchemaWikitextHooks;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\{ StatementDeserializer, SubjectContentDataDeserializer };
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\Data\{ TestSources, TestSubjectIds };

$checks = 0;
function d2check( bool $condition, string $message ): void {
	global $checks; $checks++; if ( !$condition ) { throw new RuntimeException( $message ); }
}
function d2refuses( callable $operation, string $message ): void {
	try { $operation(); } catch ( ProcessingIncomplete|InvalidArgumentException $e ) { d2check( true, $message ); return; }
	throw new RuntimeException( $message );
}
function resetD2( bool $smw = false ): void {
	S::$history = []; S::$published = []; S::$collected = []; S::$plans = []; S::$queued = []; S::$statuses = [];
	S::$smw = $smw; S::$publishedPolicy = true; S::$parserFailure = false; S::$swsFailure = false; S::$foreignHtml = null; S::$afterCollect = null;
	S::$value = 'Quellenwert'; S::$q = 'Q41390';
	S::$mode = 'store'; S::$liveReads = 0;
	S::$bridge = true;
	S::$readOnly = false;
	SWSExtension::$schema = new Schema( new SchemaName( 'Person' ), '', new PropertyDefinitions( [
		'Quelle' => TextProperty::fromPartialJson( new PropertyCore( '', false, null, externalProperty: new ExternalProperty( 'wikidata', 'P19' ), fieldNotes: [ 'type' => 'text', 'annotate' => true ] ), [] ),
		'Name' => TextProperty::fromPartialJson( new PropertyCore( '', false, null, externalProperty: new ExternalProperty( 'wikidata', 'P1477' ) ), [] ),
	] ), false );
	$subject = new Subject( new SubjectId( 's11111111111111' ), null, SchemaReference::local( new SchemaName( 'Person' ) ),
		new StatementList( [ new Statement( new PropertyName( 'Name' ), 'text', new StringValue( 'Eigene Angabe' ) ) ] ), [ 'Quelle' => 'Forschungsnotiz' ] );
	S::$page = new WikiPage( new RevisionRecord( 101, 943, [ 'main' => new JsonContent( 'Artikel' ), 'sws' => SubjectContent::newFromData( new PageSubjects( $subject, new SubjectMap() ) ) ] ) );
	S::$schemaRevision = new RevisionRecord( 100, 901, [ 'main' => new SchemaContent( '{"propertyDefinitions":{}}' ) ] );
	S::$history[100] = S::$schemaRevision; S::$history[101] = S::$page->revision;
}
function editedD2(): void {
	$prior = S::$page->revision;
	$subjects = $prior->getContent( 'sws' )->getPageSubjects();
	$subjects->getMainSubject()->setFieldNotes( [ 'Quelle' => 'Geänderte eigene Anmerkung' ] );
	S::$page->revision = new RevisionRecord( $prior->getId() + 1, 943, [ 'main' => $prior->getContent( 'main' ), 'sws' => SubjectContent::newFromData( $subjects ) ], $prior->getId() );
	S::$history[S::$page->revision->getId()] = S::$page->revision;
}
SWSExtension::$deserializer = new SubjectContentDataDeserializer( new StatementDeserializer( TestSources::newPropertyTypeRegistry(), TestSubjectIds::newParser() ), new Psr\Log\NullLogger(), TestSources::newSchemaReferenceParser() );
SWSExtension::$policy = new class {
	public function revisionIsReadableBy( mixed $revision, mixed $user ): bool { return $revision->readable; }
	public function publishedRevision( mixed $revision ): mixed { return S::$publishedPolicy ? $revision : null; }
};
SWSExtension::$resolver = static fn () => new class {
	public function resolveMainByTitle( mixed $title ): mixed { return S::$page->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject(); }
	public function resolveById( string $id ): mixed { return S::$page->revision->getContent( 'sws' )->getPageSubjects()->getAllSubjects()->getSubject( new SubjectId( $id ) ); }
};
RevisionLookup::$byTitle = static fn ( $title ) => $title->getNamespace() === 7480 ? S::$schemaRevision : ( $title->getNamespace() === 0 ? S::$page->revision : null );
RevisionLookup::$byId = static fn ( int $id ) => S::$history[$id] ?? null;
WikiPage::$afterSave = static function ( WikiPage $page, RevisionRecord $revision ): void {
	S::$history[$revision->getId()] = $revision;
	$tags = [];
	SchemaWikitextHooks::onRevisionFromEditComplete( $page, $revision, $revision->getParentId(), $page->lastActor, $tags );
	$updates = [ new \MediaWiki\Deferred\LinksUpdate\LinksUpdate() ];
	$rendered = new class( $revision ) {
		public function __construct( private RevisionRecord $revision ) {}
		public function getRevision(): RevisionRecord { return $this->revision; }
		public function getRevisionParserOutput(): mixed { return new \MediaWiki\Parser\ParserOutput(); }
	};
	ProcessingHooks::onRevisionDataUpdates( $page->getTitle(), $rendered, $updates );
	d2check( $updates === [], 'Incomplete internal-save LinksUpdate is withheld' );
};

$title = new Title(); $processor = new InternalPageProcessor();
resetD2();
d2check( $processor->refresh( $title ), 'Initial refresh succeeds without SMW' );
$stored = S::$page->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject();
d2check( S::$collected === [ [ 'de', 'Q41390' ] ], 'First Q identity is available only after full parsing and collected once' );
d2check( array_keys( $stored->getSourceSnapshot()->forLanguage( 'de' )['fields'] ) === [ 'Quelle', 'Name' ], 'All bindings collected even when author value overrides one' );
d2check( S::$page->revision->getId() === 102 && S::$page->revision->getContent( 'main' )->getText() === 'Artikel', 'Only source revision is added; article untouched' );
d2check( $stored->getStatements()->asArray()['Name']->getValue()->toScalars() === [ 'Eigene Angabe' ] && $stored->getFieldNotes() === [ 'Quelle' => 'Forschungsnotiz' ], 'Own statements and author notes preserved' );
d2check( !S::$page->lastActor->authorizeWrite( 'edit', $title ) && S::$page->lastActor->getName() === ProcessingIdentity::NAME, 'Writer uses its fixed read-only system actor' );
d2check( SourceRefreshReceipt::isCurrent( $title, S::$page->revision ) && S::$queued === [], 'Signed receipt prevents recursive refresh scheduling' );
d2check( count( S::$published ) === 1 && ProcessingResult::isCurrent( $title, S::$published[0]['known-revision-output']->getExtensionData( ProcessingResult::STAMP ) ), 'Final output is sealed against stored revision' );
$processor->refresh( $title );
d2check( count( S::$collected ) === 1 && S::$page->saveCalls === 1, 'Duplicate/retry job does not fetch or save again' );

editedD2();
d2check( !SourceRefreshReceipt::isCurrent( $title, S::$page->revision ), 'Author note edit invalidates the source-save receipt' );
S::$value = 'Neuer Quellenwert'; $processor->refresh( $title );
$stored = S::$page->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject();
d2check( count( S::$collected ) === 2 && $stored->getSourceSnapshot()->forLanguage( 'de' )['fields']['Quelle']['lastSuccess']['result']['values'][0]['value'] === 'Neuer Quellenwert', 'Note-only edit fetches all bindings again' );

foreach ( [ 'missing', 'unavailable', 'unsupported', 'manual' ] as $status ) {
	editedD2(); S::$statuses['Quelle'] = $status; $processor->refresh( $title );
	$stored = S::$page->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject();
	$entry = $stored->getSourceSnapshot()->forLanguage( 'de' )['fields']['Quelle'];
	d2check( $entry['lastAttempt']['status'] === $status && $entry['lastSuccess']['result']['values'][0]['value'] === 'Neuer Quellenwert', "$status stores outcome while retaining last usable result" );
	d2check( $stored->getFieldNotes()['Quelle'] === 'Geänderte eigene Anmerkung', "$status never aborts/pollutes author notes" );
}

resetD2( true ); $processor->refresh( $title );
d2check( S::$plans === [ [ 'Person:Quelle=Quellenwert', 'Person:Name=Eigene Angabe', 'Person:Quelle (AM)=Forschungsnotiz' ] ] ||
	S::$plans === [ [ 'Person:Quelle (AM)=Forschungsnotiz', 'Person:Quelle=Quellenwert', 'Person:Name=Eigene Angabe' ] ], 'SMW plan uses persisted source data and own-value precedence' );
d2check( count( S::$collected ) === 1, 'SMW final stage makes no second collection' );
editedD2(); S::$statuses['Quelle'] = 'unavailable'; $processor->refresh( $title );
d2check( in_array( 'Person:Quelle=Quellenwert', S::$plans[1], true ) && in_array( 'Person:Quelle (AM)=sws-notes-source-unavailable', S::$plans[1], true ), 'Retained source value and error note coexist in SMW plan' );

$oldSchema = S::$schemaRevision;
S::$schemaRevision = new RevisionRecord( 200, 901, [ 'main' => new SchemaContent( '{"propertyDefinitions":{"Kategorie":{"type":"wikitext","wikitext":"[[Kategorie:D2-Test]]","execute":true}}}' ) ], 100 );
S::$history[200] = S::$schemaRevision;
d2check( !SourceRefreshReceipt::isCurrent( $title, S::$page->revision ), 'Schema revision change invalidates prior receipt' );
$schemaPage = new class { public function getTitle(): Title { return new Title( 'Person', 901, 7480 ); } };
$tags = []; S::$queued = [];
SchemaWikitextHooks::onRevisionFromEditComplete( $schemaPage, S::$schemaRevision, $oldSchema->getId(), null, $tags );
d2check( count( S::$queued ) === 1 && S::$queued[0] instanceof Closure, 'Actual schema category addition schedules bounded rebuild' );
$schedule = S::$queued[0]; S::$queued = []; $schedule();
d2check( count( S::$queued ) === 1 && S::$queued[0]->params === [ 'after' => 0 ], 'Schema hook starts existing schema batch job' );
$batch = S::$queued[0]; S::$queued = []; $batch->run();
d2check( count( S::$queued ) === 1 && S::$queued[0]->params === [ 'page' => true ], 'Existing batch routes affected page to worker' );
$before = count( S::$collected ); $job = S::$queued[0]; S::$queued = []; $job->run();
d2check( count( S::$collected ) === $before + 1 && SourceRefreshReceipt::isCurrent( $title, S::$page->revision ), 'Schema-triggered page job collects again and completes' );

resetD2(); S::$statuses['Quelle'] = 'unavailable'; $processor->refresh( $title );
d2check( S::$page->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject()->getSourceSnapshot()->forLanguage( 'de' )['fields']['Quelle']['lastSuccess'] === null, 'First source failure completes with empty value and status' );

resetD2(); S::$swsFailure = true;
d2refuses( fn () => $processor->refresh( $title ), 'Explicit SWS processing failure remains blocking' );
d2check( S::$page->saveCalls === 0 && S::$collected === [], 'Explicit SWS failure triggers neither source collection nor partial write' );
resetD2(); S::$publishedPolicy = false;
d2refuses( fn () => $processor->refresh( $title ), 'Unpublished page is not refreshed' );
d2check( S::$collected === [] && S::$page->saveCalls === 0, 'Publication gate precedes fetch and write' );

resetD2();
S::$afterCollect = static function (): void {
	S::$schemaRevision = new RevisionRecord( 201, 901, S::$schemaRevision->content );
};
d2refuses( fn () => $processor->refresh( $title ), 'Schema change during source request rejects outdated result' );
d2check( S::$page->saveCalls === 0, 'Changed schema causes no source write' );
resetD2();
S::$page->beforeSave = static function ( WikiPage $page ): void {
	$page->revision = new RevisionRecord( 103, 943, [ 'main' => new JsonContent( 'Neue Bearbeitung' ), 'sws' => $page->revision->getContent( 'sws' ) ], 101 );
	S::$history[103] = $page->revision;
};
d2refuses( fn () => $processor->refresh( $title ), 'Concurrent user save is detected by PageUpdater CAS' );
d2check( S::$page->revision->getContent( 'main' )->getText() === 'Neue Bearbeitung' && SourceWriteScope::subjectsFor( $title ) === null && ProcessingContext::current() === null, 'Concurrent user edit survives and processing scopes are cleaned' );

resetD2(); $preparation = $processor->prepare( $title, true );
d2check( !ProcessingResult::isCurrent( $title, $preparation->stamp ), 'Preparation seal cannot authorize final SMW/LinksUpdate' );
$forged = new ProcessingResult( $preparation->title, $preparation->revision, $preparation->output, $preparation->options, $preparation->stamp, [], true );
d2refuses( fn () => ( new InternalSourceSnapshotWriter() )->save( $forged ), 'Changed source payload cannot reuse preparation seal' );
( new InternalSourceSnapshotWriter() )->save( $preparation );
$raw = json_decode( S::$page->revision->getContent( 'sws' )->getText(), true );
$raw['subjects']['s11111111111111']['statements']['Name']['value'] = [ 'Fälschung' ];
$forgedRevision = new RevisionRecord( 102, 943, [ 'main' => new JsonContent( 'Artikel' ), 'sws' => new SubjectContent( json_encode( $raw ) ) ], 101 );
d2check( !SourceRefreshReceipt::isCurrent( $title, $forgedRevision ), 'Copied receipt cannot cover changed author data' );
$changedMain = new RevisionRecord( 102, 943, [ 'main' => new JsonContent( 'Geänderter Artikel' ), 'sws' => S::$page->revision->getContent( 'sws' ) ], 101 );
d2check( !SourceRefreshReceipt::isCurrent( $title, $changedMain ), 'Copied receipt cannot cover main-slot change' );
$rights = [ 'read', 'edit', 'delete', 'sysop' ]; ProcessingIdentity::restrictRights( ProcessingIdentity::get(), $rights );
d2check( $rights === [ 'read' ], 'Processor rights restriction remains unchanged' );

resetD2( true ); S::$mode = 'cache'; $processor->refresh( $title );
d2check( S::$collected === [] && S::$page->saveCalls === 0 && S::$liveReads === 1, 'Cache mode retains existing cached source lookup and performs no source-store write' );
d2check( in_array( 'Person:Quelle=Live-Wert', S::$plans[0], true ), 'Cache mode SMW uses live lookup result' );
S::$mode = 'store'; $processor->refresh( $title );
$preservedStore = S::$page->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject()->getSourceSnapshot()->toJson();
d2check( count( S::$collected ) === 1 && S::$liveReads === 1 && in_array( 'Person:Quelle=Quellenwert', S::$plans[1], true ), 'Switch to store collects once then uses persistent values' );
S::$mode = 'cache'; $processor->refresh( $title );
d2check( S::$page->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject()->getSourceSnapshot()->toJson() === $preservedStore && S::$liveReads === 2, 'Switch back to cache retains persistent source data' );
S::$mode = 'store'; $sealed = $processor->prepare( $title, true ); S::$mode = 'cache';
d2refuses( fn () => ( new InternalSourceSnapshotWriter() )->save( $sealed ), 'Mode switch while collecting rejects now-inapplicable store write' );
$manifest = json_decode( file_get_contents( dirname( __DIR__ ) . '/extension.json' ), true );
d2check( $manifest['config']['SWSSourceDataMode']['value'] === 'store', 'Default configuration enables persistent entity store' );

resetD2();
$second = new Subject( new SubjectId( 's11111111111112' ), null, SchemaReference::local( new SchemaName( 'Person' ) ), new StatementList() );
$both = S::$page->revision->getContent( 'sws' )->getPageSubjects(); $both->createOtherSubject( $second );
S::$page->revision = new RevisionRecord( 101, 943, [ 'main' => new JsonContent( 'Artikel' ), 'sws' => SubjectContent::newFromData( $both ) ] );
S::$history[101] = S::$page->revision; $processor->refresh( $title );
d2check( count( S::$collected ) === 2 && S::$page->saveCalls === 1, 'All entities on page are collected and stored in one revision' );
d2check( S::$page->revision->getContent( 'sws' )->getPageSubjects()->getOtherSubjects()->getSubject( $second->id )->getSourceSnapshot() !== null, 'Non-main entity receives its own persistent snapshot' );

resetD2(); $processor->refresh( $title );
$originalReceiptRevision = S::$page->revision;
$resaved = $originalReceiptRevision->getContent( 'sws' )->getPageSubjects();
S::$page->revision = new RevisionRecord( 103, 943, [ 'main' => $originalReceiptRevision->getContent( 'main' ), 'sws' => SubjectContent::newFromData( $resaved ) ], 102 );
S::$history[103] = S::$page->revision;
d2check( !SourceRefreshReceipt::isCurrent( $title, S::$page->revision ), 'Normal subject reserialization removes internal receipt even when author values are unchanged' );
$processor->refresh( $title );
d2check( count( S::$collected ) === 2, 'Saving unchanged subject data refreshes all sources again' );

resetD2(); $a = $processor->prepare( $title, true ); $b = $processor->prepare( $title, true );
( new InternalSourceSnapshotWriter() )->save( $a );
d2refuses( fn () => ( new InternalSourceSnapshotWriter() )->save( $b ), 'Second concurrent collection cannot overwrite first completed write' );
d2check( S::$page->saveCalls === 1, 'Concurrent results produce only one source revision' );
$generic = new \ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSaver( new \MediaWiki\Page\WikiPageFactory( S::$page ), ProcessingIdentity::get() );
$denied = $generic->updateContentAtRevision( new \ProfessionalWiki\SWS\Domain\Page\PageId( 943 ), 102,
	static fn () => [ 'main' => new JsonContent( 'Must not change' ) ], \MediaWiki\CommentStore\CommentStoreComment::newUnsavedComment( 'test' ) );
d2check( $denied->status === 'error' && S::$page->revision->getContent( 'main' )->getText() === 'Artikel', 'Generic write permission remains denied for internal processor' );

resetD2( true ); $processor->refresh( $title ); editedD2(); S::$bridge = false; $processor->refresh( $title );
$withoutBridge = S::$page->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject()->getSourceSnapshot()->forLanguage( 'de' );
d2check( count( S::$collected ) === 1 && $withoutBridge['fields']['Quelle']['lastAttempt']['reason'] === 'bridge-unavailable', 'Missing bridge is recorded as field outcome without attempting its collector' );
d2check( $withoutBridge['fields']['Quelle']['lastSuccess']['result']['values'][0]['value'] === 'Quellenwert' && in_array( 'Person:Name=Eigene Angabe', S::$plans[1], true ), 'Missing bridge retains previous source data and manual annotations' );

resetD2(); $staged = $processor->prepare( $title, true ); S::$readOnly = true;
d2refuses( fn () => ( new InternalSourceSnapshotWriter() )->save( $staged ), 'Internal source writer respects wiki-wide read-only mode' );
d2check( S::$page->saveCalls === 0, 'Read-only mode prevents revision creation' );

echo "$checks D2 processing/capture/storage/receipt/job/SMW-boundary checks passed (controlled MediaWiki host, not live database).\n";
