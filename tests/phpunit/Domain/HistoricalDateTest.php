<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Tests\Domain;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\HistoricalDate;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\HistoricalDateType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\Rdf\RdfValueMapperRegistry;

class HistoricalDateTest extends TestCase {
	public function testPrecisionAndCalendar(): void {
		foreach ( [ '1458', '1458-10', '1458-10-01', '-0044', '-0044-03-15', '-0001-02-29', '0001' ] as $raw ) {
			$this->assertNotNull( HistoricalDate::parse( $raw ), $raw );
		}
		foreach ( [ '0000', '-0000', '1458-04-31', '2025-02-29', '-0044-02-29', '1458-00', '10000', "1458\n" ] as $raw ) {
			$this->assertNull( HistoricalDate::parse( $raw ), $raw );
		}
		$this->assertSame( [ 'start' => 2253593, 'end' => 2253957 ], HistoricalDate::parse( '1458' )->bounds() );
		$this->assertSame( 'year', HistoricalDate::parse( '-0044' )->precision() );
		$this->assertSame( 'month', HistoricalDate::parse( '1458-10' )->precision() );
		$this->assertSame( 'day', HistoricalDate::parse( '1458-10-01' )->precision() );
	}

	public function testRenamedPropertyKeepsLinkSettingsAndValidation(): void {
		$registry = new PropertyTypeRegistry();
		$type = new HistoricalDateType();
		$registry->registerType( $type );
		$registry->registerType( new TextType() );
		$link = [ 'namespace' => 'Werk', 'labelTarget' => 'Datierung', 'values' => true, 'dateBceDe' => 'v. u. Z.', 'dateBceEn' => 'BCE' ];
		foreach ( [ 'historicalDate', 'text' ] as $name ) {
			$property = PropertyDefinition::fromJson( [ 'type' => $name, 'wikiLink' => $link ], $registry );
			$this->assertSame( $link, $property->toJson()['wikiLink'] );
			$again = PropertyDefinition::fromJson( $property->toJson(), $registry );
			$this->assertSame( $link, $again->getWikiLink() );
		}
		$withDefault = PropertyDefinition::fromJson( [ 'type' => 'historicalDate', 'default' => [ '-0044' ] ], $registry );
		$this->assertSame( [ '-0044' ], $withDefault->toJson()['default'] );
		$property = PropertyDefinition::fromJson( [ 'type' => 'historicalDate' ], $registry );
		$this->assertSame( [], $type->validate( new StringValue( '-0044' ), $property ) );
		$this->assertNotEmpty( $type->validate( new StringValue( '0000' ), $property ) );
		$this->assertNotEmpty( $type->validate( new StringValue( '1458', '1459' ), $property ) );
		$literals = RdfValueMapperRegistry::withCoreMappers()->mapValue( 'historicalDate', new StringValue( '-0044' ) );
		$this->assertCount( 1, $literals );
		$this->assertSame( '-0044', $literals[0]->lexicalForm );
		$this->assertSame( HistoricalDate::RDF_DATATYPE, $literals[0]->datatype->value );
	}
}
