<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Schema;

use InvalidArgumentException;

/** Source-qualified property reference; labels are resolved, never used as identity. */
readonly class ExternalProperty {
	public function __construct( public string $source, public string $id ) {
		if ( !in_array( $source, [ 'wikidata', 'wikibase-local' ], true ) ||
			preg_match( '/\AP[1-9][0-9]*\z/', $id ) !== 1
		) {
			throw new InvalidArgumentException( 'Invalid external property reference' );
		}
	}

	public static function fromJson( mixed $json ): self {
		if ( !is_array( $json ) || array_diff( array_keys( $json ), [ 'source', 'id' ] ) !== [] ||
			!is_string( $json['source'] ?? null ) || !is_string( $json['id'] ?? null )
		) {
			throw new InvalidArgumentException( 'External property needs a source and property ID' );
		}
		return new self( $json['source'], $json['id'] );
	}

	public function toJson(): array {
		return [ 'source' => $this->source, 'id' => $this->id ];
	}
}
