<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

/** Converts native API results to the source-qualified suggestion contract. */
final class PropertyResults {
	public static function fromSearch( array $data, callable $link ): array {
		$items = [];
		foreach ( array_slice( $data['search'] ?? [], 0, 10 ) as $row ) {
			$id = $row['id'] ?? null;
			if ( !is_string( $id ) || preg_match( '/\AP[1-9][0-9]*\z/', $id ) !== 1 ) {
				continue;
			}
			$url = $link( $id );
			if ( $url === null ) {
				continue;
			}
			$items[] = [ 'id' => $id, 'label' => (string)( $row['label'] ?? $id ),
				'description' => (string)( $row['description'] ?? '' ), 'url' => $url ];
		}
		return $items;
	}

	public static function fromEntity( array $data, string $id, string $language, callable $link ): array {
		$row = $data['entities'][$id] ?? null;
		if ( !is_array( $row ) || array_key_exists( 'missing', $row ) ||
			( $row['type'] ?? null ) !== 'property' || ( $row['id'] ?? null ) !== $id
		) {
			return [];
		}
		$url = $link( $id );
		if ( $url === null ) {
			return [];
		}
		return [ [ 'id' => $id, 'label' => $row['labels'][$language]['value'] ?? $id,
			'description' => $row['descriptions'][$language]['value'] ?? '', 'url' => $url, 'datatype'=>$row['datatype'] ?? null ] ];
	}
}
