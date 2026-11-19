<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\Page;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\Page\PagePropertyProviderContext;
use AEWI\SWS\Domain\Page\PagePropertyProviderRegistry;
use AEWI\SWS\Tests\TestDoubles\StubPagePropertyProvider;

/**
 * @covers \AEWI\SWS\Domain\Page\PagePropertyProviderRegistry
 */
class PagePropertyProviderRegistryTest extends TestCase {

	public function testNewRegistryHasNoProviders(): void {
		$registry = new PagePropertyProviderRegistry();

		$this->assertSame( [], $registry->getProviders() );
	}

	public function testAddedProviderCanBeRetrieved(): void {
		$registry = new PagePropertyProviderRegistry();
		$provider = new StubPagePropertyProvider( [ 'foo' => 'bar' ] );

		$registry->addProvider( $provider );

		$this->assertSame( [ $provider ], $registry->getProviders() );
	}

	public function testMultipleProvidersCanBeRetrieved(): void {
		$registry = new PagePropertyProviderRegistry();
		$provider1 = new StubPagePropertyProvider( [ 'foo' => 'bar' ] );
		$provider2 = new StubPagePropertyProvider( [ 'baz' => 42 ] );

		$registry->addProvider( $provider1 );
		$registry->addProvider( $provider2 );

		$this->assertSame( [ $provider1, $provider2 ], $registry->getProviders() );
	}

}
