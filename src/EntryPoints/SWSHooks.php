<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints;

use Exception;
use HTMLCacheUpdateJob;
use HtmlArmor;
use ManualLogEntry;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Context\ContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Html\Html;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Search\SearchUpdate;
use MediaWiki\Specials\SpecialSearch;
use MediaWiki\Title\ForeignTitle;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Rdbms\IDBAccessObject;
use Wikimedia\Rdbms\IResultWrapper;
use MessageLocalizer;
use NullIndexField;
use ProfessionalWiki\SWS\Application\Rdf\RdfPageProjector;
use ProfessionalWiki\SWS\Application\SchemaLookup;
use ProfessionalWiki\SWS\Application\Search\SubjectSearchHit;
use ProfessionalWiki\SWS\Application\SubjectPermissionHints;
use ProfessionalWiki\SWS\Domain\GraphDatabase\BackendFailureMessage;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\EntryPoints\Content\LayoutContent;
use ProfessionalWiki\SWS\EntryPoints\Content\MappingContent;
use ProfessionalWiki\SWS\EntryPoints\Actions\SubjectsAction;
use ProfessionalWiki\SWS\EntryPoints\Jobs\RebuildLastEditorPagesJob;
use ProfessionalWiki\SWS\EntryPoints\Scribunto\ScribuntoLuaLibrary;
use ProfessionalWiki\SWS\Maintenance\RebuildPublishedSubjectIndex;
use ProfessionalWiki\SWS\Maintenance\RebuildSubjectPageIndex;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\SWS\Presentation\DocumentationUrl;
use ProfessionalWiki\SWS\Presentation\PageToolsBuilder;
use ProfessionalWiki\SWS\Presentation\SubjectNameMessage;
use ProfessionalWiki\SWS\Presentation\SubjectLabelHtml;
use ProfessionalWiki\SWS\Presentation\ViewHtmlBuilder;
use MediaWiki\SpecialPage\SpecialPage;
use SearchEngine;
use SearchIndexField;
use SearchResult;
use Skin;
use SkinTemplate;
use stdClass;
use Throwable;
use WikiPage;

class SWSHooks {

	private const SPECIAL_PAGE_CLASS_PREFIX = 'ProfessionalWiki\\SWS\\EntryPoints\\SpecialPages\\';

	private const SEARCH_UPDATE_BEFORE_1_44 = 'MediaWiki\\Deferred\\SearchUpdate';

	/**
	 * Marks a page whose HTML holds the mount MainSubjectParserFunction places, as extension data on its
	 * ParserOutput and as a property of the OutputPage showing it. Such a page gets no automatic rendering
	 * of its Main Subject.
	 */
	private const string PLACES_MAIN_SUBJECT = 'sws-places-main-subject';

	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		$carriesCreateSubjectButton = self::carriesCreateSubjectButton( $out );

		if ( self::isContentPage( $out ) ) {
			self::handleContentPage( $out, $skin );
		} elseif ( self::isSchemaPage( $out ) && $out->isArticle() ) {
			self::handleSchemaPage( $out, $skin );
		} elseif ( self::isLayoutPage( $out ) && $out->isArticle() ) {
			self::handleLayoutPage( $out, $skin );
		} elseif ( $carriesCreateSubjectButton ) {
			// The button still needs the config the other page kinds load the module with.
			SWSExtension::getInstance()->newFrontendModuleLoader()->load( $out, $skin );
		}

		if ( $carriesCreateSubjectButton ) {
			SWSExtension::getInstance()->newFrontendModuleLoader()->loadCreateSubjectPageDeniedReason( $out );
		}
	}

	/**
	 * On an article view, only {{#create_subject}} puts the frontend module in the list before any loader has
	 * run. Special pages and actions add it themselves before this hook, so they do not count.
	 */
	private static function carriesCreateSubjectButton( OutputPage $out ): bool {
		return $out->isArticle() && in_array( 'ext.sws', $out->getModules(), true );
	}

	private static function isContentPage( OutputPage $out ): bool {
		return $out->isArticle()
			&& \ProfessionalWiki\SWS\Infrastructure\SubjectNamespacePolicy::allowsTitle( $out->getTitle() );
	}

	private static function handleContentPage( OutputPage $out, Skin $skin ): void {
		self::warnAboutHalfConfiguredNeo4j();

		$extension = SWSExtension::getInstance();
		$permissionHints = $extension->newSubjectPermissionHints( $out->getAuthority() );

		$extension->newFrontendModuleLoader()->loadWithSubjectPermissions(
			$out,
			$skin,
			$permissionHints->canEditSubject( new PageId( $out->getTitle()->getArticleID() ) )
		);
		$out->addHtml( self::getSWSAppHtml( $out, $permissionHints ) );
		self::addRdfAutodiscoveryLinks( $out );

		$revisionId = self::revisionIdUnlessLatest( $out );
		$builder = $extension->newViewHtmlBuilder();

		self::headByMainSubject( $out, $builder, $revisionId );

		if ( $out->getProperty( self::PLACES_MAIN_SUBJECT ) === true ) {
			self::addSubjectUiConfig( $out );
			return;
		}

		// On a diff, the Main Subject is shown atop the revision below the diff instead.
		if ( !$extension->shouldAutoRenderMainSubject() || $out->getRequest()->getCheck( 'diff' ) ) {
			return;
		}

		if ( $extension->isSubjectFirst() && self::pageIsLatestRevision( $out ) ) {
			self::appendMainSubjectInFull( $out, $builder );
			return;
		}

		$html = $out->getHTML();
		$out->clearHTML();
		$out->addHTML( $builder->mainSubjectHtml( $out->getTitle(), $revisionId ) );
		$out->addHTML( $html );
	}

	private static function appendMainSubjectInFull( OutputPage $out, ViewHtmlBuilder $builder ): void {
		$html = $builder->mainSubjectInFullHtml( $out->getTitle() );

		if ( $html === '' ) {
			return;
		}

		self::addSubjectUiConfig( $out );
		$out->addHTML( $html );
	}

	private static function addSubjectUiConfig( OutputPage $out ): void {
		$out->addJsConfigVars( SWSExtension::getInstance()->getSubjectUiJsConfigVars( $out->getAuthority() ) );
	}

	/**
	 * A wiki with no graph backend is a supported configuration: Subjects, Schemas, Views and the value
	 * accessors all work without one, and only the query surfaces a backend brings are unavailable. Half a
	 * Neo4j configuration is not a configuration, though — it reads as a backend that was meant to be
	 * there, so it is still reported.
	 */
	private static function warnAboutHalfConfiguredNeo4j(): void {
		if ( !SWSExtension::getInstance()->config->hasHalfConfiguredNeo4j() ) {
			return;
		}

		LoggerFactory::getInstance( 'SWS' )->warning(
			'SWS: only one of the Neo4j read/write Bolt URLs is configured; both are required. '
			. 'Neo4j is disabled.'
		);
	}

	private static function getSWSAppHtml( OutputPage $out, SubjectPermissionHints $permissionHints ): string {
		$attrs = [
			'id' => 'ext-sws-app',
		];

		if ( self::shouldShowSubjectCreator( $out, $permissionHints ) ) {
			$attrs['data-mw-sws-create-subject'] = 'true';
			$attrs['data-mw-sws-page-has-main-subject'] =
				SWSExtension::getInstance()->newPageSubjectsLookup()
					->pageHasMainSubject( new PageId( $out->getTitle()->getArticleID() ) )
						? 'true'
						: 'false';
		}

		return Html::element( 'div', $attrs );
	}

	private static function shouldShowSubjectCreator( OutputPage $out, SubjectPermissionHints $permissionHints ): bool {
		return $permissionHints->canCreateMainSubject( new PageId( $out->getTitle()->getArticleID() ) )
			&& self::pageIsLatestRevision( $out );
	}

	/**
	 * Heads the page with its Main Subject's label and id, or "No label defined" and the id. The label
	 * wins over a display title the page's own wikitext sets, and is applied only where core would apply
	 * one: not on a diff, which is headed by the diff, nor where an error stands in for the revision,
	 * which either leaves no revision id or heads the page as an error.
	 */
	private static function headByMainSubject( OutputPage $out, ViewHtmlBuilder $builder, ?int $revisionId ): void {
		if ( $out->getRevisionId() === null
			|| $out->getRequest()->getCheck( 'diff' )
			|| $out->getPageTitle() === Sanitizer::removeSomeTags( $out->msg( 'errorpagetitle' )->escaped() )
		) {
			return;
		}

		$subject = $builder->subjectInPlaceOfPageTitle( $out->getTitle(), $revisionId );

		if ( $subject === null ) {
			return;
		}

		SubjectLabelHtml::headPage( $out, $subject->getLabel()?->text, $subject->getId() );
	}

	public static function onDifferenceEngineRenderRevisionAddParserOutput(
		$differenceEngine,
		OutputPage $out,
		ParserOutput $parserOutput,
		$wikiPage
	): void {
		$extension = SWSExtension::getInstance();

		if ( !self::isContentPage( $out ) || !$extension->shouldAutoRenderMainSubject() ) {
			return;
		}

		// The revision shows its Main Subject where it places it, as an infobox on a diff.
		if ( $parserOutput->getExtensionData( self::PLACES_MAIN_SUBJECT ) === true ) {
			return;
		}

		$out->addHTML( $extension->newViewHtmlBuilder()->mainSubjectHtml(
			$out->getTitle(),
			self::revisionIdUnlessLatest( $out )
		) );
	}

	private static function revisionIdUnlessLatest( OutputPage $out ): ?int {
		return self::pageIsLatestRevision( $out ) ? null : $out->getRevisionId();
	}

	/**
	 * In Recent changes and watchlists, a link to a page titled by a Subject id reads as the page's
	 * heading does when its Main Subject has a label: that label and the id. Only rows the list read up
	 * front change, and only links that would show the bare title, rendered where the list renders. The
	 * parser renders with a link renderer of its own, and a list transcluded into a page reads nothing
	 * up front, so page content never gets a label.
	 *
	 * @param string|HtmlArmor|null &$text
	 * @param string[] &$customAttribs
	 * @param string[] &$query
	 * @param string &$ret
	 */
	public static function onHtmlPageLinkRendererBegin(
		LinkRenderer $linkRenderer,
		LinkTarget $target,
		&$text,
		&$customAttribs,
		&$query,
		&$ret
	): bool {
		// The cheap checks first: this runs for every link rendered.
		if ( $text !== null
			|| !self::isIdShaped( $target )
			|| $linkRenderer !== MediaWikiServices::getInstance()->getLinkRenderer()
		) {
			return true;
		}

		$context = RequestContext::getMain();

		$subject = SWSExtension::getInstance()->getSubjectInPlaceOfPageTitleLookup()->forListedPage(
			Title::newFromLinkTarget( $target ),
			$context->getAuthority(),
			$context->getOutput()
		);

		if ( $subject === null ) {
			return true;
		}

		$text = new HtmlArmor( SubjectLabelHtml::withId(
			$context,
			$subject->getLabel()?->text,
			$subject->getId()->text
		) );

		return true;
	}

	/**
	 * A permission error is what removes the Move tab and gives Special:MovePage and the API their reason.
	 *
	 * @param array|string|MessageSpecifier &$result
	 */
	public static function onGetUserPermissionsErrors( Title $title, User $user, string $action, &$result ): bool {
		if ( $action !== 'move' || !self::isSubjectsOwnPage( $title ) ) {
			return true;
		}

		$result = [ 'sws-subject-page-immovable' ];

		return false;
	}

	private static function isSubjectsOwnPage( Title $title ): bool {
		$extension = SWSExtension::getInstance();

		if ( !$title->exists()
			|| !SubjectDisplayName::mayBeTitledBySubjectId( $title->getPrefixedText() )
			|| !$extension->isSubjectFirst()
		) {
			return false;
		}

		return SubjectDisplayName::isTitledBySubjectOnIt(
			$title->getPrefixedText(),
			$extension->newPageSubjectsLookup()->getPageSubjects( new PageId( $title->getArticleID() ) ),
            fn(string $id): string => $extension->getSubjectIdParser(true)->parse($id)?->text ?? $id
		);
	}

	private static function isIdShaped( LinkTarget $target ): bool {
		return $target->getInterwiki() === ''
			&& SubjectDisplayName::mayBeTitledBySubjectId( $target->getText() );
	}

	/**
	 * Reads the labels a Recent changes or watchlist page links by in one batch rather than one read per
	 * row. Only pages that can be titled by a Subject id, as only they are headed by their label.
	 *
	 * A list transcluded into a page renders in a context of its own, and gets no labels: the page
	 * would keep them in its cache after they changed.
	 *
	 * @param IResultWrapper|stdClass[] $rows
	 */
	public static function onChangesListInitRows( ContextSource $changesList, $rows ): void {
		if ( $changesList->getContext() !== RequestContext::getMain() ) {
			return;
		}

		$pageIds = [];

		foreach ( $rows as $row ) {
			if ( (int)$row->rc_cur_id !== 0
				&& self::isIdShaped( new TitleValue( (int)$row->rc_namespace, (string)$row->rc_title ) )
			) {
				$pageIds[] = (int)$row->rc_cur_id;
			}
		}

		// A list that cannot be labelled is still a list: its links keep their titles.
		try {
			SWSExtension::getInstance()->getSubjectInPlaceOfPageTitleLookup()->prefetch(
				$pageIds,
				$changesList->getOutput()
			);
		} catch ( Throwable $exception ) {
			LoggerFactory::getInstance( 'SWS' )->warning(
				'SWS: reading the Subjects of listed pages failed: {exception}',
				[ 'exception' => $exception ]
			);
		}
	}

	/**
	 * The styles for labelled links go with every Recent changes and watchlist page, since the filters
	 * reload the list without them and it can start out empty.
	 */
	public static function onChangesListSpecialPageStructuredFilters( SpecialPage $specialPage ): void {
		$specialPage->getOutput()->addModuleStyles( [ SubjectLabelHtml::STYLE_MODULE ] );
	}

	private static function pageIsLatestRevision( OutputPage $out ): bool {
		return $out->getRevisionId() === $out->getTitle()->getLatestRevID();
	}

	/**
	 * Advertises the page's RDF export (native projection) via `<link rel="alternate">` autodiscovery
	 * tags — one Turtle, one TriG — so Linked Data tooling finds the data without reading the API docs.
	 * Every page that exists has an export, holding its page metadata and any Subjects, so every page
	 * advertises one. The exception is a page SWS cannot read, whose export 404s; such a page does
	 * not render at all, so no link is advertised for it either way.
	 * Native only; the per-projection exports are reachable from the Data tab UI.
	 */
	private static function addRdfAutodiscoveryLinks( OutputPage $out ): void {
		$pageId = $out->getTitle()->getArticleID();

		// A view of a title that has no page is still a content-page view, and there is nothing to
		// advertise for a page that does not exist.
		if ( $pageId === 0 ) {
			return;
		}

		$services = MediaWikiServices::getInstance();
		$endpoint = $services->getMainConfig()->get( MainConfigNames::RestPath )
			. '/sws/v0/page/' . $pageId . '/rdf?projection=' . RdfPageProjector::PROJECTION;
		$urlUtils = $services->getUrlUtils();

		foreach ( [ 'turtle' => 'text/turtle', 'trig' => 'application/trig' ] as $format => $type ) {
			$href = $endpoint . '&format=' . $format;
			$out->addLink( [
				'rel' => 'alternate',
				'type' => $type,
				'href' => $urlUtils->expand( $href, PROTO_CANONICAL ) ?? $href,
			] );
		}
	}

	private static function handleSchemaPage( OutputPage $out, Skin $skin ): void {
		SWSExtension::getInstance()->newFrontendModuleLoader()->load( $out, $skin );

		$out->addHTML(
			Html::element(
				'div',
				[
					'id' => 'ext-sws-view-schema',
				]
			)
		);
	}

	public static function onMediaWikiServices( MediaWikiServices $services ): void {
		$services->addServiceManipulator(
			'SlotRoleRegistry',
			static function ( SlotRoleRegistry $registry ): void {
				if ( in_array( MediaWikiSubjectRepository::SLOT_NAME, $registry->getDefinedRoles() ) ) {
					return; // Avoid duplicate slot definition.
				}

				$registry->defineRoleWithModel(
					role: MediaWikiSubjectRepository::SLOT_NAME,
					model: SubjectContent::CONTENT_MODEL_ID,
					layout: [ 'display' => 'none' ]
				);
			}
		);
	}

	/**
	 * @see LoadExtensionSchemaUpdatesHook
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ): void {
		$sqlDirectory = dirname( __DIR__, 2 ) . '/sql/' . $updater->getDB()->getType();

		$updater->addExtensionTable( 'sws_rebuild_runs', $sqlDirectory . '/sws_rebuild_runs.sql' );
		$updater->addExtensionField(
			'sws_rebuild_runs',
			'swsrr_phase',
			$sqlDirectory . '/patch-sws_rebuild_runs-swsrr_phase.sql'
		);
		$updater->addExtensionTable( 'sws_subject_page', $sqlDirectory . '/sws_subject_page.sql' );
		$updater->addExtensionTable( 'sws_subject', $sqlDirectory . '/sws_subject.sql' );
		$updater->addExtensionTable( 'sws_relation', $sqlDirectory . '/sws_relation.sql' );

		foreach ( [ 'sws_id_counter', 'sws_identity', 'sws_identity_alias', 'sws_identity_host', 'sws_search_term' ] as $identityTable ) {
			$updater->addExtensionTable( $identityTable, $sqlDirectory . '/' . $identityTable . '.sql' );
		}

		$updater->addExtensionField( 'sws_identity', 'swsi_owner', $sqlDirectory . '/patch-sws_identity-owner.sql' );
		$updater->addExtensionField( 'sws_identity', 'swsi_used', $sqlDirectory . '/patch-sws_identity-used.sql' );

		// Between creating a table and filling it, no Subject that existed before is found through it, so
		// each backfill runs in the same update.php as the table it fills.
		$updater->addPostDatabaseUpdateMaintenance( RebuildSubjectPageIndex::class );
		$updater->addPostDatabaseUpdateMaintenance( RebuildPublishedSubjectIndex::class );

		$updater->addExtensionUpdate( [ [ self::class, 'initializeIdentityCounters' ] ] );
		$updater->addExtensionUpdate( [ [ self::class, 'initializeGraphDatabases' ] ] );
	}

	/** Seed identity kinds without resetting previously issued numbers. */
	public static function initializeIdentityCounters( DatabaseUpdater $updater ): void {
		// Seed once through update.php. Runtime locks existing rows directly; it does not upgrade
		// shared duplicate-insert locks to exclusive locks on every MySQL reservation.
		foreach ( \ProfessionalWiki\SWS\Domain\Identity\IdentityKind::cases() as $kind ) {
			$updater->getDB()->insert( 'sws_id_counter', [ 'swsic_kind' => $kind->value, 'swsic_value' => 0 ],
				__METHOD__, [ 'IGNORE' ] );
		}
	}

	/**
	 * A failing backend is reported rather than thrown, and the update carries on: DatabaseUpdater runs
	 * MediaWiki's own schema changes before it fires LoadExtensionSchemaUpdates, so a throw here would
	 * not protect those — it would skip the updates of every extension queued after SWS. The graph
	 * is rebuildable derived state, so it is not worth that.
	 *
	 * The catch is broader than the hook path's deliberate TimeoutException/DBError re-throws, since
	 * there is no user operation here to abort. It deliberately covers building the plugins as well as
	 * initializing them, so that a wiki whose SWS configuration is not readable yet — as during a
	 * fresh install — still finishes its update.
	 */
	public static function initializeGraphDatabases( DatabaseUpdater $updater ): void {
		$updater->output( 'Initializing SWS graph databases...' );

		try {
			SWSExtension::getInstance()->getGraphDatabasePlugin()->initialize();
		} catch ( Exception $e ) {
			self::reportFailedGraphDatabaseInitialization( $updater, $e );
			return;
		}

		$updater->output( "done.\n" );
	}

	private static function reportFailedGraphDatabaseInitialization( DatabaseUpdater $updater, Exception $e ): void {
		$reason = BackendFailureMessage::withoutCredentials( $e->getMessage() );

		$updater->output(
			"failed.\n"
			. '...' . $reason . "\n"
			. "...Re-run update.php once the cause is resolved. The reads the backend serves fail while\n"
			. "...it is unreachable, and the edits made meanwhile are missing from its projection: run\n"
			. "...RebuildGraphDatabases.php to reconcile it.\n"
		);

		// Logged as well, because update.php --quiet discards everything written to the updater, which
		// would otherwise leave a failed initialization with no trace anywhere. The redacted reason
		// rather than the exception, so that what is kept out of the terminal stays out of the log too.
		LoggerFactory::getInstance( 'SWS' )->error(
			'SWS failed to initialize its graph databases during update.php. The wiki itself is updated. '
			. 'Underlying error: ' . $reason
		);
	}

	/**
	 * @param array<string, mixed> $defaults
	 * @param array<string, bool> $inCacheKey
	 * @param array<string, callable> $lazyOptions
	 */
	public static function onParserOptionsRegister( array &$defaults, array &$inCacheKey, array &$lazyOptions ): void {
		ParserAuthority::registerAccessClassOption( $defaults, $inCacheKey );
	}

	/**
	 * @param string[] $forOptions
	 */
	public static function onPageRenderingHash( string &$confstr, User $user, array &$forOptions ): void {
		ParserAuthority::appendAccessClassToRenderingHash( $confstr, $user, $forOptions );
	}

	public static function onParserFirstCallInit( Parser $parser ): void {
		$neo4jPlugin = SWSExtension::getInstance()->getNeo4jPlugin();
		if ( $neo4jPlugin === null ) {
			self::registerUnconfiguredQueryFunction( $parser, 'cypher_raw', 'sws-cypher-raw-error-not-configured' );
		} else {
			$neo4jPlugin->registerParserFunctions( $parser );
		}

		$sparqlPlugin = SWSExtension::getInstance()->getFirstSparqlPlugin();
		if ( $sparqlPlugin === null ) {
			self::registerUnconfiguredQueryFunction( $parser, 'sparql_raw', 'sws-sparql-raw-error-not-configured' );
		} else {
			$sparqlPlugin->registerParserFunctions( $parser );
		}

		$parser->setFunctionHook(
			'view',
			static function ( Parser $parser, string ...$args ): string|array {
				$parserFunction = new ViewParserFunction( SWSExtension::getInstance()->newPageSubjectsLookup() );
				return $parserFunction->handle( $parser, ...$args );
			}
		);

		$parser->setFunctionHook(
			'main_subject',
			static function ( Parser $parser, string ...$args ): string|array {
				$parserFunction = new MainSubjectParserFunction( SWSExtension::getInstance()->newPageSubjectsLookup() );
				return $parserFunction->handle( $parser, ...$args );
			}
		);

		$parser->setFunctionHook(
			'sws_value',
			static function ( Parser $parser, string ...$args ): string|array {
				$parserFunction = new SWSValueParserFunction(
					SWSExtension::getInstance()->newSubjectResolver( $parser ),
					ParserFieldLookup::create( $parser )
				);
				return $parserFunction->handle( $parser, ...$args );
			}
		);

		$parser->setFunctionHook(
			'create_subject',
			static function ( Parser $parser, string ...$args ): string|array {
				$parserFunction = new CreateSubjectParserFunction(
					SWSExtension::getInstance()->newPageSubjectsLookup(),
					SWSExtension::getInstance()->isSubjectFirst(),
					static fn ( Parser $parser ): SchemaLookup => SWSExtension::getInstance()
						->getSchemaLookupFor( ParserAuthority::of( $parser ) )
				);
				return $parserFunction->handle( $parser, ...$args );
			}
		);
	}

	/**
	 * An unregistered parser function renders as its own wikitext, which looks broken instead of saying the
	 * wiki lacks the Graph Store.
	 */
	private static function registerUnconfiguredQueryFunction( Parser $parser, string $name, string $messageKey ): void {
		$parser->setFunctionHook(
			$name,
			static fn ( Parser $parser ): array => [
				'<div class="error">' . $parser->msg( $messageKey )->escaped() . '</div>',
				'noparse' => true,
				'isHTML' => true,
			]
		);
	}

	/**
	 * Only a mount that reached the HTML counts: a condition or a Lua module can discard what the parser
	 * function returned.
	 */
	public static function onParserAfterTidy( Parser $parser, string &$text ): void {
		if ( ViewHtmlBuilder::containsMainSubjectInFull( $text ) ) {
			$parser->getOutput()->setExtensionData( self::PLACES_MAIN_SUBJECT, true );
		}
	}

	public static function onOutputPageParserOutput( OutputPage $out, ParserOutput $parserOutput ): void {
		if ( $parserOutput->getExtensionData( self::PLACES_MAIN_SUBJECT ) === true ) {
			$out->setProperty( self::PLACES_MAIN_SUBJECT, true );
		}
	}

	/**
	 * @see RevisionFromEditCompleteHook
	 */
	public static function onRevisionFromEditComplete(
		WikiPage $wikiPage,
		RevisionRecord $revision,
		int|bool $originalRevId,
		UserIdentity $user,
		array &$tags
	): void {
		$mainContent = $revision->getContent( 'main' );
		if ( !$originalRevId && $mainContent instanceof SchemaContent ) {
			$data = json_decode( $mainContent->getText(), true );
			if ( ( $data['formatVersion'] ?? 1 ) === 2 ) {
				SWSExtension::getInstance()->newIdentityRegistry()->markUsed( new \ProfessionalWiki\SWS\Domain\Identity\SchemaId( $data['id'] ) );
			}
		}
        if ( \ProfessionalWiki\SWS\Application\IdentityMigration\MigrationWriteScope::active() ) {
            SWSExtension::getInstance()->indexMigrationRevision( $revision );
            return;
        }
		SWSExtension::getInstance()->getStoreContentUC()->onRevisionCreated( $revision );
		$wikiPage->doPurge(); // clear cache

		// A save from the Subject editor inherits the main slot, and MediaWiki both indexes a page for
		// search and purges the pages using it as a template only when its main slot changed.
		if ( self::changedOnlyTheSubjects( $revision ) ) {
			self::scheduleSearchUpdate( $revision );
			self::purgePagesReadingTheSubjectsOf( $wikiPage->getTitle() );
		}

		if ( self::changedTheContent( $revision ) ) {
			self::rebuildStoresHoldingChangedMapping( $wikiPage->getTitle() );
		}
	}

	/**
	 * A parse records a page it reads Subjects from as a template (ParserPageDependencyRecorder), so
	 * the pages reading this one get the purge MediaWiki queues for pages using an edited template.
	 *
	 * @see WikiPage::queueBacklinksJobs()
	 */
	private static function purgePagesReadingTheSubjectsOf( Title $title ): void {
		DeferredUpdates::addCallableUpdate( static function () use ( $title ): void {
			$services = MediaWikiServices::getInstance();

			if ( !$services->getBacklinkCacheFactory()->getBacklinkCache( $title )->hasLinks( 'templatelinks' ) ) {
				return;
			}

			$services->getJobQueueGroup()->push(
				HTMLCacheUpdateJob::newForBacklinks( $title, 'templatelinks', [ 'causeAction' => 'edit-page' ] )
			);
		} );
	}

	private static function scheduleSearchUpdate( RevisionRecord $revision ): void {
		// MediaWiki 1.44 moved SearchUpdate to MediaWiki\Search without an alias and stopped it being a
		// DeferrableUpdate, so the class this wiki has is named at runtime and run from a callable.
		$searchUpdate = class_exists( SearchUpdate::class ) ? SearchUpdate::class : self::SEARCH_UPDATE_BEFORE_1_44;

		DeferredUpdates::addCallableUpdate(
			static function () use ( $searchUpdate, $revision ): void {
				( new $searchUpdate(
					$revision->getPageId(),
					$revision->getPage(),
					$revision->getSlot( SlotRecord::MAIN, RevisionRecord::RAW )->getContent()
				) )->doUpdate();
			}
		);
	}

	/**
	 * Indexes the page's current revision when core did not: core indexes a page only for a revision
	 * that wrote the main slot, which a save from the Subject editor inherits. The current revision is
	 * read fresh from the primary database rather than taken from the revision the hook was handed,
	 * which for an undeletion or an import is not necessarily the one that ended up current.
	 */
	private static function scheduleSearchUpdateOfCurrentRevision( PageIdentity $page ): void {
		$revision = MediaWikiServices::getInstance()->getRevisionLookup()
			->getRevisionByPageId( $page->getId(), 0, IDBAccessObject::READ_LATEST );

		if ( $revision === null || !self::slotIsInherited( $revision, SlotRecord::MAIN ) ) {
			return;
		}

		self::scheduleSearchUpdate( $revision );
	}

	/**
	 * The shape of a save from the Subject editor: the Subject slot written, the main one inherited.
	 */
	private static function changedOnlyTheSubjects( RevisionRecord $revision ): bool {
		return self::slotIsInherited( $revision, SlotRecord::MAIN )
			&& !self::slotIsInherited( $revision, MediaWikiSubjectRepository::SLOT_NAME );
	}

	/**
	 * True too for a slot the revision does not have at all.
	 */
	private static function slotIsInherited( RevisionRecord $revision, string $role ): bool {
		return !$revision->hasSlot( $role )
			|| $revision->getSlot( $role, RevisionRecord::RAW )->isInherited();
	}

	/**
	 * Declares the field holding a page's Subject text, for a search engine that takes fields from extensions.
	 *
	 * @param SearchIndexField[] &$fields
	 */
	public static function onSearchIndexFields( array &$fields, SearchEngine $engine ): void {
		$field = $engine->makeSearchFieldMapping(
			SWSExtension::SUBJECT_SEARCH_FIELD,
			SearchIndexField::INDEX_TYPE_TEXT
		);

		if ( $field instanceof NullIndexField ) {
			return;
		}

		$fields[SWSExtension::SUBJECT_SEARCH_FIELD] = $field;
	}

	public static function onSearchDataForIndex2(
		array &$fields,
		ContentHandler $handler,
		WikiPage $page,
		ParserOutput $output,
		SearchEngine $engine,
		RevisionRecord $revision
	): void {
		$fields[SWSExtension::SUBJECT_SEARCH_FIELD] = SWSExtension::getInstance()
			->newSubjectSearchTextLookup()->getSearchTextForRevision( $revision );
	}

	/**
	 * Leads a result row to its Main Subject where the page exists only to hold it.
	 *
	 * @param Title &$title
	 * @param string|HtmlArmor|null &$titleSnippet
	 * @param SearchResult $result
	 * @param string[] $terms
	 * @param SpecialSearch $specialSearch
	 * @param string[] &$query
	 * @param string[] &$attributes
	 */
	public static function onShowSearchHitTitle( &$title, &$titleSnippet, $result, $terms, $specialSearch, &$query, &$attributes ): void {
		// ShowSearchHit is not run for files, so a retargeted file row would keep the thumbnail and
		// description of the page it no longer leads to.
		if ( $title->getNamespace() === NS_FILE ) {
			return;
		}

		$landing = self::subjectSearchHitFor( $specialSearch, $title, $terms )?->landing;

		if ( $landing === null ) {
			return;
		}

		$title = SpecialPage::getTitleFor( 'Subject', $landing->subjectId->text );
		$titleSnippet = SubjectNameMessage::from( $specialSearch, $landing->subjectId, $landing->chosenName )->text();
	}

	/**
	 * Shows what a result row matched in a Subject, which MediaWiki's own highlighter cannot see.
	 *
	 * @param SpecialSearch $searchPage
	 * @param SearchResult $result
	 * @param string[] $terms
	 * @param string &$link
	 * @param string &$redirect
	 * @param string &$section
	 * @param string &$extract
	 * @param string &$score
	 * @param string &$size
	 * @param string &$date
	 * @param string &$related
	 * @param string &$html
	 */
	public static function onShowSearchHit(
		$searchPage, $result, $terms, &$link, &$redirect, &$section, &$extract, &$score, &$size, &$date, &$related, &$html
	): void {
		$title = $result->getTitle();

		if ( $title === null ) {
			return;
		}

		$hit = self::subjectSearchHitFor( $searchPage, $title, $terms );

		if ( $hit === null ) {
			return;
		}

		$htmlBuilder = SWSExtension::getInstance()->newSubjectSearchHitHtmlBuilder( $searchPage );

		if ( $hit->match !== null ) {
			$extract = $htmlBuilder->buildExtract( $hit->match ) . $extract;
		}

		if ( $hit->landing !== null ) {
			// The size is the page's, not the Subject's.
			$size = '';
		}
	}

	/**
	 * @param string[] $terms
	 */
	private static function subjectSearchHitFor( SpecialSearch $searchPage, Title $title, array $terms ): ?SubjectSearchHit {
		return SWSExtension::getInstance()->getSubjectSearchHitLookup()->forRow(
			$searchPage->getAuthority(),
			$title,
			$terms,
			$searchPage->getRequest()->getText( 'search' )
		);
	}

	/**
	 * Leads the Go button to the Main Subject of a page that exists only to hold it.
	 *
	 * @param string $term
	 * @param Title|null &$title
	 */
	public static function onSearchGetNearMatchComplete( $term, &$title ): void {
		$context = RequestContext::getMain();

		// Also asked by list=search&srwhat=nearmatch, which keeps answering with pages.
		if ( $title === null || !$context->getTitle()?->isSpecial( 'Search' ) ) {
			return;
		}

		$landing = SWSExtension::getInstance()->getSubjectSearchHitLookup()
			->landingForTitle( $context->getAuthority(), $title );

		if ( $landing !== null ) {
			$title = SpecialPage::getTitleFor( 'Subject', $landing->subjectId->text );
		}
	}

	/**
	 * Whether this revision says anything new. Protecting or unprotecting a page, or changing when that
	 * expires, inserts a revision carrying the content of the one before it, and this hook fires for
	 * those as for any other. A Mapping page is what a projection is defined by, so reading one of them
	 * as a definition change throws away an in-flight rebuild and reprojects the wiki to reach the same
	 * graph it already had.
	 */
	private static function changedTheContent( RevisionRecord $revision ): bool {
		$parentId = $revision->getParentId();

		if ( $parentId === null || $parentId === 0 ) {
			return true;
		}

		$parent = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $parentId );

		return $parent === null || $parent->getSha1() !== $revision->getSha1();
	}

	/**
	 * A saved or deleted Mapping page changes what every mapped page's graph should contain, and nothing
	 * reprojects those pages. Wikis that have asked for it have the stores holding that projection
	 * rebuilt here; the rest are left to Special:GraphStores, which reports them as stale.
	 *
	 * Deferred past the change's own transaction, because starting a rebuild takes a database lock that
	 * flushes the connection's snapshot, which a transaction with writes pending may not do — and because
	 * an edit must not wait on a lock or a queue to be saved. A wiki that has not asked for this registers
	 * no update at all, so every Mapping edit on it costs nothing.
	 */
	private static function rebuildStoresHoldingChangedMapping( Title $title ): void {
		if ( $title->getNamespace() !== SWSExtension::NS_MAPPING
			|| !SWSExtension::getInstance()->shouldRebuildOnMappingChange() ) {
			return;
		}

		$mappingName = $title->getText();

		// One update per store, so each takes its start lock on a connection with nothing pending. Sharing
		// a round, the second store's lock would find the first store's writes still there and throw.
		foreach (
			SWSExtension::getInstance()->newMappingChangeRebuilder()->storesHoldingProjection( $mappingName )
			as $storeName
		) {
			DeferredUpdates::addCallableUpdate( static function () use ( $storeName, $mappingName ): void {
				self::rebuildStoreHoldingMapping( $storeName, $mappingName );
			} );
		}
	}

	/**
	 * Nothing is thrown out of here: the Mapping has been saved or deleted by the time this runs. A store
	 * whose rebuild could not even be assembled — a backend whose configuration will not resolve — is
	 * reported rather than allowed to take the rest of the deferred work down with it, and
	 * Special:GraphStores still shows the store as stale.
	 */
	private static function rebuildStoreHoldingMapping( string $storeName, string $mappingName ): void {
		try {
			SWSExtension::getInstance()->newMappingChangeRebuilder()->onMappingChanged( $storeName );
		} catch ( Throwable $e ) {
			LoggerFactory::getInstance( 'SWS' )->error(
				'SWS could not rebuild graph store "' . $storeName . '", which holds the projection '
				. 'Mapping page "' . $mappingName . '" defines, so it still holds the old vocabulary. '
				. 'Rebuild it from Special:GraphStores. Underlying error: '
				. BackendFailureMessage::withoutCredentials( $e->getMessage() ),
				[ 'exception' => $e, 'mapping' => $mappingName ]
			);
		}
	}

	/**
	 * Projects imported pages, which RevisionFromEditComplete does not cover, as imported revisions do
	 * not go through the edit path. WikiImporter fires this hook for every import path, once per page and
	 * only once all of the page's revisions are in, so one handler replaces what would otherwise be a
	 * special case per import path. Special:Import and the import API additionally project through
	 * RevisionFromEditComplete, because ImportReporter creates a null revision on top of the import; that
	 * reprojects the same content, making it redundant rather than harmful.
	 *
	 * @see AfterImportPageHook
	 *
	 * @param array<string, mixed> $pageInfo
	 */
	public static function onAfterImportPage(
		Title $title,
		ForeignTitle $foreignTitle,
		int $revCount,
		int $sRevCount,
		array $pageInfo
	): void {
		SWSExtension::getInstance()->newHookPageRebuilder()->rebuild( $title );

		self::scheduleSearchUpdateOfCurrentRevision( $title );
	}

	public static function onCodeEditorGetPageLanguage( Title $title, ?string &$lang, ?string $model, ?string $format ): void {
		if ( in_array( $model, [ SubjectContent::CONTENT_MODEL_ID, SchemaContent::CONTENT_MODEL_ID, LayoutContent::CONTENT_MODEL_ID, MappingContent::CONTENT_MODEL_ID ] ) ) {
			$lang = 'json';
		}
	}

	public static function onPageDeleteComplete( ProperPageIdentity $page, Authority $deleter, string $reason, int $pageId, RevisionRecord $deletedRev ): void {
		SWSExtension::getInstance()->getStoreContentUC()->onPageDelete( $pageId );

		$title = Title::newFromPageIdentity( $page );
		self::rebuildStoresHoldingChangedMapping( $title );
	}

	/**
	 * Projects a page restored from the archive, once per undeletion. The per-revision RevisionUndeleted
	 * hook cannot do this: it fires for every restored revision, including ones that do not become
	 * current, and projecting those would write the page as of a stale revision — a restore of old
	 * history onto a page that still exists would replace its Subjects with the ones that revision held.
	 *
	 * The page's current revision is read fresh from the primary database rather than taken from
	 * $restoredRev, which is the last restored revision: a partial restore leaves a newer revision
	 * current.
	 *
	 * @see PageUndeleteCompleteHook
	 *
	 * @param int[] $restoredPageIds
	 */
	public static function onPageUndeleteComplete(
		ProperPageIdentity $page,
		Authority $restorer,
		string $reason,
		RevisionRecord $restoredRev,
		ManualLogEntry $logEntry,
		int $restoredRevisionCount,
		bool $created,
		array $restoredPageIds
	): void {
		$title = Title::newFromPageIdentity( $page );

		SWSExtension::getInstance()->newHookPageRebuilder()->rebuild( $title );

		self::scheduleSearchUpdateOfCurrentRevision( $title );

		// Restoring a Mapping page puts a projection back that the stores holding it were rebuilt
		// without, so it changes what their graphs should contain exactly as deleting it did.
		self::rebuildStoresHoldingChangedMapping( $title );
	}

	/**
	 * Reprojects a page whose revision visibility changed. RevisionDelete hides who made a revision, or
	 * shows them again, without creating a revision, so no other entry point fires and the graph would
	 * go on serving a name MediaWiki no longer shows (#1246).
	 *
	 * Which revisions changed is not examined: a page is projected from whichever revision is current, so
	 * reprojecting it agrees with the wiki when that revision was one of them, and rewrites what the graph
	 * already holds when it was not — a redundant write at the frequency RevisionDelete is used. The
	 * current revision is read from the primary database, since the change has only just committed.
	 *
	 * @see ArticleRevisionVisibilitySetHook
	 *
	 * @param int[] $ids
	 * @param array<int, array{oldBits: int, newBits: int}> $visibilityChangeMap
	 */
	public static function onArticleRevisionVisibilitySet( Title $title, array $ids, array $visibilityChangeMap ): void {
		SWSExtension::getInstance()->newHookPageRebuilder()->rebuild( $title );
	}

	/**
	 * Reprojects the pages of a user a block has just hidden. Hiding a user hides the name on every
	 * revision they made, by a direct write that fires no revision hook of its own, so nothing else
	 * would take those names out of the graph (#1246).
	 *
	 * @see BlockIpCompleteHook
	 */
	public static function onBlockIpComplete( DatabaseBlock $block, User $blocker, ?DatabaseBlock $priorBlock ): void {
		self::rebuildPagesOfHiddenUser( $block );
	}

	/**
	 * The counterpart for an unblock, which shows a hidden user's name again.
	 *
	 * This hook runs *before* MediaWiki clears the hidden flags, so the work has to be queued rather
	 * than done here: reading the revisions now would still find the name hidden and change nothing.
	 *
	 * @see UnblockUserCompleteHook
	 */
	public static function onUnblockUserComplete( DatabaseBlock $block, User $unblocker ): void {
		self::rebuildPagesOfHiddenUser( $block );
	}

	/**
	 * Queued rather than done in the request: a block changes every page the user last edited at once,
	 * where the write path this reuses handles one page at a time. Nothing is queued for a block that
	 * does not hide the user, which leaves the graph as it was.
	 *
	 * The queueing is itself deferred, because the unblock hook runs before the flags are cleared and
	 * lazyPush is not lazy under the command line — it pushes there and then, where a runner could
	 * claim the job while the revisions still read as hidden and reproject the name straight back out.
	 */
	private static function rebuildPagesOfHiddenUser( DatabaseBlock $block ): void {
		$user = $block->getTargetUserIdentity();

		if ( !$block->getHideName() || $user === null ) {
			return;
		}

		DeferredUpdates::addCallableUpdate( static function () use ( $user ): void {
			MediaWikiServices::getInstance()->getJobQueueGroup()->lazyPush(
				RebuildLastEditorPagesJob::newSpecification( $user )
			);
		} );
	}

	public static function onSpecialPageInitList( array &$specialPages ): void {
		if ( !SWSExtension::getInstance()->isDevelopmentUIEnabled() ) {
			unset( $specialPages['NeoJson'] );
		}

		// Special:SpecialPages lists groups in the order in which each group's first page appears in this
		// list, and core offers no hook for group order, so SWS's pages are moved to the front.
		$swsPages = array_filter(
			$specialPages,
			fn ( $spec ) => is_string( $spec ) && str_starts_with( $spec, self::SPECIAL_PAGE_CLASS_PREFIX )
		);
		$specialPages = $swsPages + $specialPages;
	}

	public static function onContentModelCanBeUsedOn( string $modelId, Title $title, bool &$ok ): void {
		if ( $title->getNamespace() === SWSExtension::NS_SCHEMA ) {
			$ok = $modelId === SchemaContent::CONTENT_MODEL_ID || (
                $modelId === CONTENT_MODEL_WIKITEXT &&
                \ProfessionalWiki\SWS\Application\IdentityMigration\MigrationWriteScope::allowsSchemaRedirect($title->getDBkey())
            );
		}

		if ( $title->getNamespace() === SWSExtension::NS_LAYOUT ) {
			$ok = $modelId === LayoutContent::CONTENT_MODEL_ID;
		}

		if ( $title->getNamespace() === SWSExtension::NS_MAPPING ) {
			$ok = $modelId === MappingContent::CONTENT_MODEL_ID;
		}
	}

	public static function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ): bool {
		if ( $engine === 'lua' && SWSExtension::getInstance()->isLuaEnabled() ) {
			$extraLibraries['mw.sws'] = ScribuntoLuaLibrary::class;
		}

		return true;
	}

	private static function isSchemaPage( OutputPage $out ): bool {
		return $out->getTitle()->getNamespace() === SWSExtension::NS_SCHEMA;
	}

	private static function isLayoutPage( OutputPage $out ): bool {
		return $out->getTitle()->getNamespace() === SWSExtension::NS_LAYOUT;
	}

	public static function onSidebarBeforeOutput( Skin $skin, array &$sidebar ): void {
		$title = $skin->getTitle();

		if ( $title === null ) {
			return;
		}

		$swsTools = self::pageTools( $skin, $title );

		// First, and offered to readers as well: the page to start from when the wiki is unfamiliar.
		array_unshift( $swsTools, self::specialPageLink(
			$skin,
			specialPage: 'SWS',
			message: 'sws-sidebar-overview',
			linkId: 't-sws-overview'
		) );

		if ( $title->getNamespace() === SWSExtension::NS_SCHEMA ) {
			$swsTools[] = self::specialPageLink(
				$skin,
				specialPage: 'Schemas',
				message: 'sws-schema-sidebar-all-schemas',
				linkId: 't-sws-schemas'
			);
		}

		if ( $title->getNamespace() === SWSExtension::NS_LAYOUT ) {
			$swsTools[] = self::specialPageLink(
				$skin,
				specialPage: 'Layouts',
				message: 'sws-layout-sidebar-all-layouts',
				linkId: 't-sws-layouts'
			);
		}

		if ( $title->getNamespace() === SWSExtension::NS_MAPPING ) {
			$swsTools[] = self::specialPageLink(
				$skin,
				specialPage: 'Mappings',
				message: 'sws-mapping-sidebar-all-mappings',
				linkId: 't-sws-mappings'
			);
		}

		if ( self::shouldLinkCreateSubjectPage( $skin, $swsTools ) ) {
			$swsTools[] = self::specialPageLink(
				$skin,
				specialPage: 'CreateSubject',
				message: 'sws-sidebar-create-subject',
				linkId: 't-sws-create-subject-page'
			);
		}

		// The section array key is used by MediaWiki as the message key for
		// the section heading, so it must match an existing message name.
		$sidebar['sws-page-tools-label'] = $swsTools;
	}

	/**
	 * Page tools are offered on SWS-eligible namespaces. The check comes before the builder so that other page
	 * views, special pages included, skip the Subject permission lookups the tools need.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function pageTools( Skin $skin, Title $title ): array {
		$isContentNamespace = \ProfessionalWiki\SWS\Infrastructure\SubjectNamespacePolicy::allowsTitle( $title );

		if ( !$isContentNamespace ) {
			return [];
		}

		$extension = SWSExtension::getInstance();
		$hints = $extension->newSubjectPermissionHints( $skin->getAuthority() );
		$pageId = new PageId( $title->getArticleID() );

		return ( new PageToolsBuilder() )->build(
			title: $title,
			pageId: $title->getArticleID(),
			canCreateMainSubject: $hints->canCreateMainSubject( $pageId ),
			canEditSubject: $hints->canEditSubject( $pageId ),
			isLatestRevision: self::pageIsLatestRevision( $skin->getOutput() ),
			devUiEnabled: $extension->isDevelopmentUIEnabled()
		);
	}

	/**
	 * The page tools open the Subject creator on the page being viewed. Where they do not, the special
	 * page is the way to it, so one Create subject entry shows per page rather than two.
	 *
	 * @param list<array<string, mixed>> $tools
	 */
	private static function shouldLinkCreateSubjectPage( Skin $skin, array $tools ): bool {
		if ( in_array( PageToolsBuilder::CREATE_SUBJECT_ID, array_column( $tools, 'id' ), true ) ) {
			return false;
		}

		return $skin->getAuthority()->isAllowedAll( 'createpage', 'edit' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function specialPageLink(
		Skin $skin,
		string $specialPage,
		string $message,
		string $linkId
	): array {
		return [
			'text' => $skin->msg( $message )->text(),
			'href' => SpecialPage::getTitleFor( $specialPage )->getLocalURL(),
			'id' => $linkId,
		];
	}

	public static function onSkinTemplateNavigationUniversal( SkinTemplate $sktemplate, array &$links ): void {
		$title = $sktemplate->getTitle();

		if ( $title !== null && SWSExtension::getInstance()->isConfigPage( $title ) ) {
			self::removeNamespaceTabs( $title, $links );
			self::labelSettingsFormTabs( $sktemplate, $title, $links );
		}

		if ( !SubjectsAction::isEligibleTitle( $title ) ) {
			return;
		}

		$action = $sktemplate->getRequest()->getRawVal( 'action' );

		$links['views']['sws-subjects'] = [
			'class' => $action === SubjectsAction::ACTION_NAME ? 'selected' : false,
			'text' => $sktemplate->msg( 'sws-managesubjects-tab' )->text(),
			'href' => $title->getLocalURL( [ 'action' => SubjectsAction::ACTION_NAME ] ),
		];
	}

	/**
	 * The settings page shows neither "Message", as other MediaWiki: pages call themselves, nor "Discussion".
	 * Current skins draw associated-pages, to which core copies no removal from namespaces.
	 */
	private static function removeNamespaceTabs( Title $title, array &$links ): void {
		$subjectKey = $title->getNamespaceKey( '' );

		foreach ( [ $subjectKey, $subjectKey . '_talk' ] as $tabKey ) {
			unset( $links['associated-pages'][$tabKey], $links['namespaces'][$tabKey] );
		}
	}

	/**
	 * For someone who may change the settings, viewing the page opens the settings form, so its tab is an Edit, beside
	 * the Edit source of the JSON.
	 */
	private static function labelSettingsFormTabs( SkinTemplate $skin, Title $title, array &$links ): void {
		$mayEdit = $skin->getAuthority()->probablyCan( 'edit', $title );

		if ( $mayEdit && isset( $links['views']['view'] ) && isset( $links['views']['edit'] ) ) {
			$links['views']['view']['text'] = $skin->msg( 'skin-view-edit' )->text();
			$links['views']['edit']['text'] = $skin->msg( 'sws-config-editor-edit-source-tab' )->text();
		}
	}

	private static function handleLayoutPage( OutputPage $out, Skin $skin ): void {
		SWSExtension::getInstance()->newFrontendModuleLoader()->load( $out, $skin );

		$out->addHTML(
			Html::element(
				'div',
				[
					'id' => 'ext-sws-view-layout',
				]
			)
		);
	}

	/**
	 * Forces the JSON content model on the on-wiki configuration page, so MediaWiki core requires the
	 * editinterface and editsitejson rights to edit it and enforces JSON syntax on save.
	 */
	public static function onContentHandlerDefaultModelFor( Title $title, ?string &$model ): void {
		if ( SWSExtension::getInstance()->isConfigPage( $title ) ) {
			$model = CONTENT_MODEL_JSON;
		}
	}

	/**
	 * Validates the on-wiki configuration page on save, blocking the edit with precise per-field errors
	 * when the configuration is invalid. A JSON syntax error is left to the JSON content model itself.
	 */
	public static function onEditFilter( EditPage $editor, string $text, string $section, string &$error, string $summary ): void {
		$extension = SWSExtension::getInstance();

		if ( !$extension->isConfigPage( $editor->getTitle() ) ) {
			return;
		}

		$errors = $extension->getConfigValidator()->validate( $text );

		if ( $errors !== [] ) {
			$error = self::formatConfigErrors( $errors, $editor->getContext() );
		}
	}

	/**
	 * @param array[] $errors A list of message specs, each [ messageKey, ...params ].
	 */
	private static function formatConfigErrors( array $errors, MessageLocalizer $localizer ): string {
		$items = '';

		foreach ( $errors as $errorSpec ) {
			$items .= Html::rawElement( 'li', [], $localizer->msg( ...$errorSpec )->escaped() );
		}

		return Html::errorBox(
			$localizer->msg( 'sws-config-invalid' )->escaped() . Html::rawElement( 'ul', [], $items )
		);
	}

	public static function onAlternateEdit( EditPage $editPage ): void {
		self::frameConfigPageEdit( $editPage );
		self::explainMappingPageEdit( $editPage );
	}

	/**
	 * On the on-wiki configuration page, suppresses the default MediaWiki-namespace intro and frames the
	 * JSON editor with a pointer to the documentation and the schema-generated configuration reference.
	 */
	private static function frameConfigPageEdit( EditPage $editPage ): void {
		$extension = SWSExtension::getInstance();

		if ( !$extension->isConfigPage( $editPage->getTitle() ) ) {
			return;
		}

		$editPage->suppressIntro = true;

		$builder = $extension->newConfigDocumentationBuilder( $editPage->getContext() );
		$editPage->editFormTextTop = $builder->buildPointer();
		$editPage->editFormTextBottom = $builder->buildReference();
	}

	/**
	 * Points at the Mapping format documentation, since the raw JSON editor is currently the only way to
	 * write a Mapping. Core's edit intro is left in place, so creating a Mapping page still shows that the
	 * page does not exist yet.
	 */
	private static function explainMappingPageEdit( EditPage $editPage ): void {
		if ( $editPage->getTitle()->getNamespace() !== SWSExtension::NS_MAPPING ) {
			return;
		}

		$editPage->editFormTextTop = Html::rawElement(
			'div',
			[ 'class' => 'ext-sws-mapping-docs-pointer' ],
			$editPage->getContext()->msg( 'sws-mapping-docs-pointer', DocumentationUrl::MappingFormat->value )->parse()
		);
	}

	/**
	 * On a plain view of the on-wiki configuration page, shows the form in place of the rendered JSON.
	 */
	public static function onConfigPageBeforePageDisplay( OutputPage $out, Skin $skin ): void {
		$title = $out->getTitle();

		// Only a view or a diff of the page is an article view: no other action is, nor an error page such as
		// the one shown to a viewer who may not read the page.
		if ( $title === null || !$out->isArticle() || !SWSExtension::getInstance()->isConfigPage( $title ) ) {
			return;
		}

		$request = $out->getRequest();

		// A diff or an older revision asked for by id is core's to show, as is its error for a revision it
		// cannot show. A plain view always gets the form, which edits the latest revision even where an
		// approval extension shows readers an older one.
		if ( $request->getCheck( 'diff' ) ) {
			return;
		}

		if ( $request->getCheck( 'oldid' ) && !self::pageIsLatestRevision( $out ) ) {
			return;
		}

		self::showConfigEditor( $out, $skin, $title );
	}

	private static function showConfigEditor( OutputPage $out, Skin $skin, Title $title ): void {
		$extension = SWSExtension::getInstance();

		$extension->newFrontendModuleLoader()->load( $out, $skin );
		$out->setPageTitleMsg( $out->msg( 'sws-config-editor-title' ) );
		$out->addJsConfigVars(
			'wgSWSConfigEditor',
			$extension->newConfigEditorJsConfigBuilder( $out->getAuthority() )->build(
				revisionId: $title->exists() ? $title->getLatestRevID() : null
			)
		);
		$out->addHelpLink( DocumentationUrl::OnWikiSettings->value, true );
		// As Edit source does: a copy from the browser's cache, shown on going back, would save stale settings.
		$out->disableClientCache();

		$out->clearHTML();
		$out->addHTML( Html::element( 'div', [ 'id' => 'ext-sws-config-editor' ] ) );
	}

}
