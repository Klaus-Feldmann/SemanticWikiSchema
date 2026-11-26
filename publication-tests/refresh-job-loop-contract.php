<?php

declare( strict_types = 1 );

require __DIR__ . '/source-snapshot/MediaWikiDoubles.php';
require __DIR__ . '/source-refresh/ProcessingHost.php';
require __DIR__ . '/bootstrap.php';

use AEWI\SWS\Domain\Page\PageSubjects;
use AEWI\SWS\EntryPoints\Content\SubjectContent;
use AEWI\SWS\EntryPoints\Jobs\SchemaWikitextRefreshJob;
use AEWI\SWS\EntryPoints\Processing\InternalPageProcessor;
use AEWI\SWS\EntryPoints\Processing\ProcessingHooks;
use AEWI\SWS\EntryPoints\Processing\ProcessingIncomplete;
use AEWI\SWS\EntryPoints\Processing\ProcessingResult;
use AEWI\SWS\SWSExtension;
use D2Harness\State;
use MediaWiki\Content\JsonContent;
use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;

function checkRefreshLoop( bool $condition, string $message ): void {
	if ( !$condition ) { throw new RuntimeException( $message ); }
}

SWSExtension::$policy = new class {
	public function revisionIsReadableBy( $revision, $user ): bool { return true; }
	public function publishedRevision( RevisionRecord $revision ): RevisionRecord { return $revision; }
};
SWSExtension::$resolver = static fn () => new class {
	public function resolveMainByTitle( $title ): mixed { return null; }
};
$slot = new class( '{"subjects":[]}' ) extends SubjectContent {
	public function getPageSubjects(): PageSubjects { return PageSubjects::newEmpty(); }
};
$revision = new RevisionRecord( 101, 943, [ 'main' => new JsonContent( 'Original' ), 'sws' => $slot ] );
State::$bridge = false;
$page = State::$page = new class( $revision ) extends WikiPage {
	public string $hook = '';
	public ?ProcessingIncomplete $failure = null;
	public int $publications = 0;

	public function doSecondaryDataUpdates( array $params ): void {
		$title = $this->getTitle();
		checkRefreshLoop( ProcessingResult::isCurrent( $title,
			$params['known-revision-output']->getExtensionData( ProcessingResult::STAMP )
		), 'The worker must prepare complete, current output before updating derived data.' );
		$this->publications++;
		try {
			if ( $this->hook === 'links' ) {
				ProcessingHooks::onLinksUpdate( new class( $title ) {
					public function __construct( private Title $title ) {}
					public function getTitle(): Title { return $this->title; }
					public function getParserOutput(): ParserOutput { return new ParserOutput(); }
				} );
			} elseif ( $this->hook === 'smw' ) {
				ProcessingHooks::onBeforeStore( null, new class( $title ) {
					public function __construct( private Title $title ) {}
					public function getSubject(): self { return $this; }
					public function getTitle(): Title { return $this->title; }
					public function getExtensionData( string $name ): mixed { return null; }
				} );
			} elseif ( $this->hook === 'revision' ) {
				$updates = [ new LinksUpdate() ];
				ProcessingHooks::onRevisionDataUpdates( $title, new class( $this->revision ) {
					public function __construct( private RevisionRecord $revision ) {}
					public function getRevision(): RevisionRecord { return $this->revision; }
					public function getRevisionParserOutput(): ParserOutput { return new ParserOutput(); }
				}, $updates );
				checkRefreshLoop( $updates === [], 'Incomplete link updates must still be removed.' );
				ProcessingHooks::queue( new Title( 'Other', 944 ) );
			}
		} catch ( ProcessingIncomplete $error ) {
			$this->failure = $error;
			throw $error;
		}
	}
};

$title = $page->getTitle();
foreach ( [ 'links', 'smw' ] as $hook ) {
	$page->hook = $hook;
	$page->failure = null;
	State::$queued = [];
	$job = new SchemaWikitextRefreshJob( $title, [ 'page' => true ] );
	checkRefreshLoop( !$job->run() && $page->failure !== null, "$hook: incomplete output was not refused." );
	checkRefreshLoop( State::$queued === [], "$hook: a failing job must not enqueue itself again." );
	checkRefreshLoop( $page->revision === $revision, "$hook: the source revision changed." );
	checkRefreshLoop( !InternalPageProcessor::isRefreshing( $title ), "$hook: refresh scope survived a failure." );
	ProcessingHooks::queue( $title );
	checkRefreshLoop( count( State::$queued ) === 1, "$hook: an independent update must remain queueable." );
}

$page->hook = 'revision';
State::$queued = [];
checkRefreshLoop( ( new SchemaWikitextRefreshJob( $title, [ 'page' => true ] ) )->run(), 'Complete output must still publish.' );
checkRefreshLoop( count( State::$queued ) === 1 && State::$queued[0]->title->getArticleID() === 944,
	'Only the independent page should be queued.' );
checkRefreshLoop( !InternalPageProcessor::isRefreshing( $title ), 'Refresh scope survived success.' );

$page->hook = '';
$dependency = Title::makeTitleSafe( 10, 'Dependency' );
$dependencyRevision = 201;
RevisionLookup::$byTitle = static function ( Title $title ) use ( $dependency, &$dependencyRevision, $revision ): RevisionRecord {
	return $title->getNamespace() === $dependency->getNamespace()
		? new RevisionRecord( $dependencyRevision, $dependency->getArticleID(), [ 'main' => new JsonContent( 'Template' ) ] )
		: $revision;
};
foreach ( [ 1, 2 ] as $conflicts ) {
	$renders = 0;
	$publicationCount = $page->publications;
	State::$warnings = [];
	State::$queued = [];
	$dependencyRevision = 201;
	State::$afterRender = static function ( ParserOutput $output ) use ( $dependency, &$dependencyRevision, &$renders, $conflicts ): void {
		$output->addTemplate( $dependency, $dependency->getArticleID(), $dependencyRevision );
		if ( ++$renders <= $conflicts ) { $dependencyRevision++; }
	};
	$result = ( new SchemaWikitextRefreshJob( $title, [ 'page' => true ] ) )->run();
	checkRefreshLoop( $result === ( $conflicts === 1 ), 'Only a transient conflict should recover in the same job.' );
	checkRefreshLoop( $renders === 2, 'Preparation can run at most twice.' );
	checkRefreshLoop( $page->publications === $publicationCount + ( $conflicts === 1 ? 1 : 0 ),
		'An outdated result must never reach publication.' );
	checkRefreshLoop( State::$queued === [], 'Conflicts must not create another queue entry.' );
	checkRefreshLoop( count( State::$warnings ) === $conflicts, 'Every failed preparation must log the conflict.' );
	foreach ( State::$warnings as $index => $warning ) {
		checkRefreshLoop( $warning['reason'] === 'dependency-revision-changed' &&
			$warning['page'] === $title->getPrefixedText() && $warning['target'] === $dependency->getPrefixedText() &&
			$warning['expectedRevision'] === 201 + $index && $warning['currentRevision'] === 202 + $index,
			'The log must name the dependency and both revision IDs.' );
	}
}

echo "SWS refresh: prevents self-queuing, retries one transient conflict and never publishes stale results.\n";
