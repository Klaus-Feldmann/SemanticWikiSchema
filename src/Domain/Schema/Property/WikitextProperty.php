<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\Schema\Property;

use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;

/** The schema program is separate from all subject values, including dormant old values. */
class WikitextProperty extends TextProperty {
	public function __construct( PropertyCore $core, bool $multiple, bool $uniqueItems, ?int $minLength, ?int $maxLength,
		private readonly string $wikitext,
	) { parent::__construct( $core, $multiple, $uniqueItems, $minLength, $maxLength ); }
	public function getPropertyType(): string { return 'wikitext'; }
	public function isSchemaExecution(): bool { return true; }
	public function isRequired(): bool { return false; }
	public function getWikitext(): string { return $this->wikitext; }
	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		$execute = $property['execute'] ?? true;
		$code = $property['wikitext'] ?? '';
		if ( !is_bool( $execute ) || !is_string( $code ) ) {
			throw new \InvalidArgumentException( 'Template wikitext must be a string' );
		}
		return new self( $core, $property['multiple'] ?? false, $property['uniqueItems'] ?? false,
			$property['minLength'] ?? null, $property['maxLength'] ?? null, $code );
	}
	public function nonCoreToJson(): array {
		return parent::nonCoreToJson() + [ 'wikitext' => $this->wikitext ];
	}
}
