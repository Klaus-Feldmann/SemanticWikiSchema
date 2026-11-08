<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\EditNotice;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\EditNotice\SubjectEditNoticeProviderRegistry;
use ProfessionalWiki\SWS\Tests\TestDoubles\StubSubjectEditNoticeProvider;

/**
 * @covers \ProfessionalWiki\SWS\Domain\EditNotice\SubjectEditNoticeProviderRegistry
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
