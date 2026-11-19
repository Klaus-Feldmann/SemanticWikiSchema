<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application\Actions;

use MockHttpTrait;
use AEWI\SWS\Application\Actions\ImportPages\ImportPagesAction;
use AEWI\SWS\Application\Actions\ImportPages\LayoutContentSource;
use AEWI\SWS\Application\Actions\ImportPages\MappingContentSource;
use AEWI\SWS\Application\Actions\ImportPages\PageContentSource;
use AEWI\SWS\Application\Actions\ImportPages\SchemaContentSource;
use AEWI\SWS\Application\Actions\ImportPages\SubjectPageSource;
use AEWI\SWS\EntryPoints\Content\LayoutContent;
use AEWI\SWS\EntryPoints\Content\MappingContent;
use AEWI\SWS\EntryPoints\Content\SchemaContent;
use AEWI\SWS\Persistence\ImportedPageTitlesLookup;
use AEWI\SWS\Persistence\MediaWiki\PageContentSaver;
use AEWI\SWS\Tests\SWSIntegrationTestCase;
use AEWI\SWS\Tests\TestDoubles\ImportPresenterSpy;
use AEWI\SWS\Tests\TestDoubles\PageDeleterSpy;
use TestLogger;

/**
 * Saves the imported pages to the database, since saving a page is what projects it into the graph stores.
 *
 * @covers \AEWI\SWS\Application\Actions\ImportPages\ImportPagesAction
 * @group Database
 */
class ImportPagesProjectionTest extends SWSIntegrationTestCase {

	use MockHttpTrait;

	private const string MAPPING_JSON = '{"version":1,"schemas":{}}';

	public function testNoProjectionFailureIsLoggedWhenAGraphStoreProjectsAnImportedMapping(): void {
		$this->installMockHttp( $this->makeFakeHttpRequest( '', 200 ) );
		$this->overrideConfigValue( 'SWSSparqlStores', [
			[ 'updateUrl' => 'https://sparql.example/update', 'projection' => 'EDM' ],
		] );
		$logger = new TestLogger( true );
		$this->setLogger( 'SWS', $logger );
		$presenter = new ImportPresenterSpy();

		$this->runWithoutGraphBackend( fn () => $this->newImportAction( $presenter )->import() );

		$this->assertEqualsCanonicalizing(
			[ 'Mapping:EDM', 'Mapping:CIDOC-CRM', 'Schema:Artwork', 'Layout:ArtworkCard' ],
			$presenter->created
		);
		$this->assertSame( [], self::loggedErrors( $logger ) );
	}

	private function newImportAction( ImportPresenterSpy $presenter ): ImportPagesAction {
		return new ImportPagesAction(
			presenter: $presenter,
			pageContentSaver: new PageContentSaver(
				$this->getServiceContainer()->getWikiPageFactory(),
				$this->getTestSysop()->getUser(),
			),
			importedPageTitlesLookup: $this->createMock( ImportedPageTitlesLookup::class ),
			pageDeleter: new PageDeleterSpy(),
			schemaContentSource: $this->createConfiguredMock(
				SchemaContentSource::class,
				[ 'getSchemas' => [ 'Artwork' => new SchemaContent( '{"propertyDefinitions":{}}' ) ] ]
			),
			subjectPageSource: $this->createMock( SubjectPageSource::class ),
			pageContentSource: $this->createMock( PageContentSource::class ),
			moduleContentSource: $this->createMock( PageContentSource::class ),
			mediaWikiContentSource: $this->createMock( PageContentSource::class ),
			layoutContentSource: $this->createConfiguredMock(
				LayoutContentSource::class,
				[ 'getLayouts' => [ 'ArtworkCard' => new LayoutContent( '{"schema":"Artwork","type":"infobox"}' ) ] ]
			),
			mappingContentSource: $this->createConfiguredMock(
				MappingContentSource::class,
				[
					'getMappings' => [
						'CIDOC-CRM' => new MappingContent( self::MAPPING_JSON ),
						'EDM' => new MappingContent( self::MAPPING_JSON ),
					],
				]
			),
			graphStoreProjections: [ 'EDM' ],
		);
	}

}
