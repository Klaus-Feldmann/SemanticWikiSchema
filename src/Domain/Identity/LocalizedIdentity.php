<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Identity;

use InvalidArgumentException;

/** Own, revisioned labels and descriptions. Never copied from a Wikidata source description. */
final readonly class LocalizedIdentity {
	public function __construct(
		public string $language,
		public array $labels = [],
		public array $descriptions = [],
	) {
		self::validateLanguage( $language );
		foreach ( [ $labels, $descriptions ] as $map ) {
			foreach ( $map as $lang => $text ) {
				if ( !is_string( $lang ) || !is_string( $text ) || strlen( $text ) > 4096 ||
					preg_match( '//u', $text ) !== 1 || preg_match( '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text ) === 1 ) {
					throw new InvalidArgumentException( 'Invalid localized identity text' );
				}
				self::validateLanguage( $lang );
			}
		}
	}

	private static function validateLanguage( string $language ): void {
		if ( preg_match( '/^[a-z]{1,8}(?:-[a-z0-9]{1,8}){0,5}\z/', $language ) !== 1 ) {
			throw new InvalidArgumentException( 'Invalid identity language code' );
		}
	}

	public function label( ?string $language = null ): ?string {
		return $this->text( $this->labels, $language );
	}

	public function description( ?string $language = null ): ?string {
		return $this->text( $this->descriptions, $language );
	}

	private function text( array $map, ?string $language ): ?string {
		foreach ( array_unique( [ $language ?? $this->language, $this->language, 'en' ] ) as $lang ) {
			if ( trim( $map[$lang] ?? '' ) !== '' ) { return $map[$lang]; }
		}
		return null;
	}

	public function withDefaultLabel( ?string $label ): self {
		$labels = $this->labels;
		if ( $label === null || trim( $label ) === '' ) { unset( $labels[$this->language] ); }
		else { $labels[$this->language] = $label; }
		return new self( $this->language, $labels, $this->descriptions );
	}

	/** Canonical REST fields match the read response; absence means preserve existing metadata. */
	public static function fromRequest( array $body ): ?array {
		$metadata = array_intersect_key( $body, array_flip( [ 'identityLanguage', 'labels', 'descriptions' ] ) );
		return $metadata === [] ? null : $metadata;
	}

	public static function fromJson( array $json ): self {
		if ( !is_string( $json['identityLanguage'] ?? null ) || !is_array( $json['labels'] ?? null ) || !is_array( $json['descriptions'] ?? null ) ) {
			throw new InvalidArgumentException( 'Missing version-2 identity metadata' );
		}
		return new self( $json['identityLanguage'], $json['labels'], $json['descriptions'] );
	}

	public function toJson(): array {
		return [ 'identityLanguage'=>$this->language, 'labels'=>(object)$this->labels, 'descriptions'=>(object)$this->descriptions ];
	}
}
