<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Source;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\ImageReference;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;

/** A normalized source result, never an author's Statement or field note. */
final readonly class SourceFieldResult {

	public function __construct( public array $data ) {
		SourceSnapshot::assertJsonData( $data );
		foreach ( [ 'propertyLabel', 'datatype' ] as $name ) {
			if ( isset( $data[$name] ) && !is_string( $data[$name] ) ) { throw new InvalidArgumentException( 'Invalid source property metadata' ); }
		}
		if ( !is_string( $data['name'] ?? null ) || trim( $data['name'] ) === '' ||
			!is_string( $data['source'] ?? null ) || !SubjectId::isValidSourceKey( $data['source'] ) ||
			!is_string( $data['propertyId'] ?? null ) || preg_match( '/\AP[1-9][0-9]*\z/', $data['propertyId'] ) !== 1 ||
			!is_string( $data['propertyType'] ?? null ) || trim( $data['propertyType'] ) === '' ||
			!in_array( $data['status'] ?? null, [ 'resolved', 'missing', 'unavailable', 'unsupported', 'manual' ], true ) ||
			!is_array( $data['values'] ?? null ) || !array_is_list( $data['values'] )
		) {
			throw new InvalidArgumentException( 'Invalid source field result' );
		}
		if ( isset( $data['entityId'] ) && ( !is_string( $data['entityId'] ) ||
			preg_match( '/\AQ[1-9][0-9]*\z/', $data['entityId'] ) !== 1 )
		) {
			throw new InvalidArgumentException( 'Invalid source entity ID' );
		}
		if ( $data['status'] === 'resolved' && !isset( $data['entityId'] ) ) {
			throw new InvalidArgumentException( 'Resolved source result requires its entity ID' );
		}
		foreach ( $data['values'] as $value ) {
			if ( !is_array( $value ) || !self::validValue( $value ) ) {
				throw new InvalidArgumentException( 'Invalid typed source value' );
			}
		}
	}

	public function isUsable(): bool {
		if ( $this->data['status'] !== 'resolved' ) {
			return false;
		}
		foreach ( $this->data['values'] as $value ) {
			if ( !in_array( $value['kind'], [ 'novalue', 'somevalue' ], true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function validValue( array $value ): bool {
		return match ( $value['kind'] ?? null ) {
			'text' => is_string( $value['value'] ?? null ) && trim( $value['value'] ) !== '',
			'entity' => is_string( $value['id'] ?? null ) &&
				preg_match( '/\AQ[1-9][0-9]*\z/', $value['id'] ) === 1 &&
				is_string( $value['label'] ?? null ) && trim( $value['label'] ) !== '' && $value['label'] !== $value['id'] &&
				self::isWebUrl( $value['url'] ?? null ),
			'image' => is_string( $value['reference'] ?? null ) && ImageReference::parse( $value['reference'] ) !== null,
			'time' => is_array( $value['sourceTime'] ?? null ) &&
				is_string( $value['sourceTime']['time'] ?? null ) &&
				is_int( $value['sourceTime']['precision'] ?? null ) &&
				is_string( $value['sourceTime']['calendarmodel'] ?? null ),
			'novalue', 'somevalue' => true,
			default => false,
		};
	}

	private static function isWebUrl( mixed $url ): bool {
		return is_string( $url ) && filter_var( $url, FILTER_VALIDATE_URL ) !== false &&
			in_array( strtolower( (string)parse_url( $url, PHP_URL_SCHEME ) ), [ 'https', 'http' ], true );
	}

}
