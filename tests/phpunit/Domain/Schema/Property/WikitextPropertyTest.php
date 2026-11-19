<?php

declare( strict_types = 1 );
namespace AEWI\SWS\Tests\Domain\Schema\Property;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\Schema\Property\WikitextProperty;
use AEWI\SWS\Domain\Schema\Property\WikitextValueProperty;
use AEWI\SWS\Domain\PropertyType\Types\WikitextValueType;
use AEWI\SWS\Domain\Schema\PropertyCore;
use AEWI\SWS\Domain\PropertyType\Types\WikitextType;
use AEWI\SWS\Domain\Value\StringValue;
use AEWI\SWS\Persistence\MediaWiki\SchemaContentValidator;

class WikitextPropertyTest extends TestCase {
	public function testSchemaProgramIsSeparateFromSubjectValueAndSurvivesSerialization(): void {
		$p = WikitextProperty::fromPartialJson( new PropertyCore( '', true, [ '{{AlterStandard}}' ] ), [ 'execute' => true, 'wikitext' => " {{SWS}}\n" ] );
		$this->assertTrue( $p->isSchemaExecution() );
		$this->assertFalse( $p->isRequired() );
		$this->assertSame( " {{SWS}}\n", $p->toJson()['wikitext'] );
		$this->assertSame( [], ( new WikitextType() )->validate( new StringValue( '{{NichtAusfuehren}}' ), $p ) );
	}
	public function testOrdinaryWikitextRetainsTextConstraints(): void {
		$p = WikitextValueProperty::fromPartialJson( new PropertyCore( '', true, null ), [ 'minLength' => 4 ] );
		$this->assertSame( 'wikitextValue', $p->getPropertyType() );
		$this->assertCount( 1, ( new WikitextValueType() )->validate( new StringValue( 'Ab' ), $p ) );
		$this->assertCount( 1, ( new WikitextValueType() )->validate( new StringValue(), $p ) );
	}
	public function testSchemaValidatorRejectsEmptyOrMalformedExecutionPrograms(): void {
		$validator = SchemaContentValidator::newInstance();
		foreach ( [ [ 'execute' => 'true', 'wikitext' => '{{X}}' ] ] as $bad ) {
			$this->assertFalse( $validator->validate( json_encode( [ 'propertyDefinitions' => [ 'Auswertung' => [ 'type' => 'wikitext' ] + $bad ] ] ) ) );
		}
		$this->assertTrue( $validator->validate( '{"propertyDefinitions":{"Auswertung":{"type":"wikitext","execute":true,"wikitext":"{{SWS}}"}}}' ) );
		$this->assertTrue( $validator->validate( '{"propertyDefinitions":{"Notiz":{"type":"wikitext","execute":false}}}' ) );
	}
}
