<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\NumberType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\SWS\Presentation\SchemaPresentationSerializer;
use ProfessionalWiki\SWS\Tests\Data\TestData;
use ProfessionalWiki\SWS\Tests\Data\TestProperty;
use ProfessionalWiki\SWS\Tests\Data\TestSchema;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;

/**
 * @covers \ProfessionalWiki\SWS\Presentation\SchemaPresentationSerializer
 */
class SchemaPresentationSerializerTest extends TestCase {

	private SchemaPresentationSerializer $serializer;

	protected function setUp(): void {
		$this->serializer = new SchemaPresentationSerializer();
	}

	private function assertSerializesTo( string $expectedJson, Schema $schema ): void {
		$json = $this->serializer->serialize( $schema );
		$this->assertJsonStringEqualsJsonString( $expectedJson, $json );
	}

	public function testSerializeText(): void {
		$schema = TestSchema::build(
			name: new SchemaName( 'testSchema' ),
			description: 'Test schema description',
			properties: new PropertyDefinitions( [
				'testText' => TestProperty::buildText(
					description: 'Test text property',
					required: true,
					default: 'foo'
				)
			] )
		);

		$expectedJson = json_encode( [
			'description' => 'Test schema description',
			'propertyDefinitions' => [
				'testText' => [
					'description' => 'Test text property',
					'required' => true,
					'multiple' => false,
					'default' => 'foo',
					'uniqueItems' => false,
					'minLength' => null,
					'maxLength' => null,
					'type' => TextType::NAME,
				]
			]
		] );

		$this->assertSerializesTo( $expectedJson, $schema );
	}

	public function testSerializeNumber(): void {
		$schema = TestSchema::build(
			name: new SchemaName( 'testSchema' ),
			description: 'Test schema description',
			properties: new PropertyDefinitions( [
				'testNumber' => TestProperty::buildNumber(
					description: 'Test number property',
					required: false,
					default: 0,
					minimum: null,
					maximum: null
				)
			] )
		);

		$expectedJson = json_encode( [
			'description' => 'Test schema description',
			'propertyDefinitions' => [
				'testNumber' => [
					'description' => 'Test number property',
					'required' => false,
					'default' => 0,
					'type' => NumberType::NAME,
					'maximum' => null,
					'minimum' => null,
					'precision' => null,
				]
			]
		] );

		$this->assertSerializesTo( $expectedJson, $schema );
	}

	public function testSerializeRelation(): void {
		$schema = TestSchema::build(
			name: new SchemaName( 'testSchema' ),
			description: 'Test schema description',
			properties: new PropertyDefinitions( [
				'testRelation' => TestProperty::buildRelation(
					description: 'Test relation property',
					required: false,
					default: null,
					targetSchemas: [ SchemaReference::local( new SchemaName( 'targetSchema' ) ) ],
					multiple: false
				)
			] )
		);

		$expectedJson = json_encode( [
			'description' => 'Test schema description',
			'propertyDefinitions' => [
				'testRelation' => [
					'description' => 'Test relation property',
					'required' => false,
					'default' => null,
					'targetSchemas' => [ 'targetSchema' ],
					'multiple' => false,
					'type' => 'relation'
				]
			]
		] );

		$this->assertSerializesTo( $expectedJson, $schema );
	}

	public function testSerializeString(): void {
		$schema = TestSchema::build(
			name: new SchemaName( 'testSchema' ),
			description: 'Test schema description',
			properties: new PropertyDefinitions( [
				'testString' => TestProperty::buildText(
					description: 'Test string property',
					required: true,
					default: 'default',
					multiple: false,
				)
			] )
		);

		$expectedJson = json_encode( [
			'description' => 'Test schema description',
			'propertyDefinitions' => [
				'testString' => [
					'description' => 'Test string property',
					'required' => true,
					'default' => 'default',
					'type' => 'text',
					'multiple' => false,
					'uniqueItems' => false,
					'minLength' => null,
					'maxLength' => null,
				]
			]
		] );

		$this->assertSerializesTo( $expectedJson, $schema );
	}

	public function testSchemaWithDependentSubjectsDeclaresThem(): void {
		$this->assertSerializesTo(
			'{ "description": "", "subjectsAreDependent": true, "propertyDefinitions": {} }',
			TestSchema::build( name: 'Birth', description: '', subjectsAreDependent: true )
		);
	}

	public function testSchemaWithStandaloneSubjectsLeavesTheDeclarationOut(): void {
		$this->assertSerializesTo(
			'{ "description": "", "propertyDefinitions": {} }',
			TestSchema::build( name: 'Place', description: '' )
		);
	}

	public function testSchemaWithoutPropertiesHasAnEmptyPropertyDefinitionsObject(): void {
		$schema = TestSchema::build(
			name: new SchemaName( 'testSchema' ),
			description: '',
			properties: new PropertyDefinitions( [] )
		);

		$this->assertSerializesTo( '{ "description": "", "propertyDefinitions": {} }', $schema );
	}

	/**
	 * @dataProvider exampleSchemaProvider
	 */
	public function testSchemaIntegrityAfterRoundTripSerialization( string $schemaName, string $originalSchemaJson ): void {
		$originalSchemaArray = json_decode( $originalSchemaJson, true );

		$deserializer = new SchemaPersistenceDeserializer( SWSExtension::getInstance()->getPropertyTypeLookup() );
		$originalSchema = $deserializer->deserialize( new SchemaName( $schemaName ), json_encode( $originalSchemaArray ) );

		$serialized = $this->serializer->serialize( $originalSchema );

		$deserialized = $deserializer->deserialize( $originalSchema->getName(), $serialized );

		$this->assertEquals( $originalSchema, $deserialized );
	}

	public function exampleSchemaProvider(): iterable {
		$dir = new \DirectoryIterator( __DIR__ . '/../../../DemoData/Schema' );

		foreach ( $dir as $fileinfo ) {
			if ( !$fileinfo->isDot() && $fileinfo->getExtension() === 'json' ) {
				yield [ $fileinfo->getBasename( '.json' ), TestData::getFileContents( 'Schema/' . $fileinfo->getFilename() ) ];
			}
		}
	}

}
