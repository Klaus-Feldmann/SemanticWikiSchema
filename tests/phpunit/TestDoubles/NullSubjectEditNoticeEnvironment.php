<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Application\EditNotice\SubjectEditNoticeEnvironment;
use AEWI\SWS\Domain\EditNotice\SubjectEditNoticeContext;

class NullSubjectEditNoticeEnvironment implements SubjectEditNoticeEnvironment {

	public ?SubjectEditNoticeContext $preparedFor = null;

	public bool $restored = false;

	public function prepareFor( SubjectEditNoticeContext $context ): void {
		$this->preparedFor = $context;
	}

	public function restore(): void {
		$this->restored = true;
	}

}
