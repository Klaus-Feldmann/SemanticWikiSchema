<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Queries\ValidateSubject;

use AEWI\SWS\Application\Schema\Exception\SchemaNotFoundException;
use AEWI\SWS\Application\Source\SchemaResolver;
use AEWI\SWS\Application\StatementListBuilder;
use AEWI\SWS\Application\Validation\SubjectValidator;
use AEWI\SWS\Domain\Schema\SchemaReference;
use AEWI\SWS\Domain\Schema\SchemaReferenceParser;
use AEWI\SWS\Domain\Validation\Violation;

readonly class ValidateSubjectQuery {

	public function __construct(
		private SchemaResolver $schemaResolver,
		private SubjectValidator $subjectValidator,
		private StatementListBuilder $statementListBuilder,
		private SchemaReferenceParser $schemaReferenceParser,
	) {
	}

	/**
	 * @param mixed $schema A Schema reference as stored: a name for a Schema of this wiki, or a
	 *  `{source, name}` array for one from elsewhere (ADR 23).
	 * @param array<string, mixed> $statements
	 *
	 * @return Violation[]
	 *
	 * @throws \InvalidArgumentException when the reference is malformed (empty name etc.).
	 * @throws SchemaNotFoundException when no Source offers the Schema.
	 */
	public function validate( mixed $schema, array $statements ): array {
		$reference = $this->schemaReferenceParser->fromJson( $schema );
		$resolvedSchema = $this->schemaResolver->getSchema( $reference );

		if ( $resolvedSchema === null ) {
			throw SchemaNotFoundException::forName( $reference->getText() );
		}

		return $this->subjectValidator->validate(
			$this->statementListBuilder->build( $statements ),
			$resolvedSchema,
		);
	}

}
