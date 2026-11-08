<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\Value;

use InvalidArgumentException;

/** A URL and its optional presentation data form one indivisible list entry. */
readonly class UrlValue implements NeoValue {
	public array $parts;

	public function __construct( array $parts ) {
		if ( !array_is_list( $parts ) ) {
			throw new InvalidArgumentException( 'URL values must be a list.' );
		}
		$normalized = [];
		foreach ( $parts as $part ) {
			if ( is_string( $part ) ) { $part = [ 'url' => $part ]; }
			if ( !is_array( $part ) || !isset( $part['url'] ) || !is_string( $part['url'] )
				|| array_diff( array_keys( $part ), [ 'url', 'label', 'caption', 'captionPage' ] ) !== [] ) {
				throw new InvalidArgumentException( 'Malformed URL entry.' );
			}
			$entry = [];
			foreach ( [ 'url', 'label', 'caption', 'captionPage' ] as $key ) {
				if ( !array_key_exists( $key, $part ) ) { continue; }
				if ( !is_string( $part[$key] ) ) { throw new InvalidArgumentException( 'URL entry fields must be text.' ); }
				$text = trim( $part[$key] );
				if ( $key === 'url' || $text !== '' ) { $entry[$key] = $text; }
			}
			if ( $entry['url'] === '' ) {
				if ( count( $entry ) > 1 ) { throw new InvalidArgumentException( 'A labelled URL entry needs an address.' ); }
				continue;
			}
			$normalized[] = $entry;
		}
		$this->parts = $normalized;
	}

	public function getType(): ValueType { return ValueType::Url; }
	public function toScalars(): array { return $this->parts; }
	public function urls(): array { return array_column( $this->parts, 'url' ); }
	public function isEmpty(): bool { return $this->parts === []; }
}
