<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Data;

use ProfessionalWiki\SWS\Application\HostSubjectBuilder;
use ProfessionalWiki\SWS\Application\PageSubjectsLookup;
use ProfessionalWiki\SWS\Application\SchemaLookup;
use ProfessionalWiki\SWS\Application\SubjectRepository;
use ProfessionalWiki\SWS\Application\SubjectResponseItemFactory;
use ProfessionalWiki\SWS\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySchemaLookup;

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
