<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Api;

use ApiBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Schema\Property\WikitextValueProperty;
use Wikimedia\Rdbms\IDBAccessObject;
use ProfessionalWiki\SWS\EntryPoints\Processing\UserWikitextRenderer;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SubjectSlotReader;

/** A page batch shares one authorized revision. Neither submitted source nor store writes. */
final class ApiRenderWikitext extends ApiBase {
    public function execute(): void {
        $this->getMain()->setCacheMode( 'private' );
        $params = $this->extractRequestParams();
        $services = MediaWikiServices::getInstance();
        $title = $services->getTitleFactory()->newFromID( $params['pageid'] );
        $authority = $this->getAuthority();
        $extension = SWSExtension::getInstance();
        if ( !$title || !$authority->authorizeRead( 'read', $title ) ) { $this->dieWithError( 'sws-wikitext-render-error', 'unavailable' ); }
        $revision = $services->getRevisionLookup()->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );
        if ( !$revision || !$revision->userCan( RevisionRecord::DELETED_TEXT, $this->getUser() )
            || !$extension->getRevisionPolicy()->revisionIsReadableBy( $revision, $authority )
            || $extension->getRevisionPolicy()->publishedRevision( $revision )?->getId() !== $revision->getId() ) {
            $this->dieWithError( 'sws-wikitext-render-error', 'unavailable' );
        }
        $subjects = SubjectSlotReader::read( $revision )?->getPageSubjects();
        $all = $params['all'] ?? false;
        $requested = $extension->getSubjectIdParser( true )->parse( $params['subject'] ?? '' );
        $entries = []; $single = null;
        $schemas = [];
        foreach ( $subjects?->getAllSubjects()->asArray() ?? [] as $subject ) {
            if ( !$all && ( $requested === null || !$subject->id->equals( $requested ) ) ) { continue; }
            $ref = $subject->getSchemaReference();
            if ( !$ref->isLocal() ) { continue; }
            $schema = $schemas[$ref->getText()] ??= $extension->getSchemaResolverFor( $authority )->getSchema( $ref );
            if ( !$schema ) { continue; }
            foreach ( $schema->getAllProperties()->asMap() as $name => $property ) {
                if ( !$all && $name !== ( $params['field'] ?? '' ) ) { continue; }
                foreach ( [ 'value', 'note' ] as $kind ) {
                    if ( !$all && ( $params['note'] ?? false ) !== ( $kind === 'note' ) ) { continue; }
                    if ( $kind === 'note' ) {
                        if ( ( $property->getFieldNotes()['type'] ?? null ) !== 'wikitext' || $property->isSchemaExecution() ) { continue; }
                        $values = [ $subject->getFieldNotes()[$name] ?? '' ];
                    } else {
                        if ( !$property instanceof WikitextValueProperty ) { continue; }
                        $values = $subject->getStatements()->getStatement( new PropertyName( $name ) )?->getValue()->toScalars() ?? [];
                    }
                    $source = implode( "\n\n", $values );
                    if ( $all && trim( $source ) === '' ) { continue; }
                    try {
                        $output = ( new UserWikitextRenderer() )->render( $title, $values, $this->getUser(), $revision->getId() );
                        $item = [ 'source' => $source, 'html' => $output->getContentHolderText(), 'modules' => $output->getModules(), 'styles' => $output->getModuleStyles(), 'revision' => $revision->getId() ];
                    } catch ( \Throwable $e ) {
                        if ( !$all ) { throw $e; }
                        $item = [ 'source' => $source, 'error' => true ];
                    }
                    $entries[json_encode( [ $subject->id->text, $name, $kind ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )] = $item;
                    $single = $item;
                }
            }
        }
        if ( !$all && $single === null ) { $this->dieWithError( 'sws-wikitext-render-error', 'unavailable' ); }
        $this->getResult()->addValue( null, $this->getModuleName(), $all ? [ 'revision' => $revision->getId(), 'entries' => $entries ] : $single );
    }
    public function getAllowedParams(): array {
        return [ 'pageid' => [ self::PARAM_TYPE => 'integer', self::PARAM_REQUIRED => true ],
            'subject' => [ self::PARAM_TYPE => 'string', self::PARAM_DFLT => '' ],
            'field' => [ self::PARAM_TYPE => 'string', self::PARAM_DFLT => '' ],
            'all' => [ self::PARAM_TYPE => 'boolean', self::PARAM_DFLT => false ],
            'note' => [ self::PARAM_TYPE => 'boolean', self::PARAM_DFLT => false ] ];
    }
    public function isWriteMode(): bool { return false; }
    protected function getExamplesMessages(): array { return []; }
}
