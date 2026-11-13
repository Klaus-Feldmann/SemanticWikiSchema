<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Source;

use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\EntryPoints\Processing\{ ProcessingContext, SourceWriteScope };
use ProfessionalWiki\SWS\SWSExtension;
use RuntimeException;
use Wikimedia\Rdbms\IDBAccessObject;

/** One authorized published snapshot per page and request; includes the worker's revision overlay. */
final class PublishedSubjectsLookup {
	private array $pages = [];
	public function __construct( private readonly IContextSource $context ) {}
	public function page( Title $title ): PageSubjects {
		$authority = $this->context->getAuthority();
		if ( !$authority->authorizeRead( 'read', $title ) ) { throw new RuntimeException( 'SWS page unavailable' ); }
		$processing = ProcessingContext::current();
		if ( $processing !== null && $processing->useStoredSources && $processing->title->isSamePageAs( $title ) && $processing->subjects !== null ) {
			return $processing->subjects;
		}
		if ( ( $overlay = SourceWriteScope::subjectsFor( $title ) ) !== null ) { return $overlay; }
		$key = $title->getArticleID();
		if ( isset( $this->pages[$key] ) ) { return $this->pages[$key]; }
		$extension = SWSExtension::getInstance();
		$revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );
		if ( $revision === null ) { throw new RuntimeException( 'SWS revision unavailable' ); }
		$policy = $extension->getRevisionPolicy();
		$published = $policy->publishedRevision( $revision );
		if ( $published === null || $published->getPageId() !== $key ||
			!$policy->revisionIsReadableBy( $published, $authority ) || !$published->userCan( RevisionRecord::DELETED_TEXT, $authority ) ) {
			throw new RuntimeException( 'SWS published revision unavailable' );
		}
		if ( !$published->hasSlot( 'sws' ) ) { return $this->pages[$key] = PageSubjects::newEmpty(); }
		$content = $extension->newSubjectContentRepository( $authority )->getSubjectContentByRevisionId( $published->getId() );
		if ( $content === null ) { throw new RuntimeException( 'SWS slot unavailable' ); }
		return $this->pages[$key] = $content->getPageSubjects();
	}

}
