<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Rdf;

use ProfessionalWiki\SWS\Domain\PropertyType\Types\BooleanType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\DateTimeType;
use ProfessionalWiki\SWS\Domain\HistoricalDate;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\MonolingualTextType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\NumberType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\UrlType;
use ProfessionalWiki\SWS\Domain\Schema\Property\DateProperty;
use ProfessionalWiki\SWS\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\SWS\Domain\Value\MonolingualText;
use ProfessionalWiki\SWS\Domain\Value\MonolingualTextValue;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;

/**
 * Maps a Statement's {@see NeoValue} to the RDF {@see RdfTerm}s that represent it — {@see Literal}s, or
 * {@see Iri}s for values that denote a resource (a url) — keyed by the Statement's Property Type. The
 * seam mirrors Neo4jValueBuilderRegistry so extension-defined property types can contribute their RDF
 * representation via SWSRegistrar::addRdfValueMapper.
 *
 * Relation values are not mapped here; the projector reifies them separately (two-layer approach).
 * A Property Type without a mapper (including an unregistered type) yields null and is skipped,
 * matching the Neo4j projection's graceful degradation.
 */
class RdfValueMapperRegistry {

	/**
	 * @var array<string, callable(NeoValue): RdfTerm[]>
	 */
	private array $mappers = [];

	/**
	 * @param callable(NeoValue): RdfTerm[] $mapper
	 */
	public function registerMapper( string $propertyTypeName, callable $mapper ): void {
		$this->mappers[$propertyTypeName] = $mapper;
	}

	/**
	 * @return RdfTerm[]|null null when no mapper is registered for the Property Type.
	 */
	public function mapValue( string $propertyTypeName, NeoValue $value ): ?array {
		if ( !array_key_exists( $propertyTypeName, $this->mappers ) ) {
			return null;
		}

		return ( $this->mappers[$propertyTypeName] )( $value );
	}

	public function hasMapper( string $propertyTypeName ): bool {
		return array_key_exists( $propertyTypeName, $this->mappers );
	}

	public static function withCoreMappers(): self {
		$registry = new self();

		$registry->registerMapper( TextType::NAME, self::stringMapper( 'string' ) );
		$registry->registerMapper( 'wikitext', self::stringMapper( 'string' ) );
		$registry->registerMapper( 'wikitextValue', self::stringMapper( 'string' ) );
		$registry->registerMapper( 'page', static function ( NeoValue $value ): array {
			$result = [];
			foreach ( (array)$value->toScalars() as $text ) {
				$normal = is_string( $text ) ? \ProfessionalWiki\SWS\Domain\PropertyType\Types\LocalPageTitle::normalize( $text ) : null;
				if ( $normal !== null ) {
					$result[] = new Iri( \MediaWiki\Title\Title::newFromText( $normal )->getFullURL() );
				}
			}
			return $result;
		} );
		$registry->registerMapper( 'image', self::stringMapper( 'string' ) );
		$registry->registerMapper( SelectType::NAME, self::stringMapper( 'string' ) );
		$registry->registerMapper( UrlType::NAME, self::urlMapper( ... ) );
		$registry->registerMapper( NumberType::NAME, self::mapNumber( ... ) );
		$registry->registerMapper( BooleanType::NAME, self::mapBoolean( ... ) );
		$registry->registerMapper( DateType::NAME, self::mapDate( ... ) );
		$registry->registerMapper( 'historicalDate', self::mapHistoricalDate( ... ) );
		$registry->registerMapper( DateTimeType::NAME, self::mapDateTime( ... ) );
		$registry->registerMapper( MonolingualTextType::NAME, self::mapMonolingualText( ... ) );

		return $registry;
	}

	/**
	 * @return callable(NeoValue): Literal[]
	 */
	private static function stringMapper( string $xsdType ): callable {
		return static function ( NeoValue $value ) use ( $xsdType ): array {
			$scalars = $value->toScalars();

			if ( !is_array( $scalars ) ) {
				return [];
			}

			$literals = [];

			foreach ( $scalars as $part ) {
				if ( is_scalar( $part ) ) {
					$literals[] = RdfLiteralFactory::typed( (string)$part, $xsdType );
				}
			}

			return $literals;
		};
	}

	/**
	 * Maps a url value: each part becomes an IRI object when it is a valid absolute IRI, so the statement
	 * joins the graph as a resource that SPARQL can follow rather than an inert string. A part that is not
	 * a valid absolute IRI (e.g. the scheme-less values the url Property Type still accepts) keeps the
	 * xsd:anyURI literal form, so nothing is lost. Reuses {@see Iri::isSafeAbsolute}, the same IRI-safety
	 * check the Mapping layer applies to authored terms.
	 *
	 * @return RdfTerm[]
	 */
	private static function urlMapper( NeoValue $value ): array {
		$scalars = $value instanceof \ProfessionalWiki\SWS\Domain\Value\UrlValue ? $value->urls() : $value->toScalars();

		if ( !is_array( $scalars ) ) {
			return [];
		}

		$terms = [];

		foreach ( $scalars as $part ) {
			if ( is_scalar( $part ) ) {
				$string = (string)$part;
				$terms[] = Iri::isSafeAbsolute( $string )
					? new Iri( $string )
					: RdfLiteralFactory::typed( $string, 'anyURI' );
			}
		}

		return $terms;
	}

	/**
	 * Each part becomes a literal tagged with its own language, which is what an `rdf:langString`
	 * is: `"Zinema"@eu`.
	 *
	 * @return Literal[]
	 */
	private static function mapMonolingualText( NeoValue $value ): array {
		if ( !$value instanceof MonolingualTextValue ) {
			return [];
		}

		return array_map(
			static fn( MonolingualText $part ): Literal => RdfLiteralFactory::languageTagged( $part->text, $part->language ),
			$value->parts
		);
	}

	/**
	 * @return Literal[]
	 */
	private static function mapNumber( NeoValue $value ): array {
		$number = $value->toScalars();

		if ( !is_int( $number ) && !is_float( $number ) ) {
			return [];
		}

		$literal = RdfLiteralFactory::number( $number );

		return $literal === null ? [] : [ $literal ];
	}

	/**
	 * @return Literal[]
	 */
	private static function mapBoolean( NeoValue $value ): array {
		$boolean = $value->toScalars();

		if ( !is_bool( $boolean ) ) {
			return [];
		}

		return [ RdfLiteralFactory::boolean( $boolean ) ];
	}

	/**
	 * @return Literal[]
	 */
	private static function mapHistoricalDate( NeoValue $value ): array {
		$result = [];
		$parts = $value->toScalars();
		foreach ( is_array( $parts ) ? $parts : [] as $part ) {
			$date = is_string( $part ) ? HistoricalDate::parse( $part ) : null;
			if ( $date !== null ) {
				$result[] = new Literal( $part, new Iri( HistoricalDate::RDF_DATATYPE ) );
			}
		}
		return $result;
	}

	private static function mapDate( NeoValue $value ): array {
		return self::mapValidatedStrings(
			$value,
			'date',
			static fn( string $string ): bool => DateProperty::parseStrictDate( $string ) !== null
		);
	}

	/**
	 * @return Literal[]
	 */
	private static function mapDateTime( NeoValue $value ): array {
		return self::mapValidatedStrings(
			$value,
			'dateTime',
			static fn( string $string ): bool => DateTimeProperty::parseStrictDateTime( $string ) !== null
		);
	}

	/**
	 * Keeps only the parts that are valid lexical forms for the given xsd datatype, so the projection
	 * stays well-typed. Invalid parts are dropped, as the Neo4j projection drops unparseable dateTimes.
	 *
	 * @param callable(string): bool $isValid
	 * @return Literal[]
	 */
	private static function mapValidatedStrings( NeoValue $value, string $xsdType, callable $isValid ): array {
		$scalars = $value->toScalars();

		if ( !is_array( $scalars ) ) {
			return [];
		}

		$literals = [];

		foreach ( $scalars as $part ) {
			if ( is_string( $part ) && $isValid( $part ) ) {
				$literals[] = RdfLiteralFactory::typed( $part, $xsdType );
			}
		}

		return $literals;
	}

}
