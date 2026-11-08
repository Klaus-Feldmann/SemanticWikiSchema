<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\PropertyType\Types;
use ProfessionalWiki\SWS\Domain\Schema\Property\WikitextProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;

class WikitextType extends TextType {
	public const NAME = 'wikitext';
	public function getTypeName(): string { return self::NAME; }
	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): WikitextProperty {
		return WikitextProperty::fromPartialJson( $core, $property );
	}
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		return $definition->isSchemaExecution() ? [] : parent::validate( $value, $definition );
	}
}
