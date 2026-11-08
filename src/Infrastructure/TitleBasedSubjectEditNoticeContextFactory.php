<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Infrastructure;

use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\SWS\Application\EditNotice\SubjectEditNoticeContextFactory;
use ProfessionalWiki\SWS\Domain\EditNotice\SubjectEditNoticeContext;
use ProfessionalWiki\SWS\Domain\Page\PageId;

readonly class TitleBasedSubjectEditNoticeContextFactory implements SubjectEditNoticeContextFactory {

	public function __construct(
		private TitleFactory $titleFactory,
		private NamespaceInfo $namespaceInfo,
	) {
	}

	public function newContext( PageId $pageId, ?string $schemaName ): ?SubjectEditNoticeContext {
		$title = $this->titleFactory->newFromID( $pageId->id );

		if ( $title === null ) {
			return null;
		}

		return new SubjectEditNoticeContext(
			pageId: $pageId,
			pageDbKey: $title->getDBkey(),
			namespaceId: $title->getNamespace(),
			schemaName: $schemaName,
			namespaceHasSubpages: $this->namespaceInfo->hasSubpages( $title->getNamespace() ),
		);
	}

}
