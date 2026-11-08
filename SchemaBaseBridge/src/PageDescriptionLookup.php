<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use RuntimeException;
use Throwable;

/** Reads persisted SHORTDESC metadata only. Never parses the page or invokes templates. */
final class PageDescriptionLookup {
	public function read( Title $title, Authority $authority ): array {
		if ( !$authority->authorizeRead( 'read', $title ) ) { throw new RuntimeException( 'Page description unavailable' ); }
		try {
			$values = MediaWikiServices::getInstance()->getPageProps()->getProperties( $title, 'shortdesc' );
			$text = $values[$title->getArticleID()] ?? null;
			if ( $text === null || is_string( $text ) && trim( $text ) === '' ) { return [ 'status' => 'missing' ]; }
			if ( !is_string( $text ) ) { return [ 'status' => 'unavailable' ]; }
			return [ 'status' => 'resolved', 'origin' => 'shortdesc', 'text' => trim( $text ),
				'language' => $title->getPageLanguage()->getCode(), 'pageId' => $title->getArticleID(), 'url' => $title->getFullURL() ];
		} catch ( Throwable ) { return [ 'status' => 'unavailable' ]; }
	}
}
