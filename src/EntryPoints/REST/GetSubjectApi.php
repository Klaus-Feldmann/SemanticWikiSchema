<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\SWS\Application\Queries\GetSubject\GetSubjectQuery;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Presentation\RestGetSubjectPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetSubjectApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	private const string EXPAND_PAGE = 'page';
	private const string EXPAND_RELATIONS = 'relations';

	public function run( string $subjectId ): Response {
		// Validate the ID shape first, so a malformed value is a clean 400 rather than becoming a 500.
		$historical = ( $this->getValidatedParams()['revisionId'] ?? null ) !== null;
        $parsedId = SWSExtension::getInstance()->getSubjectIdParser( !$historical )->parse( $subjectId );

		if ( $parsedId === null ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'Invalid Subject ID: ' . $subjectId,
			] );
		}

		$subjectId = $parsedId->text;
        $presenter = new RestGetSubjectPresenter();
		$revisionId = $this->getValidatedParams()['revisionId'] ?? null;
		$latest = $this->getValidatedParams()['latest'] ?? false;
		$expandOptions = explode( '|', $this->getRequest()->getQueryParams()['expand'] ?? '' );
		$includeReferencedSubjects = in_array( self::EXPAND_RELATIONS, $expandOptions );

		if ( $latest && ( $revisionId !== null || $includeReferencedSubjects ) ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'The latest parameter cannot be combined with revisionId or with expand=relations.',
			] );
		}

		$query = $this->newGetSubjectQuery( $presenter, $parsedId, $revisionId, $latest );

		if ( $query instanceof Response ) {
			return $query;
		}

		$query->execute(
			subjectId: $subjectId,
			includePageIdentifiers: in_array( self::EXPAND_PAGE, $expandOptions ),
			includeReferencedSubjects: $includeReferencedSubjects
		);

		return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
	}

	private function newGetSubjectQuery(
		RestGetSubjectPresenter $presenter,
		SubjectId $subjectId,
		?int $revisionId,
		bool $latest
	): GetSubjectQuery|Response {
		if ( $revisionId !== null ) {
			return $this->newQueryForRevisionId( $presenter, $revisionId );
		}

		if ( $latest ) {
			return $this->newQueryForLatestRevision( $presenter, $subjectId );
		}

		return SWSExtension::getInstance()->newGetSubjectQuery( $presenter, $this->getAuthority() );
	}

	private function newQueryForRevisionId( RestGetSubjectPresenter $presenter, int $revisionId ): GetSubjectQuery|Response {
		$revision = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionById( $revisionId );

		// A revision the viewer may not see answers exactly like a nonexistent one: revision ids are
		// sequential, so any distinguishable answer is a sweepable existence oracle over restricted
		// pages (#1046), and over the unapproved revisions an approval extension hides.
		if ( $revision === null || !$this->revisionIsReadable( $revision ) ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'message' => 'Revision not found: ' . $revisionId,
			] );
		}

		return SWSExtension::getInstance()->newGetSubjectQueryForRevision( $presenter, $revision, $this->getAuthority() );
	}

	private function revisionIsReadable( RevisionRecord $revision ): bool {
		return $this->revisionPageIsReadable( $revision->getPageId() )
			&& $this->revisionPolicyPermits( $revision );
	}

	private function revisionPolicyPermits( RevisionRecord $revision ): bool {
		return SWSExtension::getInstance()->getRevisionPolicy()
			->revisionIsReadableBy( $revision, $this->getAuthority() );
	}

	private function revisionPageIsReadable( int $pageId ): bool {
		return SWSExtension::getInstance()
			->newPageReadAuthorizer( $this->getAuthority() )
			->authorizeReadByPageId( new PageId( $pageId ) );
	}

	/**
	 * A viewer who may not see the latest revision is answered as an absent Subject is, so that
	 * asking for a draft cannot confirm a harvested Subject id exists. The read is the revision-keyed
	 * one at the revision the gate cleared: with relation expansion refused it reads no other page.
	 */
	private function newQueryForLatestRevision( RestGetSubjectPresenter $presenter, SubjectId $subjectId ): GetSubjectQuery|Response {
		$revision = $this->getLatestRevisionOfReadableSubjectPage( $subjectId );

		if ( $revision === null || !$this->revisionPolicyPermits( $revision ) ) {
			$presenter->presentSubjectNotFound();

			return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
		}

		return SWSExtension::getInstance()->newGetSubjectQueryForRevision( $presenter, $revision, $this->getAuthority() );
	}

	private function getLatestRevisionOfReadableSubjectPage( SubjectId $subjectId ): ?RevisionRecord {
		$pageIdentifiers = SWSExtension::getInstance()
			->newSubjectHostingPageResolver( $this->getAuthority() )
			->resolveReadableHostingPage( $subjectId );

		if ( $pageIdentifiers === null ) {
			return null;
		}

		return MediaWikiServices::getInstance()->getRevisionLookup()
			->getRevisionByPageId( $pageIdentifiers->getId()->id );
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Subject: E followed by its positive number, an existing legacy s-ID, or sourceKey:localId for another Source.',
			],
			'revisionId' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Revision ID to fetch the Subject at. Defaults to the revision the wiki publishes.',
			],
			'latest' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
				self::PARAM_DESCRIPTION => 'Return the hosting page\'s current revision, for editing, rather than the revision the wiki publishes. Requires the viewer to be allowed to see that revision. Cannot be combined with revisionId or with expand=relations.',
			],
			'expand' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [
					self::EXPAND_PAGE,
					self::EXPAND_RELATIONS,
				],
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Embed related data in the response. Accepted values: "page", "relations".',
			],
		];
	}

}
