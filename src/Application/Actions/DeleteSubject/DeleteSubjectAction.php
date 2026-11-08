<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Actions\DeleteSubject;

use ProfessionalWiki\SWS\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\SWS\Application\SubjectRepository;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\SWS\Application\SubjectHostingPageResolver;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSavingStatus;

readonly class DeleteSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectHostingPageResolver $hostingPageResolver,
		private SubjectWriteAuthorizer $writeAuthorizer,
	) {
	}

	public function deleteSubject( SubjectId $subjectId, ?string $comment ): void {
		$pageId = $this->hostingPageResolver->resolveReadableHostingPage( $subjectId )?->getId();

		if ( $pageId === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		if ( !$this->writeAuthorizer->authorize( $pageId ) ) {
			throw new SubjectEditNotAuthorizedException( 'You do not have the necessary permissions to delete this subject' );
		}

		$status = $this->subjectRepository->deleteSubject( $subjectId, $comment );

		// Only a new revision means the Subject was removed. The index can name a page whose slot no
		// longer holds it, and the page can go away between the checks above and the write; either
		// way the Subject the caller named is not there, which is what an absent one answers.
		if ( $status->status !== PageContentSavingStatus::REVISION_CREATED ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}
	}

}
