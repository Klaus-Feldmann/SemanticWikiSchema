<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki\Subject;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\SWS\Application\SubjectContentRepository;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSaver;
use RuntimeException;
use WikiPage;
use Wikimedia\Rdbms\IDBAccessObject;

class MediaWikiSubjectContentRepository implements SubjectContentRepository {

	public function __construct(
		private readonly WikiPageFactory $wikiPageFactory,
		private readonly Authority $authority,
		private readonly PageContentSaver $pageContentSaver,
		private readonly RevisionLookup $revisionLookup,
	) {
	}

	public function getSubjectContentByPageId( PageId $pageId ): ?SubjectContent {
		return $this->getSubjectContentFromWikiPage( $this->wikiPageFactory->newFromID( $pageId->id ) );
	}

	public function getSubjectContentByPageTitle( PageIdentity $pageIdentity ): ?SubjectContent {
		if ( !$pageIdentity->canExist() ) {
			return null;
		}

		return $this->getSubjectContentFromWikiPage( $this->wikiPageFactory->newFromTitle( $pageIdentity ) );
	}

	public function getSubjectContentByRevisionId( int $revisionId ): ?SubjectContent {
		$revision = $this->revisionLookup->getRevisionById( $revisionId );

		if ( $revision === null ) {
			return null;
		}

		return $this->getSubjectContentFromRevision( $revision );
	}

	private function getSubjectContentFromWikiPage( ?WikiPage $wikiPage ): ?SubjectContent {
		if ( $wikiPage === null ) {
			return null;
		}

		$revision = $wikiPage->getRevisionRecord();

		if ( $revision === null ) {
			return null;
		}

		return $this->getSubjectContentFromRevision( $revision );
	}

	private function getSubjectContentFromRevision( RevisionRecord $revision ): ?SubjectContent {
		try {
			$slot = $revision->getSlot(
				MediaWikiSubjectRepository::SLOT_NAME,
				RevisionRecord::FOR_THIS_USER,
				$this->authority
			);
		}
		catch ( RevisionAccessException ) {
			return null;
		}

		$content = $slot->getContent();

		if ( !( $content instanceof SubjectContent ) ) {
			throw new RuntimeException( 'Expected SubjectContent' );
		}

		return $content;
	}

	public function editSubjectContent(
		SubjectContent $subjectContent,
		PageId $pageId,
		string $editSummary
	): void {
		$proposed = $subjectContent->getPageSubjects();
		$revision = $this->revisionLookup->getRevisionByPageId( $pageId->id, 0, IDBAccessObject::READ_LATEST );
		if ( $revision === null ) {
			throw new RuntimeException( 'Page not found' );
		}
		$savingResult = $this->pageContentSaver->updateContentAtRevision(
			$pageId,
			$revision->getId(),
			function ( RevisionRecord $parent ) use ( $proposed ): array {
				$current = $this->getSubjectContentFromRevision( $parent );
				\ProfessionalWiki\SWS\Application\Source\SourceSnapshotEditGuard::preserve( $proposed,
					$current?->getPageSubjects() ?? \ProfessionalWiki\SWS\Domain\Page\PageSubjects::newEmpty() );
				return [ MediaWikiSubjectRepository::SLOT_NAME => SubjectContent::newFromData( $proposed ) ];
			},
			CommentStoreComment::newUnsavedComment( $editSummary )
		);

		if ( $savingResult->errorMessage !== null ) {
			throw new RuntimeException( $savingResult->errorMessage );
		}
	}

}
