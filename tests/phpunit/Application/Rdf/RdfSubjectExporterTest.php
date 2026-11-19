<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application\Rdf;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Application\Rdf\PageProjector;
use AEWI\SWS\Application\Rdf\RdfPageLoader;
use AEWI\SWS\Application\Rdf\RdfSubjectExporter;
use AEWI\SWS\Application\SubjectHostingPageResolver;
use AEWI\SWS\Domain\Page\Page;
use AEWI\SWS\Domain\Page\PageId;
use AEWI\SWS\Domain\Page\PageIdentifiers;
use AEWI\SWS\Domain\Rdf\Iri;
use AEWI\SWS\Domain\Rdf\Quad;
use AEWI\SWS\Domain\Rdf\QuadList;
use AEWI\SWS\Domain\Rdf\RdfFormat;
use AEWI\SWS\Domain\Rdf\RdfLiteralFactory;
use AEWI\SWS\Infrastructure\Rdf\HardfRdfSerializer;
use AEWI\SWS\Domain\Subject\SubjectId;
use AEWI\SWS\Tests\Data\TestPage;
use AEWI\SWS\Tests\Data\TestSubject;
use AEWI\SWS\Tests\TestDoubles\FixedPageProjector;
use AEWI\SWS\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use AEWI\SWS\Tests\TestDoubles\StubPageReadAuthorizer;
use Psr\Log\NullLogger;

/**
 * @covers \AEWI\SWS\Application\Rdf\RdfSubjectExporter
 */
class RdfSubjectExporterTest extends TestCase {

	private const string SUBJECT_ID = 's1acmeaaaaaaaa1';
	private const string OTHER_ID = 's1janeaaaaaaaa2';

	public function testReturnsNullWhenNoPageHostsTheSubject(): void {
		$exporter = $this->exporter(
			lookup: new InMemoryPageIdentifiersLookup(),
			loadedPage: $this->pageWithTheSubject(),
			authorized: true,
		);

		$this->assertNull( $exporter->exportBySubjectId( new SubjectId( self::SUBJECT_ID ), RdfFormat::TriG ) );
	}

	public function testReturnsNullWhenTheHostingPageIsNotReadable(): void {
		$exporter = $this->exporter(
			lookup: $this->lookupResolvingTheSubject(),
			loadedPage: $this->pageWithTheSubject(),
			authorized: false,
		);

		$this->assertNull( $exporter->exportBySubjectId( new SubjectId( self::SUBJECT_ID ), RdfFormat::TriG ) );
	}

	public function testReturnsNullWhenTheHostingPageCannotBeLoaded(): void {
		$exporter = $this->exporter(
			lookup: $this->lookupResolvingTheSubject(),
			loadedPage: null,
			authorized: true,
		);

		$this->assertNull( $exporter->exportBySubjectId( new SubjectId( self::SUBJECT_ID ), RdfFormat::TriG ) );
	}

	public function testReturnsNullWhenTheIndexPointsToAPageThatNoLongerHasTheSubject(): void {
		// Stale index: it names, for the Subject, a page whose current revision no longer carries it.
		$exporter = $this->exporter(
			lookup: $this->lookupResolvingTheSubject(),
			loadedPage: TestPage::build( id: 42, mainSubject: TestSubject::build( id: self::OTHER_ID ) ),
			authorized: true,
		);

		$this->assertNull( $exporter->exportBySubjectId( new SubjectId( self::SUBJECT_ID ), RdfFormat::TriG ) );
	}

	public function testSerializesTheProjectedSubjectWhenItIsPresentAndReadable(): void {
		$quads = new QuadList(
			new Quad(
				new Iri( 'https://wiki.example/entity/' . self::SUBJECT_ID ),
				new Iri( 'http://www.w3.org/2000/01/rdf-schema#label' ),
				RdfLiteralFactory::typed( 'ACME Corp', 'string' ),
				new Iri( 'https://wiki.example/graph/native/page/42' ),
			)
		);

		$document = $this->exporter(
			lookup: $this->lookupResolvingTheSubject(),
			loadedPage: $this->pageWithTheSubject(),
			authorized: true,
			projector: new FixedPageProjector( $quads ),
		)->exportBySubjectId( new SubjectId( self::SUBJECT_ID ), RdfFormat::TriG );

		$this->assertSame(
			( new HardfRdfSerializer( [], new NullLogger() ) )->serialize( $quads, RdfFormat::TriG ),
			$document,
			'The document is the projected Subject serialized in the requested format.'
		);
	}

	private function exporter(
		InMemoryPageIdentifiersLookup $lookup,
		?Page $loadedPage,
		bool $authorized,
		?PageProjector $projector = null
	): RdfSubjectExporter {
		return new RdfSubjectExporter(
			new SubjectHostingPageResolver( $lookup, new StubPageReadAuthorizer( $authorized ) ),
			$this->fixedLoader( $loadedPage ),
			$projector ?? new FixedPageProjector( new QuadList() ),
			new HardfRdfSerializer( [], new NullLogger() ),
		);
	}

	private function lookupResolvingTheSubject(): InMemoryPageIdentifiersLookup {
		return new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( self::SUBJECT_ID ), new PageIdentifiers( new PageId( 42 ), 'ACME Corp', 0 ) ],
		] );
	}

	private function pageWithTheSubject(): Page {
		return TestPage::build( id: 42, mainSubject: TestSubject::build( id: self::SUBJECT_ID ) );
	}

	private function fixedLoader( ?Page $page ): RdfPageLoader {
		return new class( $page ) extends RdfPageLoader {

			public function __construct( private readonly ?Page $page ) {
			}

			public function loadByPageId( PageId $pageId ): ?Page {
				return $this->page;
			}

		};
	}

}
