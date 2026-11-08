<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Search;

use ProfessionalWiki\SWS\Application\Source\SchemaResolver;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\Subject;

/**
 * What a page's Subjects add to the wiki's search index, one searchable string per line: each Subject's
 * label, plus what each Statement's Property Type says its value contributes. Property and Schema names
 * stay out: they match every page using them rather than the one being looked for.
 */
readonly class SubjectSearchTextBuilder {

	public function __construct(
		private PropertyTypeLookup $propertyTypeLookup,
		private SchemaResolver $schemaResolver,
	) {
	}

	public function build( PageSubjects $pageSubjects ): string {
		$texts = [];

		foreach ( $pageSubjects->getAllSubjects()->asArray() as $subject ) {
			foreach ( $this->linesOf( $subject ) as $line ) {
				$texts[] = $line->text;
			}
		}

		return implode( "\n", $texts );
	}

	/**
	 * @return SubjectSearchLine[]
	 */
	public function linesOf( Subject $subject ): array {
		$lines = [];
		$label = $subject->getLabel();

		if ( $label !== null ) {
			$lines[] = new SubjectSearchLine( null, $label->text );
		}

		$schema = $this->schemaResolver->getSchema( $subject->getSchemaReference() );

		foreach ( $subject->getStatements()->asArray() as $statement ) {
			$propertyType = $this->propertyTypeLookup->getType( $statement->getPropertyType() );

			if ( $propertyType === null ) {
				continue;
			}

			$propertyName = $statement->getPropertyName()->text;

			foreach ( $propertyType->searchText( $statement->getValue(), $this->definitionFor( $statement, $schema ) ) as $text ) {
				$lines[] = new SubjectSearchLine( $propertyName, $text );
			}
		}

		return $lines;
	}

	/**
	 * The definition the value was written under; null when the Schema is missing, lacks the property,
	 * or gives it another type.
	 */
	private function definitionFor( Statement $statement, ?Schema $schema ): ?PropertyDefinition {
		if ( $schema === null || !$schema->hasProperty( $statement->getPropertyName() ) ) {
			return null;
		}

		$definition = $schema->getProperty( $statement->getPropertyName() );

		return $definition->getPropertyType() === $statement->getPropertyType() ? $definition : null;
	}

}
