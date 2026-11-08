<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\SpecialPages;

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use PermissionsError;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Presentation\DocumentationUrl;

class SpecialCreateSubject extends SpecialPage {

	use EnforcesRestriction;

	public function __construct() {
		parent::__construct( 'CreateSubject' );
	}

	/**
	 * @param ?string $subPage
	 */
	public function execute( $subPage ): void {
		parent::execute( $subPage );
		$this->addHelpLink( DocumentationUrl::CreatingSubjects->value, true );

		// getRestriction() names one right; the page the Subject goes on needs createpage as well.
		if ( !$this->getAuthority()->isAllowed( 'createpage' ) ) {
			throw new PermissionsError( 'createpage' );
		}

		$schemaTitle = MediaWikiServices::getInstance()->getTitleFactory()
			->newFromText( $subPage ?? '', SWSExtension::NS_SCHEMA );

		if ( $schemaTitle !== null
			&& SWSExtension::getInstance()->newDependentSubjectRules()->localSchemaDeclaresDependentSubjects( $schemaTitle->getText() )
		) {
			$this->getOutput()->addHTML( Html::errorBox(
				$this->msg( 'sws-create-subject-error-dependent-subjects', $schemaTitle->getText() )->escaped()
			) );
			return;
		}

		SWSExtension::getInstance()->newFrontendModuleLoader()->load( $this->getOutput(), $this->getSkin() );

		$attributes = [ 'id' => 'ext-sws-create-subject' ];

		if ( $schemaTitle !== null ) {
			$attributes['data-mw-sws-schema'] = $schemaTitle->getText();
		}

		$this->getOutput()->addHTML( Html::element( 'div', $attributes ) );
	}

	public function getRestriction(): string {
		return 'edit';
	}

	public function getGroupName(): string {
		return 'sws';
	}

	public function getDescription(): Message {
		return $this->msg( 'sws-special-createsubject' );
	}

}
