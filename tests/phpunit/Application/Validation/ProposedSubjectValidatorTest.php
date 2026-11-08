<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\SWS\Application\Validation\SubjectValidator;
use ProfessionalWiki\SWS\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Value\RelationValue;
use ProfessionalWiki\SWS\Tests\Data\TestRelation;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectLookup;
use ProfessionalWiki\SWS\Tests\Data\TestSources;
use ProfessionalWiki\SWS\Tests\Data\TestSchema;

/**
 * @covers \ProfessionalWiki\SWS\Application\Validation\ProposedSubjectValidator
 */
class ProposedSubjectValidatorTest extends TestCase {

	private const string SCHEMA_NAME = 'Person';

	private InMemorySchemaLookup $schemaLookup;

	protected function setUp(): void {
		$this->schemaLookup = new InMemorySchemaLookup();
	}

	private function newValidator(): ProposedSubjectValidator {
		return new ProposedSubjectValidator(
			schemaResolver: TestSources::newSchemaResolver( $this->schemaLookup ),
			subjectValidator: new SubjectValidator(
				propertyTypeLookup: TestSources::newPropertyTypeRegistry(),
				subjectLookup: new InMemorySubjectLookup(),
				sourceRegistry: TestSources::newRegistry(),
			),
		);
	}

	private function registerSchemaWithAge( bool $required ): void {
		$this->schemaLookup->updateSchema( TestSchema::build(
			name: new SchemaName( self::SCHEMA_NAME ),
			description: '',
			properties: new PropertyDefinitions( [
				'Age' => NumberProperty::fromPartialJson(
					new PropertyCore( description: '', required: $required, default: null ),
					[ 'minimum' => null, 'maximum' => null, 'precision' => null ],
				),
			] )
		) );
	}

	private function newSubject( string $schemaName = self::SCHEMA_NAME ): Subject {
		return TestSubject::build(
			label: 'John Doe',
			schemaName: new SchemaName( $schemaName ),
		);
	}

	public function testReturnsNoViolationsForValidSubject(): void {
		$this->registerSchemaWithAge( required: false );

		$this->assertSame( [], $this->newValidator()->validate( $this->newSubject() ) );
	}

	public function testReturnsViolationFromSchemaForMissingRequiredProperty(): void {
		$this->registerSchemaWithAge( required: true );

		$violations = $this->newValidator()->validate( $this->newSubject() );

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertSame( 'Age', $violations[0]->propertyName?->text );
	}

	public function testReturnsSchemaNotFoundViolationWhenSchemaIsMissing(): void {
		$this->registerSchemaWithAge( required: true );

		$violations = $this->newValidator()->validate( $this->newSubject( 'UnregisteredSchema' ) );

		$this->assertCount( 1, $violations );
		$this->assertSame( 'schema-not-found', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
		$this->assertSame( [ 'UnregisteredSchema' ], $violations[0]->args );
	}

	/**
	 * A missing Schema costs the Schema-scoped checks, not the reachability one: the target would be
	 * written unreadable whether or not the Schema turns up later.
	 */
	public function testStillRejectsAnUnresolvableRelationTargetWhenTheSchemaIsMissing(): void {
		$subject = TestSubject::build(
			label: 'John Doe',
			schemaName: new SchemaName( 'UnregisteredSchema' ),
			statements: new StatementList( [
				new Statement(
					new PropertyName( 'Links' ),
					'relation',
					new RelationValue( TestRelation::build( targetId: 'neverinstalled:Q42' ) )
				),
			] ),
		);

		$violations = $this->newValidator()->validate( $subject );

		$this->assertSame(
			[ 'schema-not-found', 'relation-target-unresolvable-source' ],
			array_map( static fn ( $violation ): string => $violation->code, $violations )
		);
		$this->assertTrue( $violations[1]->isBlocking() );
	}

}
