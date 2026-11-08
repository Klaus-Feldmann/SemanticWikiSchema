<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\PropertyType\Types;

use ProfessionalWiki\SWS\Domain\PropertyType\PropertyType;
use ProfessionalWiki\SWS\Domain\Schema\Property\UrlProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Validation\Severity;
use ProfessionalWiki\SWS\Domain\Validation\Violation;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\Value\UrlValue;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Value\ValueType;

class UrlType implements PropertyType {

	public const NAME = 'url';

	/**
	 * A scheme is optional and, when present, http or https. Carries no delimiters and stays within
	 * ECMA-262, so that the JSON Schema documents can use it as a `pattern`. A `pattern` takes no
	 * flags, and the inline `(?i)` and `(?i:...)` are a syntax error in most ECMA-262 engines and
	 * match beyond ASCII where they parse, so the case rules are spelled out rather than left to `/i`.
	 * The class for path, query and fragment excludes only ASCII characters, so that the validator,
	 * which matches bytes, and ECMA-262, which matches code units or code points, accept the same
	 * non-ASCII text.
	 */
	public const string URL_PATTERN = '^(?:[Hh][Tt][Tt][Pp][Ss]?://)?'
		. '(?:(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}'
		. '|(?:[0-9]{1,3}\.){3}[0-9]{1,3}'
		. '|[Ll][Oo][Cc][Aa][Ll][Hh][Oo][Ss][Tt])'
		. '(?::[0-9]+)?'
		. '(?:[/?#][^\x00-\x20\x7F<>"{}|\\\\^`]*)?$';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Url;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): UrlProperty {
		return UrlProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$value instanceof StringValue && !$value instanceof UrlValue ) {
			return [];
		}

		if ( !$definition instanceof UrlProperty ) {
			return [];
		}

		$strings = $value instanceof UrlValue ? $value->urls() : $value->strings;
		$hasContent = false;
		foreach ( $strings as $part ) {
			if ( trim( $part ) !== '' ) {
				$hasContent = true;
				break;
			}
		}

		if ( $definition->isRequired() && !$hasContent ) {
			return [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ];
		}

		$violations = [];

		foreach ( $strings as $index => $part ) {
			$url = trim( $part );
			if ( $url !== '' && !self::isValidUrl( $url ) ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'invalid-url',
					valuePartIndex: $index,
					severity: Severity::Error,
				);
			}
		}

		$uniqueParts = $value instanceof UrlValue ? array_map( static fn ( array $entry ): string => json_encode( $entry, JSON_THROW_ON_ERROR ), $value->parts ) : $strings;
		if ( $definition->enforcesUniqueValues()
			&& count( array_unique( $uniqueParts ) ) !== count( $uniqueParts )
		) {
			$violations[] = new Violation( propertyName: null, code: 'unique', severity: $definition->severityOf( 'uniqueItems' ) );
		}

		if ( $value instanceof UrlValue ) {
			foreach ( $value->parts as $index => $entry ) {
				if ( isset( $entry['captionPage'] ) ) {
					$title = Title::newFromText( $entry['captionPage'] );
					if ( $title === null || $title->isExternal() || !$title->canExist() ) {
						$violations[] = new Violation( propertyName: null, code: 'invalid-url-caption-page', valuePartIndex: $index, severity: Severity::Error );
					}
				}
			}
		}
		return $violations;
	}

	private static function isValidUrl( string $urlString ): bool {
		return preg_match( '@' . self::URL_PATTERN . '@', $urlString ) === 1;
	}

	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		return $value instanceof UrlValue ? array_merge( ...array_map( 'array_values', $value->parts ?: [ [] ] ) ) : ( $value instanceof StringValue ? $value->strings : [] );
	}

}
