<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWiki\WikiMap\WikiMap;
use Wikibase\Client\WikibaseClient;
use Wikibase\Repo\WikibaseRepo;

/** Caller authorizes the hosting page; the local entity is separately read-authorized here. */
final class PageIdentityLookup {
	public function wikidata( Title $page ): array {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'UnlinkedWikibase' ) ) {
			return [ 'status' => 'unavailable' ];
		}
		$values = MediaWikiServices::getInstance()->getPageProps()->getProperties( $page, 'unlinkedwikibase_id' );
		$current = \ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingContext::current()?->currentProperty( $page, 'unlinkedwikibase_id' );
		$id = $current ?? $values[$page->getArticleID()] ?? null;
		if ( $id === null || $id === '' ) {
			return [ 'status' => 'missing' ];
		}
		if ( !is_string( $id ) || preg_match( '/\AQ[1-9][0-9]*\z/', $id ) !== 1 ) {
			return [ 'status' => 'error' ];
		}
		return [ 'status' => 'resolved', 'id' => $id,
			'url' => 'https://www.wikidata.org/wiki/' . $id, 'origin' => 'page' ];
	}

	public function wikibase( Title $page, Authority $authority ): array {
		$registry = ExtensionRegistry::getInstance();
		if ( !$registry->isLoaded( 'WikibaseRepository' ) ) {
			return [ 'status' => 'unavailable' ];
		}
		$siteId = MediaWikiServices::getInstance()->getMainConfig()->get( 'SWSBaseBridgeLocalSiteId' );
		if ( $siteId === null ) {
			$siteId = $registry->isLoaded( 'WikibaseClient' )
				? WikibaseClient::getSite()->getGlobalId() : WikiMap::getCurrentWikiId();
		}
		if ( !is_string( $siteId ) || $siteId === '' ) {
			return [ 'status' => 'error' ];
		}
		$id = WikibaseRepo::getStore()->getEntityByLinkedTitleLookup()
			->getEntityIdForLinkedTitle( $siteId, $page->getPrefixedText() );
		if ( $id === null ) {
			return [ 'status' => 'missing' ];
		}
		$text = $id->getSerialization();
		if ( preg_match( '/\AQ[1-9][0-9]*\z/', $text ) !== 1 ) {
			return [ 'status' => 'error' ];
		}
		$entityTitle = WikibaseRepo::getEntityTitleLookup()->getTitleForId( $id );
		if ( $entityTitle === null || !$authority->authorizeRead( 'read', $entityTitle ) ) {
			return [ 'status' => 'unavailable' ];
		}
		return [ 'status' => 'resolved', 'id' => $text, 'url' => $entityTitle->getFullURL(), 'origin' => 'local-sitelink' ];
	}
}
