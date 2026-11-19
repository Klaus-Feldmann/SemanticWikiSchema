<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use AEWI\SWS\Domain\Layout\LayoutName;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Persistence\MediaWiki\LayoutPersistenceDeserializer;
use AEWI\SWS\Persistence\MediaWiki\PageContentFetcher;
use AEWI\SWS\Persistence\MediaWiki\WikiPageLayoutLookup;
use AEWI\SWS\Tests\Data\TestSources;
use AEWI\SWS\Tests\SWSMockAuthorityTrait;
use AEWI\SWS\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * @covers \AEWI\SWS\Persistence\MediaWiki\WikiPageLayoutLookup
 */
class WikiPageLayoutLookupTest extends MediaWikiIntegrationTestCase {

	use SWSMockAuthorityTrait;

	public function testUnreadableLayoutPageIsNullAndContentIsNeverFetched(): void {
		$fetcher = $this->createMock( PageContentFetcher::class );
		$fetcher->expects( $this->never() )->method( 'getPageContent' );

		$lookup = $this->newLookup( canRead: false, fetcher: $fetcher );

		$this->assertNull( $lookup->getLayout( new LayoutName( 'Person card' ) ) );
	}

	public function testReadableLayoutPageDelegatesToTheContentFetcher(): void {
		$fetcher = $this->createMock( PageContentFetcher::class );
		$fetcher->expects( $this->once() )->method( 'getPageContent' )->willReturn( null );

		$lookup = $this->newLookup( canRead: true, fetcher: $fetcher );

		$this->assertNull( $lookup->getLayout( new LayoutName( 'Person card' ) ) );
	}

	private function newLookup( bool $canRead, PageContentFetcher $fetcher ): WikiPageLayoutLookup {
		$title = $this->createMock( Title::class );
		$title->method( 'exists' )->willReturn( true );
		$title->method( 'getPrefixedDBkey' )->willReturn( 'Layout:Person_card' );

		$titleFactory = $this->createStub( TitleFactory::class );
		$titleFactory->method( 'newFromText' )->with( 'Person card', SWSExtension::NS_LAYOUT )->willReturn( $title );

		return new WikiPageLayoutLookup(
			pageContentFetcher: $fetcher,
			authority: $this->mockRegisteredUltimateAuthority(),
			layoutDeserializer: new LayoutPersistenceDeserializer( TestSources::newSchemaReferenceParser() ),
			titleFactory: $titleFactory,
			readAuthorizer: new StubPageReadAuthorizer( allowed: $canRead ),
		);
	}

}
