<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\PropertyType\Types;
use ProfessionalWiki\SWS\Domain\Schema\Property\WikitextValueProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;

class WikitextValueType extends TextType {
    public const NAME = 'wikitextValue';
    public function getTypeName(): string { return self::NAME; }
    public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): WikitextValueProperty {
        return WikitextValueProperty::fromPartialJson( $core, $property );
    }
}
