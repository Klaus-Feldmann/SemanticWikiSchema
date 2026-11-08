<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\Queries\ValidateSubject;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ProfessionalWiki\SWS\Application\Queries\ValidateSubject\ValidateSubjectQuery;
use ProfessionalWiki\SWS\Application\Schema\Exception\SchemaNotFoundException;
use ProfessionalWiki\SWS\Application\Source\SchemaResolver;
use ProfessionalWiki\SWS\Application\StatementListBuilder;
use ProfessionalWiki\SWS\Application\Validation\SubjectValidator;
use ProfessionalWiki\SWS\Infrastructure\ProductionIdGenerator;
use ProfessionalWiki\SWS\Tests\Data\TestSchema;
use ProfessionalWiki\SWS\Tests\Data\TestSources;
use ProfessionalWiki\SWS\Tests\Data\TestSubjectIds;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySource;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectLookup;

/**
 * @covers \ProfessionalWiki\SWS\Application\Queries\ValidateSubject\ValidateSubjectQuery
 */
class ValidateSubjectQueryTest extends TestCase {

	private const string LOCAL_SCHEMA_NAME = 'Company';
	private const string SOURCED_SCHEMA_NAME = 'Widget';

	public function testValidatesAgainstASchemaOfThisWiki(): void {
		$violations = $this->newQuery()->validate( self::LOCAL_SCHEMA_NAME, [] );

		$this->assertSame( [], $violations );
	}

	/**
	 * The reference the caller sends is read as a Subject's own is, so validating against the Schema
	 * a spelling names agrees with validating the Subject that ends up stored under it.
	 */
	public function testValidatesAgainstTheSchemaAnotherSpellingNames(): void {
		$violations = $this->newQuery( [ 'company' => self::LOCAL_SCHEMA_NAME ] )->validate( 'company', [] );

		$this->assertSame( [], $violations );
	}

	public function testValidatesAgainstASchemaOfAnotherSource(): void {
		$violations = $this->newQuery()->validate(
			[ 'source' => 'catalog', 'name' => self::SOURCED_SCHEMA_NAME ],
			[]
		);

		$this->assertSame( [], $violations );
	}

	public function testReportsASchemaNoSourceOffers(): void {
		$this->expectException( SchemaNotFoundException::class );

		$this->newQuery()->validate( [ 'source' => 'catalog', 'name' => 'NoSuchSchema' ], [] );
	}

	public function testReportsAReferenceToAnUnregisteredSource(): void {
		$this->expectException( SchemaNotFoundException::class );

		$this->newQuery()->validate(
			[ 'source' => 'neverinstalled', 'name' => self::SOURCED_SCHEMA_NAME ],
			[]
		);
	}

	/**
	 * @param array<string, string> $schemaNames Name as written => the name of the Schema it names.
	 */
	private function newQuery( array $schemaNames = [] ): ValidateSubjectQuery {
		return new ValidateSubjectQuery(
			schemaResolver: $this->newSchemaResolver(),
			subjectValidator: new SubjectValidator(
				propertyTypeLookup: TestSources::newPropertyTypeRegistry(),
				subjectLookup: new InMemorySubjectLookup(),
				sourceRegistry: TestSources::newRegistry(),
			),
			statementListBuilder: new StatementListBuilder(
				TestSources::newPropertyTypeRegistry(),
				new ProductionIdGenerator(),
				TestSubjectIds::newParser(),
			),
			schemaReferenceParser: TestSources::newSchemaReferenceParser( $schemaNames ),
		);
	}

	private function newSchemaResolver(): SchemaResolver {
		$catalog = new InMemorySource();
		$catalog->addSchema( TestSchema::build( name: self::SOURCED_SCHEMA_NAME ) );

		$registry = TestSources::newRegistryWithLocalSchemas(
			new InMemorySchemaLookup( TestSchema::build( name: self::LOCAL_SCHEMA_NAME ) )
		);
		$registry->registerSource( 'catalog', $catalog );

		return new SchemaResolver( $registry, new NullLogger() );
	}

}
