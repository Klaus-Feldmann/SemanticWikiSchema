<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\ResponseException;
use MediaWiki\Rest\SimpleHandler;
use AEWI\SWS\Application\SubjectSummaries\InvalidSubjectSummaryCursorException;
use AEWI\SWS\Application\SubjectSummaries\SortDirection;
use AEWI\SWS\Application\SubjectSummaries\SubjectSummaries;
use AEWI\SWS\Application\SubjectSummaries\SubjectSummaryCursor;
use AEWI\SWS\Application\SubjectSummaries\SubjectSummaryLookup;
use AEWI\SWS\Application\SubjectSummaries\SubjectSummaryQuery;
use AEWI\SWS\Application\SubjectSummaries\SubjectSummarySearchTimeoutException;
use AEWI\SWS\Application\SubjectSummaries\SubjectSummarySort;
use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Presentation\SubjectSummarySerializer;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class GetSubjectSummariesApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function __construct(
		private readonly SubjectSummaryLookup $lookup,
	) {
	}

	public function run(): Response {
		$params = $this->getValidatedParams();
		$sort = SubjectSummarySort::from( $params['sort'] );
		$direction = $sort === SubjectSummarySort::Newest ? SortDirection::Descending : SortDirection::from( $params['direction'] );

		$after = $this->cursorFrom( $params['cursor'], $sort, $direction );

		try {
			$schemaName = $params['schema'] === null ? null : new SchemaName( $params['schema'] );
		} catch ( InvalidArgumentException ) {
			// No Subject has a Schema that cannot exist, such as one named "Subject".
			return $this->listingResponse( new SubjectSummaries( [], null ) );
		}

		try {
			return $this->listingResponse( $this->lookup->getSubjectSummaries( new SubjectSummaryQuery(
				schemaName: $schemaName,
				search: trim( $params['search'] ),
				sort: $sort,
				direction: $direction,
				after: $after,
				limit: $params['limit'],
			) ) );
		} catch ( SubjectSummarySearchTimeoutException ) {
			return $this->getResponseFactory()->createHttpError( 503, [
				'errorType' => 'searchTimeout',
				'message' => wfMessage('sws-api-entity-search-timeout')->text(),
			] );
		}
	}

	private function listingResponse( SubjectSummaries $summaries ): Response {
		return $this->getResponseFactory()->createJson( [
			'subjects' => array_map(
				SubjectSummarySerializer::serialize( ... ),
				SWSExtension::getInstance()->newSubjectSummaryHostSubjects()->addTo( $summaries->summaries )
			),
			'nextCursor' => $summaries->nextCursor?->encode(),
		] );
	}

	private function cursorFrom( ?string $cursor, SubjectSummarySort $sort, SortDirection $direction ): ?SubjectSummaryCursor {
		if ( $cursor === null || $cursor === '' ) {
			return null;
		}

		try {
			return SubjectSummaryCursor::decode( $cursor, $sort, $direction );
		} catch ( InvalidSubjectSummaryCursorException ) {
			// The structured body matches the other handlers' createHttpError responses.
			throw new ResponseException(
				$this->getResponseFactory()->createHttpError( 400, [ 'message' => 'Invalid cursor' ] )
			);
		}
	}

	public function getParamSettings(): array {
		return [
			'schema' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => null,
				self::PARAM_DESCRIPTION => 'Only Entities of this Schema (case-sensitive). Omit it for every Schema.',
			],
			'search' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => '',
				self::PARAM_DESCRIPTION => 'Keeps Entities whose name or page title contains it, in any case, or whose ID starts with it.',
			],
			'sort' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => array_column( SubjectSummarySort::cases(), 'value' ),
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => SubjectSummarySort::Newest->value,
				self::PARAM_DESCRIPTION => 'Order of the list. "newest" is by creation, newest first, and ignores direction.',
			],
			'direction' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => array_column( SortDirection::cases(), 'value' ),
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => SortDirection::Ascending->value,
				self::PARAM_DESCRIPTION => 'Direction of the sort.',
			],
			'cursor' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => null,
				self::PARAM_DESCRIPTION => 'Opaque pagination cursor: the nextCursor of the previous response. Omit for the first page.',
			],
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
