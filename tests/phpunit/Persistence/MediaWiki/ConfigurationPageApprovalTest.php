<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Persistence\MediaWiki;

use AEWI\SWS\Domain\Mapping\MappingName;
use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Tests\SWSIntegrationTestCase;
use AEWI\SWS\Tests\TestDoubles\FixedRevisionPolicy;

/**
 * Needs the database for the page revisions. Registering a policy rebuilds the extension singleton but
 * keeps MediaWiki's shared object cache, as a new request would.
 *
 * @covers \AEWI\SWS\Persistence\MediaWiki\PublishedPageJsonLookup
 * @covers \AEWI\SWS\Persistence\MediaWiki\CachingSchemaLookup
 * @covers \AEWI\SWS\Persistence\MediaWiki\WikiPageMappingLookup
 * @group Database
 */
class ConfigurationPageApprovalTest extends SWSIntegrationTestCase {

	public function testServesThePreviousSchemaRevisionOnceApprovalOfTheLatestIsWithdrawn(): void {
		$previous = $this->createSchema( 'Approval', '{"description":"Previous","propertyDefinitions":{}}' );
		$latest = $this->createSchema( 'Approval', '{"description":"Latest","propertyDefinitions":{}}' );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $latest ) );
		$this->readSchemaDescription();

		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $previous ) );

		$this->assertSame( 'Previous', $this->readSchemaDescription() );
	}

	public function testServesThePreviousMappingRevisionOnceApprovalOfTheLatestIsWithdrawn(): void {
		$previous = $this->createMapping( 'Approval', $this->newMappingJson( 'edm:Agent' ) );
		$latest = $this->createMapping( 'Approval', $this->newMappingJson( 'edm:ProvidedCHO' ) );
		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $latest ) );
		$this->readMappedClass();

		$this->registerRevisionPolicy( FixedRevisionPolicy::publishing( $previous ) );

		$this->assertSame( 'edm:Agent', $this->readMappedClass() );
	}

	private function readSchemaDescription(): ?string {
		return SWSExtension::getInstance()->getSchemaLookup()
			->getSchema( new SchemaName( 'Approval' ) )
			?->getDescription();
	}

	private function readMappedClass(): ?string {
		return SWSExtension::getInstance()->getMappingLookup()
			->getMapping( new MappingName( 'Approval' ) )
			?->forSchema( new SchemaName( 'Person' ) )
			?->subject
			?->class;
	}

	private function newMappingJson( string $personClass ): string {
		return json_encode( [
			'version' => 1,
			'prefixes' => [ 'edm' => 'http://www.europeana.eu/schemas/edm/' ],
			'schemas' => [
				'Person' => [ 'subject' => [ 'class' => $personClass ], 'properties' => (object)[] ],
			],
		] );
	}

}
