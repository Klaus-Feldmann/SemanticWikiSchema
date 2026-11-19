<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Application\NullRevisionPolicy;
use AEWI\SWS\Application\RevisionPolicyRegistry;
use AEWI\SWS\Tests\TestDoubles\FixedRevisionPolicy;
use Psr\Log\Test\TestLogger;

/**
 * @covers \AEWI\SWS\Application\RevisionPolicyRegistry
 */
class RevisionPolicyRegistryTest extends TestCase {

	public function testReturnsTheNullPolicyWhenNothingIsRegistered(): void {
		$this->assertInstanceOf( NullRevisionPolicy::class, ( new RevisionPolicyRegistry() )->getPolicy() );
	}

	public function testUsesTheRegisteredPolicy(): void {
		$policy = FixedRevisionPolicy::publishingNothing();
		$registry = new RevisionPolicyRegistry();

		$registry->setPolicy( $policy );

		$this->assertSame( $policy, $registry->getPolicy() );
	}

	public function testKeepsTheFirstPolicyWhenASecondIsRegistered(): void {
		$first = FixedRevisionPolicy::publishingNothing();
		$registry = new RevisionPolicyRegistry();

		$registry->setPolicy( $first );
		$registry->setPolicy( FixedRevisionPolicy::publishingNothing() );

		$this->assertSame( $first, $registry->getPolicy() );
	}

	public function testWarnsWhenASecondPolicyIsRegistered(): void {
		$logger = new TestLogger();
		$registry = new RevisionPolicyRegistry( $logger );

		$registry->setPolicy( FixedRevisionPolicy::publishingNothing() );
		$this->assertFalse( $logger->hasWarningRecords(), 'the first registration is not a warning' );

		$registry->setPolicy( FixedRevisionPolicy::publishingNothing() );

		$this->assertCount( 1, $logger->records );
	}

}
