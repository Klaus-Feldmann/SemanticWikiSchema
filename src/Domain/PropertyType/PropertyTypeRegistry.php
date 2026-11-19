<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Domain\PropertyType;

use AEWI\SWS\Domain\Schema\SchemaReferenceParser;
use AEWI\SWS\Domain\PropertyType\Types\BooleanType;
use AEWI\SWS\Domain\PropertyType\Types\DateTimeType;
use AEWI\SWS\Domain\PropertyType\Types\DateType;
use AEWI\SWS\Domain\PropertyType\Types\HistoricalDateType;
use AEWI\SWS\Domain\PropertyType\Types\MonolingualTextType;
use AEWI\SWS\Domain\PropertyType\Types\NumberType;
use AEWI\SWS\Domain\PropertyType\Types\RelationType;
use AEWI\SWS\Domain\PropertyType\Types\SelectType;
use AEWI\SWS\Domain\PropertyType\Types\TextType;
use AEWI\SWS\Domain\PropertyType\Types\PageType;
use AEWI\SWS\Domain\PropertyType\Types\WikitextType;
use AEWI\SWS\Domain\PropertyType\Types\WikitextValueType;
use AEWI\SWS\Domain\PropertyType\Types\UrlType;
use AEWI\SWS\Domain\PropertyType\Types\ImageType;

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
		$registry->registerType( new WikitextValueType() );
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
