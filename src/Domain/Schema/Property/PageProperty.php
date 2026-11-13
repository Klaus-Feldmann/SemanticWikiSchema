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
		private readonly bool $assignCategories = false,
	) {
		parent::__construct( $core, $multiple, $uniqueItems, $minLength, $maxLength );
	}

	public function getPropertyType(): string { return PageType::NAME; }
	public function getPageNamespace(): ?int { return $this->pageNamespace; }
	public function assignsCategories(): bool { return $this->assignCategories; }

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		$namespace = $property['pageNamespace'] ?? null;
		if ( $namespace !== null && ( !is_int( $namespace ) || $namespace < 0
			|| !array_key_exists( $namespace, \MediaWiki\MediaWikiServices::getInstance()->getNamespaceInfo()->getCanonicalNamespaces() ) ) ) {
			throw new \InvalidArgumentException( 'Invalid local page namespace' );
		}
		$assign = $property['assignCategories'] ?? false;
		if ( !is_bool( $assign ) || $assign && $namespace !== 14 ) {
			throw new \InvalidArgumentException( 'Category assignment requires category namespace' );
		}
		if ( $assign && $core->default !== null ) {
			if ( !is_array( $core->default ) || !array_is_list( $core->default ) ) { throw new \InvalidArgumentException( 'Category default must be a list of page titles' ); }
			$defaults = [];
			foreach ( $core->default as $text ) {
				if ( !is_string( $text ) ) { throw new \InvalidArgumentException( 'Invalid category default' ); }
				if ( trim( $text ) === '' ) { continue; }
				$normalized = \ProfessionalWiki\SWS\Domain\PropertyType\Types\LocalPageTitle::normalize( $text );
				$title = $normalized === null ? null : \MediaWiki\Title\Title::newFromText( $normalized );
				if ( $title === null ) { throw new \InvalidArgumentException( 'Invalid category default' ); }
				if ( $title->getNamespace() !== 14 ) {
					$title = \MediaWiki\Title\Title::makeTitleSafe( 14, $normalized );
				}
				if ( $title === null ) { throw new \InvalidArgumentException( 'Invalid category default' ); }
				$defaults[] = $title->getPrefixedText();
			}
			$core = new PropertyCore( $core->description, $core->required, array_values( array_unique( $defaults ) ),
				$core->constraintSeverities, $core->wikiLink, $core->externalProperty, $core->fieldNotes );
		}
		return new self( $core, $property['multiple'] ?? false, $property['uniqueItems'] ?? false,
			$property['minLength'] ?? null, $property['maxLength'] ?? null, $namespace, $assign );
	}

	public function nonCoreToJson(): array {
		return parent::nonCoreToJson() + [ 'pageNamespace' => $this->pageNamespace, 'assignCategories' => $this->assignCategories ];
	}
}
