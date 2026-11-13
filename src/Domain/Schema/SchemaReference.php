<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Schema;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use Stringable;

/**
 * Which Schema, and where it comes from (ADR 23). Legacy schemas keep their names (ADR 17). New schemas use
 * explicit S-ID references; the Source need not be the Source of the Subject using the schema.
 *
 * A Schema of this wiki is referenced by its bare name, which is its page title in the Schema namespace.
 * That title may itself contain a colon (`ISO:9001`), so a stored string is never split: it is always
 * one local name, exactly as it was before Sources existed. A Schema from elsewhere is stored as an
 * object instead, `{"source": "<key>", "name": "<name>"}`, which no local name can be mistaken for.
 *
 * {@see getText()} renders a reference for people. It is one-way: nothing reads it back.
 */
readonly class SchemaReference implements Stringable {

	/**
	 * @param ?string $source Source key, or null for a Schema of this wiki.
	 */
	private function __construct(
		public ?string $source,
		public SchemaName $name,
		public ?\ProfessionalWiki\SWS\Domain\Identity\SchemaId $id = null,
	) {
	}

	public static function identified( \ProfessionalWiki\SWS\Domain\Identity\SchemaId $id, ?string $source = null ): self {
		if ( $source !== null && !SubjectId::isValidSourceKey( $source ) ) { throw new InvalidArgumentException( 'Invalid Schema source' ); }
		return new self( $source, new SchemaName( $id->text ), $id );
	}

	public static function local( SchemaName $name ): self {
		return new self( null, $name );
	}

	/**
	 * @throws InvalidArgumentException When $source is not a well-formed Source key.
	 */
	public static function sourced( string $source, SchemaName $name ): self {
		if ( !SubjectId::isValidSourceKey( $source ) ) {
			throw new InvalidArgumentException( "Source key has the wrong format: '$source'" );
		}

		return new self( $source, $name );
	}

	/**
	 * Reads a stored reference. A reference naming this wiki's own Source canonicalizes to a local one,
	 * so a Schema has one identity however it was written down — the same rule Subject ids follow, and
	 * what keeps {@see equals()} agreeing with what the resolver hands back.
	 *
	 * @param mixed $json The value of a `schema` field or of a `targetSchemas` entry.
	 *
	 * @throws InvalidArgumentException When $json is not a well-formed reference.
	 */
	public static function fromJson( mixed $json, string $localSourceKey ): self {
		if ( is_array( $json ) && array_key_exists( 'id', $json ) ) {
			$ref = \ProfessionalWiki\SWS\Domain\Identity\IdentityReference::fromJson( $json, $localSourceKey );
			if ( !$ref->id instanceof \ProfessionalWiki\SWS\Domain\Identity\SchemaId ) { throw new InvalidArgumentException( 'Expected a Schema ID' ); }
			return self::identified( $ref->id, $ref->source );
		}
		if ( is_string( $json ) ) {
			return self::local( new SchemaName( $json ) );
		}

		if ( !is_array( $json ) || !is_string( $json['source'] ?? null ) || !is_string( $json['name'] ?? null ) ) {
			throw new InvalidArgumentException( 'Schema reference must be a name or a {source, name} object' );
		}

		if ( $json['source'] === $localSourceKey ) {
			return self::local( new SchemaName( $json['name'] ) );
		}

		return self::sourced( $json['source'], new SchemaName( $json['name'] ) );
	}

	/**
	 * @return string|array A legacy local reference keeps the bare string every
	 *   stored reference has always used; only a sourced one costs the object form.
	 */
	public function toJson(): string|array {
		if ( $this->id !== null ) { return ( new \ProfessionalWiki\SWS\Domain\Identity\IdentityReference( $this->id, $this->source ) )->toJson(); }
		if ( $this->source === null ) {
			return $this->name->getText();
		}

		return [ 'source' => $this->source, 'name' => $this->name->getText() ];
	}

	public function isLocal(): bool {
		return $this->source === null;
	}

	/**
	 * How a reference reads to a person, in a message or a UI. Not a serialization: `sourceKey:Name` is
	 * ambiguous with a colon-bearing local name, which is why nothing parses it.
	 */
	public function getText(): string {
		return $this->source === null ? $this->name->getText() : $this->source . ':' . $this->name->getText();
	}

	public function equals( self $other ): bool {
		return $this->source === $other->source && ( $this->id === null ) === ( $other->id === null ) && $this->name->getText() === $other->name->getText();
	}

	public function __toString(): string {
		return $this->getText();
	}

}
