<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application\Source;

use Psr\Log\LogLevel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use AEWI\SWS\Application\Source\SchemaResolver;
use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\Domain\Schema\SchemaReference;
use AEWI\SWS\Domain\Source\SourceRegistry;
use AEWI\SWS\Tests\Data\TestSchema;
use AEWI\SWS\Tests\Data\TestSubjectIds;
use AEWI\SWS\Tests\TestDoubles\InMemorySource;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * @covers \AEWI\SWS\Application\Source\SchemaResolver
 */
class SchemaResolverTest extends TestCase {

	public function testResolvesABareReferenceThroughTheLocalSource(): void {
		$schema = TestSchema::build( name: new SchemaName( 'Company' ) );

		$this->assertSame(
			$schema,
			$this->newResolver( localSchemas: [ $schema ] )
				->getSchema( SchemaReference::local( new SchemaName( 'Company' ) ) )
		);
	}

	public function testResolvesAQualifiedReferenceThroughItsOwnSource(): void {
		$schema = TestSchema::build( name: new SchemaName( 'Company' ) );

		$this->assertSame(
			$schema,
			$this->newResolver( otherSchemas: [ $schema ] )
				->getSchema( SchemaReference::sourced( TestSubjectIds::OTHER_SOURCE_KEY, new SchemaName( 'Company' ) ) )
		);
	}

	public function testAReferenceIsNotResolvedThroughTheWrongSource(): void {
		$this->assertNull(
			$this->newResolver( otherSchemas: [ TestSchema::build( name: new SchemaName( 'Company' ) ) ] )
				->getSchema( SchemaReference::local( new SchemaName( 'Company' ) ) )
		);
	}

	public function testUnknownSourceResolvesToNoSchema(): void {
		$this->assertNull(
			$this->newResolver()->getSchema( SchemaReference::sourced( 'neverinstalled', new SchemaName( 'Company' ) ) )
		);
	}

	public function testUnknownSourceIsLoggedAsAWarning(): void {
		$logger = new LegacyLoggerSpy();

		$this->newResolver( logger: $logger )
			->getSchema( SchemaReference::sourced( 'neverinstalled', new SchemaName( 'Company' ) ) );

		$this->assertCount( 1, $logger->getLogCalls()->getMessages() );
		$this->assertSame( LogLevel::WARNING, $logger->getFirstLogCall()->getLevel() );
		$this->assertStringContainsString(
			'neverinstalled:Company',
			$logger->getLogCalls()->getMessages()[0]
		);
	}

	/**
	 * @param \AEWI\SWS\Domain\Schema\Schema[] $localSchemas
	 * @param \AEWI\SWS\Domain\Schema\Schema[] $otherSchemas
	 */
	private function newResolver(
		array $localSchemas = [],
		array $otherSchemas = [],
		?LoggerInterface $logger = null
	): SchemaResolver {
		$registry = new SourceRegistry( TestSubjectIds::LOCAL_SOURCE_KEY );
		$registry->registerSource( TestSubjectIds::LOCAL_SOURCE_KEY, $this->newSource( $localSchemas ) );
		$registry->registerSource( TestSubjectIds::OTHER_SOURCE_KEY, $this->newSource( $otherSchemas ) );

		return new SchemaResolver( $registry, $logger ?? new NullLogger() );
	}

	/**
	 * @param \AEWI\SWS\Domain\Schema\Schema[] $schemas
	 */
	private function newSource( array $schemas ): InMemorySource {
		$source = new InMemorySource();

		foreach ( $schemas as $schema ) {
			$source->addSchema( $schema );
		}

		return $source;
	}

}
