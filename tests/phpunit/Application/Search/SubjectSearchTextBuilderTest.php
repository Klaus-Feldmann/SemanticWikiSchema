<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\Search;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\Search\SubjectSearchLine;
use ProfessionalWiki\SWS\Application\Search\SubjectSearchTextBuilder;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Domain\Value\BooleanValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Tests\Data\TestProperty;
use ProfessionalWiki\SWS\Tests\Data\TestSchema;
use ProfessionalWiki\SWS\Tests\Data\TestSources;
use ProfessionalWiki\SWS\Tests\Data\TestStatement;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\Data\TestSubjectIds;
use ProfessionalWiki\SWS\Tests\TestDoubles\DefinitionReportingPropertyType;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\SWS\Application\Search\SubjectSearchTextBuilder
 */
class SubjectSearchTextBuilderTest extends TestCase {

	private const SCHEMA_NAME = 'Museum';

	public function testLabelIsIncluded(): void {
		$this->assertSame(
			'Rijksmuseum',
			$this->textForSubjects( TestSubject::build( label: 'Rijksmuseum' ) )
		);
	}

	public function testUnnamedSubjectContributesNoLabel(): void {
		$this->assertSame(
			'Amsterdam',
			$this->textForSubjects( $this->subjectWith( null, TestStatement::build( value: 'Amsterdam' ) ) )
		);
	}

	public function testStatementValuesFollowTheLabel(): void {
		$this->assertSame(
			"Rijksmuseum\nAmsterdam",
			$this->textForSubjects(
				$this->subjectWith( 'Rijksmuseum', TestStatement::build( value: 'Amsterdam' ) )
			)
		);
	}

	public function testValueOfAPropertyTypeThatIndexesNothingIsSkipped(): void {
		$this->assertSame(
			"Before\nAfter",
			$this->textForSubjects( $this->subjectWith(
				null,
				TestStatement::build( property: 'First', value: 'Before' ),
				TestStatement::build( property: 'Open', value: new BooleanValue( true ), propertyType: 'boolean' ),
				TestStatement::build( property: 'Last', value: 'After' )
			) )
		);
	}

	public function testValueOfAnUnregisteredPropertyTypeIsSkipped(): void {
		$this->assertSame(
			"Before\nAfter",
			$this->textForSubjects( $this->subjectWith(
				null,
				TestStatement::build( property: 'First', value: 'Before' ),
				TestStatement::build( property: 'Shade', value: '#ff5733', propertyType: 'color' ),
				TestStatement::build( property: 'Last', value: 'After' )
			) )
		);
	}

	public function testEverySubjectOnThePageIsIncluded(): void {
		$this->assertSame(
			"Rijksmuseum\n2023\nVan Gogh Museum",
			$this->textForSubjects(
				$this->subjectWith( 'Rijksmuseum', TestStatement::build( value: '2023' ) ),
				TestSubject::build( id: 's22222222222222', label: 'Van Gogh Museum' )
			)
		);
	}

	public function testPageWithoutSubjectsHasNoText(): void {
		$this->assertSame(
			'',
			$this->newBuilder( $this->coreTypes() )->build( PageSubjects::newEmpty() )
		);
	}

	public function testDefinitionIsPassedWhenTheSchemaDeclaresThePropertyWithTheStatementsType(): void {
		$this->assertSame(
			"Rijksmuseum\ndefinition of type text",
			$this->textIndexedWith(
				TestStatement::build( property: 'City', value: 'Amsterdam' ),
				$this->museumSchemaWith( 'City', TestProperty::buildText() )
			)
		);
	}

	public function testNoDefinitionIsPassedWhenTheSchemaDoesNotDeclareTheProperty(): void {
		$this->assertSame(
			"Rijksmuseum\nno definition",
			$this->textIndexedWith(
				TestStatement::build( property: 'City', value: 'Amsterdam' ),
				$this->museumSchemaWith( 'Founded', TestProperty::buildText() )
			)
		);
	}

	public function testNoDefinitionIsPassedWhenTheSchemaGaveThePropertyAnotherType(): void {
		$this->assertSame(
			"Rijksmuseum\nno definition",
			$this->textIndexedWith(
				TestStatement::build( property: 'City', value: 'Amsterdam' ),
				$this->museumSchemaWith( 'City', TestProperty::buildNumber() )
			)
		);
	}

	public function testNoDefinitionIsPassedWhenTheSchemaIsGone(): void {
		$this->assertSame(
			"Rijksmuseum\nno definition",
			$this->textIndexedWith( TestStatement::build( property: 'City', value: 'Amsterdam' ), null )
		);
	}

	public function testALabelLineNamesNoProperty(): void {
		$this->assertEquals(
			[ new SubjectSearchLine( null, 'Rijksmuseum' ) ],
			$this->linesFor( TestSubject::build( label: 'Rijksmuseum' ) )
		);
	}

	public function testAValueLineNamesTheStatementsProperty(): void {
		$this->assertEquals(
			[
				new SubjectSearchLine( null, 'Rijksmuseum' ),
				new SubjectSearchLine( 'City', 'Amsterdam' ),
			],
			$this->linesFor(
				$this->subjectWith( 'Rijksmuseum', TestStatement::build( property: 'City', value: 'Amsterdam' ) )
			)
		);
	}

	public function testEveryValueOfAStatementIsItsOwnLine(): void {
		$this->assertEquals(
			[
				new SubjectSearchLine( 'Website', 'https://pro.wiki' ),
				new SubjectSearchLine( 'Website', 'https://professional.wiki' ),
			],
			$this->linesFor( $this->subjectWith(
				null,
				TestStatement::build(
					property: 'Website',
					value: new StringValue( 'https://pro.wiki', 'https://professional.wiki' ),
					propertyType: 'url'
				)
			) )
		);
	}

	public function testASubjectWithoutSearchableContentHasNoLines(): void {
		$this->assertSame( [], $this->linesFor( TestSubject::build( label: null ) ) );
	}

	/**
	 * @return SubjectSearchLine[]
	 */
	private function linesFor( Subject $subject ): array {
		return $this->newBuilder( $this->coreTypes() )->linesOf( $subject );
	}

	private function museumSchemaWith( string $propertyName, PropertyDefinition $definition ): Schema {
		return TestSchema::build(
			name: self::SCHEMA_NAME,
			properties: new PropertyDefinitions( [ $propertyName => $definition ] )
		);
	}

	/**
	 * The text built for a Subject of {@see self::SCHEMA_NAME} holding $statement, whose Property Type
	 * indexes the Property Definition it is handed, so the result names the one that reached it.
	 */
	private function textIndexedWith( Statement $statement, ?Schema $schema ): string {
		return $this->newBuilder( $this->typesReportingTheDefinition(), $schema )->build(
			new PageSubjects(
				TestSubject::build(
					label: 'Rijksmuseum',
					schemaName: new SchemaName( self::SCHEMA_NAME ),
					statements: new StatementList( [ $statement ] )
				),
				new SubjectMap()
			)
		);
	}

	private function typesReportingTheDefinition(): PropertyTypeRegistry {
		$registry = new PropertyTypeRegistry();
		$registry->registerType( new DefinitionReportingPropertyType( TextType::NAME ) );

		return $registry;
	}

	private function coreTypes(): PropertyTypeRegistry {
		return PropertyTypeRegistry::withCoreTypes( TestSources::newSchemaReferenceParser() );
	}

	private function subjectWith( ?string $label, Statement ...$statements ): Subject {
		return TestSubject::build( label: $label, statements: new StatementList( $statements ) );
	}

	private function textForSubjects( Subject $mainSubject, Subject ...$otherSubjects ): string {
		return $this->newBuilder( $this->coreTypes() )->build(
			new PageSubjects( $mainSubject, new SubjectMap( ...$otherSubjects ) )
		);
	}

	private function newBuilder(
		PropertyTypeLookup $propertyTypes,
		?Schema $schema = null
	): SubjectSearchTextBuilder {
		return new SubjectSearchTextBuilder(
			$propertyTypes,
			TestSources::newSchemaResolver(
				$schema === null ? new InMemorySchemaLookup() : new InMemorySchemaLookup( $schema )
			)
		);
	}

}
