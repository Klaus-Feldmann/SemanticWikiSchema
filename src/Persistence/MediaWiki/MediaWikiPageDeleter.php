<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki;

use MediaWiki\Page\DeletePageFactory;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use ProfessionalWiki\SWS\Persistence\PageDeleter;
use ProfessionalWiki\SWS\Persistence\PageDeletionStatus;

class MediaWikiPageDeleter implements PageDeleter {

	public function __construct(
		private readonly DeletePageFactory $deletePageFactory,
		private readonly Authority $performer,
	) {
	}

	public function deletePage( ProperPageIdentity $page, string $reason ): PageDeletionStatus {
		$status = $this->deletePageFactory
			->newDeletePage( $page, $this->performer )
			->deleteUnsafe( $reason );

		if ( $status->isGood() ) {
			return new PageDeletionStatus( true );
		}

		return new PageDeletionStatus( false, $status->getWikiText() );
	}

}
