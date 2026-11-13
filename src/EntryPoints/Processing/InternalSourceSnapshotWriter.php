<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\MediaWikiServices;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SourceSnapshotStore;

/**
 * A narrowly scoped internal writer, not a general edit permission. It obtains its own read-only
 * system actor and only accepts a current source-preparation result. No caller-supplied slots,
 * statements, actor or edit callback. PageUpdater's CAS bracket protects the final insertion.
 */
final class InternalSourceSnapshotWriter {

	public function save( ProcessingResult $preparation ): void {
		if ( !\ProfessionalWiki\SWS\Application\Source\SourceDataMode::usesStore() || !$preparation->isSourcePreparation || $preparation->sourceSnapshots === [] || ProcessingContext::current() !== null ) {
			throw new ProcessingIncomplete( 'Expected a completed, non-recursive source preparation.' );
		}
		$preparation->assertCurrent();
		if ( ( $preparation->stamp['stage'] ?? null ) !== 'sources' ||
			!hash_equals( $preparation->stamp['sourceSnapshotHash'] ?? '', SourceSnapshotCapture::hash( $preparation->sourceSnapshots ) ) ) {
			throw new ProcessingIncomplete( 'Source preparation payload does not match its sealed result.' );
		}
		$services = MediaWikiServices::getInstance();
		if ( $services->getReadOnlyMode()->getReason() !== false ) {
			throw new ProcessingIncomplete( 'Source storage is paused while the wiki is read-only.', 'pending' );
		}
		$user = ProcessingIdentity::get();
		if ( !ProcessingIdentity::isProcessor( $user ) || !$user->authorizeRead( 'read', $preparation->title ) ) {
			throw new ProcessingIncomplete( 'Source writer identity cannot read this page.', 'denied' );
		}
		$updater = $services->getWikiPageFactory()->newFromTitle( $preparation->title )->newPageUpdater( $user );
		$parent = $updater->grabParentRevision();
		if ( $parent === null || $parent->getId() !== $preparation->revision->getId() || $parent->getSha1() !== $preparation->revision->getSha1() ) {
			throw new ProcessingIncomplete( 'Source write base revision changed.', 'superseded' );
		}
		$policy = \ProfessionalWiki\SWS\SWSExtension::getInstance()->getRevisionPolicy();
		if ( !$parent->userCan( \MediaWiki\Revision\RevisionRecord::DELETED_TEXT, $user ) ||
			!$policy->revisionIsReadableBy( $parent, $user ) || $policy->publishedRevision( $parent )?->getId() !== $parent->getId() ) {
			throw new ProcessingIncomplete( 'Source write revision is not publishable.', 'pending' );
		}
		$patcher = new SourceSnapshotStore( new PageContentSaver( $services->getWikiPageFactory(), $user ), $user );
		$content = $patcher->contentForRevision( $parent, new PageId( $parent->getPageId() ), $parent->getId(), $preparation->sourceSnapshots );
		$content = SourceRefreshReceipt::attach( $content, $preparation );
		$preparation->assertCurrent();
		SourceWriteScope::run( $preparation->title, $content->getPageSubjects(), static function () use ( $updater, $content, $preparation ): void {
			$updater->setContent( 'sws', $content );
			// Check dependencies after patch construction, immediately before the CAS-protected save.
			$preparation->assertCurrent();
			$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Update SWS source snapshot' ), EDIT_UPDATE | EDIT_INTERNAL );
			if ( !$updater->wasSuccessful() ) {
				throw new ProcessingIncomplete( 'Source snapshot revision was not saved: ' . ( $updater->getStatus()?->getWikiText() ?? 'unknown error' ), 'superseded' );
			}
		} );
	}

}
