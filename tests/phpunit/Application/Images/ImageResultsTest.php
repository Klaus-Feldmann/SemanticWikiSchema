<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Tests\Application\Images;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\Images\ImageResults;

class ImageResultsTest extends TestCase {
	private function page( array $info = [] ): array {
		return [ 'ns' => 6, 'title' => 'File:Example.jpg', 'imageinfo' => [ $info + [ 'mime' => 'image/jpeg',
			'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Example.jpg',
			'url' => 'https://upload.wikimedia.org/original.jpg', 'thumburl' => 'https://upload.wikimedia.org/thumb.jpg',
			'width' => 1000, 'height' => 2000, 'thumbwidth' => 75, 'thumbheight' => 150 ] ] ];
	}
	public function testReturnsCanonicalReferenceAndBoundedPreview(): void {
		$r = ImageResults::fromPages( [ $this->page() ], 'commons' )[0];
		$this->assertSame( 'commons:Example.jpg', $r['reference'] );
		$this->assertSame( 75, $r['width'] ); $this->assertSame( 150, $r['height'] );
		$this->assertSame( 'https://commons.wikimedia.org/wiki/File:Example.jpg', $r['descriptionUrl'] );
	}
	public function testDoesNotLeakOriginalFullSizeImageAsPreview(): void {
		$r = ImageResults::fromPages( [ $this->page( [ 'thumburl' => null ] ) ], 'commons' )[0];
		$this->assertNull( $r['thumbnailUrl'] );
		$r = ImageResults::fromPages( [ $this->page( [ 'thumbwidth' => 500 ] ) ], 'commons' )[0];
		$this->assertNull( $r['thumbnailUrl'] );
	}
	public function testSmallRasterMayUseOriginalWithoutUpscaling(): void {
		$r = ImageResults::fromPages( [ $this->page( [ 'thumburl' => null, 'width' => 20, 'height' => 30 ] ) ], 'commons' )[0];
		$this->assertSame( 20, $r['width'] ); $this->assertSame( 30, $r['height'] );
		$this->assertSame( 'https://upload.wikimedia.org/original.jpg', $r['thumbnailUrl'] );
	}
	public function testSvgUsesRepositoryPreviewOnly(): void {
		$r = ImageResults::fromPages( [ $this->page( [ 'mime' => 'image/svg+xml', 'thumburl' => null, 'width' => 20, 'height' => 30 ] ) ], 'commons' )[0];
		$this->assertNull( $r['thumbnailUrl'] );
	}
	public function testSourceIsNotChangedBySharedFileOrLocalizedNamespace(): void {
		$page = $this->page(); $page['title'] = 'Datei:Ä: Test.jpg'; $page['missing'] = ''; $page['imagerepository'] = 'shared';
		$r = ImageResults::fromPages( [ $page ], 'local' )[0];
		$this->assertSame( 'local:Ä: Test.jpg', $r['reference'] );
	}
	public function testFiltersNonImagesWrongNamespacesAndUnsafeSourceUrls(): void {
		$this->assertSame( [], ImageResults::fromPages( [ $this->page( [ 'mime' => 'application/pdf' ] ) ], 'commons' ) );
		$this->assertSame( [], ImageResults::fromPages( [ [ 'ns' => 0 ] ], 'local' ) );
		foreach ( [ 'javascript:alert(1)', 'https://evil.example/X', 'https://user:password@commons.wikimedia.org/X' ] as $url ) {
			$this->assertSame( [], ImageResults::fromPages( [ $this->page( [ 'descriptionurl' => $url ] ) ], 'commons' ) );
		}
	}
	public function testMissingFileDoesNotBecomeAnInventedImage(): void {
		$this->assertSame( [], ImageResults::fromPages( [ [ 'ns' => 6, 'title' => 'File:Missing.jpg', 'missing' => true ] ], 'commons' ) );
	}
	public function testActualGoetheThumbnailHostIsAccepted(): void {
		$r = ImageResults::fromPages( [ $this->page( [ 'thumburl' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/0/0e/Goethe.jpg/250px-Goethe.jpg', 'thumbwidth' => 122, 'thumbheight' => 150 ] ) ], 'commons' )[0];
		$this->assertStringStartsWith( 'https://thumb.wikimedia.org/', $r['thumbnailUrl'] );
		$this->assertSame( 122, $r['width'] ); $this->assertSame( 150, $r['height'] );
	}

}
