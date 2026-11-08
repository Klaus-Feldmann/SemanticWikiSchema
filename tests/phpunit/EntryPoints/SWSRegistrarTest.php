<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\GraphDatabase\GraphDatabasePluginRegistry;
use ProfessionalWiki\SWS\Domain\EditNotice\SubjectEditNoticeProviderRegistry;
use ProfessionalWiki\SWS\Domain\Page\PagePropertyProviderRegistry;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\SWS\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\SWS\Application\RevisionPolicyRegistry;
use ProfessionalWiki\SWS\Domain\Source\SourceRegistry;
use ProfessionalWiki\SWS\Tests\Data\TestSubjectIds;
use ProfessionalWiki\SWS\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySource;
use ProfessionalWiki\SWS\EntryPoints\SWSRegistrar;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry;
use ProfessionalWiki\SWS\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\SWS\Tests\TestDoubles\StubSubjectEditNoticeProvider;
use ProfessionalWiki\SWS\Tests\TestDoubles\StubPagePropertyProvider;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSRegistrar
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
