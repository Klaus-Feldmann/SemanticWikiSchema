<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\Schema\Property;

use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\PageType;

/** Text constraints and storage, with a local-page meaning and namespace preference. */
class PageProperty extends TextProperty {
	public function __construct(
		PropertyCore $core, bool $multiple, bool $uniqueItems, ?int $minLength, ?int $maxLength,
		private readonly ?int $pageNamespace,
	) {
		parent::__construct( $core, $multiple, $uniqueItems, $minLength, $maxLength );
	}

	public function getPropertyType(): string { return PageType::NAME; }
	public function getPageNamespace(): ?int { return $this->pageNamespace; }

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		$namespace = $property['pageNamespace'] ?? null;
		if ( $namespace !== null && ( !is_int( $namespace ) || $namespace < 0
			|| !array_key_exists( $namespace, \MediaWiki\MediaWikiServices::getInstance()->getNamespaceInfo()->getCanonicalNamespaces() ) ) ) {
			throw new \InvalidArgumentException( 'Invalid local page namespace' );
		}
		return new self( $core, $property['multiple'] ?? false, $property['uniqueItems'] ?? false,
			$property['minLength'] ?? null, $property['maxLength'] ?? null, $namespace );
	}

	public function nonCoreToJson(): array {
		return parent::nonCoreToJson() + [ 'pageNamespace' => $this->pageNamespace ];
	}
}
