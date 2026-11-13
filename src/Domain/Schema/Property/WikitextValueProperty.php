<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\Schema\Property;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;

/** Editable Subject wikitext. Distinct from a central schema template. */
class WikitextValueProperty extends TextProperty {
    public function getPropertyType(): string { return 'wikitextValue'; }
    public static function fromPartialJson( PropertyCore $core, array $property ): self {
        return new self( $core, $property['multiple'] ?? false, $property['uniqueItems'] ?? false,
            $property['minLength'] ?? null, $property['maxLength'] ?? null );
    }
}
