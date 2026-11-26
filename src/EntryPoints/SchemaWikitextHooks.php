<?php

declare( strict_types = 1 );
namespace AEWI\SWS\EntryPoints;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use AEWI\SWS\Application\SchemaWikitext;
use AEWI\SWS\EntryPoints\Content\SchemaContent;
use AEWI\SWS\Infrastructure\ParserPageDependencyRecorder;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Persistence\MediaWiki\Subject\SubjectSlotReader;
use AEWI\SWS\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use AEWI\SWS\EntryPoints\Jobs\SchemaWikitextRefreshJob;
use AEWI\SWS\EntryPoints\Processing\UserWikitextProcessor;
use Throwable;

final class SchemaWikitextHooks {
	private const MARKER = 'sws-schema-wikitext-visited';

	public static function onParserBeforeInternalParse( Parser $parser, string &$text, $stripState ): void {
		if ( \AEWI\SWS\Application\IdentityMigration\MigrationWriteScope::active() || \AEWI\SWS\EntryPoints\Processing\FieldWikitextContext::isActive()
			|| $parser->getOutputType() !== Parser::OT_HTML || $parser->getOptions()->isMessage()
			|| $parser->getOutput()->getExtensionData( self::MARKER ) ) { return; }
		$parser->getOutput()->setExtensionData( self::MARKER, true );
		$title = $parser->getTitle();
		if ( !$title->canExist() || $title->getContentModel() !== 'wikitext' ) { return; }
		// Lua reads the current subject, so do not graft that subject onto historical page views.
		$revisionId = $parser->getRevisionId();
		if ( $revisionId && $revisionId < $title->getLatestRevID() ) { return; }
		try {
			$extension = SWSExtension::getInstance();
			$subjects = $extension->newSubjectResolver( $parser )->getPageSubjectsByTitle( $title );
			if ( $subjects === null ) { return; }
			$authority = ParserAuthority::of( $parser );
			$resolver = $extension->getSchemaResolverFor( $authority );
			$recorder = new ParserPageDependencyRecorder( $parser );
			$schemas = [];
			$resolveSchema = static function ( $ref ) use ( $recorder, $resolver, &$schemas ) {
				$key = $ref->getText();
				if ( !array_key_exists( $key, $schemas ) ) {
					$recorder->recordDependencyOnSchema( $ref );
					$schemas[$key] = $resolver->getSchema( $ref );
				}
				return $schemas[$key];
			};
			$subject = $subjects->getMainSubject();
			$revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title );
			$policy = $extension->getRevisionPolicy();
			if ( $revision && $policy->publishedRevision( $revision )?->getId() === $revision->getId()
				&& $policy->revisionIsReadableBy( $revision, $authority ) ) {
				$values = UserWikitextProcessor::bodyValues( $subjects, $resolveSchema );
				if ( $values !== [] ) {
					// Transclusion fetches the stored main-slot source without this host-page parse.
					$text = SchemaWikitext::append( $text, [ implode( "\n\n", $values ) ] );
					$parser->getOutput()->setExtensionData( UserWikitextProcessor::BODY_RENDERED, true );
				}
			}
			if ( $subject === null || !$subject->getSchemaReference()->isLocal() ) { return; }
			$reference = $subject->getSchemaReference();
			$schema = $resolveSchema( $reference );
			if ( $schema === null ) { return; }
			// Include schema categories in ordinary view parses as well as in the sealed worker output.
			if ( $revision && $revision->userCan( RevisionRecord::DELETED_TEXT, $authority ) ) {
				$pageSubjects = SubjectSlotReader::read( $revision )?->getPageSubjects();
				if ( $pageSubjects !== null && $pageSubjects->getMainSubject() == $subject ) {
					$resolve = static function ( $ref ) use ( $extension, $authority, $parser ) {
						( new ParserPageDependencyRecorder( $parser ) )->recordDependencyOnSchema( $ref );
						return $extension->getSchemaResolverFor( $authority )->getSchema( $ref )
							?? throw new \RuntimeException( 'Category schema unavailable' );
					};
					( new \AEWI\SWS\EntryPoints\Processing\CategoryAssignments() )->apply(
						$parser->getOutput(), $schema, $pageSubjects, $subject, $resolve
					);
				}
			}
			$text = SchemaWikitext::append( $text, SchemaWikitext::programs( $schema ) );
		} catch ( Throwable $e ) {
			if ( $context = \AEWI\SWS\EntryPoints\Processing\ProcessingContext::current() ) { $context->failure = $e->getMessage(); }
			LoggerFactory::getInstance( 'SWS' )->error( 'Schema wikitext preparation failed', [ 'exception' => $e ] );
			$text .= "\n<strong class=\"error\">" . wfMessage( 'sws-wikitext-error' )->escaped() . '</strong>';
		}
	}

	public static function onRevisionFromEditComplete( $wikiPage, RevisionRecord $revision, int|bool $originalRevId, $user, array &$tags ): void {
        if ( \AEWI\SWS\Application\IdentityMigration\MigrationWriteScope::active() ) { return; }
		try {
			if ( \AEWI\SWS\EntryPoints\Processing\SourceRefreshReceipt::isCurrent( $wikiPage->getTitle(), $revision ) ) { return; }
			$old = $originalRevId ? MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( (int)$originalRevId ) : null;
			$main = $revision->getContent( SlotRecord::MAIN );
			$oldMain = $old?->getContent( SlotRecord::MAIN );
			if ( $main instanceof SchemaContent || $oldMain instanceof SchemaContent ) {
				if ( $main?->getText() !== $oldMain?->getText() ) {
					$title = $wikiPage->getTitle();
					DeferredUpdates::addCallableUpdate( static function () use ( $title ): void {
						MediaWikiServices::getInstance()->getJobQueueGroup()->push( new SchemaWikitextRefreshJob( $title, [ 'after' => 0 ] ) );
					} );
				}
				return;
			}
			if ( !$revision->hasSlot( MediaWikiSubjectRepository::SLOT_NAME ) ) {
				if ( !$old?->hasSlot( MediaWikiSubjectRepository::SLOT_NAME ) ) { return; }
			} elseif ( $revision->getSlot( MediaWikiSubjectRepository::SLOT_NAME, RevisionRecord::RAW )->isInherited() ) { return; }
			if ( !MediaWikiServices::getInstance()->hasService( 'SMW.ParserDataFactory' ) &&
                !self::usesExecution( $revision ) && !( $old && self::usesExecution( $old ) ) ) { return; }
			$title = $wikiPage->getTitle();
			DeferredUpdates::addCallableUpdate( static function () use ( $title ): void {
				try {
					$ok = self::refreshPage( $title );
					if ( !$ok ) { MediaWikiServices::getInstance()->getJobQueueGroup()->push( new SchemaWikitextRefreshJob( $title, [ 'page' => true ] ) ); }
				} catch ( Throwable $e ) {
					LoggerFactory::getInstance( 'SWS' )->error( 'Schema wikitext page refresh failed; queued retry', [ 'exception' => $e ] );
					MediaWikiServices::getInstance()->getJobQueueGroup()->push( new SchemaWikitextRefreshJob( $title, [ 'page' => true ] ) );
				}
			} );
		} catch ( Throwable $e ) {
			LoggerFactory::getInstance( 'SWS' )->error( 'Schema wikitext refresh scheduling failed', [ 'exception' => $e ] );
		}
	}

	private static function usesExecution( RevisionRecord $revision ): bool {
		$subjects = SubjectSlotReader::read( $revision )?->getPageSubjects();
		foreach ( $subjects?->getAllSubjects()->asArray() ?? [] as $subject ) {
			if ( !$subject->getSchemaReference()->isLocal() ) { continue; }
			$schema = SWSExtension::getInstance()->getSchemaResolver()->getSchema( $subject->getSchemaReference() );
			if ( $schema === null ) { continue; }
			if ( SchemaWikitext::programs( $schema ) !== [] ) { return true; }
			foreach ( $schema->getAllProperties()->asMap() as $property ) {
				if ( $property->getExternalProperty() !== null
                    || $property instanceof \AEWI\SWS\Domain\Schema\Property\PageProperty && $property->assignsCategories()
					|| $property instanceof \AEWI\SWS\Domain\Schema\Property\WikitextValueProperty
                    || ( $property->getFieldNotes()['type'] ?? null ) === 'wikitext' ) { return true; }
			}
		}
		return false;
	}

	public static function refreshPage( Title $title ): bool {
        return ( new \AEWI\SWS\EntryPoints\Processing\InternalPageProcessor() )->refresh( $title );
	}

	public static function refreshJob( Title $title ): object {
		MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title )->doPurge();
		$class = class_exists( \MediaWiki\JobQueue\Jobs\RefreshLinksJob::class )
			? \MediaWiki\JobQueue\Jobs\RefreshLinksJob::class : \RefreshLinksJob::class;
		// No root timestamp: the job must obtain a fresh parse rather than reuse an inherited main-slot output.
		return new $class( $title, [ 'recursive' => false, 'useRecursiveLinksUpdate' => false,
			'causeAction' => 'sws-schema-wikitext', 'causeAgent' => 'SWS' ] );
	}
}
