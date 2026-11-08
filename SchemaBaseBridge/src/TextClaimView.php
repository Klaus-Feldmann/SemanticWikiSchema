<?php

declare( strict_types = 1 );

namespace SWSBaseBridge;

use RuntimeException;

/** Source text stays text: no fabricated entity ID or local annotation on fetch. */
final class TextClaimView {
	public static function values( array $claims, string $datatype ): array {
		$values = [];
		foreach ( $claims as $claim ) {
			$snak = $claim['mainsnak'] ?? [];
			$kind = $snak['snaktype'] ?? '';
			if ( in_array( $kind, [ 'somevalue', 'novalue' ], true ) ) {
				$values[] = [ 'kind' => $kind ];
				continue;
			}
			if ( $kind !== 'value' || ( $snak['datatype'] ?? '' ) !== $datatype ) {
				throw new RuntimeException( 'Invalid text claim' );
			}

			$raw = $snak['datavalue']['value'] ?? null;
			$language = null;
			if ( $datatype === 'monolingualtext' ) {
				if ( !is_array( $raw ) || !is_string( $raw['text'] ?? null ) || !is_string( $raw['language'] ?? null ) ||
					preg_match( '/\A[a-z][a-z0-9-]{0,39}\z/', $raw['language'] ) !== 1
				) {
					throw new RuntimeException( 'Invalid language-tagged text' );
				}
				$text = $raw['text'];
				$language = $raw['language'];
			} else {
				if ( !is_string( $raw ) ) {
					throw new RuntimeException( 'Invalid string claim' );
				}
				$text = $raw;
			}
			if ( trim( $text ) === '' ) {
				throw new RuntimeException( 'Empty text claim' );
			}

			$rank = $claim['rank'] ?? 'normal';
			$statementId = $claim['id'] ?? null;
			if ( !in_array( $rank, [ 'preferred', 'normal', 'deprecated' ], true ) ||
				( $statementId !== null && !is_string( $statementId ) )
			) {
				throw new RuntimeException( 'Invalid text claim metadata' );
			}

			$values[] = [
				'kind' => 'text',
				'value' => $text,
				...( $language === null ? [] : [ 'language' => $language ] ),
				'rank' => $rank,
				'statementId' => $statementId,
			];
		}
		return $values;
	}
}
