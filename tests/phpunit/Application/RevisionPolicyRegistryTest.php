<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\NullRevisionPolicy;
use ProfessionalWiki\SWS\Application\RevisionPolicyRegistry;
use ProfessionalWiki\SWS\Tests\TestDoubles\FixedRevisionPolicy;
use Psr\Log\Test\TestLogger;

/**
 * @covers \ProfessionalWiki\SWS\Application\RevisionPolicyRegistry
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
