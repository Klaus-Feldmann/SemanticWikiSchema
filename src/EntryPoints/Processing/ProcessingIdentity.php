<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\User\User;
use RuntimeException;

/** A credential-less system account. Never takes over an existing human account. */
final class ProcessingIdentity {
    public const NAME = 'SemanticSWS internal processor';

    public static function get(): User {
        $user = User::newSystemUser( self::NAME, [ 'steal' => false ] );
        if ( $user === null ) {
            throw new RuntimeException( 'SemanticWikiSchema: internal account name is already in use.' );
        }
        return $user;
    }

    public static function isProcessor( User $user ): bool {
        return $user->getName() === self::NAME && $user->isSystemUser();
    }

    public static function addRead( User $user, array &$rights ): void {
        if ( self::isProcessor( $user ) ) { $rights[] = 'read'; }
    }

    public static function restrictRights( User $user, array &$rights ): void {
        // Strip inherited user/sysop rights too. No edit, delete, query or suppressed-revision access.
        if ( self::isProcessor( $user ) ) { $rights = array_values( array_intersect( $rights, [ 'read' ] ) ); }
    }
}
