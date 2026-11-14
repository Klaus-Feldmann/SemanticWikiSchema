<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Schema;

use OutOfBoundsException;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\RelationType;

class PropertyDefinitions {

	/**
	 * @var array<string, PropertyDefinition>
	 */
	private array $properties = [];

	/**
	 * @param array<string, PropertyDefinition> $properties Property definitions indexed by name
	 */
	public function __construct( array $properties ) {
		$this->properties = $properties;
	}

	/**
	 * @throws OutOfBoundsException
	 */
	public function getProperty( string|PropertyName $name ): PropertyDefinition {
		$key = $this->resolveName( $name );
		if ( $key !== null ) {
			return $this->properties[$key];
		}

		throw new OutOfBoundsException( "Property '$name' does not exist" );
	}

	public function hasProperty( string|PropertyName $name ): bool {
		return $this->resolveName( $name ) !== null;
	}

	public function resolveName( string|PropertyName $reference ): ?string {
		return FieldNameResolver::resolve( $reference, $this->properties );
	}

	/**
	 * @param callable(PropertyDefinition):bool $filter
	 */
	public function filter( callable $filter ): self {
		return new self( array_filter( $this->properties, $filter ) );
	}

	public function getRelations(): self {
		return $this->filter(
			fn( PropertyDefinition $property ) => $property->getPropertyType() === RelationType::NAME
		);
	}

	/**
	 * @return array<string, PropertyDefinition>
	 */
	public function asMap(): array {
		return $this->properties;
	}

}
