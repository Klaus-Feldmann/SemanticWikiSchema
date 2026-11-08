<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests;

use ProfessionalWiki\SWS\Persistence\MediaWiki\DatabasePublishedSubjectIndex;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

trait ReadsPublishedSubjectIndex {

	/**
	 * @return array<string, array{schema: string, displayName: ?string, isMain: bool}> By Subject id, in id order
	 */
	private function subjectRowsOf( int $pageId ): array {
		$rows = [];

		foreach ( $this->selectSubjectRows( $pageId ) as $row ) {
			$rows[$row->swss_subject_id] = [
				'schema' => $row->swss_schema,
				'displayName' => $row->swss_display_name,
				'isMain' => (bool)$row->swss_is_main,
			];
		}

		return $rows;
	}

	/**
	 * @return array<string, ?string> By Subject id, in id order
	 */
	private function displayNamesOf( int $pageId ): array {
		return array_map(
			static fn ( array $row ): ?string => $row['displayName'],
			$this->subjectRowsOf( $pageId )
		);
	}

	/**
	 * @return array<string, bool> Whether each Subject's row marks it a Dependent Subject, by Subject id, in id order
	 */
	private function dependentFlagsOf( int $pageId ): array {
		$flags = [];

		foreach ( $this->selectSubjectRows( $pageId ) as $row ) {
			$flags[$row->swss_subject_id] = (bool)$row->swss_dependent;
		}

		return $flags;
	}

	/**
	 * @return array<string, string> By Subject id
	 */
	private function editTimesOf( int $pageId ): array {
		$editTimes = [];

		foreach ( $this->selectSubjectRows( $pageId ) as $row ) {
			$editTimes[$row->swss_subject_id] = (string)ConvertibleTimestamp::convert( TS_MW, $row->swss_edited );
		}

		return $editTimes;
	}

	private function selectSubjectRows( int $pageId ): IResultWrapper {
		return $this->getDb()->newSelectQueryBuilder()
			->select( '*' )
			->from( DatabasePublishedSubjectIndex::SUBJECT_TABLE )
			->where( [ 'swss_page_id' => $pageId ] )
			->orderBy( 'swss_subject_id' )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * @return list<array{0: string, 1: string, 2: string}> Subject, property and target, sorted
	 */
	private function relationsOf( int $pageId ): array {
		$relations = [];

		$result = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'swsr_subject_id', 'swsr_property', 'swsr_target_id' ] )
			->from( DatabasePublishedSubjectIndex::RELATION_TABLE )
			->where( [ 'swsr_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $result as $row ) {
			$relations[] = [ $row->swsr_subject_id, $row->swsr_property, $row->swsr_target_id ];
		}

		sort( $relations );

		return $relations;
	}

}
