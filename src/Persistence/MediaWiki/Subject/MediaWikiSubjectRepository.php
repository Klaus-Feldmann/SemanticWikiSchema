<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki\Subject;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Content;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\SWS\Application\DependentSubjectGuard;
use ProfessionalWiki\SWS\Application\PageIdentifiersLookup;
use ProfessionalWiki\SWS\Application\SubjectRepository;
use ProfessionalWiki\SWS\Application\Subject\Exception\DependentSubjectRulesViolatedException;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectIdList;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSavingStatus;
use Wikimedia\Rdbms\IDBAccessObject;

class MediaWikiSubjectRepository implements SubjectRepository {

	public const string SLOT_NAME = 'sws';

	public function __construct(
		private readonly PageIdentifiersLookup $pageIdentifiersLookup,
		private readonly RevisionLookup $revisionLookup,
		private readonly PageContentSaver $pageContentSaver,
		private readonly TitleFactory $titleFactory,
		private readonly IContentHandlerFactory $contentHandlerFactory,
		private readonly DependentSubjectGuard $dependentSubjectGuard,
	) {
	}

	public function getSubject( SubjectId $subjectId ): ?Subject {
		return $this->getSubjects( new SubjectIdList( [ $subjectId ] ) )->getSubject( $subjectId );
	}

	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap {
		$subjects = new SubjectMap();
		$idsByHostingPage = new SubjectIdsByHostingPage( $this->pageIdentifiersLookup );

		foreach ( $idsByHostingPage->group( $subjectIds ) as $pageId => $idsOnPage ) {
			$pageSubjects = $this->getContentByPageId( new PageId( $pageId ) )?->getPageSubjects()->getAllSubjects();

			if ( $pageSubjects !== null ) {
				$subjects = $subjects->union( $pageSubjects->onlyWithIds( $idsOnPage ) );
			}
		}

		return $subjects;
	}

	private function getPageIdForSubject( SubjectId $subjectId ): ?PageId {
		return $this->pageIdentifiersLookup->getPageIdOfSubject( $subjectId )?->getId();
	}

	/**
	 * Strict where the read-only lookups are lenient: this content is what the write methods mutate
	 * and save back, so a slot holding another content model has to stop the write rather than read
	 * as no Subjects and be saved over.
	 */
	private function getContentByPageId( PageId $pageId ): ?SubjectContent {
		$revision = $this->revisionLookup->getRevisionByPageId( $pageId->id );

		if ( $revision === null ) {
			return null;
		}

		try {
			$content = $revision->getContent( self::SLOT_NAME );
		}
		catch ( RevisionAccessException ) {
			return null;
		}

		if ( $content instanceof SubjectContent ) {
			return $content;
		}

		throw new \RuntimeException( 'Content is not a SubjectContent' );
	}

	public function updateSubject(
		Subject $subject,
		?string $comment = null,
		SubjectMap $dependentSubjects = new SubjectMap()
	): PageContentSavingStatus {
		$pageId = $this->getPageIdForSubject( $subject->id );
		$content = $pageId === null ? null : $this->getContentByPageId( $pageId );
		$pageSubjects = $content?->getPageSubjects();

		if ( $pageId === null || $content === null || $pageSubjects?->getAllSubjects()->hasSubject( $subject->id ) !== true ) {
			return new PageContentSavingStatus( PageContentSavingStatus::ERROR, 'Subject not found' );
		}

		$pageSubjects->updateSubject( $subject );

		foreach ( $dependentSubjects->asArray() as $dependentSubject ) {
			$pageSubjects->putSubject( $dependentSubject );
		}

		return $this->saveGuarded( $pageSubjects, $dependentSubjects->getIds(), $pageId, $comment );
	}

	/**
	 * @throws DependentSubjectRulesViolatedException
	 */
	private function saveGuarded(
		PageSubjects $pageSubjects,
		SubjectIdList $suppliedIds,
		PageId $pageId,
		?string $comment
	): PageContentSavingStatus {
		$revision = $this->revisionLookup->getRevisionByPageId( $pageId->id, 0, IDBAccessObject::READ_LATEST );
		if ( $revision === null ) {
			return new PageContentSavingStatus( PageContentSavingStatus::ERROR, 'Page not found' );
		}
		return $this->pageContentSaver->updateContentAtRevision(
			$pageId,
			$revision->getId(),
			function ( \MediaWiki\Revision\RevisionRecord $parent ) use ( $pageSubjects, $suppliedIds ): array {
				$content = $parent->hasSlot( self::SLOT_NAME )
					? $parent->getContent( self::SLOT_NAME ) : SubjectContent::newEmpty();
				// A page without a subject slot may receive its first Subject. A foreign or
				// inaccessible slot must never be read as empty and overwritten.
				if ( !$content instanceof SubjectContent ) {
					throw new \RuntimeException( 'Content is not a SubjectContent' );
				}
				$previous = $content->getPageSubjects();
				// Copy from the CAS parent, not an earlier replica/Subject object: normal edits
				// cannot erase a source result that finished while the editor was open.
				foreach ( $pageSubjects->getAllSubjects()->asArray() as $subject ) {
					$prior = $previous->getAllSubjects()->getSubject( $subject->id );
					if ( $prior !== null && $prior->getSchemaReference()->equals( $subject->getSchemaReference() ) ) {
						$subject->setSourceSnapshot( $prior->getSourceSnapshot() );
					}
				}
				$next = SubjectContent::newFromData( $this->dependentSubjectGuard->admit( $previous, $pageSubjects, $suppliedIds ) );
                $title = str_contains( $content->getText(), '"sourceRefresh"' ) ? $this->titleFactory->newFromID( $parent->getPageId() ) : null;
                if ( $title !== null ) {
                    $next = \ProfessionalWiki\SWS\EntryPoints\Processing\SourceRefreshReceipt::carryMetadata( $title, $parent, $next );
                }
                return [ self::SLOT_NAME => $next ];
			},
			CommentStoreComment::newUnsavedComment( $comment ?? 'Update SWS subject' )
		);
	}

	private function saveContent( SubjectContent $content, PageId $pageId, ?string $comment = null ): PageContentSavingStatus {
		return $this->pageContentSaver->saveContent(
			$pageId,
			[
				self::SLOT_NAME => $content,
			],
			CommentStoreComment::newUnsavedComment( $comment ?? 'Update SWS subject' )
		);
	}

	public function deleteSubject( SubjectId $id, ?string $comment ): PageContentSavingStatus {
		$pageId = $this->getPageIdForSubject( $id );

		if ( $pageId === null ) {
			return new PageContentSavingStatus( PageContentSavingStatus::NO_CHANGES );
		}

		$content = $this->getContentByPageId( $pageId );

		if ( $content === null ) {
			return new PageContentSavingStatus( PageContentSavingStatus::NO_CHANGES );
		}

		// Asked of the slot rather than inferred from the save: the write re-serializes the slot
		// whatever it removed, so a slot written by anything other than the serializer - an import,
		// or Special:NeoJson - yields new bytes and a real revision even when the removal removed
		// nothing.
		if ( $content->getPageSubjects()->getAllSubjects()->getSubject( $id ) === null ) {
			return new PageContentSavingStatus( PageContentSavingStatus::NO_CHANGES );
		}

		return $this->saveGuarded( $content->getPageSubjects()->without( $id ), new SubjectIdList( [] ), $pageId, $comment );
	}

	public function getMainSubject( PageId $pageId ): ?Subject {
		return $this->getContentByPageId( $pageId )?->getPageSubjects()->getMainSubject();
	}

	public function getSubjectsByPageId( PageId $pageId ): PageSubjects {
		return $this->getContentByPageId( $pageId )?->getPageSubjects() ?? PageSubjects::newEmpty();
	}

	public function savePageSubjects( PageSubjects $pageSubjects, PageId $pageId, ?string $comment = null ): PageContentSavingStatus {
		return $this->saveGuarded(
			$pageSubjects,
			new SubjectIdList( [] ),
			$pageId,
			$comment
		);
	}

	public function restorePageSubjects( PageSubjects $pageSubjects, PageId $pageId, ?string $comment ): PageContentSavingStatus {
		$content = $this->getContentByPageId( $pageId ) ?? SubjectContent::newEmpty();

		$content->setPageSubjects( $pageSubjects );

		return $this->saveContent( $content, $pageId, $comment );
	}

	public function createPageWithSubjects( string $pageTitle, PageSubjects $pageSubjects, ?string $comment = null ): PageContentSavingStatus {
		$title = $this->titleFactory->newFromTextThrow( $pageTitle );

		return $this->pageContentSaver->createPage(
			$title,
			[
				// Empty rather than absent, because a page needs a main slot.
				SlotRecord::MAIN => $this->emptyContent( $title ),
				self::SLOT_NAME => SubjectContent::newFromData(
					$this->dependentSubjectGuard->admit( PageSubjects::newEmpty(), $pageSubjects, new SubjectIdList( [] ) )
				),
			],
			CommentStoreComment::newUnsavedComment( $comment ?? 'Create a page for a new SWS subject' )
		);
	}

	private function emptyContent( Title $title ): Content {
		return $this->contentHandlerFactory
			->getContentHandler( $title->getContentModel() )
			->makeEmptyContent();
	}
}
