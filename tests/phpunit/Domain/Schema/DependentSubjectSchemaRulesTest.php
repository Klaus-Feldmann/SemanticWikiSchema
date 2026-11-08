<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\Schema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\DependentSubjectSchemaRules;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Tests\Data\TestProperty;
use ProfessionalWiki\SWS\Tests\Data\TestSchema;
use ProfessionalWiki\SWS\Tests\Data\TestSources;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\SWS\Domain\Schema\DependentSubjectSchemaRules
 */
class DependentSubjectSchemaRulesTest extends TestCase {

	private function newRules( Schema ...$storedSchemas ): DependentSubjectSchemaRules {
		return new DependentSubjectSchemaRules( TestSources::newSchemaResolver( new InMemorySchemaLookup(
			TestSchema::build( name: 'Birth', subjectsAreDependent: true ),
			TestSchema::build( name: 'Name', subjectsAreDependent: true ),
			TestSchema::build( name: 'Place' ),
			...$storedSchemas
		) ) );
	}

	/**
	 * @param string[] $targetSchemas
	 */
	private function personTargeting( array $targetSchemas, bool $subjectsAreDependent = false ): Schema {
		return TestSchema::build(
			name: 'Person',
			properties: new PropertyDefinitions( [
				'Occupation' => TestProperty::buildText(),
				'Related' => TestProperty::buildRelation( targetSchemas: $targetSchemas ),
			] ),
			subjectsAreDependent: $subjectsAreDependent
		);
	}

	public function testSavingAStandaloneSchemaAsDependentChangesItsDeclaration(): void {
		$rules = $this->newRules( TestSchema::build( name: 'Event' ) );

		$this->assertTrue( $rules->changesDeclaration( TestSchema::build( name: 'Event', subjectsAreDependent: true ) ) );
	}

	public function testSavingASchemaOfDependentSubjectsAsStandaloneChangesItsDeclaration(): void {
		$this->assertTrue( $this->newRules()->changesDeclaration( TestSchema::build( name: 'Birth' ) ) );
	}

	public function testSavingASchemaWithItsDeclarationUnchangedChangesNothing(): void {
		$this->assertFalse(
			$this->newRules()->changesDeclaration( TestSchema::build( name: 'Birth', description: 'Edited', subjectsAreDependent: true ) )
		);
	}

	public function testCreatingASchemaChangesNoDeclaration(): void {
		$this->assertFalse( $this->newRules()->changesDeclaration( TestSchema::build( name: 'Event', subjectsAreDependent: true ) ) );
	}

	public function testNamesARelationPropertyTargetingSchemasWithDependentAndWithStandaloneSubjects(): void {
		$this->assertSame(
			[ 'Related' ],
			$this->newRules()->propertiesMixingTargetSchemas( $this->personTargeting( [ 'Birth', 'Place' ] ) )
		);
	}

	public function testARelationPropertyTargetingOnlySchemasWithDependentSubjectsMixesNothing(): void {
		$this->assertSame(
			[],
			$this->newRules()->propertiesMixingTargetSchemas( $this->personTargeting( [ 'Birth', 'Name' ] ) )
		);
	}

	public function testTargetSchemasThatDoNotResolveAreLeftOut(): void {
		$this->assertSame(
			[],
			$this->newRules()->propertiesMixingTargetSchemas( $this->personTargeting( [ 'Birth', 'Missing' ] ) )
		);
	}

	public function testASchemaTargetingItselfCountsAsTheSchemaBeingSaved(): void {
		$section = TestSchema::build(
			name: 'Section',
			properties: new PropertyDefinitions( [
				'Related' => TestProperty::buildRelation( targetSchemas: [ 'Section', 'Place' ] ),
			] ),
			subjectsAreDependent: true
		);

		$this->assertSame( [ 'Related' ], $this->newRules()->propertiesMixingTargetSchemas( $section ) );
	}

}
