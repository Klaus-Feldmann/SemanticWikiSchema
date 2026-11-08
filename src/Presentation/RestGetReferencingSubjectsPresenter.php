<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Presentation;

use ProfessionalWiki\SWS\Application\Queries\GetReferencingSubjects\GetReferencingSubjectsPresenter;
use ProfessionalWiki\SWS\Application\Queries\GetReferencingSubjects\GetReferencingSubjectsResponse;
use ProfessionalWiki\SWS\Application\Queries\GetReferencingSubjects\ReferencingSubject;

class RestGetReferencingSubjectsPresenter implements GetReferencingSubjectsPresenter {

	private array $apiResponse = [];
	private readonly SubjectPresentationSerializer $subjectSerializer;

	public function __construct() {
		$this->subjectSerializer = new SubjectPresentationSerializer();
	}

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function presentReferencingSubjects( GetReferencingSubjectsResponse $response ): void {
		$this->apiResponse = [
			'referencingSubjects' => array_map(
				fn ( ReferencingSubject $referencing ): array => [
					'subject' => $this->subjectSerializer->serialize( $referencing->subject ),
					'propertyNames' => $referencing->propertyNames,
				],
				$response->referencingSubjects
			),
			'truncated' => $response->truncated,
		];
	}

}
