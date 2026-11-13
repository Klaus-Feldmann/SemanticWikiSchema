<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Application\Identity;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\Identity\{SchemaId, LocalizedIdentity};
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;

/** Ordinary edits may rename labels, but cannot change identity or reinterpret legacy pages. */
final class SchemaIdentityWritePolicy {
	public static function validate( SchemaName $page, ?array $old, array $new, IdentityRegistry $registry, string $owner ): void {
		$version = $new['formatVersion'] ?? 1;
		if ( !in_array( $version, [1, 2], true ) ) { throw new InvalidArgumentException( 'Unsupported schema content version' ); }
		if ( ( $old['formatVersion'] ?? 1 ) === 2 && ( $version !== 2 || ( $new['id'] ?? null ) !== $old['id'] ) ) {
			throw new InvalidArgumentException( 'A Schema identity cannot be changed or downgraded' );
		}
		if ( $old !== null && ( $old['formatVersion'] ?? 1 ) === 1 && $version === 2 ) {
			throw new InvalidArgumentException( 'Existing legacy schemas require the migration path' );
		}
		if ( $version === 1 ) {
			if ( $old === null ) { throw new InvalidArgumentException( 'New Schemas must be created with an S ID' ); }
			return;
		}
		$id = new SchemaId( $new['id'] ?? '' );
		if ( $page->getText() !== $id->text ) { throw new InvalidArgumentException( 'Schema ID does not match its ID page' ); }
		$identity = LocalizedIdentity::fromJson( $new );
		if ( !array_filter( $identity->labels, static fn ( $text ) => trim( $text ) !== '' ) ) {
			throw new InvalidArgumentException( 'At least one Schema label is required' );
		}
		if ( $old === null && !$registry->canCreate( $id, $owner ) ) {
			throw new InvalidArgumentException( 'Schema identity is not a fresh reservation of this author; restore deleted identities instead of recreating them' );
		}
	}
}
