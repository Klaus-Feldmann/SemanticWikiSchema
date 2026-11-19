<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\Actions;

use FormlessAction;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use AEWI\SWS\Domain\Page\PageId;
use AEWI\SWS\SWSExtension;

class SubjectsAction extends FormlessAction {

	public const string ACTION_NAME = 'entities';
	public const string LEGACY_ACTION_NAME = 'subjects';

	public static function isActionName( ?string $name ): bool {
		return $name === self::ACTION_NAME || $name === self::LEGACY_ACTION_NAME;
	}

	public function getName(): string {
		return self::ACTION_NAME;
	}

	public function getRestriction(): string {
		return 'read';
	}

	public function requiresWrite(): bool {
		return false;
	}

	public function getPageTitle(): Message {
		return $this->msg( 'sws-managesubjects-title', $this->getTitle()->getPrefixedText() );
	}

	protected function getDescription(): string {
		return '';
	}

	public function onView(): string {
		$out = $this->getOutput();
		$title = $out->getTitle();

		if ( !self::isEligibleTitle( $title ) ) {
			return Html::errorBox(
				$this->msg( 'sws-managesubjects-not-applicable' )->escaped()
			);
		}

		$extension = SWSExtension::getInstance();
		$extension->newFrontendModuleLoader()->loadWithSubjectPermissions(
			$out,
			$this->getSkin(),
			$extension->newSubjectPermissionHints( $this->getAuthority() )
				->canEditSubject( new PageId( $title->getArticleID() ) )
		);

		$out->addJsConfigVars( [
			...$extension->getSubjectUiJsConfigVars( $this->getAuthority() ),
			'wgSWSManageSubjectsPageId' => $title->getArticleID(),
		] );

		return Html::element( 'div', [ 'id' => 'ext-sws-manage-subjects' ] );
	}

	public static function isEligibleTitle( ?Title $title ): bool {
		if ( $title === null || !$title->canExist() || $title->getArticleID() === 0 ) {
			return false;
		}

		return \AEWI\SWS\Infrastructure\SubjectNamespacePolicy::allowsTitle( $title );
	}

}
