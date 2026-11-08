<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Persistence\MediaWiki\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Tests\Data\TestSources;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;
use ProfessionalWiki\SWS\Domain\Relation\Relation;
use ProfessionalWiki\SWS\Domain\Relation\RelationId;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Value\RelationValue;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\StatementDeserializer;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer;
use ProfessionalWiki\SWS\Tests\Data\TestData;
use ProfessionalWiki\SWS\Tests\Data\TestSubjectIds;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;

/**
 * @covers \ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer
 */
class SubjectContentDataDeserializerTest extends TestCase {

	public function testNodeExampleSmokeTest(): void {
		$subjects = $this->newDeserializer()->deserialize( TestData::getFileContents( 'Subject/ACME_Inc.json' ) );

		$this->assertSame(
			'ACME Inc',
			$subjects->getMainSubject()->getLabel()->text
		);
	}

	private function newDeserializer( ?LoggerInterface $logger = null ): SubjectContentDataDeserializer {
		return new SubjectContentDataDeserializer(
			new StatementDeserializer( SWSExtension::getInstance()->getPropertyTypeLookup(), TestSubjectIds::newParser() ),
			$logger ?? new NullLogger(),
			TestSources::newSchemaReferenceParser()
		);
	}

	/**
	 * The read path with the normalizer this wiki actually builds, rather than the double the other
	 * tests use. This is what carries the claim that Subjects written down before normalization
	 * existed are read under the name of the Schema they name, with nothing migrated.
	 */
	public function testSlotWrittenWithAnotherSpellingIsReadUnderTheSchemasName(): void {
		$subjects = SWSExtension::getInstance()->newSubjectContentDataDeserializer()->deserialize(
			<<<'JSON'
{
	"subjects": {
		"sTestSCDD111115": {
			"label": "Wilhelm",
			"schema": "person"
		}
	}
}
JSON
		);

		$this->assertSame(
			'Person',
			$subjects->getAllSubjects()->asArray()[0]->getSchemaName()->getText()
		);
	}

	public function testMinimalJson(): void {
		$data = $this->newDeserializer()->deserialize( '{}' );

		$this->assertSame( [], $data->getAllSubjects()->asArray() );
		$this->assertNull( $data->getMainSubject() );
	}

	/**
	 * A `mainSubject` that is not a string is treated as absent rather than parsed. Only hand-edited or
	 * corrupt slot content reaches this, and a page must render regardless.
	 *
	 * @dataProvider nonStringMainSubjectProvider
	 */
	public function testNonStringMainSubjectLeavesThePageWithoutOne( string $mainSubjectJson ): void {
		$data = $this->newDeserializer()->deserialize( '{"mainSubject": ' . $mainSubjectJson . ', "subjects": {}}' );

		$this->assertNull( $data->getMainSubject() );
	}

	public static function nonStringMainSubjectProvider(): iterable {
		yield 'null' => [ 'null' ];
		yield 'a number' => [ '42' ];
		yield 'an array' => [ '["sTestSCDD111115"]' ];
		yield 'an object' => [ '{"id": "sTestSCDD111115"}' ];
	}

	/**
	 * A local slot holds local Subjects only: the subject-to-page index reads its keys as local ids
	 * (ADR 32), so an entry keyed otherwise would be projected while never being indexed. Imported or
	 * hand-edited content is the only way one gets there, and a page must render regardless.
	 *
	 * @dataProvider nonLocalSubjectKeyProvider
	 */
	public function testSubjectKeyThatIsNotALocalIdIsSkipped( string $key ): void {
		$logger = new TestLogger();

		$data = $this->newDeserializer( $logger )->deserialize(
			'{"subjects":{"' . $key . '":{"label":"Foreign","schema":"Company"},'
				. '"sTestSCDD111115":{"label":"Local","schema":"Company"}}}'
		);

		$this->assertSame(
			[ 'sTestSCDD111115' ],
			array_map(
				static fn ( Subject $subject ): string => $subject->getId()->text,
				$data->getAllSubjects()->asArray()
			)
		);
		$this->assertTrue( $logger->hasWarningRecords() );
	}

	public static function nonLocalSubjectKeyProvider(): iterable {
		yield 'another Source' => [ 'catalog:widget-7' ];
		yield 'this wiki named explicitly' => [ TestSubjectIds::LOCAL_SOURCE_KEY . ':sTestSCDD111116' ];
		yield 'not an id at all' => [ 'nonsense' ];
	}

	public function testQualifiedMainSubjectLeavesThePageWithoutOne(): void {
		$logger = new TestLogger();

		$data = $this->newDeserializer( $logger )->deserialize(
			'{"mainSubject":"catalog:widget-7","subjects":{"sTestSCDD111115":{"label":"Local","schema":"Company"}}}'
		);

		$this->assertNull( $data->getMainSubject() );
		$this->assertCount( 1, $data->getAllSubjects()->asArray() );
		$this->assertTrue( $logger->hasWarningRecords() );
	}

	public function testMinimalSubjects(): void {
		$data = $this->newDeserializer()->deserialize(
			<<<JSON
{
	"subjects": {
		"sTestSCDD111115": {
			"label": "ACME Inc.",
			"schema": "Company"
		},
		"sTestSCDD111114": {
			"label": "Contoso Ltd.",
			"schema": "Company"
		}
	}
}
JSON
		);

		$this->assertEquals(
			[
				Subject::newSubject(
					new SubjectId( 'sTestSCDD111115' ),
					new SubjectLabel( 'ACME Inc.' ),
					SchemaReference::local( new SchemaName( "Company" ) )
				),
				Subject::newSubject(
					new SubjectId( 'sTestSCDD111114' ),
					new SubjectLabel( 'Contoso Ltd.' ),
					SchemaReference::local( new SchemaName( "Company" ) )
				),
			],
			$data->getAllSubjects()->asArray()
		);
	}

	/**
	 * @dataProvider labelJsonThatMeansNoLabelProvider
	 */
	public function testLabelJsonThatMeansNoLabelDeserializesToNull( string $labelJson ): void {
		$data = $this->newDeserializer()->deserialize(
			'{"subjects":{"sTestSCDD111115":{' . $labelJson . '"schema":"Company","statements":{}}}}'
		);

		$this->assertNull( $data->getAllSubjects()->getSubject( new SubjectId( 'sTestSCDD111115' ) )?->getLabel() );
	}

	public static function labelJsonThatMeansNoLabelProvider(): iterable {
		yield 'key omitted' => [ '' ];
		yield 'empty string' => [ '"label":"",' ];
		yield 'whitespace only' => [ '"label":"  \\t ",' ];
		yield 'null' => [ '"label":null,' ];
	}

	public function testStoredLabelIsKeptVerbatim(): void {
		$data = $this->newDeserializer()->deserialize(
			'{"subjects":{"sTestSCDD111115":{"label":"  ACME Inc.  ","schema":"Company","statements":{}}}}'
		);

		$this->assertSame(
			'  ACME Inc.  ',
			$data->getAllSubjects()->getSubject( new SubjectId( 'sTestSCDD111115' ) )?->getLabel()?->text
		);
	}

	public function testEmptyTopLevelSubjectAttributes(): void {
		$data = $this->newDeserializer()->deserialize(
			<<<JSON
{
	"subjects": {
		"sTestSCDD111114": {
			"label": "ACME Inc.",
			"schema": "Company",
			"statements": {
			}
		}
	}
}
JSON
		);

		$this->assertEquals(
			[
				Subject::newSubject(
					new SubjectId( 'sTestSCDD111114' ),
					new SubjectLabel( 'ACME Inc.' ),
					SchemaReference::local( new SchemaName( 'Company' ) )
				),
			],
			$data->getAllSubjects()->asArray()
		);
	}

	public function testRelationPropertyWithMultipleValues(): void {
		$subjects = $this->newDeserializer()->deserialize(
			<<<JSON
{
	"mainSubject": "sTestSCDD111111",
	"subjects": {
		"sTestSCDD111111": {
			"label": "Professional Wiki GmbH",
			"schema": "Company",
			"statements": {
				"Products": {
					"propertyType": "relation",
					"value": [
						{
							"id": "rTestSCDDrrrrr1",
							"target": "sTestSCDD111112"
						},
						{
							"id": "rTestSCDDrrrrr2",
							"target": "sTestSCDD111113"
						}
					]
				}
			}
		}
	}
}
JSON
		);

		$this->assertEquals(
			[
				'Products' => new Statement(
					property: new PropertyName( 'Products' ),
					propertyType: 'relation',
					value: new RelationValue(
						new Relation(
							id: new RelationId( 'rTestSCDDrrrrr1' ),
							targetId: new SubjectId( 'sTestSCDD111112' ),
						),
						new Relation(
							id: new RelationId( 'rTestSCDDrrrrr2' ),
							targetId: new SubjectId( 'sTestSCDD111113' ),
						)
					)
				),
			],
			$subjects->getMainSubject()->getStatements()->asArray()
		);
	}

}
