<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Search;

use AEWI\SWS\Application\Source\SchemaResolver;
use AEWI\SWS\Domain\Page\PageSubjects;
use AEWI\SWS\Domain\PropertyType\PropertyTypeLookup;
use AEWI\SWS\Domain\Schema\PropertyDefinition;
use AEWI\SWS\Domain\Schema\Schema;
use AEWI\SWS\Domain\Statement;
use AEWI\SWS\Domain\Subject\Subject;

/**
 * What a page's Subjects add to the wiki's search index, one searchable string per line: each Subject's
 * own localized labels and descriptions, plus what each Statement's Property Type says its value contributes. Property and Schema names
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

		$labels = $label === null ? [] : [ $label->text ];
		foreach ( $subject->getIdentity()?->labels ?? [] as $localizedLabel ) {
			$text = trim( $localizedLabel );
			if ( $text !== '' && !in_array( $text, $labels, true ) ) {
				$labels[] = $text;
				$lines[] = new SubjectSearchLine( null, $text );
			}
		}

		$descriptions = [];
		foreach ( $subject->getIdentity()?->descriptions ?? [] as $description ) {
			$text = trim( $description );
			if ( $text !== '' && !in_array( $text, $descriptions, true ) ) {
				$descriptions[] = $text;
				$lines[] = new SubjectSearchLine( null, $text, isDescription: true );
			}
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
