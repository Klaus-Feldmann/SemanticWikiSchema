<?php

declare( strict_types = 1 );
namespace AEWI\SWS\Domain\PropertyType\Types;
use AEWI\SWS\Domain\Schema\Property\WikitextValueProperty;
use AEWI\SWS\Domain\Schema\PropertyCore;

class WikitextValueType extends TextType {
    public const NAME = 'wikitextValue';
    public function getTypeName(): string { return self::NAME; }
    public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): WikitextValueProperty {
        return WikitextValueProperty::fromPartialJson( $core, $property );
    }
}
