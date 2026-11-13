<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Infrastructure;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\SWSExtension;

/** Namespace eligibility is not authorization. Callers must still check the reader/writer. */
final class SubjectNamespacePolicy {
    public static function allowsTitle( Title $title ): bool {
        $id = $title->getNamespace();
        if ( !$title->canExist() || $id < 0 || in_array( $id, [
            SWSExtension::NS_SCHEMA, SWSExtension::NS_LAYOUT, SWSExtension::NS_MAPPING
        ], true ) || SWSExtension::getInstance()->isConfigPage( $title ) ) {
            return false;
        }
        $services = MediaWikiServices::getInstance();
        return $services->getNamespaceInfo()->isContent( $id )
            || in_array( $id, $services->getMainConfig()->get( 'SWSAdditionalSubjectNamespaces' ), true );
    }
}
