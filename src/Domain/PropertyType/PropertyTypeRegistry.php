<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\PropertyType;

use ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceParser;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\BooleanType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\DateTimeType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\HistoricalDateType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\MonolingualTextType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\NumberType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\PageType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\WikitextType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\UrlType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\ImageType;

class PropertyTypeRegistry implements PropertyTypeLookup {

	/**
	 * @var array<string, PropertyType> Keys are type names
	 */
	private array $types = [];

	/**
	 * @param SchemaReferenceParser $schemaReferenceParser Reads the `targetSchemas` of a relation
	 *   property, each a Schema reference like any other that names one Schema however written.
	 */
	public static function withCoreTypes( SchemaReferenceParser $schemaReferenceParser ): self {
		$registry = new self();
		$registry->registerType( new TextType() );
		$registry->registerType( new PageType() );
		$registry->registerType( new WikitextType() );
		$registry->registerType( new UrlType() );
		$registry->registerType( new ImageType() );
		$registry->registerType( new NumberType() );
		$registry->registerType( new SelectType() );
		$registry->registerType( new BooleanType() );
		$registry->registerType( new RelationType( $schemaReferenceParser ) );
		$registry->registerType( new DateTimeType() );
		$registry->registerType( new DateType() );
		$registry->registerType( new HistoricalDateType() );
		$registry->registerType( new MonolingualTextType() );
		return $registry;
	}

	public function registerType( PropertyType $type ): void {
		$this->types[$type->getTypeName()] = $type;
	}

	public function getType( string $typeName ): ?PropertyType {
		return $this->types[$typeName] ?? null;
	}

}
