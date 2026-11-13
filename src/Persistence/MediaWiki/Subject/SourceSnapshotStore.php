<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki\Subject;

use InvalidArgumentException;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Source\SourceSnapshot;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSavingStatus;
use RuntimeException;

/**
 * Internal-only source write. Loads its own parent slot and changes only sourceSnapshot,
 * preserving Statements, author notes, labels, ordering and all other Subjects in that revision.
 * Normal authorized writes use save(); D2's fixed internal writer reuses only contentForRevision().
 */
final readonly class SourceSnapshotStore {

	public function __construct( private PageContentSaver $saver, private Authority $authority ) {
	}

	/** @param SourceSnapshot[] $snapshots */
	public function save( PageId $page, int $expectedRevisionId, array $snapshots ): PageContentSavingStatus {
		$seen = [];
		foreach ( $snapshots as $snapshot ) {
			if ( !$snapshot instanceof SourceSnapshot || isset( $seen[$snapshot->getSubjectId()] ) ) {
				throw new InvalidArgumentException( 'Expected unique source snapshots' );
			}
			$seen[$snapshot->getSubjectId()] = true;
		}
		if ( $snapshots === [] ) {
			return new PageContentSavingStatus( PageContentSavingStatus::NO_CHANGES );
		}
		return $this->saver->updateContentAtRevision(
			$page,
			$expectedRevisionId,
			fn ( RevisionRecord $revision ): array => [ MediaWikiSubjectRepository::SLOT_NAME =>
				$this->contentForRevision( $revision, $page, $expectedRevisionId, $snapshots ) ],
			CommentStoreComment::newUnsavedComment( 'Update SWS source snapshot' )
		);
	}

	/** Only a source patch is returned; the caller cannot provide replacement Statements/slots. */
	public function contentForRevision( RevisionRecord $revision, PageId $page, int $expectedRevisionId, array $snapshots ): SubjectContent {
		if ( $revision->getPageId() !== $page->id || $revision->getId() !== $expectedRevisionId ) {
			throw new InvalidArgumentException( 'Source patch base revision changed' );
		}
		$content = $revision->getContent( MediaWikiSubjectRepository::SLOT_NAME, RevisionRecord::FOR_THIS_USER, $this->authority );
		if ( !$content instanceof SubjectContent ) {
			throw new RuntimeException( 'Cannot write a source snapshot without a readable SWS slot' );
		}
		$subjects = $content->getPageSubjects();
		$seen = [];
		foreach ( $snapshots as $snapshot ) {
			if ( !$snapshot instanceof SourceSnapshot || isset( $seen[$snapshot->getSubjectId()] ) ) {
				throw new InvalidArgumentException( 'Expected unique source snapshots' );
			}
			$seen[$snapshot->getSubjectId()] = true;
			$subject = $subjects->getAllSubjects()->getSubject( new SubjectId( $snapshot->getSubjectId() ) );
			if ( $subject === null ) {
				throw new RuntimeException( 'Source snapshot Subject no longer exists in this revision' );
			}
			self::assertWriteContext( $subject->getSourceSnapshot(), $snapshot, $page, $expectedRevisionId );
			$subject->setSourceSnapshot( $snapshot );
		}
		return SubjectContent::newFromData( $subjects );
	}

	private static function assertWriteContext( ?SourceSnapshot $previous, SourceSnapshot $next, PageId $page, int $revisionId ): void {
		$oldLanguages = $previous?->toJson()['languages'] ?? [];
		$newLanguages = $next->toJson()['languages'];
		foreach ( $oldLanguages as $language => $old ) {
			if ( !array_key_exists( $language, $newLanguages ) ) {
				throw new InvalidArgumentException( 'A source write must preserve other language snapshots' );
			}
		}
		foreach ( $newLanguages as $language => $context ) {
			if ( $context === ( $oldLanguages[$language] ?? null ) ) {
				continue;
			}
			if ( $context['pageId'] !== $page->id || $context['baseRevisionId'] !== $revisionId ) {
				throw new InvalidArgumentException( 'Source snapshot does not match its page and base revision' );
			}
		}
	}

}
