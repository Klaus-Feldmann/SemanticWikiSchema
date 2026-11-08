<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Tests\Application\Images;
use PHPUnit\Framework\TestCase;
use SWSBaseBridge\ImageClaimView;
require_once __DIR__ . '/../../../../SchemaBaseBridge/src/ImageClaimView.php';
class ImageClaimViewTest extends TestCase {
	private function claim( string $name, string $rank = 'normal' ): array {
		return [ 'rank' => $rank, 'mainsnak' => [ 'snaktype' => 'value', 'datatype' => 'commonsMedia', 'datavalue' => [ 'type' => 'string', 'value' => $name ] ] ];
	}
	public function testImageClaimsKeepFilenameRepositoryAndRank(): void {
		$input = [ $this->claim( 'Goethe (Stieler 1828).jpg', 'preferred' ), $this->claim( 'Other.jpg' ), $this->claim( 'Deprecated.jpg', 'deprecated' ) ];
		$before = $input; $values = ImageClaimView::values( $input );
		$this->assertCount( 2, $values );
		$this->assertSame( 'commons:Goethe (Stieler 1828).jpg', $values[0]['reference'] );
		$this->assertSame( 'preferred', $values[0]['rank'] );
		$this->assertSame( $before, $input );
	}
	public function testUnknownNoValueAndInvalidValuesRemainDistinct(): void {
		$values = ImageClaimView::values( [ [ 'mainsnak' => [ 'snaktype' => 'somevalue' ] ], [ 'mainsnak' => [ 'snaktype' => 'novalue' ] ], $this->claim( '[invalid]' ) ] );
		$this->assertSame( [ [ 'kind' => 'somevalue' ], [ 'kind' => 'novalue' ] ], $values );
	}
}
