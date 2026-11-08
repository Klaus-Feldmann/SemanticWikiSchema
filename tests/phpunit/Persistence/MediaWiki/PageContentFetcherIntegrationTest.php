<?php

declare( strict_types=1 );

namespace ProfessionalWiki\SWS\Tests\Persistence\MediaWiki;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentFetcher
 * @covers \ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSaver
 * @group Database
 */
class PageContentFetcherIntegrationTest extends SWSIntegrationTestCase {

	private const TITLE = 'PageContentFetcherIntegrationTest';

	public function setUp(): void {
		$savingStatus = SWSExtension::getInstance()->getPageContentSaver()->saveContent(
			Title::newFromText( self::TITLE ),
			[
				'main' => new WikitextContent( 'foo' ),
				//'blocks' => BlocksContent::newEmpty(),
			],
			CommentStoreComment::newUnsavedComment( 'whatever' )
		);

		$this->assertNull( $savingStatus->errorMessage );
	}

	public function testCanGetMainContent(): void {
		$content = SWSExtension::getInstance()->getPageContentFetcher()->getPageContent(
			self::TITLE,
			$this->getTestSysop()->getAuthority()
		);

		$this->assertSame( 'foo', $content->getText() );
	}

	public function testReturnsNullForNonExistingPage(): void {
		$this->assertNull(
				SWSExtension::getInstance()->getPageContentFetcher()->getPageContent(
				self::TITLE . '404',
				$this->getTestSysop()->getAuthority()
			)
		);
	}

	public function testReturnsNullForNonExistingSlot(): void {
		$this->assertNull(
			SWSExtension::getInstance()->getPageContentFetcher()->getPageContent(
				self::TITLE,
				$this->getTestSysop()->getAuthority(),
				slotName: 'sws'
			)
		);
	}

	//public function testCanGetSpecialSlotContent(): void {
	//	$content = SWSExtension::getInstance()->getPageContentFetcher()->getPageContent(
	//		self::TITLE,
	//		$this->getTestSysop()->getAuthority(),
	//		slotName: 'blocks'
	//	);
	//
	//	$this->assertJsonStringEqualsJsonString(
	//		BlocksContent::newEmpty()->getText(),
	//		$content->getText()
	//	);
	//}

	public function testCanUseTitleObject(): void {
		$content = SWSExtension::getInstance()->getPageContentFetcher()->getPageContent(
			Title::newFromText( self::TITLE ),
			$this->getTestSysop()->getAuthority()
		);

		$this->assertSame( 'foo', $content->getText() );
	}

}
