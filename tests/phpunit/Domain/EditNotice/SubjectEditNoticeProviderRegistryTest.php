<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\EditNotice;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\EditNotice\SubjectEditNoticeProviderRegistry;
use AEWI\SWS\Tests\TestDoubles\StubSubjectEditNoticeProvider;

/**
 * @covers \AEWI\SWS\Domain\EditNotice\SubjectEditNoticeProviderRegistry
 */
class SubjectEditNoticeProviderRegistryTest extends TestCase {

	public function testNewRegistryHasNoProviders(): void {
		$registry = new SubjectEditNoticeProviderRegistry();

		$this->assertSame( [], $registry->getProviders() );
	}

	public function testAddedProviderCanBeRetrieved(): void {
		$registry = new SubjectEditNoticeProviderRegistry();
		$provider = new StubSubjectEditNoticeProvider( [] );

		$registry->addProvider( $provider );

		$this->assertSame( [ $provider ], $registry->getProviders() );
	}

	public function testProvidersAreRetrievedInRegistrationOrder(): void {
		$registry = new SubjectEditNoticeProviderRegistry();
		$first = new StubSubjectEditNoticeProvider( [] );
		$second = new StubSubjectEditNoticeProvider( [] );
		$third = new StubSubjectEditNoticeProvider( [] );

		$registry->addProvider( $first );
		$registry->addProvider( $second );
		$registry->addProvider( $third );

		$this->assertSame( [ $first, $second, $third ], $registry->getProviders() );
	}

}
