<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Application\Validation\ProposedSubjectValidator;
use AEWI\SWS\Application\Validation\SubjectValidator;
use AEWI\SWS\Domain\Schema\Property\NumberProperty;
use AEWI\SWS\Domain\Schema\PropertyCore;
use AEWI\SWS\Domain\Schema\PropertyDefinitions;
use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\Domain\Subject\Subject;
use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Domain\Statement;
use AEWI\SWS\Domain\Subject\StatementList;
use AEWI\SWS\Domain\Value\RelationValue;
use AEWI\SWS\Tests\Data\TestRelation;
use AEWI\SWS\Tests\Data\TestSubject;
use AEWI\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use AEWI\SWS\Tests\TestDoubles\InMemorySubjectLookup;
use AEWI\SWS\Tests\Data\TestSources;
use AEWI\SWS\Tests\Data\TestSchema;

/**
 * @covers \AEWI\SWS\Application\Validation\ProposedSubjectValidator
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
