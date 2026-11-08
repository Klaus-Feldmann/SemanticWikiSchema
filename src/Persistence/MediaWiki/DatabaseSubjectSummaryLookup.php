<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\SWS\Application\PageReadAuthorizer;
use ProfessionalWiki\SWS\Application\SubjectSummaries\SortDirection;
use ProfessionalWiki\SWS\Application\SubjectSummaries\SubjectSummaries;
use ProfessionalWiki\SWS\Application\SubjectSummaries\SubjectSummary;
use ProfessionalWiki\SWS\Application\SubjectSummaries\SubjectSummaryCursor;
use ProfessionalWiki\SWS\Application\SubjectSummaries\SubjectSummaryLookup;
use ProfessionalWiki\SWS\Application\SubjectSummaries\SubjectSummaryQuery;
use ProfessionalWiki\SWS\Application\SubjectSummaries\SubjectSummarySearchTimeoutException;
use ProfessionalWiki\SWS\Application\SubjectSummaries\SubjectSummarySort;
use stdClass;
use Wikimedia\Rdbms\DBQueryTimeoutError;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Rows on pages the caller may not read are dropped as they are reached, and the request reads on past them, up to
 * the scan bound. Reaching the bound ends the listing: the rows found so far come without a next cursor.
 *
 * Ties break by Subject id in the direction of the sort, so that every order with an index walks it from the cursor
 * on: all but the page and edit time orders, which sort every row they match.
 */
class DatabaseSubjectSummaryLookup implements SubjectSummaryLookup {

	private const string ALIAS = 'subject';
	private const string SUBJECT_ID = self::ALIAS . '.swss_subject_id';
	private const string NAME_KEY = self::ALIAS . '.swss_display_name_key';

	private const int OVERFETCH_FACTOR = 4;

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly PageReadAuthorizer $readAuthorizer,
		private readonly TitleFactory $titleFactory,
		/** The most rows one request reads looking for readable ones. */
		private readonly int $scanBound,
		/** Milliseconds a search may run on MySQL and MariaDB, 0 for no limit. Other databases do not limit it. */
		private readonly int $searchTimeLimit,
	) {
	}

	public function getSubjectSummaries( SubjectSummaryQuery $query ): SubjectSummaries {
		$summaries = [];
		$lastServed = null;
		$position = $query->after;
		$scanned = 0;
		$requested = min( ( $query->limit + 1 ) * self::OVERFETCH_FACTOR, $this->scanBound );
		$readablePages = [];

		// A second fetch, if any, reads the rest of the bound, so a request makes at most two.
		while ( $requested > 0 ) {
			$rows = $this->fetchRows( $query, $position, $requested );

			foreach ( $rows as $row ) {
				$scanned++;
				$position = $row['cursor'];

				if ( !$this->mayRead( $row['title'], $readablePages ) ) {
					continue;
				}

				// A readable row past a full page is what says there is a next page.
				if ( count( $summaries ) === $query->limit ) {
					return new SubjectSummaries( $summaries, $lastServed );
				}

				$summaries[] = $row['summary'];
				$lastServed = $row['cursor'];
			}

			if ( count( $rows ) < $requested ) {
				break;
			}

			$requested = $this->scanBound - $scanned;
		}

		return new SubjectSummaries( $summaries, null );
	}

	/**
	 * @param array<int, bool> $readablePages Page id → readable, filled as pages are reached.
	 */
	private function mayRead( Title $page, array &$readablePages ): bool {
		$readablePages[$page->getId()] ??= $this->readAuthorizer->authorizeReadByPageTitle( $page );

		return $readablePages[$page->getId()];
	}

	/**
	 * @return list<array{summary: SubjectSummary, title: Title, cursor: SubjectSummaryCursor}>
	 * @throws SubjectSummarySearchTimeoutException
	 */
	private function fetchRows( SubjectSummaryQuery $query, ?SubjectSummaryCursor $after, int $limit ): array {
		$db = $this->connectionProvider->getReplicaDatabase();

		try {
			return $query->sort === SubjectSummarySort::Name
				? $this->fetchByName( $db, $query, $after, $limit )
				: $this->fetchRun( $db, $query, $this->sortColumn( $query->sort ), [], $after, $limit );
		} catch ( DBQueryTimeoutError $timeout ) {
			if ( $query->search === '' ) {
				throw $timeout;
			}

			throw new SubjectSummarySearchTimeoutException( previous: $timeout );
		}
	}

	private function sortColumn( SubjectSummarySort $sort ): ?string {
		return match ( $sort ) {
			SubjectSummarySort::Newest => null,
			SubjectSummarySort::Name => self::NAME_KEY,
			SubjectSummarySort::Schema => self::ALIAS . '.swss_schema',
			SubjectSummarySort::Page => self::ALIAS . '.swss_page_key',
			SubjectSummarySort::Edited => self::ALIAS . '.swss_edited',
		};
	}

	/**
	 * Generated names come after chosen ones whichever way the names run, so the two are read as two runs, each in
	 * the order of the name index.
	 *
	 * @return list<array{summary: SubjectSummary, title: Title, cursor: SubjectSummaryCursor}>
	 */
	private function fetchByName(
		IReadableDatabase $db,
		SubjectSummaryQuery $query,
		?SubjectSummaryCursor $after,
		int $limit
	): array {
		$afterAGeneratedName = $after !== null && $after->sortValue === null;

		$rows = $afterAGeneratedName
			? []
			: $this->fetchRun( $db, $query, self::NAME_KEY, [ $db->expr( self::NAME_KEY, '!=', null ) ], $after, $limit );

		if ( count( $rows ) === $limit ) {
			return $rows;
		}

		return array_merge(
			$rows,
			$this->fetchRun(
				$db,
				$query,
				null,
				[ $db->expr( self::NAME_KEY, '=', null ) ],
				$afterAGeneratedName ? $after : null,
				$limit - count( $rows )
			)
		);
	}

	/**
	 * @param ?string $sortColumn Null to order by Subject id alone
	 * @param IExpression[] $runConditions
	 * @return list<array{summary: SubjectSummary, title: Title, cursor: SubjectSummaryCursor}>
	 */
	private function fetchRun(
		IReadableDatabase $db,
		SubjectSummaryQuery $query,
		?string $sortColumn,
		array $runConditions,
		?SubjectSummaryCursor $after,
		int $limit
	): array {
		$direction = $query->direction === SortDirection::Ascending
			? SelectQueryBuilder::SORT_ASC
			: SelectQueryBuilder::SORT_DESC;
		$orderColumns = $sortColumn === null ? [ self::SUBJECT_ID ] : [ $sortColumn, self::SUBJECT_ID ];

		$builder = $db->newSelectQueryBuilder()
			->select( [
				'swss_subject_id', 'swss_schema', 'swss_display_name', 'swss_display_name_key', 'swss_page_key', 'swss_edited',
				'page_id', 'page_namespace', 'page_title',
			] )
			->from( DatabasePublishedSubjectIndex::SUBJECT_TABLE, self::ALIAS )
			->join( 'page', null, 'page_id = ' . self::ALIAS . '.swss_page_id' )
			->where( $runConditions )
			->orderBy( $orderColumns, $direction )
			->limit( $limit )
			// Without it, MariaDB reads all of `page` and sorts every row once Subjects outnumber pages a few
			// times over, instead of walking the sort's index and stopping at the limit.
			->straightJoinOption()
			->caller( __METHOD__ );

		if ( $query->schemaName === null ) {
			$builder->andWhere( [ self::ALIAS . '.swss_dependent' => 0 ] );
		} else {
			$builder->andWhere( [ self::ALIAS . '.swss_schema' => $query->schemaName->getText() ] );
		}

		if ( $query->search !== '' ) {
			$builder->andWhere( $this->searchCondition( $db, $query->search ) );
			$builder->setMaxExecutionTime( $this->searchTimeLimit );
		}

		if ( $after !== null ) {
			$position = $sortColumn === null ? [] : [ $sortColumn => $this->sortColumnValue( $db, $after ) ];
			$position[self::SUBJECT_ID] = $after->subjectId;

			$builder->andWhere(
				$db->buildComparison( $direction === SelectQueryBuilder::SORT_ASC ? '>' : '<', $position )
			);
		}

		// Last: MariaDB tests the conditions in the order they are added, and this one is a lookup per row.
		$builder->andWhere( LowestPageRows::condition( $db, self::ALIAS ) );

		$rows = [];

		/** @var stdClass $row */
		foreach ( $builder->fetchResultSet() as $row ) {
			$rows[] = $this->toRow( $row, $query );
		}

		return $rows;
	}

	private function searchCondition( IReadableDatabase $db, string $search ): IExpression {
		$containingKey = new LikeValue( $db->anyString(), CaseInsensitiveKey::of( $search ), $db->anyString() );

		return $db->expr( self::NAME_KEY, IExpression::LIKE, $containingKey )
			->or( self::ALIAS . '.swss_page_key', IExpression::LIKE, $containingKey )
			->or( self::SUBJECT_ID, IExpression::LIKE, new LikeValue( $search, $db->anyString() ) );
	}

	private function sortColumnValue( IReadableDatabase $db, SubjectSummaryCursor $after ): string|int|null {
		return $after->sort === SubjectSummarySort::Edited
			? $db->timestamp( (int)$after->sortValue )
			: $after->sortValue;
	}

	/**
	 * @return array{summary: SubjectSummary, title: Title, cursor: SubjectSummaryCursor}
	 */
	private function toRow( stdClass $row, SubjectSummaryQuery $query ): array {
		$id = (string)$row->swss_subject_id;
		$schemaName = (string)$row->swss_schema;
		$name = $row->swss_display_name === null ? null : (string)$row->swss_display_name;
		$title = $this->titleFactory->newFromRow( $row );

		return [
			'summary' => new SubjectSummary(
				subjectId: $id,
				displayName: $name ?? $schemaName,
				displayNameIsGenerated: $name === null,
				schemaName: $schemaName,
				pageId: $title->getId(),
				pageTitle: $title->getPrefixedText(),
				lastEdited: (string)ConvertibleTimestamp::convert( TS_ISO_8601, $row->swss_edited ),
				hostSubject: null,
			),
			'title' => $title,
			'cursor' => new SubjectSummaryCursor( $query->sort, $query->direction, $this->sortValueOf( $row, $query->sort ), $id ),
		];
	}

	private function sortValueOf( stdClass $row, SubjectSummarySort $sort ): string|int|null {
		return match ( $sort ) {
			SubjectSummarySort::Newest => null,
			SubjectSummarySort::Name => $row->swss_display_name_key === null ? null : (string)$row->swss_display_name_key,
			SubjectSummarySort::Schema => (string)$row->swss_schema,
			SubjectSummarySort::Page => (string)$row->swss_page_key,
			SubjectSummarySort::Edited => (int)ConvertibleTimestamp::convert( TS_UNIX, $row->swss_edited ),
		};
	}

}
