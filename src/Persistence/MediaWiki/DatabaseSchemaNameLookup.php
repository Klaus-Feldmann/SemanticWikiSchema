<?php

namespace ProfessionalWiki\SWS\Persistence\MediaWiki;

use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\SWS\Application\PageReadAuthorizer;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Persistence\SchemaNameLookup;
use RuntimeException;
use SearchEngine;
use SearchSuggestion;
use SearchSuggestionSet;
use MediaWiki\Title\TitleValue;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

class DatabaseSchemaNameLookup implements SchemaNameLookup {

	public const int READABLE_NAMES_BATCH_SIZE = 100;

	public function __construct(
		private readonly IDatabase $db,
		private readonly SearchEngine $searchEngine,
		private readonly PageReadAuthorizer $readAuthorizer,
		private readonly TitleFactory $titleFactory,
	) {
	}

	/**
	 * @return TitleValue[]
	 */
	public function getSchemaNamesMatching( string $search, int $limit, int $offset = 0 ): array {
		if ( trim( $search ) === '' ) {
			return $this->filterReadable( $this->getFirstSchemaNames( $limit, $offset ) );
		}

        $db = $this->db;
        $pattern = new \Wikimedia\Rdbms\LikeValue( $db->anyString(), CaseInsensitiveKey::of($search), $db->anyString() );
        $rows = $db->newSelectQueryBuilder()->select(['page_id','page_title'])->distinct()->from('page')
            ->leftJoin(DatabaseIdentityTermIndex::TABLE,'term','term.swst_page_id = page_id AND term.swst_kind = ' . $db->addQuotes('schema'))
            ->where(['page_namespace'=>SWSExtension::NS_SCHEMA, 'page_is_redirect'=>0])
            ->andWhere($db->expr('term.swst_key',\Wikimedia\Rdbms\IExpression::LIKE,$pattern)
                ->or('page_title',\Wikimedia\Rdbms\IExpression::LIKE,new \Wikimedia\Rdbms\LikeValue($search,$db->anyString())))
            ->orderBy('page_id')->limit($limit * 4)->offset($offset)->caller(__METHOD__)->fetchResultSet();
        $titles=[];
        foreach ($rows as $row) { $titles[]=new TitleValue(SWSExtension::NS_SCHEMA,$row->page_title); }
        return array_slice($this->filterReadable($titles),0,$limit);
	}

	/**
	 * The search engine applies its own visibility rules inconsistently across engines, and the
	 * raw DB branch applies none, so both branches share this binding per-title read filter.
	 * Filtered names are simply absent, like Schemas that do not exist (#1046).
	 *
	 * @param TitleValue[] $titles
	 * @return TitleValue[]
	 */
	private function filterReadable( array $titles ): array {
		return array_values( array_filter(
			$titles,
			fn ( TitleValue $titleValue ): bool => $this->readAuthorizer->authorizeReadByPageTitle(
				$this->titleFactory->newFromLinkTarget( $titleValue )
			)
		) );
	}

	/**
	 * Keyset pagination over page_id: each batch seeks past the last seen page ID instead of
	 * re-walking the namespace, so a request costs the batches it consumes, not the whole
	 * namespace, and pages stay stable when Schemas are created or deleted between requests.
	 * Unreadable Schemas are never yielded, so a cursor built from the yielded keys pages over
	 * readable Schemas only (#1062).
	 *
	 * @return iterable<int, TitleValue> Readable Schema names keyed by page ID, in page-ID order.
	 */
	public function getReadableSchemaNames( int $afterPageId = 0 ): iterable {
		$lastPageId = $afterPageId;

		do {
			$res = $this->db->select(
				'page',
				[ 'page_id', 'page_title' ],
				[
					'page_namespace' => SWSExtension::NS_SCHEMA,
					'page_is_redirect' => 0,
					$this->db->expr( 'page_id', '>', $lastPageId ),
				],
				__METHOD__,
				[
					'ORDER BY' => 'page_id ASC',
					'LIMIT' => self::READABLE_NAMES_BATCH_SIZE,
				]
			);

			foreach ( $res as $row ) {
				$lastPageId = (int)$row->page_id;
				$title = new TitleValue( SWSExtension::NS_SCHEMA, $row->page_title );

				if ( $this->readAuthorizer->authorizeReadByPageTitle( $this->titleFactory->newFromLinkTarget( $title ) ) ) {
					yield $lastPageId => $title;
				}
			}
		} while ( $res->numRows() === self::READABLE_NAMES_BATCH_SIZE );
	}

	private function getSearchSuggestions( string $search, int $limit, int $offset ): SearchSuggestionSet {
		$this->searchEngine->setNamespaces( [ SWSExtension::NS_SCHEMA ] );
		$this->searchEngine->setLimitOffset( $limit, $offset );

		return $this->searchEngine->completionSearch( $search );
	}

	/**
	 * @return TitleValue[]
	 */
	private function searchSuggestionsToTitleArray( SearchSuggestionSet $suggestions ): array {
		return $suggestions->map( function ( SearchSuggestion $suggestion ) {
			$title = $suggestion->getSuggestedTitle();

			if ( $title === null ) {
				throw new RuntimeException( 'Title is null' );
			}

			return new TitleValue( $title->getNamespace(), $title->getText() );
		} );
	}

	/**
	 * @return TitleValue[]
	 */
	private function getFirstSchemaNames( int $limit, int $offset ): array {
		$res = $this->db->select(
			'page',
			[ 'page_title' ],
			[ 'page_namespace' => SWSExtension::NS_SCHEMA, 'page_is_redirect' => 0 ],
			__METHOD__,
			[
				'ORDER BY' => 'page_id ASC',
				'LIMIT' => $limit,
				'OFFSET' => $offset,
			]
		);

		return $this->dbResultToTitleValueArray( $res );
	}

	/**
	 * @return TitleValue[]
	 */
	private function dbResultToTitleValueArray( IResultWrapper $result ): array {
		$titles = [];

		foreach ( $result as $row ) {
			$titles[] = new TitleValue( SWSExtension::NS_SCHEMA, $row->page_title );
		}

		return $titles;
	}

}
