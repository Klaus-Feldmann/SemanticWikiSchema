<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SubjectSlotReader;
use Wikimedia\Rdbms\IDBAccessObject;

/** Renders full revisions without consulting or writing ParserCache. */
final class InternalPageProcessor {
    public const MANAGED = 'semantic-sws-internal-managed';

    public static function manages( Title $title, ?RevisionRecord $revision = null ): bool {
        if ( !$title->canExist() ) { return false; }
        $services = MediaWikiServices::getInstance();
        $revision ??= $services->getRevisionLookup()->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );
        if ( !$revision ) { return false; } // Deletions must use the regular deletion path.
        if ( $revision->hasSlot( 'sws' ) ) { return true; }
        // One final complete pass after intentional removal of the subject slot.
        return (bool)( $services->getPageProps()->getProperties( $title, self::MANAGED )[$title->getArticleID()] ?? false );
    }

    public function prepare( Title $title, bool $captureSources = false ): ProcessingResult {
        $services = MediaWikiServices::getInstance();
        $revision = $services->getRevisionLookup()->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );
        if ( !$revision ) { throw new ProcessingIncomplete( 'SemanticWikiSchema: page no longer exists.' ); }
        $user = ProcessingIdentity::get();
        if ( !$user->authorizeRead( 'read', $title ) || !$revision->userCan( RevisionRecord::DELETED_TEXT, $user ) ) {
            throw new ProcessingIncomplete( 'SemanticWikiSchema: processing identity cannot read the current revision.', 'denied' );
        }
        $policy = SWSExtension::getInstance()->getRevisionPolicy();
        if ( !$policy->revisionIsReadableBy( $revision, $user ) || $policy->publishedRevision( $revision )?->getId() !== $revision->getId() ) {
            throw new ProcessingIncomplete( 'SemanticWikiSchema: current revision is not publishable under the registered revision policy.', 'pending' );
        }
        $options = ParserOptions::newFromUser( $user );
        $context = new ProcessingContext( $title );
        $context->useStoredSources = \ProfessionalWiki\SWS\Application\Source\SourceDataMode::usesStore();
        return $context->run( function () use ( $services, $revision, $title, $user, $options, $context, $captureSources ): ProcessingResult {
            $dependencies = [];
            $schema = null;
            $content = SubjectSlotReader::read( $revision );
            if ( $revision->hasSlot( 'sws' ) && $content === null ) {
                throw new ProcessingIncomplete( 'SemanticWikiSchema: subject slot is not readable.' );
            }
            $raw = $content ? json_decode( $content->getText(), true, 512, JSON_THROW_ON_ERROR ) : [];
            $subjects = $content?->getPageSubjects();
            $context->subjects = $subjects;
            if ( !is_array( $raw ) || count( $raw['subjects'] ?? [] ) !== count( $subjects?->getAllSubjects()->asArray() ?? [] ) ) {
                throw new ProcessingIncomplete( 'SemanticWikiSchema: invalid/incomplete subject slot.' );
            }
            $subject = $subjects?->getMainSubject();
            if ( isset( $raw['mainSubject'] ) && $subject === null ) {
                throw new ProcessingIncomplete( 'SemanticWikiSchema: main subject could not be resolved.' );
            }
            $schemas = [];
            $resolveSchema = static function ( \ProfessionalWiki\SWS\Domain\Schema\SchemaReference $reference ) use ( $services, $user, &$dependencies, &$schemas ): \ProfessionalWiki\SWS\Domain\Schema\Schema {
                $key = json_encode( $reference->toJson(), JSON_THROW_ON_ERROR );
                if ( isset( $schemas[$key] ) ) {
                    return $schemas[$key];
                }
                if ( !$reference->isLocal() ) { throw new ProcessingIncomplete( 'SemanticWikiSchema: remote schema processing is not supported.' ); }
                $schemaTitle = Title::makeTitleSafe( SWSExtension::NS_SCHEMA, $reference->name->getText() );
                if ( !$schemaTitle || !$user->authorizeRead( 'read', $schemaTitle ) ) {
                    throw new ProcessingIncomplete( 'SemanticWikiSchema: schema is not readable.', 'denied' );
                }
                $schemaRevision = $services->getRevisionLookup()->getRevisionByTitle( $schemaTitle, 0, IDBAccessObject::READ_LATEST );
                if ( !$schemaRevision || !$schemaRevision->getContent( 'main', RevisionRecord::FOR_THIS_USER, $user ) instanceof SchemaContent ) {
                    throw new ProcessingIncomplete( 'SemanticWikiSchema: schema is missing or unavailable.' );
                }
                $publishedSchema = SWSExtension::getInstance()->getRevisionPolicy()->publishedRevision( $schemaRevision );
                if ( $publishedSchema === null ) {
                    throw new ProcessingIncomplete( 'SemanticWikiSchema: schema has no published revision.', 'pending' );
                }
                if ( $publishedSchema->getId() !== $schemaRevision->getId() ) {
                    throw new ProcessingIncomplete( 'SemanticWikiSchema: current schema is not yet publishable.', 'pending' );
                }
                if ( ( $schema = SWSExtension::getInstance()->getSchemaResolverFor( $user )->getSchema( $reference ) ) === null ) {
                    throw new ProcessingIncomplete( 'SemanticWikiSchema: invalid or unavailable schema.' );
                }
                $dependencies[] = [ 'namespace' => $schemaTitle->getNamespace(), 'title' => $schemaTitle->getDBkey(), 'revision' => $schemaRevision->getId() ];
                return $schemas[$key] = $schema;
            };
            if ( $subject !== null ) {
                $schema = $resolveSchema( $subject->getSchemaReference() );
            }
            if ( $subjects !== null ) {
                // Lua reads both by page and by Subject ID. A stale index or a denied lookup must
                // not turn an existing own value into an apparently empty field.
                $probe = $services->getParserFactory()->create();
                $probe->startExternalParse( $title, $options, \MediaWiki\Parser\Parser::OT_HTML );
                $resolver = SWSExtension::getInstance()->newSubjectResolver( $probe );
                if ( $resolver->resolveMainByTitle( $title ) != $subject ) {
                    throw new ProcessingIncomplete( 'SemanticWikiSchema: page lookup does not match the subject snapshot.' );
                }
                foreach ( $subjects->getAllSubjects()->asArray() as $expected ) {
                    if ( $resolver->resolveById( $expected->id->text ) != $expected ) {
                        throw new ProcessingIncomplete( 'SemanticWikiSchema: Subject-ID lookup does not match the saved snapshot.' );
                    }
                }
            }
            $rendered = $services->getRevisionRenderer()->getRenderedRevision( $revision, $options, $user,
                [ 'audience' => RevisionRecord::FOR_THIS_USER, 'use-master' => true ] );
            $output = $rendered->getRevisionParserOutput( [ 'generate-html' => true ] );
            // Article/template presentation messages belong to their own producers. Only
            // explicit failures from SWS orchestration may stop this processing stage.
            if ( $context->failure !== null ) {
                throw new ProcessingIncomplete( 'SemanticWikiSchema: SWS processing failed: ' . $context->failure );
            }
            if ( $subjects !== null ) {
                ( new UserWikitextProcessor() )->apply( $title, $output, $subjects, $user, $resolveSchema, $revision->getId() );
            }
            // Identity registration may have been produced only by this full parse. Collect
            // after it, before annotations, including every Subject and every configured binding.
            $context->output = $output;
            $sourceSnapshots = $captureSources && $context->useStoredSources && $subjects !== null
                ? ( new SourceSnapshotCapture() )->capture( $title, $revision, $subjects, $user, $resolveSchema ) : [];
            $sourcePreparation = $sourceSnapshots !== [];
            if ( $subject !== null && $schema !== null ) {
                ( new CategoryAssignments() )->apply( $output, $schema, $subjects, $subject, $resolveSchema );
            }
            if ( !$sourcePreparation && $subject !== null && $schema !== null && $services->hasService( 'SMW.ParserDataFactory' ) ) {
                ( new \ProfessionalWiki\SWS\EntryPoints\Semantic\AutomaticAnnotations() )->apply(
                    $title, $output, $context->parser, $schema, $subjects, $subject, $user, $resolveSchema
                );
            }
            foreach ( $output->getLinkList( ParserOutputLinkTypes::TEMPLATE ) as $link ) {
                $dependencies[] = [ 'namespace' => $link['link']->getNamespace(), 'title' => $link['link']->getDBkey(), 'revision' => $link['revid'] ];
            }
            $data = $output->getExtensionData( 'smwdata' );
            if ( $services->hasService( 'SMW.ParserDataFactory' ) ) {
                $data ??= ( new \SMW\ParserData( $title, $output ) )->getSemanticData();
                if ( $data->isEmpty() ) {
                    $factory = \SMW\Services\ServicesFactory::getInstance()->singleton( 'PropertyAnnotatorFactory' );
                    $factory->newSortKeyPropertyAnnotator( $factory->newNullPropertyAnnotator( $data ), '' )->addAnnotation();
                }
                $output->setExtensionData( 'smwdata', $data );
            }
            $stamp = ProcessingResult::seal( [ 'page' => $title->getArticleID(), 'revision' => $revision->getId(),
                'sha1' => $revision->getSha1(), 'dependencies' => $dependencies,
                'stage' => $sourcePreparation ? 'sources' : 'complete',
                'sourceDataMode' => \ProfessionalWiki\SWS\Application\Source\SourceDataMode::current(),
                'sourceSnapshotHash' => SourceSnapshotCapture::hash( $sourceSnapshots ),
                'semanticHash' => ProcessingResult::semanticHash( $data ) ] );
            $output->setExtensionData( ProcessingResult::STAMP, $stamp );
            if ( $revision->hasSlot( 'sws' ) ) { $output->setPageProperty( self::MANAGED, '1' ); }
            // Even an accidental caller must not cache this privileged HTML.
            $output->updateCacheExpiry( 0 );
            if ( $data !== null ) { $data->setExtensionData( ProcessingResult::STAMP, $stamp ); }
            $result = new ProcessingResult( $title, $revision, $output, $options, $stamp, $sourceSnapshots, $sourcePreparation );
            $result->assertCurrent();
            return $result;
        } );
    }

    public function refresh( Title $title, ?Title $movedFrom = null, bool $refreshSources = false, bool $strictProjection = false ): bool {
        if ( !$title->exists( IDBAccessObject::READ_LATEST ) ) { return true; }
        ProcessingState::record( $title, 'pending', $title->getLatestRevID() );
        try {
            $current = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );
            $result = $this->prepare( $title, \ProfessionalWiki\SWS\Application\Source\SourceDataMode::usesStore() && ( $refreshSources || !SourceRefreshReceipt::isCurrent( $title, $current ) ) );
            $result->assertCurrent();
            if ( $result->isSourcePreparation ) {
                // Write outside ProcessingContext: saving a revision renders it again.
                ( new InternalSourceSnapshotWriter() )->save( $result );
                // The final output and SMW plan must belong to the new stored revision.
                $result = $this->prepare( $title );
                $result->assertCurrent();
                if ( !SourceRefreshReceipt::isCurrent( $title, $result->revision ) ) {
                    throw new ProcessingIncomplete( 'Source revision or dependencies changed before final processing.', 'superseded' );
                }
            }
        }
        catch ( \Throwable $e ) {
            ProcessingState::record( $title, $e instanceof ProcessingIncomplete ? $e->state : 'failed', $title->getLatestRevID() );
            throw $e;
        }
        ProcessingState::record( $title, 'prepared', $result->revision->getId() );
        ProcessingHooks::$movedFrom = $movedFrom;
        try {
            // A fresh WikiPage avoids an updater already bound to the anonymous save parse.
            MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title )->doSecondaryDataUpdates( [
                'known-revision-output' => $result->output, 'known-revision-options' => $result->options,
                'recursive' => false, 'causeAction' => 'semantic-sws-internal', 'causeAgent' => ProcessingIdentity::NAME
            ] );
        } catch ( \Throwable $e ) {
            ProcessingState::record( $title, $e instanceof ProcessingIncomplete ? $e->state : 'failed', $result->revision->getId() );
            throw $e;
        } finally { ProcessingHooks::$movedFrom = null; }
        // Preserve the original graph/index projection path and its publication policy.
        $extension = SWSExtension::getInstance();
        $outcome = ($strictProjection ? $extension->newMigrationPageRebuilder() : $extension->newHookPageRebuilder())->rebuild($title);
        if ($strictProjection && !in_array($outcome,[\ProfessionalWiki\SWS\Application\PageRefreshOutcome::Refreshed,\ProfessionalWiki\SWS\Application\PageRefreshOutcome::Unpublished],true)) {
            throw new ProcessingIncomplete('Current data projection was not completed');
        }
        // Schema-only category changes also invalidate the ordinary view parse.
        $title->invalidateCache();
        if ( !MediaWikiServices::getInstance()->hasService( 'SMW.ParserDataFactory' ) ) {
            ProcessingState::record( $title, 'complete', $result->revision->getId() );
        }
        return true;
    }
}
