<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Domain\Validation;

enum Severity: string {
	case Error = 'error';
	case Warning = 'warning';
}
