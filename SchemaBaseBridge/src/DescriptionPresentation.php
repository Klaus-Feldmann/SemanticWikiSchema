<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

/** Same readable descriptions for API, Data and Graph; never choose one source over another. */
final class DescriptionPresentation {
	public static function groups( array $identities ): array {
		$groups = [];
		foreach ( [ 'wikidata', 'wikibase-local' ] as $source ) {
			$item = $identities[$source] ?? [];
			$description = $item['description'] ?? null;
			if ( ( $item['status'] ?? '' ) !== 'resolved' || !is_array( $description ) ||
				!is_string( $description['value'] ?? null ) || !is_string( $description['language'] ?? null ) ||
				!is_string( $item['id'] ?? null ) || !is_string( $item['url'] ?? null ) ) { continue; }
			$text = trim( $description['value'] );
			if ( $text === '' ) { continue; }
			$key = $description['language'] . "\0" . $text;
			if ( !isset( $groups[$key] ) ) { $groups[$key] = [ 'text' => $text, 'language' => $description['language'], 'sources' => [] ]; }
			$groups[$key]['sources'][] = [ 'source' => $source, 'id' => $item['id'], 'url' => $item['url'] ];
		}
		return array_values( $groups );
	}
	/** One display choice; the full source groups remain available separately. */
	public static function select( array $identities, array $page ): ?array {
		if ( ( $page['status'] ?? '' ) === 'resolved' && is_string( $page['text'] ?? null ) && trim( $page['text'] ) !== '' ) {
			return [ 'text' => trim( $page['text'] ), 'language' => $page['language'],
				'sources' => [ [ 'source' => 'page', 'id' => (string)$page['pageId'], 'url' => $page['url'] ] ] ];
		}
		foreach ( [ 'wikibase-local', 'wikidata' ] as $source ) {
			$groups = self::groups( [ $source => $identities[$source] ?? [] ] );
			if ( $groups !== [] ) { return $groups[0]; }
		}
		return null;
	}
}
