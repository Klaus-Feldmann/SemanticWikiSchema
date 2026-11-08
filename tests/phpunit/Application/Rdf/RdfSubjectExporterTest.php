<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\Rdf;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\Rdf\PageProjector;
use ProfessionalWiki\SWS\Application\Rdf\RdfPageLoader;
use ProfessionalWiki\SWS\Application\Rdf\RdfSubjectExporter;
use ProfessionalWiki\SWS\Application\SubjectHostingPageResolver;
use ProfessionalWiki\SWS\Domain\Page\Page;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageIdentifiers;
use ProfessionalWiki\SWS\Domain\Rdf\Iri;
use ProfessionalWiki\SWS\Domain\Rdf\Quad;
use ProfessionalWiki\SWS\Domain\Rdf\QuadList;
use ProfessionalWiki\SWS\Domain\Rdf\RdfFormat;
use ProfessionalWiki\SWS\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\SWS\Infrastructure\Rdf\HardfRdfSerializer;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Tests\Data\TestPage;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\TestDoubles\FixedPageProjector;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\StubPageReadAuthorizer;
use Psr\Log\NullLogger;

/**
 * @covers \ProfessionalWiki\SWS\Application\Rdf\RdfSubjectExporter
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
