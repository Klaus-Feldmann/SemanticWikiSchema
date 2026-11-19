<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Data;

use AEWI\SWS\Application\HostSubjectBuilder;
use AEWI\SWS\Application\PageSubjectsLookup;
use AEWI\SWS\Application\SchemaLookup;
use AEWI\SWS\Application\SubjectRepository;
use AEWI\SWS\Application\SubjectResponseItemFactory;
use AEWI\SWS\Domain\Page\DependentSubjectRules;
use AEWI\SWS\Tests\TestDoubles\InMemorySchemaLookup;

class TestNaming {

	public static function newResponseItemFactory(
		SubjectRepository $subjectRepository,
		SchemaLookup $schemaLookup = new InMemorySchemaLookup()
	): SubjectResponseItemFactory {
		return new SubjectResponseItemFactory(
			new PageSubjectsLookup( $subjectRepository ),
			self::newHostSubjectBuilder( $schemaLookup )
		);
	}

	public static function newHostSubjectBuilder( SchemaLookup $schemaLookup = new InMemorySchemaLookup() ): HostSubjectBuilder {
		return new HostSubjectBuilder( self::newDependentSubjectRules( $schemaLookup ) );
	}

	public static function newDependentSubjectRules( SchemaLookup $schemaLookup = new InMemorySchemaLookup() ): DependentSubjectRules {
		return new DependentSubjectRules( TestSources::newSchemaResolver( $schemaLookup ) );
	}

}
