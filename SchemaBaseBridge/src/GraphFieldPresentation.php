<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use ProfessionalWiki\SWS\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Value\StringValue;

/** Read-only display metadata. No SMW dependency and no copy of derived data into storage/RDF. */
final class GraphFieldPresentation {
	public static function fromSchema( Schema $schema, ?string $pageUrlTemplate, ?Subject $subject = null, string $language = 'de' ): array {
		$definitions = [];
		foreach ( $schema->getAllProperties()->asMap() as $name => $property ) {
			$definitions[] = [ 'name' => $name, 'type' => $property->getPropertyType(),
				'predicateLocalName' => RdfNamespaces::localName( $name ),
				'wikiLink' => $property->getWikiLink(),
				...( $property->isSchemaExecution() ? [ 'execute' => true ] : [] ),
				...( $subject !== null && $property->getPropertyType() === 'page' ? [ 'ownPages' => self::ownImages( $subject, $name ) ] : [] ),
				...( $subject !== null && $property->getPropertyType() === 'url' ? [ 'ownUrls' => self::ownUrls( $subject, $name ) ] : [] ),
				'externalProperty' => $property->getExternalProperty()?->toJson(),
				...( $subject !== null && $property->getPropertyType() === 'historicalDate' ? [ 'ownDateParts' => self::ownDateParts( $subject, $name, $language, $property->getWikiLink() ?? [] ) ] : [] ),
				...( $subject !== null && in_array( $property->getPropertyType(), [ 'image', 'historicalDate' ], true ) ? [ ( $property->getPropertyType() === 'image' ? 'ownImages' : 'ownDates' ) => self::ownImages( $subject, $name ) ] : [] ) ];
		}
		return [ 'version' => 1, 'language' => $language,
            ...( $subject?->getIdentity() === null ? [] : [ 'ownIdentity'=>$subject->getIdentity()->toJson(), 'ownDescription'=>$subject->getIdentity()->description( $language ) ] ), 'pageUrlTemplate' => $pageUrlTemplate, 'definitions' => $definitions ];
	}
	private static function ownUrls( Subject $subject, string $name ): array {
		$value = $subject->getStatements()->getStatement( new PropertyName( $name ) )?->getValue();
		if ( $value instanceof \ProfessionalWiki\SWS\Domain\Value\UrlValue ) { return $value->parts; }
		return $value instanceof StringValue ? array_map( static fn ( string $url ): array => [ 'url' => $url ], $value->strings ) : [];
	}
	private static function ownDateParts( Subject $subject, string $name, string $language, array $options ): array {
		return array_map( static function ( string $raw ) use ( $language, $options ): array {
			$date = \ProfessionalWiki\SWS\Domain\HistoricalDate::parse( $raw );
			return $date === null ? [ [ 'text' => $raw ] ] : TimePresentation::parts(
				[ 'year' => $date->year, ...( $date->month === null ? [] : [ 'month' => $date->month ] ), ...( $date->day === null ? [] : [ 'day' => $date->day ] ) ],
				$language, (string)( $GLOBALS['wgLanguageCode'] ?? 'de' ), $options );
		}, self::ownImages( $subject, $name ) );
	}

	private static function ownImages( Subject $subject, string $name ): array {
		$value = $subject->getStatements()->getStatement( new PropertyName( $name ) )?->getValue();
		return $value instanceof StringValue ? $value->strings : [];
	}
}
