<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\SWS\Application\SubjectIdMinter;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Mints a batch of unused Subject IDs so an importer can wire relations between interlinked Subjects
 * before creating them. Reservations are confirmed with the request transaction; minting requires
 * write access and CSRF validation.
 */
class MintSubjectIdsApi extends SimpleHandler {

	private const int MIN_COUNT = 1;
	private const int MAX_COUNT = 1000;

	public function __construct(
		private readonly SubjectIdMinter $subjectIdMinter,
		private readonly ?\ProfessionalWiki\SWS\Presentation\CsrfValidator $csrfValidator = null,
	) {
	}

	public function run(): Response {
		if ( $this->csrfValidator === null ) { throw new \LogicException( 'Mint API requires CSRF validation' ); }
		$this->csrfValidator->verifyCsrfToken();
		if ( !$this->getAuthority()->isAllowed( 'edit' ) ) { return $this->getResponseFactory()->createHttpError( 403, [ 'message'=>'Edit permission required' ] ); }
		$count = $this->getValidatedBody()['count'];

		if ( $count < self::MIN_COUNT || $count > self::MAX_COUNT ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => 'count must be between ' . self::MIN_COUNT . ' and ' . self::MAX_COUNT . '.',
			] );
		}

		return $this->getResponseFactory()->createJson( [
			'subjectIds' => array_map(
				static fn ( SubjectId $id ): string => $id->text,
				$this->subjectIdMinter->mint( $count, $this->getValidatedBody()['requestKey'] ?? null )
			),
		] );
	}

	public function getBodyParamSettings(): array {
		return [
			'requestKey' => [ self::PARAM_SOURCE=>'body', ParamValidator::PARAM_TYPE=>'string', ParamValidator::PARAM_REQUIRED=>false ],
			'count' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Number of Subject IDs to mint, between '
					. self::MIN_COUNT . ' and ' . self::MAX_COUNT . ' inclusive.',
			],
		];
	}

	public function needsWriteAccess(): bool {
		return true;
	}

}
