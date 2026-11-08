<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Subject\Exception;

use RuntimeException;

class SubjectEditNotAuthorizedException extends RuntimeException {

	public function __construct( string $message = 'You do not have the necessary permissions to edit this subject' ) {
		parent::__construct( $message );
	}

}
