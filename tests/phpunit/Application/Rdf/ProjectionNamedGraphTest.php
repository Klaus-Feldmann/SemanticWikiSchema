<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application\Rdf;

use AEWI\SWS\Tests\Data\TestSources;
use PHPUnit\Framework\TestCase;
use AEWI\SWS\Application\Rdf\OntologyMappingProjector;
use AEWI\SWS\Application\Rdf\RdfPageProjector;
use AEWI\SWS\Application\Rdf\SubjectIriResolver;
use AEWI\SWS\Domain\Mapping\Mapping;
use AEWI\SWS\Domain\Mapping\MappingName;
use AEWI\SWS\Domain\Mapping\SchemaMapping;
use AEWI\SWS\Domain\Mapping\SubjectMapping;
use AEWI\SWS\Domain\Page\Page;
use AEWI\SWS\Domain\Rdf\Quad;
use AEWI\SWS\Domain\Rdf\QuadList;
use AEWI\SWS\Domain\Rdf\RdfNamespaces;
use AEWI\SWS\Domain\Rdf\RdfValueMapperRegistry;
use AEWI\SWS\Domain\Schema\PropertyDefinitions;
use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\Domain\Subject\StatementList;
use AEWI\SWS\Domain\Value\StringValue;
use AEWI\SWS\Tests\Data\TestPage;
use AEWI\SWS\Tests\Data\TestSchema;
use AEWI\SWS\Tests\Data\TestStatement;
use AEWI\SWS\Tests\Data\TestSubject;
use AEWI\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use WMDE\PsrLogTestDoubles\LegacyLoggerSpy;

/**
 * Regression test for #1053 (discussion #996): named-graph IRIs are qualified by projection. The same
 * page projected natively and through an ontology Mapping must land in DIFFERENT named graphs, so two
 * projections can share one triple store without the per-page replace sync of one wiping the other's
 * triples. The page RESOURCE IRI that appears inside the triples is unaffected — only the graph moves.
 *
 * @covers \AEWI\SWS\Application\Rdf\OntologyMappingProjector
 * @covers \AEWI\SWS\Application\Rdf\RdfPageProjector
 * @covers \AEWI\SWS\Domain\Rdf\RdfNamespaces
 */
class ProjectionNamedGraphTest extends TestCase {

	private const string PERSON_ID = 's1janeaaaaaaaa2';

	private RdfNamespaces $ns;

	protected function setUp(): void {
		$this->ns = new RdfNamespaces( 'https://wiki.example' );
	}

	public function testNativeAndOntologyProjectionsOfTheSamePageUseDifferentNamedGraphs(): void {
		$page = $this->page();

		$nativeGraph = $this->soleGraph( $this->nativeProjector()->projectPage( $page ) );
		$ontologyGraph = $this->soleGraph( $this->edmProjector()->projectPage( $page ) );

		$this->assertSame( 'https://wiki.example/graph/native/page/42', $nativeGraph );
		$this->assertSame( 'https://wiki.example/graph/edm/page/42', $ontologyGraph );
		$this->assertNotSame(
			$nativeGraph,
			$ontologyGraph,
			'A native and an ontology projection of the same page must not collide in one store.'
		);
	}

	public function testThePageResourceIriStaysUnqualifiedWhileItsGraphMoves(): void {
		$quads = $this->nativeProjector()->projectPage( $this->page() )->asArray();

		$pageTypeTriples = array_values( array_filter(
			$quads,
			fn ( Quad $quad ): bool => $quad->subject->value === 'https://wiki.example/page/42'
				&& $quad->predicate->equals( $this->ns->rdfType() )
		) );

		$this->assertCount(
			1,
			$pageTypeTriples,
			'The page resource IRI still appears in the triples as https://wiki.example/page/42.'
		);
		$this->assertSame(
			'https://wiki.example/graph/native/page/42',
			$pageTypeTriples[0]->graph->value,
			'while that triple now lives in the projection-qualified named graph.'
		);
	}

	private function nativeProjector(): RdfPageProjector {
		return new RdfPageProjector(
			RdfValueMapperRegistry::withCoreMappers(),
			$this->ns,
			TestSources::newSchemaResolver( new InMemorySchemaLookup(
				TestSchema::build( name: 'Person', properties: new PropertyDefinitions( [] ) )
			) ),
			new SubjectIriResolver( $this->ns, TestSources::newRegistry(), new LegacyLoggerSpy() ),
			new LegacyLoggerSpy(),
		);
	}

	private function edmProjector(): OntologyMappingProjector {
		return new OntologyMappingProjector(
			$this->personToEdmMapping(),
			$this->ns,
			RdfValueMapperRegistry::withCoreMappers(),
			new SubjectIriResolver( $this->ns, TestSources::newRegistry(), new LegacyLoggerSpy() ),
			new LegacyLoggerSpy(),
		);
	}

	private function personToEdmMapping(): Mapping {
		return new Mapping(
			name: new MappingName( 'edm' ),
			prefixes: [ 'edm' => 'http://www.europeana.eu/schemas/edm/' ],
			schemas: [
				'Person' => new SchemaMapping( subject: new SubjectMapping( 'edm:Agent' ) ),
			],
		);
	}

	private function page(): Page {
		return TestPage::build(
			id: 42,
			mainSubject: TestSubject::build(
				id: self::PERSON_ID,
				label: 'Jane',
				schemaName: new SchemaName( 'Person' ),
				statements: new StatementList( [
					TestStatement::build( 'Name', new StringValue( 'Jane' ), 'text' ),
				] )
			),
		);
	}

	private function soleGraph( QuadList $quads ): string {
		$graphs = array_values( array_unique( array_map(
			static fn ( Quad $quad ): string => $quad->graph->value,
			$quads->asArray()
		) ) );

		$this->assertCount( 1, $graphs, 'A projection places every quad in exactly one named graph.' );

		return $graphs[0];
	}

}
