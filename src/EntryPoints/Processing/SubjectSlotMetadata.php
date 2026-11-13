<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\EntryPoints\ParserAuthority;
use ProfessionalWiki\SWS\EntryPoints\Semantic\RevisionDependencies;
use ProfessionalWiki\SWS\Infrastructure\SubjectNamespacePolicy;
use ProfessionalWiki\SWS\SWSExtension;

/** Saved field metadata in ordinary views; the worker processes the complete revision separately. */
final class SubjectSlotMetadata {
    public static function addCategories( SubjectContent $content, ContentParseParams $params, ParserOutput $output ): void {
        $title = Title::newFromPageReference( $params->getPage() );
        if ( ProcessingContext::current() !== null || !SubjectNamespacePolicy::allowsTitle( $title ) ) { return; }
        $subjects = $content->getPageSubjects();
        $main = $subjects->getMainSubject();
        if ( !$subjects->hasSubjects() ) { return; }
        // Use the parsing identity, never the request's user, and key this output accordingly.
        $params->getParserOptions()->getOption( ParserAuthority::ACCESS_CLASS_OPTION );
        $output->recordOption( ParserAuthority::ACCESS_CLASS_OPTION );
        $authority = MediaWikiServices::getInstance()->getUserFactory()->newFromUserIdentity(
            $params->getParserOptions()->getUserIdentity()
        );
        if ( !$authority->authorizeRead( 'read', $title ) ) { return; }
        $recorder = new RevisionDependencies( $title, $output );
        $resolver = SWSExtension::getInstance()->getSchemaResolverFor( $authority );
        $resolved = [];
        $resolve = static function ( $ref ) use ( $recorder, $resolver, &$resolved ) {
            $key = $ref->name->getText();
            if ( isset( $resolved[$key] ) ) { return $resolved[$key]; }
            $recorder->recordDependencyOnSchema( $ref );
            return $resolved[$key] = $resolver->getSchema( $ref ) ?? throw new ProcessingIncomplete( 'Subject metadata schema unavailable.' );
        };
        try {
            // Stage into a separate output: a later field error must not leave partial metadata.
            $staged = new ParserOutput();
            ( new UserWikitextProcessor() )->apply( $title, $staged, $subjects, $authority, $resolve, $params->getRevId() );
            if ( $title->getContentModel() !== 'wikitext' && $main !== null && $main->getSchemaReference()->isLocal() ) {
                ( new CategoryAssignments() )->apply( $staged, $resolve( $main->getSchemaReference() ), $subjects, $main, $resolve );
            }
            $originalCategories = $output->getCategoryMap();
            $output->mergeTrackingMetaDataFrom( $staged );
            $output->mergeHtmlMetaDataFrom( $staged );
            foreach ( $originalCategories as $category => $sort ) { $output->addCategory( (string)$category, $sort ); }
        } catch ( \Throwable $e ) {
            // No unvalidated category output is cached, and no database writes occur in this path.
            $output->updateCacheExpiry( 0 );
            LoggerFactory::getInstance( 'SWS' )->warning( 'Subject wikitext/category display unavailable', [ 'exception' => $e ] );
        }
    }
}
