<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain;

/** Repository-qualified identity; preview URLs are deliberately not persisted. */
final readonly class ImageReference {
	public const PATTERN = '^(local|commons):[^\s:\x00-\x1F\x7F\[\]{}<>|#](?:[^\x00-\x1F\x7F\[\]{}<>|#]{0,498}[^\s\x00-\x1F\x7F\[\]{}<>|#])?(?![\s\S])';
	public function __construct( public string $repository, public string $filename ) {}
	public static function parse( string $reference ): ?self {
		$parts = explode( ':', $reference, 2 );
		if ( preg_match( '~' . self::PATTERN . '~uD', $reference ) !== 1 ) {
			return null;
		}
		return new self( $parts[0], $parts[1] );
	}
	public function toString(): string { return $this->repository . ':' . $this->filename; }
}
