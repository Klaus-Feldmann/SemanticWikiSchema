<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\Deferred\LinksUpdate\LinksUpdate;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\EntryPoints\Jobs\SchemaWikitextRefreshJob;

/** Gates ordinary writers instead of relying on the identity of the job-queue runner. */
final class ProcessingHooks {
    public static ?Title $movedFrom = null;

    public static function queue( Title $title, ?Title $old = null ): void {
        if ( \ProfessionalWiki\SWS\Application\IdentityMigration\MigrationWriteScope::active() ) { return; }
        MediaWikiServices::getInstance()->getJobQueueGroup()->push( new SchemaWikitextRefreshJob( $title,
            [ 'page' => true ] + ( $old ? [ 'movedFrom' => $old->getPrefixedText() ] : [] ) ) );
    }

    public static function onRevisionDataUpdates( $title, $renderedRevision, array &$updates ): void {
        if ( !InternalPageProcessor::manages( $title, $renderedRevision->getRevision() ) ) { return; }
        if ( \ProfessionalWiki\SWS\Application\IdentityMigration\MigrationWriteScope::active() ) {
            $updates = array_values( array_filter( $updates, static fn ( $update ) => !$update instanceof LinksUpdate ) );
            return;
        }
        $output = $renderedRevision->getRevisionParserOutput();
        if ( ProcessingResult::isCurrent( $title, $output->getExtensionData( ProcessingResult::STAMP ) ) ) {
            if ( self::$movedFrom ) {
                foreach ( $updates as $update ) {
                    if ( $update instanceof LinksUpdate ) { $update->setMoveDetails( self::$movedFrom ); }
                }
            }
            return;
        }
        // Retain content-handler updates, indexing and other extensions' work. Only the incomplete
        // LinksUpdate (including pageprops and its SMW callback) is replaced by a complete worker.
        $updates = array_values( array_filter( $updates, static fn ( $update ) => !$update instanceof LinksUpdate ) );
        // The outer worker publishes the final sealed output after its source-only revision.
        // Deferred updates outside this scope may queue a final pass; its signed receipt skips fetch.
        if ( SourceWriteScope::subjectsFor( $title ) !== null &&
            SourceRefreshReceipt::isCurrent( $title, $renderedRevision->getRevision() ) ) { return; }
        self::queue( $title );
    }

    public static function onParserBeforeInternalParse( Parser $parser, string &$text, $stripState ): void {
        ProcessingContext::current()?->observe( $parser );
        $title = $parser->getTitle();
        if ( !$title->canExist() || !InternalPageProcessor::manages( $title ) || !MediaWikiServices::getInstance()->hasService( 'SMW.ObjectCache' ) ) { return; }
        // SMW can otherwise write from ParserAfterTidy during a purge, before validation. Consume
        // its one-shot purge marker and route that update through our normal complete worker.
        $cache = MediaWikiServices::getInstance()->getService( 'SMW.ObjectCache' );
        $key = smwfCacheKey( \SMW\MediaWiki\Hooks\ArticlePurge::CACHE_NAMESPACE, $title->getArticleID() );
        if ( $cache->get( $key ) ) {
            $cache->delete( $key );
            if ( ProcessingContext::current() === null ) { self::queue( $title ); }
        }
    }

    public static function onLinksUpdate( $update ): void {
        $title = $update->getTitle();
        if ( InternalPageProcessor::manages( $title ) &&
            !ProcessingResult::isCurrent( $title, $update->getParserOutput()->getExtensionData( ProcessingResult::STAMP ) ) ) {
            self::queue( $title );
            throw new ProcessingIncomplete( 'SemanticWikiSchema: stale/incomplete links update refused before table writes.' );
        }
    }

    public static function onBeforeStore( $store, $data ): void {
        $title = $data->getSubject()->getTitle();
        if ( !$title || !InternalPageProcessor::manages( $title ) ) { return; }
        $stamp = $data->getExtensionData( ProcessingResult::STAMP );
        if ( !ProcessingResult::isCurrent( $title, $stamp ) ) {
            self::queue( $title );
            throw new ProcessingIncomplete( 'SemanticWikiSchema: incomplete/stale SMW update refused; complete refresh queued.' );
        }
        if ( ( $stamp['semanticHash'] ?? null ) !== ProcessingResult::semanticHash( $data ) ) {
            // Do not create a fresh self-repeating job for a deterministic integration error.
            ProcessingState::record( $title, 'failed', $stamp['revision'] );
            throw new ProcessingIncomplete( 'SemanticWikiSchema: prepared semantic assertions changed before persistence.' );
        }
    }

    public static function onAfterStore( $store, $data ): void {
        $title = $data->getSubject()->getTitle();
        $stamp = $data->getExtensionData( ProcessingResult::STAMP );
        if ( $title && ProcessingResult::isCurrent( $title, $stamp ) ) {
            ProcessingState::record( $title, ProcessingResult::semanticHash( $data ) === ProcessingResult::semanticHash( null ) ? 'complete-empty' : 'complete', $stamp['revision'] );
        }
    }

    public static function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ): void {
        $title = Title::newFromLinkTarget( $new );
        if ( InternalPageProcessor::manages( $title ) ) { self::queue( $title, Title::newFromLinkTarget( $old ) ); }
    }

    public static function onServices( MediaWikiServices $services ): void {
        if ( $services->hasService( 'SMW.ContentParserFactory' ) ) {
            $services->addServiceManipulator( 'SMW.ContentParserFactory', static fn ( $factory ) => new SmwContentParserFactory( $factory ) );
        }
    }
}
