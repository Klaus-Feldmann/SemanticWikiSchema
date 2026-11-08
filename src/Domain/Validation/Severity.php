<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Validation;

enum Severity: string {
	case Error = 'error';
	case Warning = 'warning';
}
