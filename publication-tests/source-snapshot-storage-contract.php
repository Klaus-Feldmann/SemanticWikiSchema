<?php

declare( strict_types = 1 );

require __DIR__ . '/source-snapshot/MediaWikiDoubles.php';
require __DIR__ . '/source-snapshot-contract.php';

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\{ Content, IContentHandlerFactory, JsonContent };
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\{ RevisionAccessException, RevisionLookup, RevisionRecord };
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\SWS\Application\DependentSubjectGuard;
use ProfessionalWiki\SWS\Domain\Page\{ PageId, PageSubjects };
use ProfessionalWiki\SWS\Domain\Subject\{ SubjectId, SubjectMap };
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\Persistence\MediaWiki\{ PageContentSaver, PageContentSavingStatus };
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\{ MediaWikiSubjectContentRepository, MediaWikiSubjectRepository, SourceSnapshotStore };
use ProfessionalWiki\SWS\Tests\Data\TestNaming;
use ProfessionalWiki\SWS\Tests\TestDoubles\{ InMemoryPageIdentifiersLookup, InMemorySubjectLookup };

\ProfessionalWiki\SWS\SWSExtension::$deserializer = $deserializer;
$authority = new class implements Authority {
	public bool $read = true;
	public bool $edit = true;
	public function authorizeRead( string $action, mixed $title ): bool { return $this->read; }
	public function authorizeWrite( string $action, mixed $title ): bool { return $this->edit; }
};
function pageForSource( ?SubjectContent $content = null, int $revision = 101 ): WikiPage {
	return new WikiPage( new RevisionRecord( $revision, 943, [ 'main' => new JsonContent( 'Unveränderter Artikel' ),
		'sws' => $content ?? SubjectContent::newFromData( new PageSubjects( subjectSource(), new SubjectMap() ) ) ] ) );
}
function storeForSource( WikiPage $page ): SourceSnapshotStore {
	global $authority;
	return new SourceSnapshotStore( new PageContentSaver( new WikiPageFactory( $page ), $authority ), $authority );
}

$wiki = pageForSource();
$ownBefore = $wiki->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject();
$status = storeForSource( $wiki )->save( new PageId( 943 ), 101, [ $first ] );
checkSource( $status->status === PageContentSavingStatus::REVISION_CREATED, 'Internal source write creates a revision' );
checkSource( array_keys( $wiki->lastWrittenSlots ) === [ 'sws' ], 'Source writer changes only SWS slot' );
checkSource( $wiki->revision->getContent( 'main' )->getText() === 'Unveränderter Artikel', 'Main article body unchanged' );
$storedSubject = $wiki->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject();
checkSource( $storedSubject->getSourceSnapshot()->toJson() === $first->toJson(), 'Real SubjectContent storage contains source snapshot' );
checkSource( $storedSubject->getStatements() == $ownBefore->getStatements() && $storedSubject->getFieldNotes() === $ownBefore->getFieldNotes(), 'Internal write preserves own data and notes' );
checkSource( storeForSource( $wiki )->save( new PageId( 943 ), 102, [ $first ] )->status === PageContentSavingStatus::NO_CHANGES, 'Retry of identical snapshot makes no revision' );

$stale = pageForSource( revision: 102 );
checkSource( storeForSource( $stale )->save( new PageId( 943 ), 101, [ $first ] )->status === PageContentSavingStatus::ERROR && $stale->saveCalls === 0, 'Stale base is rejected before transformation/save' );
$race = pageForSource();
$race->beforeSave = static function ( WikiPage $page ): void {
	$page->revision = new RevisionRecord( 102, 943, [ 'main' => new JsonContent( 'Neue Benutzeränderung' ), 'sws' => $page->revision->getContent( 'sws' ) ] );
};
checkSource( storeForSource( $race )->save( new PageId( 943 ), 101, [ $first ] )->status === PageContentSavingStatus::ERROR, 'Late race returns PageUpdater conflict' );
checkSource( $race->revision->getContent( 'main' )->getText() === 'Neue Benutzeränderung' && $race->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject()->getSourceSnapshot() === null, 'Late concurrent edit is not overwritten' );
$authority->edit = false;
$denied = pageForSource();
checkSource( storeForSource( $denied )->save( new PageId( 943 ), 101, [ $first ] )->status === PageContentSavingStatus::ERROR && $denied->saveCalls === 0, 'Edit denial prevents internal write' );
$authority->edit = true; $authority->read = false;
checkSource( storeForSource( $denied )->save( new PageId( 943 ), 101, [ $first ] )->status === PageContentSavingStatus::ERROR, 'Read denial prevents internal write' );
$authority->read = true;
$suppressed = pageForSource(); $suppressed->revision->readable = false;
try { storeForSource( $suppressed )->save( new PageId( 943 ), 101, [ $first ] ); throw new RuntimeException( 'Suppressed revision accepted' ); }
catch ( RevisionAccessException ) { checkSource( $suppressed->saveCalls === 0, 'Suppressed slot does not become empty overwrite' ); }
$wrongContext = pageForSource( revision: 102 );
refusesSource( fn () => storeForSource( $wrongContext )->save( new PageId( 943 ), 102, [ $first ] ), 'Snapshot from wrong processing revision refused' );
checkSource( $wrongContext->saveCalls === 0, 'Wrong context leaves page unchanged' );
$deleted = pageForSource( SubjectContent::newEmpty() );
try { storeForSource( $deleted )->save( new PageId( 943 ), 101, [ $first ] ); throw new RuntimeException( 'Deleted entity restored' ); }
catch ( RuntimeException $e ) { checkSource( $e->getMessage() !== 'Deleted entity restored' && $deleted->saveCalls === 0, 'Source write never resurrects deleted entity' ); }
refusesSource( fn () => storeForSource( pageForSource() )->save( new PageId( 943 ), 101, [ $first, $first ] ), 'Duplicate entity snapshot rejected atomically' );
$foreign = pageForSource(); $foreign->revision->content['sws'] = new JsonContent( '{}' );
try { storeForSource( $foreign )->save( new PageId( 943 ), 101, [ $first ] ); throw new RuntimeException( 'Foreign slot accepted' ); }
catch ( RuntimeException $e ) { checkSource( $e->getMessage() !== 'Foreign slot accepted' && $foreign->saveCalls === 0, 'Foreign content model is not overwritten' ); }
$missingPageSaver = new PageContentSaver( new WikiPageFactory( null ), $authority );
checkSource( $missingPageSaver->updateContentAtRevision( new PageId( 943 ), 101, static fn () => throw new RuntimeException( 'Must not run' ), CommentStoreComment::newUnsavedComment( 'test' ) )->status === PageContentSavingStatus::ERROR, 'Missing page does not invoke callback' );

function repositoryForSource( WikiPage $page ): MediaWikiSubjectRepository {
	global $authority;
	return new MediaWikiSubjectRepository( new InMemoryPageIdentifiersLookup(), new RevisionLookup( $page ),
		new PageContentSaver( new WikiPageFactory( $page ), $authority ), new TitleFactory(), new class implements IContentHandlerFactory {},
		new DependentSubjectGuard( TestNaming::newDependentSubjectRules(), static fn () => new InMemorySubjectLookup() ) );
}
$normal = pageForSource( SubjectContent::newFromData( new PageSubjects( subjectSource( $first ), new SubjectMap() ) ), 102 );
$oldClient = subjectSource(); $oldClient->setFieldNotes( [ 'Geburtsort' => 'Neue eigene Notiz' ] );
$normalResult = repositoryForSource( $normal )->savePageSubjects( new PageSubjects( $oldClient, new SubjectMap() ), new PageId( 943 ) );
checkSource( $normalResult->status === PageContentSavingStatus::REVISION_CREATED, 'Ordinary repository edit still works' );
$afterNormal = $normal->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject();
checkSource( $afterNormal->getSourceSnapshot()->toJson() === $first->toJson() && $afterNormal->getFieldNotes() === [ 'Geburtsort' => 'Neue eigene Notiz' ], 'Old client keeps server snapshot while updating author note' );
$new = pageForSource(); unset( $new->revision->content['sws'] );
checkSource( repositoryForSource( $new )->savePageSubjects( new PageSubjects( subjectSource(), new SubjectMap() ), new PageId( 943 ) )->status === PageContentSavingStatus::REVISION_CREATED, 'First entity on page without SWS slot still works' );

$raw = pageForSource( SubjectContent::newFromData( new PageSubjects( subjectSource( $first ), new SubjectMap() ) ), 102 );
$rawRepository = new MediaWikiSubjectContentRepository( new WikiPageFactory( $raw ), $authority,
	new PageContentSaver( new WikiPageFactory( $raw ), $authority ), new RevisionLookup( $raw ) );
$ownJsonEdit = subjectSource(); $ownJsonEdit->setFieldNotes( [ 'Geburtsort' => 'JSON-Notiz' ] );
$rawRepository->editSubjectContent( SubjectContent::newFromData( new PageSubjects( $ownJsonEdit, new SubjectMap() ) ), new PageId( 943 ), 'Test' );
$rawAfter = $raw->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject();
checkSource( $rawAfter->getSourceSnapshot()->toJson() === $first->toJson() && $rawAfter->getFieldNotes() === [ 'Geburtsort' => 'JSON-Notiz' ], 'Actual raw editor path preserves omitted source data' );
refusesSource( fn () => $rawRepository->editSubjectContent( SubjectContent::newFromData( new PageSubjects( subjectSource( $recovered ), new SubjectMap() ) ), new PageId( 943 ), 'Forge' ), 'Raw repository refuses forged source values' );

$multi = pageForSource();
$other = new \ProfessionalWiki\SWS\Domain\Subject\Subject( new SubjectId( 's11111111111112' ), null,
	\ProfessionalWiki\SWS\Tests\Data\TestSchema::reference( 'Person' ), new \ProfessionalWiki\SWS\Domain\Subject\StatementList() );
$multi->revision->content['sws'] = SubjectContent::newFromData( new PageSubjects( subjectSource(), new SubjectMap( $other ) ) );
storeForSource( $multi )->save( new PageId( 943 ), 101, [ $first ] );
$multiAfter = $multi->revision->getContent( 'sws' )->getPageSubjects();
checkSource( $multiAfter->getMainSubject()->id->text === 's11111111111111' && $multiAfter->getOtherSubjects()->getSubject( $other->id )->getSourceSnapshot() === null, 'Other entity and main designation unchanged by single snapshot write' );
$atomic = pageForSource();
$nonexistentData = $first->toJson(); $nonexistentData['subjectId'] = 's11111111111112';
try {
	storeForSource( $atomic )->save( new PageId( 943 ), 101, [ $first, \ProfessionalWiki\SWS\Domain\Source\SourceSnapshot::fromJson( $nonexistentData ) ] );
	throw new RuntimeException( 'Partial batch written' );
} catch ( RuntimeException $e ) {
	checkSource( $e->getMessage() !== 'Partial batch written' && $atomic->saveCalls === 0 && $atomic->revision->getContent( 'sws' )->getPageSubjects()->getMainSubject()->getSourceSnapshot() === null, 'Invalid entity in batch prevents all slot changes' );
}
$twoLanguages = pageForSource( SubjectContent::newFromData( new PageSubjects( subjectSource( $english ), new SubjectMap() ) ), 103 );
$germanOnly = attemptSource( [ sourceField() ], revision: 103, at: '2026-11-11T18:03:00Z' );
refusesSource( fn () => storeForSource( $twoLanguages )->save( new PageId( 943 ), 103, [ $germanOnly ] ), 'Updating one language cannot silently remove another language' );
$foreignPage = pageForSource();
$foreignPageData = $first->toJson(); $foreignPageData['languages']['de']['pageId'] = 944;
refusesSource( fn () => storeForSource( $foreignPage )->save( new PageId( 943 ), 101, [ \ProfessionalWiki\SWS\Domain\Source\SourceSnapshot::fromJson( $foreignPageData ) ] ), 'Snapshot from another host page cannot be written' );

echo "$checks total checks passed, including controlled MediaWiki storage/CAS/edit boundaries (not a live database test).\n";
