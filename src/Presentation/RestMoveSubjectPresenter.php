<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Presentation;

use AEWI\SWS\Domain\Validation\Violation;
use AEWI\SWS\Application\Actions\MoveSubject\MoveSubjectPresenter;

class RestMoveSubjectPresenter implements MoveSubjectPresenter {

	private array $apiResponse = [ 'status' => 'unchanged' ];
	private int $statusCode = 200;

	public function getJsonArray(): array {
		return $this->apiResponse;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	public function presentMoved(): void {
		$this->apiResponse = [ 'status' => 'changed' ];
		$this->statusCode = 200;
	}

	public function presentNoChange(): void {
		$this->apiResponse = [ 'status' => 'unchanged' ];
		$this->statusCode = 200;
	}

	public function presentSubjectNotFound(): void {
		$this->apiResponse = [ 'status' => 'error', 'message' => wfMessage('sws-api-entity-not-found')->text() ];
		$this->statusCode = 404;
	}

	public function presentTargetPageNotFound(): void {
		$this->apiResponse = [ 'status' => 'error', 'message' => 'Target page not found' ];
		$this->statusCode = 404;
	}

	public function presentSubjectAlreadyOnTargetPage(): void {
		$this->apiResponse = [ 'status' => 'error', 'message' => wfMessage('sws-api-entity-already-on-target')->text() ];
		$this->statusCode = 409;
	}

	public function presentMoveIncomplete(): void {
		$this->apiResponse = [
			'status' => 'error',
			'message' => wfMessage('sws-api-entity-move-incomplete')->text(),
		];
		$this->statusCode = 500;
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

}
