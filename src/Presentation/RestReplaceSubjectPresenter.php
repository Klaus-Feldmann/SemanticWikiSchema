<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Presentation;

use ProfessionalWiki\SWS\Application\Actions\ReplaceSubject\ReplaceSubjectPresenter;
use ProfessionalWiki\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Validation\Violation;

class RestReplaceSubjectPresenter implements ReplaceSubjectPresenter {

	private array $apiResponse = [];
	private int $statusCode = 200;
	private readonly SubjectPresentationSerializer $subjectSerializer;
	private readonly SchemaPresentationSerializer $schemaSerializer;

	public function __construct() {
		$this->subjectSerializer = new SubjectPresentationSerializer();
		$this->schemaSerializer = new SchemaPresentationSerializer();
	}

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/**
	 * @param Violation[] $violations
	 */
	public function presentUpdated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations ): void {
		$this->apiResponse = [
			'status' => 'updated',
			'subjectId' => $subject->id,
			'violations' => ViolationSerializer::serializeMany( $violations ),
			'subject' => $this->subjectSerializer->serialize( $subject ),
		];

		if ( $schema !== null ) {
			$this->apiResponse['schema'] = $this->schemaSerializer->toArray( $schema );
		}

		$this->statusCode = 200;
	}

	/**
	 * @param Violation[] $violations
	 */
	public function presentValidationFailed( array $violations ): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => 'Validation failed',
			'violations' => ViolationSerializer::serializeMany( $violations ),
		];
		$this->statusCode = 422;
	}

	public function presentSaveFailed( ?string $errorMessage ): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => 'The Subject could not be saved' . ( $errorMessage === null ? '' : ": $errorMessage" ),
		];
		$this->statusCode = 500;
	}

}
