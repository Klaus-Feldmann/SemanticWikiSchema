<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Application\Images;

use ProfessionalWiki\SWS\Domain\ImageReference;

/** Normalises imageinfo from the authenticated wiki API or the fixed Commons API. */
final class ImageResults {
	public static function fromPages( array $pages, string $repository ): array {
		$items = [];
		foreach ( $pages as $page ) {
			$info = $page['imageinfo'][0] ?? null;
			if ( ( $page['ns'] ?? null ) !== 6 || !is_array( $info ) || !is_string( $page['title'] ?? null ) ) {
				continue;
			}
			$title = explode( ':', $page['title'], 2 )[1] ?? '';
			$reference = ImageReference::parse( $repository . ':' . $title );
			if ( $reference === null || !str_starts_with( $info['mime'] ?? '', 'image/' ) ) { continue; }
			$description = self::url( $info['descriptionurl'] ?? null, $repository );
			if ( $description === null ) { continue; }
			$thumb = self::url( $info['thumburl'] ?? null, $repository );
			$width = $info['thumbwidth'] ?? 0;
			$height = $info['thumbheight'] ?? 0;
			if ( !$thumb && ( $info['width'] ?? 0 ) <= 150 && ( $info['height'] ?? 0 ) <= 150 && ( $info['mime'] ?? '' ) !== 'image/svg+xml' ) {
				$thumb = self::url( $info['url'] ?? null, $repository );
				$width = $info['width'] ?? 0; $height = $info['height'] ?? 0;
			}
			if ( !is_int( $width ) || !is_int( $height ) || $width < 1 || $height < 1 || $width > 150 || $height > 150 ) {
				$thumb = null; $width = 0; $height = 0;
			}
			$items[] = [ 'reference' => $reference->toString(), 'repository' => $repository, 'filename' => $title,
				'descriptionUrl' => $description, 'thumbnailUrl' => $thumb, 'width' => $width, 'height' => $height,
				'mime' => $info['mime'] ];
		}
		return array_slice( $items, 0, 10 );
	}
	private static function url( mixed $value, string $repository ): ?string {
		if ( !is_string( $value ) ) { return null; }
		if ( str_starts_with( $value, '//' ) ) { $value = 'https:' . $value; }
		$url = parse_url( $value );
		if ( !is_array( $url ) || !in_array( $url['scheme'] ?? '', [ 'https', 'http' ], true ) ||
			!isset( $url['host'] ) || isset( $url['user'] ) || isset( $url['pass'] ) ) { return null; }
		if ( $repository === 'commons' && !in_array( strtolower( $url['host'] ), [ 'commons.wikimedia.org', 'upload.wikimedia.org', 'thumb.wikimedia.org' ], true ) ) { return null; }
		return $value;
	}
}
