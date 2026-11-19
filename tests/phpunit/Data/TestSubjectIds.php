<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Data;

use AEWI\SWS\Domain\Subject\SubjectIdParser;

class TestSubjectIds {

	public const string LOCAL_SOURCE_KEY = 'localwiki';

	public const string OTHER_SOURCE_KEY = 'otherwiki';

	public static function newParser(): SubjectIdParser {
		return new SubjectIdParser( self::LOCAL_SOURCE_KEY );
	}

}
