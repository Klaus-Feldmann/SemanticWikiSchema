<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

/** P31-style entity values only in this increment; other datatypes are explicitly unsupported. */
final class EntityStatementView {
	public static function claims( array $entity, string $property ): array {
		return array_values( array_filter( $entity['claims'][$property] ?? [],
			static fn ( array $claim ): bool => ( $claim['rank'] ?? 'normal' ) !== 'deprecated' ) );
	}

	public static function targetIds( array $claims ): array {
		$ids = [];
		foreach ( $claims as $claim ) {
			$id = $claim['mainsnak']['datavalue']['value']['id'] ?? null;
			if ( is_string( $id ) && preg_match( '/\AQ[1-9][0-9]*\z/', $id ) === 1 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	public static function values( array $claims, array $targets, string $language, callable $link ): array {
		$values = [];
		foreach ( $claims as $claim ) {
			$snak = $claim['mainsnak'] ?? [];
			$kind = $snak['snaktype'] ?? '';
			if ( in_array( $kind, [ 'novalue', 'somevalue' ], true ) ) {
				$values[] = [ 'kind' => $kind ];
				continue;
			}
			$id = $snak['datavalue']['value']['id'] ?? null;
			if ( $kind !== 'value' || ( $snak['datatype'] ?? null ) !== 'wikibase-item' ||
				!is_string( $id ) || preg_match( '/\AQ[1-9][0-9]*\z/', $id ) !== 1 || !isset( $targets[$id] ) ) {
				continue;
			}
			$url = $link( $id );
			if ( $url !== null ) {
				$values[] = [ 'kind' => 'entity', 'id' => $id,
					'label' => $targets[$id]['labels'][$language]['value'] ?? $id, 'url' => $url,
					'rank' => $claim['rank'] ?? 'normal' ];
			}
		}
		return $values;
	}
}
