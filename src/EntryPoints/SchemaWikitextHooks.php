<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\SchemaWikitext;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\SWS\Infrastructure\ParserPageDependencyRecorder;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SubjectSlotReader;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\SWS\EntryPoints\Jobs\SchemaWikitextRefreshJob;
use Throwable;

final class SchemaWikitextHooks {
	private const MARKER = 'sws-schema-wikitext-visited';

	public static function onParserBeforeInternalParse( Parser $parser, string &$text, $stripState ): void {
		if ( \ProfessionalWiki\SWS\Application\IdentityMigration\MigrationWriteScope::active() || \ProfessionalWiki\SWS\EntryPoints\Processing\FieldWikitextContext::isActive()
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
			$subject = $extension->newSubjectResolver( $parser )->resolveMainByTitle( $title );
			if ( $subject === null || !$subject->getSchemaReference()->isLocal() ) { return; }
			$reference = $subject->getSchemaReference();
			( new ParserPageDependencyRecorder( $parser ) )->recordDependencyOnSchema( $reference );
			$schema = $extension->getSchemaResolverFor( ParserAuthority::of( $parser ) )->getSchema( $reference );
			if ( $schema === null ) { return; }
			// Include schema categories in ordinary view parses as well as in the sealed worker output.
			$authority = ParserAuthority::of( $parser );
			$revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title );
			if ( $revision && $revision->userCan( RevisionRecord::DELETED_TEXT, $authority ) ) {
				$pageSubjects = SubjectSlotReader::read( $revision )?->getPageSubjects();
				if ( $pageSubjects !== null && $pageSubjects->getMainSubject() == $subject ) {
					$resolve = static function ( $ref ) use ( $extension, $authority, $parser ) {
						( new ParserPageDependencyRecorder( $parser ) )->recordDependencyOnSchema( $ref );
						return $extension->getSchemaResolverFor( $authority )->getSchema( $ref )
							?? throw new \RuntimeException( 'Category schema unavailable' );
					};
					( new \ProfessionalWiki\SWS\EntryPoints\Processing\CategoryAssignments() )->apply(
						$parser->getOutput(), $schema, $pageSubjects, $subject, $resolve
					);
				}
			}
			$text = SchemaWikitext::append( $text, SchemaWikitext::programs( $schema ) );
		} catch ( Throwable $e ) {
			if ( $context = \ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingContext::current() ) { $context->failure = $e->getMessage(); }
			LoggerFactory::getInstance( 'SWS' )->error( 'Schema wikitext preparation failed', [ 'exception' => $e ] );
			$text .= "\n<strong class=\"error\">" . wfMessage( 'sws-wikitext-error' )->escaped() . '</strong>';
		}
	}

	public static function onRevisionFromEditComplete( $wikiPage, RevisionRecord $revision, int|bool $originalRevId, $user, array &$tags ): void {
        if ( \ProfessionalWiki\SWS\Application\IdentityMigration\MigrationWriteScope::active() ) { return; }
		try {
			if ( \ProfessionalWiki\SWS\EntryPoints\Processing\SourceRefreshReceipt::isCurrent( $wikiPage->getTitle(), $revision ) ) { return; }
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
                    || $property instanceof \ProfessionalWiki\SWS\Domain\Schema\Property\PageProperty && $property->assignsCategories()
					|| $property instanceof \ProfessionalWiki\SWS\Domain\Schema\Property\WikitextValueProperty
                    || ( $property->getFieldNotes()['type'] ?? null ) === 'wikitext' ) { return true; }
			}
		}
		return false;
	}

	public static function refreshPage( Title $title ): bool {
        return ( new \ProfessionalWiki\SWS\EntryPoints\Processing\InternalPageProcessor() )->refresh( $title );
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
