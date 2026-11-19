<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use AEWI\SWS\Application\SubjectSummaries\RecentlyEditedSubjectLookup;
use AEWI\SWS\Presentation\SubjectSummarySerializer;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class GetRecentlyEditedSubjectsApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function __construct(
		private readonly RecentlyEditedSubjectLookup $lookup,
	) {
	}

	public function run(): Response {
		return $this->getResponseFactory()->createJson( [
			'subjects' => array_map(
				SubjectSummarySerializer::serialize( ... ),
				$this->lookup->getRecentlyEditedSubjects( $this->getValidatedParams()['limit'] )
			),
		] );
	}

	public function getParamSettings(): array {
		return [
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 10,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 50,
				self::PARAM_DESCRIPTION => 'Maximum number of Entities to return.',
			],
		];
	}

}
