<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Identity;

use InvalidArgumentException;

/** Version-2 reference. Legacy strings are resolved explicitly through LegacyIdentity, never guessed. */
final readonly class IdentityReference {
	public function __construct( public PersistentId $id, public ?string $source = null ) {
		if ( $source !== null && preg_match( '/^[A-Za-z][A-Za-z0-9_-]{0,63}\z/', $source ) !== 1 ) {
			throw new InvalidArgumentException( 'Invalid identity source key' );
		}
	}

	public function toJson(): array {
		return $this->source === null ? [ 'id' => $this->id->text ] : [ 'source' => $this->source, 'id' => $this->id->text ];
	}

	public static function fromJson( mixed $json, ?string $localSource = null ): self {
		if ( !is_array( $json ) || !is_string( $json['id'] ?? null ) ||
			array_diff( array_keys( $json ), [ 'source', 'id' ] ) !== [] ||
			( array_key_exists( 'source', $json ) && !is_string( $json['source'] ) ) ) {
			throw new InvalidArgumentException( 'Expected an explicit version-2 identity reference' );
		}
		$source = $json['source'] ?? null;
		// Validate before canonicalization; an invalid explicitly local source is not accepted.
		$reference = new self( PersistentId::parse( $json['id'] ), $source );
		return $source !== null && $source === $localSource ? new self( $reference->id ) : $reference;
	}
}
