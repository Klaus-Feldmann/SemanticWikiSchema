<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Presentation\DocumentationUrl;

class SpecialSWS extends SpecialPage {

	public function __construct() {
		parent::__construct( 'SWS' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );
		$this->addHelpLink( DocumentationUrl::GettingStarted->value, true );

		SWSExtension::getInstance()->newFrontendModuleLoader()->load( $this->getOutput(), $this->getSkin() );

		$attributes = [ 'id' => 'ext-sws-overview' ];

		// Special:GraphStores is restricted, and Special:SpecialPages lists it only to those who may use
		// it. Offering a link here to anyone else would lead to a permissions error instead of a page.
		if ( $this->getAuthority()->isAllowed( SWSExtension::ADMIN_RIGHT ) ) {
			$attributes['data-mw-sws-can-manage-graph-stores'] = 'true';
		}

		if ( $this->canEditConfiguration() ) {
			$attributes['data-mw-sws-can-edit-configuration'] = 'true';
		}

		if ( SWSExtension::getInstance()->getPageIdentifiersLookup()->wikiHasSubjects() ) {
			$attributes['data-mw-sws-wiki-has-subjects'] = 'true';
		}

		$this->getOutput()->addHTML( Html::element( 'div', $attributes ) );
	}

	/**
	 * The map offers the configuration page only to those who may edit it, as with Graph stores.
	 */
	private function canEditConfiguration(): bool {
		if ( !SWSExtension::getInstance()->isInWikiConfigEnabled() ) {
			return false;
		}

		$configTitle = MediaWikiServices::getInstance()->getTitleFactory()
			->makeTitle( NS_MEDIAWIKI, SWSExtension::CONFIG_PAGE_TITLE );

		return $this->getAuthority()->probablyCan( 'edit', $configTitle );
	}

	public function getGroupName(): string {
		return 'sws';
	}

	public function getDescription(): Message {
		return $this->msg( 'sws-special-sws' );
	}

}
