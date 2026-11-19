<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\Subject\SubjectId;
use AEWI\SWS\Domain\Subject\SubjectIdList;

/**
 * @covers \AEWI\SWS\Domain\Subject\SubjectIdList
 */
class SubjectIdListTest extends TestCase {

	public function testAsStringArray(): void {
		$ids = new SubjectIdList( [
			new SubjectId( 's1111111111a123' ),
			new SubjectId( 's1111111111a456' ),
			new SubjectId( 's1111111111a123' ),
		] );

		$this->assertSame(
			[
				's1111111111a123',
				's1111111111a456',
			],
			$ids->asStringArray()
		);
	}

}
