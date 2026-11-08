<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence;

use MediaWiki\Page\ProperPageIdentity;

interface PageDeleter {

	public function deletePage( ProperPageIdentity $page, string $reason ): PageDeletionStatus;

}
