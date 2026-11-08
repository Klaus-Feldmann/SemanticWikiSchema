<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki\Subject;

use ProfessionalWiki\SWS\Application\PageIdentifiersLookup;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectIdList;

/**
 * Which of the given Subjects each page hosts. Subjects hosted by no page are absent, so a page
 * that appears has at least one, and lookups reading Subjects out of page content can read each
 * page once rather than once per Subject on it.
 */
readonly class SubjectIdsByHostingPage {

	public function __construct(
		private PageIdentifiersLookup $pageIdentifiersLookup,
	) {
	}

	/**
	 * @return array<int, SubjectIdList> Keyed by page id
	 */
	public function group( SubjectIdList $subjectIds ): array {
		$idsByText = $subjectIds->asArray();
		$idsByPageId = [];

		foreach ( $this->pageIdentifiersLookup->getPageIdsOfSubjects( $subjectIds ) as $idText => $pageIdentifiers ) {
			if ( array_key_exists( $idText, $idsByText ) ) {
				$idsByPageId[$pageIdentifiers->getId()->id][] = $idsByText[$idText];
			}
		}

		return array_map(
			/** @param SubjectId[] $ids */
			static fn ( array $ids ): SubjectIdList => new SubjectIdList( $ids ),
			$idsByPageId
		);
	}

}
