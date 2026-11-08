<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use ProfessionalWiki\SWS\Domain\ImageReference;

/** commonsMedia denotes Commons files in standard Wikidata AND Wikibase. The claim source is retained by the field. */
final class ImageClaimView {
	public static function values( array $claims ): array {
		$result = [];
		foreach ( $claims as $claim ) {
			if ( ( $claim['rank'] ?? 'normal' ) === 'deprecated' ) { continue; }
			$snak = $claim['mainsnak'] ?? [];
			$kind = $snak['snaktype'] ?? '';
			if ( in_array( $kind, [ 'somevalue', 'novalue' ], true ) ) { $result[] = [ 'kind' => $kind ]; continue; }
			$name = $snak['datavalue']['value'] ?? null;
			if ( $kind !== 'value' || ( $snak['datatype'] ?? null ) !== 'commonsMedia' ||
				( $snak['datavalue']['type'] ?? null ) !== 'string' || !is_string( $name ) ) { continue; }
			$reference = ImageReference::parse( 'commons:' . $name );
			if ( $reference !== null ) {
				$result[] = [ 'kind' => 'image', 'reference' => $reference->toString(), 'filename' => $reference->filename,
					'repository' => 'commons', 'rank' => $claim['rank'] ?? 'normal' ];
			}
		}
		return $result;
	}
}
