<?php

declare( strict_types = 1 );

namespace SWSBaseBridge;

/** The same rule serves schema save validation, property lookup and value retrieval. */
final class PropertyDatatypePolicy {
	public static function supports( string $fieldType, string $datatype ): bool {
		return in_array( $datatype, match ( $fieldType ) {
			'text' => [ 'wikibase-item', 'monolingualtext', 'string', 'external-id' ],
			'page' => [ 'wikibase-item' ],
			'image' => [ 'commonsMedia' ],
			'historicalDate' => [ 'time' ],
			default => [],
		}, true );
	}
}
