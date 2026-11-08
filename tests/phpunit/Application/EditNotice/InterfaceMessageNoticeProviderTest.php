<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\EditNotice;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\EditNotice\InterfaceMessageNoticeProvider;
use ProfessionalWiki\SWS\Domain\EditNotice\SubjectEditNoticeContext;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Tests\TestDoubles\StubEditNoticeMessageRenderer;

/**
 * @covers \ProfessionalWiki\SWS\Application\EditNotice\InterfaceMessageNoticeProvider
 */
class InterfaceMessageNoticeProviderTest extends TestCase {

	private function newContext(
		int $namespaceId = 0,
		string $dbKey = 'Berlin',
		?string $schemaName = null,
		bool $namespaceHasSubpages = false
	): SubjectEditNoticeContext {
		return new SubjectEditNoticeContext(
			pageId: new PageId( 42 ),
			pageDbKey: $dbKey,
			namespaceId: $namespaceId,
			schemaName: $schemaName,
			namespaceHasSubpages: $namespaceHasSubpages
		);
	}

	private function newProvider( array $renderedMessages ): InterfaceMessageNoticeProvider {
		return new InterfaceMessageNoticeProvider( new StubEditNoticeMessageRenderer( $renderedMessages ) );
	}

	public function testNoNoticesWhenNoMessagesExist(): void {
		$provider = $this->newProvider( [] );

		$this->assertSame( [], $provider->getNotices( $this->newContext() ) );
	}

	public function testNamespaceMessageBecomesNotice(): void {
		$provider = $this->newProvider( [ 'sws-editnotice-0' => '<p>Namespace notice</p>' ] );

		$notices = $provider->getNotices( $this->newContext() );

		$this->assertCount( 1, $notices );
		$this->assertSame( 'sws-editnotice-0', $notices[0]->key );
		$this->assertSame( '<p>Namespace notice</p>', $notices[0]->html );
	}

	public function testPageMessageUsesNamespaceAndDbKey(): void {
		$provider = $this->newProvider( [ 'sws-editnotice-4-Berlin' => '<p>Page notice</p>' ] );

		$notices = $provider->getNotices( $this->newContext( namespaceId: 4 ) );

		$this->assertCount( 1, $notices );
		$this->assertSame( 'sws-editnotice-4-Berlin', $notices[0]->key );
	}

	public function testSlashesAreFlattenedWhereTheNamespaceHasNoSubpages(): void {
		$provider = $this->newProvider( [ 'sws-editnotice-0-Europe-Berlin' => '<p>Flattened</p>' ] );

		$notices = $provider->getNotices( $this->newContext( dbKey: 'Europe/Berlin' ) );

		$this->assertCount( 1, $notices );
	}

	public function testSubpageNamespaceGetsANoticePerAncestor(): void {
		$provider = $this->newProvider( [
			'sws-editnotice-4-Europe' => '<p>Subtree</p>',
			'sws-editnotice-4-Europe-Berlin' => '<p>Leaf</p>',
		] );

		$notices = $provider->getNotices(
			$this->newContext( namespaceId: 4, dbKey: 'Europe/Berlin', namespaceHasSubpages: true )
		);

		$this->assertSame(
			[ 'sws-editnotice-4-Europe', 'sws-editnotice-4-Europe-Berlin' ],
			array_map( static fn ( $notice ) => $notice->key, $notices )
		);
	}

	public function testAncestorNoticeCoversItsSubpages(): void {
		$provider = $this->newProvider( [ 'sws-editnotice-4-Handbook' => '<p>Subtree</p>' ] );

		$notices = $provider->getNotices(
			$this->newContext( namespaceId: 4, dbKey: 'Handbook/Chapter1', namespaceHasSubpages: true )
		);

		$this->assertCount( 1, $notices );
	}

	public function testSpacesInSchemaNameBecomeUnderscores(): void {
		$provider = $this->newProvider( [ 'sws-editnotice-schema-Control_Document' => '<p>Schema</p>' ] );

		$notices = $provider->getNotices( $this->newContext( schemaName: 'Control Document' ) );

		$this->assertCount( 1, $notices );
		$this->assertSame( 'sws-editnotice-schema-Control_Document', $notices[0]->key );
	}

	public function testSchemaMessageBecomesNoticeWhenSchemaIsKnown(): void {
		$provider = $this->newProvider( [ 'sws-editnotice-schema-Person' => '<p>Schema notice</p>' ] );

		$notices = $provider->getNotices( $this->newContext( schemaName: 'Person' ) );

		$this->assertCount( 1, $notices );
		$this->assertSame( 'sws-editnotice-schema-Person', $notices[0]->key );
	}

	public function testSchemaMessageIsNotConsultedWithoutASchema(): void {
		$provider = $this->newProvider( [ 'sws-editnotice-schema-Person' => '<p>Schema notice</p>' ] );

		$this->assertSame( [], $provider->getNotices( $this->newContext( schemaName: null ) ) );
	}

	public function testSlashesInSchemaNameBecomeDashes(): void {
		$provider = $this->newProvider( [ 'sws-editnotice-schema-Person-Author' => '<p>Schema notice</p>' ] );

		$notices = $provider->getNotices( $this->newContext( schemaName: 'Person/Author' ) );

		$this->assertCount( 1, $notices );
	}

	public function testThePageBeingEditedReachesTheRenderer(): void {
		$renderer = new StubEditNoticeMessageRenderer( [] );

		( new InterfaceMessageNoticeProvider( $renderer ) )
			->getNotices( $this->newContext( namespaceId: 12, dbKey: 'New_York' ) );

		$this->assertSame(
			[ [ 12, 'New_York' ] ],
			array_values( array_unique(
				array_map( static fn ( array $call ) => [ $call['namespaceId'], $call['pageDbKey'] ], $renderer->calls ),
				SORT_REGULAR
			) )
		);
	}

	public function testNoticesAreOrderedFromBroadestToNarrowest(): void {
		$provider = $this->newProvider( [
			'sws-editnotice-schema-Person' => '<p>Schema</p>',
			'sws-editnotice-0-Berlin' => '<p>Page</p>',
			'sws-editnotice-0' => '<p>Namespace</p>',
		] );

		$notices = $provider->getNotices( $this->newContext( schemaName: 'Person' ) );

		$this->assertSame(
			[ 'sws-editnotice-0', 'sws-editnotice-0-Berlin', 'sws-editnotice-schema-Person' ],
			array_map( static fn ( $notice ) => $notice->key, $notices )
		);
	}

}
