<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\SpecialPages;

use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Presentation\DocumentationUrl;

class SpecialLayouts extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Layouts' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );
		$this->addHelpLink( DocumentationUrl::Layouts->value, true );

		SWSExtension::getInstance()->newFrontendModuleLoader()->load( $this->getOutput(), $this->getSkin() );
		$this->getOutput()->addHTML( '<div id="ext-sws-layouts"></div>' );
	}

	public function getGroupName(): string {
		return 'sws';
	}

	public function getDescription(): Message {
		return $this->msg( 'sws-special-layouts' );
	}

}
