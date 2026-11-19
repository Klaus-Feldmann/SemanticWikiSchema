<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Presentation;

use AEWI\SWS\Application\Queries\GetSubject\GetSubjectPresenter;
use AEWI\SWS\Application\Queries\GetSubject\GetSubjectResponse;
use AEWI\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;

class RestGetSubjectPresenter implements GetSubjectPresenter {

	private array $apiResponse = [];
	private readonly SubjectPresentationSerializer $subjectSerializer;

	public function __construct() {
		$this->subjectSerializer = new SubjectPresentationSerializer();
	}

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function presentSubject( GetSubjectResponse $response ): void {
		$this->apiResponse = [
			'requestedId' => $response->requestedId,
			'subjects' => $this->buildSubjectsMap( $response->subjects ),
		];
	}

	/**
	 * @param GetSubjectResponseItem[] $subjects
	 */
	private function buildSubjectsMap( array $subjects ): array {
		$map = [];

		foreach ( $subjects as $subject ) {
			$map[$subject->id] = $this->subjectSerializer->serialize( $subject );
		}

		return $map;
	}

	public function presentSubjectNotFound(): void {
		$this->apiResponse = [
			'subject' => null,
		];
	}

}
