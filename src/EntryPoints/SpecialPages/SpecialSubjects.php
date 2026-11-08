<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Presentation\DocumentationUrl;

/**
 * Every Subject on the wiki, newest first, to filter by Schema and search. Special:Subjects/<Schema> opens on one
 * Schema's Subjects.
 */
class SpecialSubjects extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Subjects' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );
		$this->addHelpLink( DocumentationUrl::Subjects->value, true );

		$out = $this->getOutput();
		SWSExtension::getInstance()->newFrontendModuleLoader()->load( $out, $this->getSkin() );

		$attributes = [ 'id' => 'ext-sws-subjects' ];

		// As Schema names are read everywhere: "Help:Person" names the Schema page Schema:Help:Person.
		$schemaTitle = MediaWikiServices::getInstance()->getTitleFactory()
			->makeTitleSafe( SWSExtension::NS_SCHEMA, $subPage ?? '' );

		if ( $schemaTitle !== null ) {
			$attributes['data-mw-sws-schema'] = $schemaTitle->getText();
		}

		$out->addHTML( Html::element( 'div', $attributes ) );
	}

	public function getGroupName(): string {
		return 'sws';
	}

	public function getDescription(): Message {
		return $this->msg( 'sws-special-subjects' );
	}

}
