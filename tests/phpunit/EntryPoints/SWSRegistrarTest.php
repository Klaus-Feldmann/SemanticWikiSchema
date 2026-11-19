<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\EntryPoints;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\GraphDatabase\GraphDatabasePluginRegistry;
use AEWI\SWS\Domain\EditNotice\SubjectEditNoticeProviderRegistry;
use AEWI\SWS\Domain\Page\PagePropertyProviderRegistry;
use AEWI\SWS\Domain\PropertyType\PropertyTypeRegistry;
use AEWI\SWS\Domain\PropertyType\Types\TextType;
use AEWI\SWS\Domain\Rdf\RdfValueMapperRegistry;
use AEWI\SWS\Application\RevisionPolicyRegistry;
use AEWI\SWS\Domain\Source\SourceRegistry;
use AEWI\SWS\Tests\Data\TestSubjectIds;
use AEWI\SWS\Tests\TestDoubles\FixedRevisionPolicy;
use AEWI\SWS\Tests\TestDoubles\InMemorySource;
use AEWI\SWS\EntryPoints\SWSRegistrar;
use AEWI\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry;
use AEWI\SWS\Tests\TestDoubles\SpyGraphDatabasePlugin;
use AEWI\SWS\Tests\TestDoubles\StubSubjectEditNoticeProvider;
use AEWI\SWS\Tests\TestDoubles\StubPagePropertyProvider;

/**
 * @covers \AEWI\SWS\EntryPoints\SWSRegistrar
 */
class SWSRegistrarTest extends TestCase {

	public function testAddPropertyTypeRegistersInRegistry(): void {
		$propertyTypeRegistry = new PropertyTypeRegistry();
		$registrar = $this->newRegistrar( propertyTypeRegistry: $propertyTypeRegistry );

		$registrar->addPropertyType( new TextType() );

		$this->assertNotNull( $propertyTypeRegistry->getType( 'text' ) );
	}

	public function testAddNeo4jValueBuilderRegistersInRegistry(): void {
		$valueBuilderRegistry = new Neo4jValueBuilderRegistry();
		$registrar = $this->newRegistrar( valueBuilderRegistry: $valueBuilderRegistry );

		$registrar->addNeo4jValueBuilder( 'custom', static fn() => 'value' );

		$this->assertTrue( $valueBuilderRegistry->hasBuilder( 'custom' ) );
	}

	public function testAddPagePropertyProviderRegistersInRegistry(): void {
		$providerRegistry = new PagePropertyProviderRegistry();
		$registrar = $this->newRegistrar( pagePropertyProviderRegistry: $providerRegistry );

		$provider = new StubPagePropertyProvider( [ 'foo' => 'bar' ] );
		$registrar->addPagePropertyProvider( $provider );

		$this->assertSame( [ $provider ], $providerRegistry->getProviders() );
	}

	public function testAddGraphDatabasePluginRegistersInRegistryUnderItsName(): void {
		$pluginRegistry = new GraphDatabasePluginRegistry();
		$registrar = $this->newRegistrar( graphDatabasePluginRegistry: $pluginRegistry );

		$plugin = new SpyGraphDatabasePlugin();
		$registrar->addGraphDatabasePlugin( 'my-store', $plugin );

		$this->assertSame( [ 'my-store' => $plugin ], $pluginRegistry->getPlugins() );
	}

	public function testAddRdfValueMapperRegistersInRegistry(): void {
		$rdfValueMapperRegistry = new RdfValueMapperRegistry();
		$registrar = $this->newRegistrar( rdfValueMapperRegistry: $rdfValueMapperRegistry );

		$registrar->addRdfValueMapper( 'custom', static fn() => [] );

		$this->assertTrue( $rdfValueMapperRegistry->hasMapper( 'custom' ) );
	}

	public function testAddSubjectEditNoticeProviderRegistersInRegistry(): void {
		$noticeRegistry = new SubjectEditNoticeProviderRegistry();
		$registrar = $this->newRegistrar( subjectEditNoticeProviderRegistry: $noticeRegistry );
		$provider = new StubSubjectEditNoticeProvider( [] );

		$registrar->addSubjectEditNoticeProvider( $provider );

		$this->assertSame( [ $provider ], $noticeRegistry->getProviders() );
	}

	public function testRegistersTheRevisionPolicy(): void {
		$policyRegistry = new RevisionPolicyRegistry();
		$registrar = $this->newRegistrar( revisionPolicyRegistry: $policyRegistry );
		$policy = FixedRevisionPolicy::publishingNothing();

		$registrar->setRevisionPolicy( $policy );

		$this->assertSame( $policy, $policyRegistry->getPolicy() );
	}

	public function testAddSourceRegistersItUnderItsKey(): void {
		$sourceRegistry = new SourceRegistry( TestSubjectIds::LOCAL_SOURCE_KEY );
		$source = new InMemorySource();

		$this->newRegistrar( sourceRegistry: $sourceRegistry )->addSource( 'custom', $source );

		$this->assertSame( $source, $sourceRegistry->getSource( 'custom' ) );
	}

	private function newRegistrar(
		?PropertyTypeRegistry $propertyTypeRegistry = null,
		?Neo4jValueBuilderRegistry $valueBuilderRegistry = null,
		?PagePropertyProviderRegistry $pagePropertyProviderRegistry = null,
		?GraphDatabasePluginRegistry $graphDatabasePluginRegistry = null,
		?RdfValueMapperRegistry $rdfValueMapperRegistry = null,
		?SubjectEditNoticeProviderRegistry $subjectEditNoticeProviderRegistry = null,
		?RevisionPolicyRegistry $revisionPolicyRegistry = null,
		?SourceRegistry $sourceRegistry = null,
	): SWSRegistrar {
		return new SWSRegistrar(
			propertyTypeRegistry: $propertyTypeRegistry ?? new PropertyTypeRegistry(),
			valueBuilderRegistry: $valueBuilderRegistry ?? new Neo4jValueBuilderRegistry(),
			pagePropertyProviderRegistry: $pagePropertyProviderRegistry ?? new PagePropertyProviderRegistry(),
			graphDatabasePluginRegistry: $graphDatabasePluginRegistry ?? new GraphDatabasePluginRegistry(),
			rdfValueMapperRegistry: $rdfValueMapperRegistry ?? new RdfValueMapperRegistry(),
			subjectEditNoticeProviderRegistry: $subjectEditNoticeProviderRegistry ?? new SubjectEditNoticeProviderRegistry(),
			revisionPolicyRegistry: $revisionPolicyRegistry ?? new RevisionPolicyRegistry(),
			sourceRegistry: $sourceRegistry ?? new SourceRegistry( TestSubjectIds::LOCAL_SOURCE_KEY ),
		);
	}

}
