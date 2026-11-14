<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectIdInUseException;
use ProfessionalWiki\SWS\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceParser;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceResolver;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;

readonly class DependentSubjectBuilder {

	public function __construct(
		private NewSubjectIdResolver $newSubjectIdResolver,
		private SchemaReferenceParser $schemaReferenceParser,
		private SchemaReferenceResolver $schemaResolver,
		private StatementListBuilder $statementListBuilder,
		private DependentSubjectRules $dependentSubjectRules,
		private string $identityLanguage = 'en',
	) {
	}

	/**
	 * @param array<mixed> $items Each an object with an `id`, a `schema` and `statements`.
	 * @param PageSubjects $pageSubjects The page's Subjects before the write.
	 *
	 * @throws InvalidArgumentException When an item is malformed, repeats an id, or names a Schema that
	 *   does not declare its Subjects dependent.
	 * @throws SubjectIdInUseException When an item takes the id of another Subject, except a Dependent Subject
	 *   of the same Schema that the Host Subject reaches.
	 */
	public function build( array $items, SubjectId $hostSubjectId, PageSubjects $pageSubjects ): SubjectMap {
		$subjects = new SubjectMap();
		$replaceableSubjects = $this->replaceableSubjects( $hostSubjectId, $pageSubjects );

		foreach ( $items as $item ) {
			$subject = $this->buildSubject( $item );

			if ( $subjects->hasSubject( $subject->id ) || $subject->id->equals( $hostSubjectId ) ) {
				throw new InvalidArgumentException( "dependentSubjects holds the Subject ID {$subject->id->text} twice" );
			}

			if ( $this->takesTheIdOfAnotherSubject( $subject, $pageSubjects, $replaceableSubjects ) ) {
				throw new SubjectIdInUseException( $subject->id );
			}

			$priorSubject = $pageSubjects->getAllSubjects()->getSubject( $subject->id );
			$subject->setSourceSnapshot( $priorSubject?->getSourceSnapshot() );
			if ( \ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity::fromRequest( $item ) === null && $priorSubject?->getIdentity() !== null ) {
				$subject->setIdentity( $priorSubject->getIdentity()->withDefaultLabel( $subject->getLabel()?->text ) );
			}
			if ( !array_key_exists( 'fieldNotes', $item ) ) {
                $prior = $pageSubjects->getAllSubjects()->getSubject( $subject->id );
                if ( $prior !== null ) { $subject->setFieldNotes( $prior->getFieldNotes() ); }
            }
            $subject->setFieldNotes( \ProfessionalWiki\SWS\Domain\FieldNotes::forSchema( $subject->getFieldNotes(), $pageSubjects->getAllSubjects()->getSubject( $subject->id )?->getFieldNotes() ?? [], $subject->getFieldNotes() === [] ? null : $this->schemaResolver->getSchema( $subject->getSchemaReference() ) ) );
            $subjects->addOrUpdateSubject( $subject );
		}

		return $subjects;
	}

	private function replaceableSubjects( SubjectId $hostSubjectId, PageSubjects $pageSubjects ): SubjectMap {
		$storedHostSubject = $pageSubjects->getAllSubjects()->getSubject( $hostSubjectId );

		if ( $storedHostSubject === null ) {
			return new SubjectMap();
		}

		return $this->dependentSubjectRules->dependentSubjectsReachedFrom( $storedHostSubject, $pageSubjects );
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private function buildSubject( mixed $item ): Subject {
		if ( is_array( $item ) && array_key_exists( 'sourceSnapshot', $item ) ) {
			throw new InvalidArgumentException( 'Source snapshots are managed by the server, not dependentSubjects requests' );
		}
		if ( !is_array( $item ) || !is_string( $item['id'] ?? null ) || !( is_string( $item['schema'] ?? null ) || is_array( $item['schema'] ?? null ) )
			|| !is_array( $item['statements'] ?? null ) || !is_string( $item['label'] ?? '' ) ) {
			throw new InvalidArgumentException(
				'Each item of dependentSubjects must be an object with a string id, a string schema and statements'
			);
		}

		$schemaReference = is_array( $item['schema'] ) ? $this->schemaReferenceParser->fromJson( $item['schema'] ) : $this->schemaReferenceParser->localName( $item['schema'] );
		if ( !$schemaReference->isLocal() ) { throw new InvalidArgumentException( 'Dependent entities must use a local Schema' ); }
		$schema = $this->schemaResolver->getSchema( $schemaReference );

		if ( $schema === null || !$schema->subjectsAreDependent() ) {
			throw new InvalidArgumentException(
				"dependentSubjects holds a Subject of the Schema {$schemaReference->getText()}, which does not declare its Subjects dependent"
			);
		}

		if ( isset( $item['fieldKeyVersion'] ) && $item['fieldKeyVersion'] !== $schema->getFieldKeyVersion() ) {
            throw new InvalidArgumentException( 'Dependent field keys changed; reload the editor' );
        }
        if ( $schema->getFieldKeyVersion() > 0 ) {
            foreach ( array_keys( $item['statements'] ) as $key ) {
                if ( !$schema->hasProperty( (string)$key ) ) { throw new InvalidArgumentException( 'Unknown dependent field key: ' . $key ); }
            }
        }

        // Kept, not dropped, so that the rule against labelled Dependent Subjects refuses it.
		$schemaReference = $schema->getReference();
		return \ProfessionalWiki\SWS\Application\Identity\IdentityMetadataWriter::initialize( new Subject(
			id: $this->newSubjectIdResolver->resolve( $item['id'] ),
			label: SubjectLabel::fromText( $item['label'] ?? null ),
			schema: $schemaReference,
			statements: $this->statementListBuilder->build( $item['statements'] ),
			fieldKeyVersion: $schema->getFieldKeyVersion(),
            fieldNotes: \ProfessionalWiki\SWS\Domain\FieldNotes::validate( $item['fieldNotes'] ?? [] ),
		), $this->identityLanguage, \ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity::fromRequest( $item ) );
	}

	private function takesTheIdOfAnotherSubject( Subject $subject, PageSubjects $pageSubjects, SubjectMap $replaceableSubjects ): bool {
		$replaceableSubject = $replaceableSubjects->getSubject( $subject->id );

		if ( $replaceableSubject !== null ) {
			return !$replaceableSubject->getSchemaReference()->equals( $subject->getSchemaReference() );
		}

		return $pageSubjects->getAllSubjects()->hasSubject( $subject->id )
			|| $this->newSubjectIdResolver->isInUse( $subject->id );
	}

}
