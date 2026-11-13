<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Identity;

use InvalidArgumentException;
use Stringable;

/** Local identity, independent of label, page title and source-domain identifiers (Q/P/L). */
abstract readonly class PersistentId implements Stringable {
	public const int MAX_NUMBER = 999999999999999999;
	protected const string PREFIX = '';
	public string $text;
	public int $number;

	final public function __construct( string $text ) {
		if ( PHP_INT_SIZE < 8 || preg_match( '/^' . static::PREFIX . '([1-9][0-9]{0,17})\z/', $text, $match ) !== 1 ) {
			throw new InvalidArgumentException( 'Invalid ' . static::class . ': ' . $text );
		}
		$this->text = $text;
		$this->number = (int)$match[1];
	}

	final public static function fromNumber( int $number ): static {
		if ( $number < 1 || $number > self::MAX_NUMBER ) {
			throw new InvalidArgumentException( 'Identity number outside supported range' );
		}
		return new static( static::PREFIX . $number );
	}

	final public static function parse( string $text ): self {
		return match ( $text[0] ?? '' ) {
			'S' => new SchemaId( $text ),
			'E' => new EntityId( $text ),
			'R' => new RelationResourceId( $text ),
			default => throw new InvalidArgumentException( 'Unknown identity prefix' ),
		};
	}

	abstract public function kind(): IdentityKind;

	final public function equals( self $other ): bool {
		return $this->kind() === $other->kind() && $this->text === $other->text;
	}

	final public function __toString(): string {
		return $this->text;
	}
}
