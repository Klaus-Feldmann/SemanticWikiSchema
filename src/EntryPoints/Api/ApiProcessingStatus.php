<?php

declare( strict_types = 1 );
namespace AEWI\SWS\EntryPoints\Api;

use ApiBase;
use MediaWiki\Title\Title;
use AEWI\SWS\Domain\Subject\SubjectId;
use AEWI\SWS\EntryPoints\Processing\ProcessingDiagnosticLookup;
use AEWI\SWS\SWSExtension;

/** Read-only processing evidence, independent of SchemaBaseBridge and live source caches. */
final class ApiProcessingStatus extends ApiBase {
    public function execute(): void {
        $this->getMain()->setCacheMode( 'private' );
        $p = $this->extractRequestParams();
        $targets = array_filter( [ $p['page'] ?? null, $p['pageid'] ?? null, $p['entity'] ?? null ], static fn ( $v ) => $v !== null );
        if ( count( $targets ) !== 1 || preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $p['language'] ) !== 1 ) {
            $this->dieWithError( 'sws-processing-invalid-target', 'swsprocessing-invalid-target' );
        }
        $entity = $p['entity'] ?? null;
        if ( $entity !== null ) {
            if ( !SubjectId::isValidLocalId( $entity ) ) { $this->dieWithError( 'sws-processing-invalid-target', 'swsprocessing-invalid-target' ); }
            $extension = SWSExtension::getInstance();
            $id = $extension->newIdentityBoundaryResolver()->entity( new SubjectId( $entity ) );
            $entity = $id->text;
            $host = $extension->newSubjectHostingPageResolver( $this->getAuthority() )->resolveReadableHostingPage( $id );
            $title = $host === null ? null : Title::newFromID( $host->getId()->id );
        } else {
            $title = isset( $p['pageid'] ) ? Title::newFromID( $p['pageid'] ) : Title::newFromText( $p['page'] );
        }
        if ( $title === null || $title->isExternal() || !$title->canExist() ) { $this->dieWithError( 'sws-processing-unavailable', 'swsprocessing-unavailable' ); }
        try {
            $data = ( new ProcessingDiagnosticLookup( $this->getContext() ) )->read( $title, $p['language'], $entity, $p['field'] ?? null );
        } catch ( \RuntimeException|\InvalidArgumentException ) { $this->dieWithError( 'sws-processing-unavailable', 'swsprocessing-unavailable' ); }
        // Explicit refusal rather than presenting a partial report as a complete diagnostic.
        if ( strlen( json_encode( $data, JSON_THROW_ON_ERROR ) ) > 1048576 ) { $this->dieWithError( 'sws-processing-too-large', 'swsprocessing-too-large' ); }
        if ( !$this->getResult()->addValue( null, $this->getModuleName(), $data ) ) { $this->dieWithError( 'sws-processing-too-large', 'swsprocessing-too-large' ); }
    }
    public function getAllowedParams(): array {
        return [ 'page' => [ self::PARAM_TYPE => 'string' ],
            'pageid' => [ self::PARAM_TYPE => 'integer', self::PARAM_MIN => 1 ],
            'entity' => [ self::PARAM_TYPE => 'string' ], 'field' => [ self::PARAM_TYPE => 'string' ],
            'language' => [ self::PARAM_TYPE => 'string', self::PARAM_DFLT => $this->getLanguage()->getCode() ] ];
    }
    public function isWriteMode(): bool { return false; }
    protected function getExamplesMessages(): array { return []; }
}
