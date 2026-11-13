<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Identity;

use InvalidArgumentException;

/** Explicit legacy context: an old schema called S1 is never guessed to be a new SchemaId. */
final readonly class LegacyIdentity {
	public function __construct(
		public IdentityKind $kind,
		public string $origin,
		public string $key,
	) {
		if ( preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}\z/', $origin ) !== 1 ||
			$key === '' || strlen( $key ) > 1024 || preg_match( '//u', $key ) !== 1 ||
			preg_match( '/[\x00-\x1f\x7f]/', $key ) === 1 ) {
			throw new InvalidArgumentException( 'Invalid legacy identity origin or key' );
		}
	}

	public function hash(): string {
		return hash( 'sha256', $this->kind->value . "\0" . $this->origin . "\0" . $this->key );
	}
}
