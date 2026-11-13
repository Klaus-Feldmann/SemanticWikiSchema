<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Schema;

use OutOfBoundsException;

readonly class Schema {

	public function __construct(
		private SchemaName $name,
		private string $description,
		private PropertyDefinitions $properties,
		private bool $subjectsAreDependent,
		private ?\ProfessionalWiki\SWS\Domain\Identity\SchemaId $id = null,
		private ?\ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity $identity = null,
	) {
		if ( $id !== null && ( $name->getText() !== $id->text || $identity === null ) ) {
			throw new \InvalidArgumentException( 'Schema ID must match its ID page and have identity metadata' );
		}
	}

	public function getId(): ?\ProfessionalWiki\SWS\Domain\Identity\SchemaId { return $this->id; }
	public function getIdentity(): ?\ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity { return $this->identity; }
	public function getReference(): SchemaReference {
		return $this->id === null ? SchemaReference::local( $this->name ) : SchemaReference::identified( $this->id );
	}

	public function getName(): SchemaName {
		return $this->name;
	}

	public function getDescription(): string {
		return $this->identity === null ? $this->description : ( $this->identity->description() ?? '' );
	}

	/**
	 * @throws OutOfBoundsException
	 */
	public function getProperty( string|PropertyName $name ): PropertyDefinition {
		return $this->properties->getProperty( $name );
	}

	public function hasProperty( string|PropertyName $name ): bool {
		return $this->properties->hasProperty( $name );
	}

	public function getRelationProperties(): PropertyDefinitions {
		return $this->properties->getRelations();
	}

	public function getAllProperties(): PropertyDefinitions {
		return $this->properties;
	}

	public function subjectsAreDependent(): bool {
		return $this->subjectsAreDependent;
	}

}
