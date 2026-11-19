<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Presentation;

use AEWI\SWS\Application\Queries\GetSubject\GetSubjectPresenter;
use AEWI\SWS\Application\Queries\GetSubject\GetSubjectResponse;
use AEWI\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;

/**
 * Keeps the Subject a {@see \AEWI\SWS\Application\Queries\GetSubject\GetSubjectQuery}
 * was asked for, for a caller that wants to name it and nothing more.
 */
class SubjectNamePresenter implements GetSubjectPresenter {

	private ?GetSubjectResponseItem $requestedSubject = null;

	public function presentSubject( GetSubjectResponse $response ): void {
		$this->requestedSubject = $response->subjects[$response->requestedId] ?? null;
	}

	public function presentSubjectNotFound(): void {
		$this->requestedSubject = null;
	}

	/**
	 * Null when the query served no Subject — because the wiki holds none with that id, or because it
	 * is on a page the caller may not read (#1046).
	 */
	public function getRequestedSubject(): ?GetSubjectResponseItem {
		return $this->requestedSubject;
	}

}
