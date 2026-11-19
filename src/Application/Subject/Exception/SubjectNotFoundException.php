<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Subject\Exception;

use AEWI\SWS\Domain\Subject\SubjectId;
use RuntimeException;

class SubjectNotFoundException extends RuntimeException {

	public static function forId( SubjectId $id ): self {
		return new self( 'Subject not found: ' . $id->text );
	}

}
