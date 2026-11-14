<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\Schema;

/** Exact technical-key lookup in one schema/snapshot. Labels are never identifiers. */
final class FieldNameResolver {
	/** @param array<string|int, mixed> $fields Already indexed by their stored technical key. */
	public static function resolve( string|PropertyName $reference, array $fields ): ?string {
		$key = (string)$reference;
		return array_key_exists( $key, $fields ) ? $key : null;
	}
}
