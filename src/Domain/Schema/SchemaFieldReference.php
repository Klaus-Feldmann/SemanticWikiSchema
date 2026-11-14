<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\Schema;

/** Schema-scoped field identity; no global registry, label lookup or implicit equivalence. */
final readonly class SchemaFieldReference {
	public function __construct( public SchemaReference $schema, public string $field ) {}

	public function equals( self $other ): bool {
		return $this->schema->equals( $other->schema ) && $this->field === $other->field;
	}

	/** Explicit reference for internal adapters; does not change stored schema/entity formats. */
	public function toJson(): array {
		return [ 'schema' => $this->schema->toJson(), 'field' => $this->field ];
	}
}
