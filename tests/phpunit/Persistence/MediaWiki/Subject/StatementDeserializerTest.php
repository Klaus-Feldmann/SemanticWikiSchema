<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Persistence\MediaWiki\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Relation\Relation;
use ProfessionalWiki\SWS\Domain\Relation\RelationId;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Value\MonolingualText;
use ProfessionalWiki\SWS\Domain\Value\MonolingualTextValue;
use ProfessionalWiki\SWS\Domain\Value\NumberValue;
use ProfessionalWiki\SWS\Domain\Value\RelationValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\Value\UnregisteredTypeValue;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\StatementDeserializer;
use ProfessionalWiki\SWS\Tests\Data\TestSources;
use ProfessionalWiki\SWS\Tests\Data\TestSubjectIds;

/**
 * @covers \ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\StatementDeserializer
 * @covers \ProfessionalWiki\SWS\Application\ValueDeserializer
 */
class StatementDeserializerTest extends TestCase {

	public function testDeserializesLegacyAndStructuredUrls(): void {
		$entry = [ 'url' => 'https://example.org/?a=1&b=2', 'label' => 'Title', 'caption' => 'Source', 'captionPage' => 'Vermerk:Quelle' ];
		$statement = $this->newDeserializer()->deserialize( 'Source', [ 'propertyType' => 'url', 'value' => [ 'https://legacy.org', $entry ] ] );
		$this->assertSame( [ [ 'url' => 'https://legacy.org' ], $entry ], $statement->getValue()->toScalars() );
	}

	public function testDeserializesNumber(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'MyNumber' ),
				propertyType: 'number',
				value: new NumberValue( 42 )
			),
			$this->newDeserializer()->deserialize(
				'MyNumber',
				[
					'propertyType' => 'number',
					'value' => 42,
				]
			)
		);
	}

	public function testDeserializesRevisionUsingTheLegacyTypeKey(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'MyNumber' ),
				propertyType: 'number',
				value: new NumberValue( 42 )
			),
			$this->newDeserializer()->deserialize(
				'MyNumber',
				[
					'type' => 'number',
					'value' => 42,
				]
			)
		);
	}

	/**
	 * Core types only: no extension is loaded, so "color" is an unregistered type.
	 */
	private function newDeserializer(): StatementDeserializer {
		return new StatementDeserializer( TestSources::newPropertyTypeRegistry(), TestSubjectIds::newParser() );
	}

	public function testDeserializesText(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'MyText' ),
				propertyType: 'text',
				value: new StringValue( 'Foo', 'Bar', 'Baz' )
			),
			$this->newDeserializer()->deserialize(
				'MyText',
				[
					'propertyType' => 'text',
					'value' => [ 'Foo', 'Bar', 'Baz' ],
				]
			)
		);
	}

	public function testDeserializesTextStoredAsAnObjectWithNamedAndNumberedKeys(): void {
		$statement = $this->newDeserializer()->deserialize(
			'MyText',
			[
				'propertyType' => 'text',
				'value' => [ 'a' => 'Foo', 0 => 'Bar' ],
			]
		);

		$this->assertSame( [ 'Foo', 'Bar' ], $statement->getValue()->toScalars() );
	}

	public function testDropsStoredPartsWithoutContent(): void {
		$statement = $this->newDeserializer()->deserialize(
			'MyText',
			[
				'propertyType' => 'text',
				'value' => [ '', 'Foo' ],
			]
		);

		$this->assertSame( [ 'Foo' ], $statement->getValue()->toScalars() );
	}

	public function testDeserializesMonolingualText(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'Original title' ),
				propertyType: 'monolingualText',
				value: new MonolingualTextValue(
					new MonolingualText( 'Zinema', 'eu' ),
					new MonolingualText( 'Cine', 'es' ),
				)
			),
			$this->newDeserializer()->deserialize(
				'Original title',
				[
					'propertyType' => 'monolingualText',
					'value' => [
						[ 'text' => 'Zinema', 'language' => 'eu' ],
						[ 'text' => 'Cine', 'language' => 'es' ],
					],
				]
			)
		);
	}

	public function testDeserializesRelation(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'MyRelation' ),
				propertyType: 'relation',
				value: new RelationValue(
					new Relation(
						id: new RelationId( 'rTestSDT1111rr1' ),
						targetId: new SubjectId( 'sTestSDT1111111' ),
					),
					new Relation(
						id: new RelationId( 'rTestSDT1111rr2' ),
						targetId: new SubjectId( 'sTestSDT1111112' ),
					),
				)
			),
			$this->newDeserializer()->deserialize(
				'MyRelation',
				[
					'propertyType' => 'relation',
					'value' => [
						[
							'id' => 'rTestSDT1111rr1',
							'target' => 'sTestSDT1111111',
						],
						[
							'id' => 'rTestSDT1111rr2',
							'target' => 'sTestSDT1111112',
						],
					],
				]
			)
		);
	}

	public function testIgnoresPropertiesStoredOnARelation(): void {
		$statement = $this->newDeserializer()->deserialize(
			'MyRelation',
			[
				'propertyType' => 'relation',
				'value' => [
					[ 'id' => 'rTestSDT1111rr1', 'target' => 'sTestSDT1111111', 'properties' => [ 'Since' => 2019 ] ],
				],
			]
		);

		$this->assertEquals(
			[ [ 'id' => 'rTestSDT1111rr1', 'target' => 'sTestSDT1111111' ] ],
			$statement->getValue()->toScalars()
		);
	}

	/**
	 * Never reject already-persisted data on read (ADR 23): whether the Source is still installed is
	 * answered where the target is resolved, not here.
	 */
	public function testDeserializesARelationTargetFromAnotherSource(): void {
		$statement = $this->newDeserializer()->deserialize(
			'MyRelation',
			[
				'propertyType' => 'relation',
				'value' => [
					[ 'id' => 'rTestSDT1111rr1', 'target' => 'neverinstalled:Q42' ],
				],
			]
		);

		$value = $statement->getValue();
		$this->assertInstanceOf( RelationValue::class, $value );
		$this->assertSame( 'neverinstalled:Q42', $value->relations[0]->targetId->text );
	}

	public function testDeserializesUnregisteredTypeWithoutThrowing(): void {
		$this->assertEquals(
			new Statement(
				property: new PropertyName( 'Swatch' ),
				propertyType: 'color',
				value: new UnregisteredTypeValue( 'color', [ '#ff5733' ] )
			),
			$this->newDeserializer()->deserialize(
				'Swatch',
				[
					'propertyType' => 'color',
					'value' => [ '#ff5733' ],
				]
			)
		);
	}

	public function testUnregisteredTypeValueReserializesToTheOriginalJson(): void {
		$value = [ 'nested' => [ 'a', 1, true ], 'other' => null ];

		$statement = $this->newDeserializer()->deserialize( 'Swatch', [ 'propertyType' => 'color', 'value' => $value ] );

		$this->assertSame( $value, $statement->getValue()->toScalars() );
	}

}
