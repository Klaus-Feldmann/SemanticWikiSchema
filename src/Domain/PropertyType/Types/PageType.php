<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\PropertyType\Types;

use ProfessionalWiki\SWS\Domain\Schema\Property\PageProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Validation\Violation;
use ProfessionalWiki\SWS\Domain\Validation\Severity;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;

class PageType extends TextType {
	public const NAME = 'page';

	public function getTypeName(): string { return self::NAME; }

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): PageProperty {
		return PageProperty::fromPartialJson( $core, $property );
	}

	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		$violations = parent::validate( $value, $definition );
		if ( $value instanceof StringValue && $definition instanceof PageProperty ) {
			foreach ( $value->strings as $index => $part ) {
				if ( trim( $part ) !== '' && ( LocalPageTitle::normalize( $part ) === null
					|| $definition->assignsCategories() && \MediaWiki\Title\Title::newFromText( $part )?->getNamespace() !== 14 ) ) {
					$violations[] = new Violation( propertyName: null, code: 'invalid-page', valuePartIndex: $index,
						severity: Severity::Error );
				}
			}
		}
		return $violations;
	}
}
