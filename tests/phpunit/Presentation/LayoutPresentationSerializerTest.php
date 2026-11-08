<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Layout\DisplayRule;
use ProfessionalWiki\SWS\Domain\Layout\DisplayRules;
use ProfessionalWiki\SWS\Domain\Layout\Layout;
use ProfessionalWiki\SWS\Domain\Layout\LayoutName;
use ProfessionalWiki\SWS\Presentation\LayoutPresentationSerializer;

/**
 * @covers \ProfessionalWiki\SWS\Presentation\LayoutPresentationSerializer
 */
class LayoutPresentationSerializerTest extends TestCase {

	public function testSerializesMinimalLayout(): void {
		$layout = new Layout(
			name: new LayoutName( 'TestView' ),
			schema: new SchemaName( 'Company' ),
			type: 'infobox',
			description: '',
			displayRules: new DisplayRules( [] ),
			settings: [],
		);

		$json = ( new LayoutPresentationSerializer() )->serialize( $layout );
		$data = json_decode( $json, true );

		$this->assertSame( 'Company', $data['schema'] );
		$this->assertSame( 'infobox', $data['type'] );
		$this->assertArrayNotHasKey( 'description', $data );
		$this->assertArrayNotHasKey( 'displayRules', $data );
		$this->assertArrayNotHasKey( 'settings', $data );
	}

	public function testSerializesFullLayout(): void {
		$layout = new Layout(
			name: new LayoutName( 'TestView' ),
			schema: new SchemaName( 'Company' ),
			type: 'infobox',
			description: 'Key financial data',
			displayRules: new DisplayRules( [
				new DisplayRule( new PropertyName( 'Revenue' ), [ 'precision' => 0 ] ),
				new DisplayRule( new PropertyName( 'Net Income' ), [] ),
			] ),
			settings: [ 'borderColor' => '#336699' ],
		);

		$json = ( new LayoutPresentationSerializer() )->serialize( $layout );
		$data = json_decode( $json, true );

		$this->assertSame( 'Key financial data', $data['description'] );
		$this->assertCount( 2, $data['displayRules'] );
		$this->assertSame( 'Revenue', $data['displayRules'][0]['property'] );
		$this->assertSame( [ 'precision' => 0 ], $data['displayRules'][0]['displayAttributes'] );
		$this->assertSame( 'Net Income', $data['displayRules'][1]['property'] );
		$this->assertArrayNotHasKey( 'displayAttributes', $data['displayRules'][1] );
		$this->assertSame( [ 'borderColor' => '#336699' ], $data['settings'] );
	}

}
