<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki\Subject;

use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;

class SubjectContentDataSerializer {

	/**
	 * Mirrors @see SubjectContentDataDeserializer::deserialize
	 */
	public function serialize( PageSubjects $contentData ): string {
		return json_encode(
			( $this->formatVersion( $contentData ) === 2 ? [ 'formatVersion'=>2 ] : [] ) + [
				'mainSubject' => $contentData->getMainSubject()?->id->text,
				'subjects' => $this->serializeSubjects( $contentData->getAllSubjects() ),
			],
			JSON_PRETTY_PRINT
		);
	}

	private function formatVersion( PageSubjects $page ): int {
		foreach ( $page->getAllSubjects()->asArray() as $subject ) {
			if ( $subject->id->isSequential() || $subject->getIdentity() !== null ) { return 2; }
		}
		return 1;
	}

	private function serializeSubjects( SubjectMap $subjectMap ): object {
		$serializedSubjects = [];

		foreach ( $subjectMap->asArray() as $subject ) {
			$serialized = [];
			if ( $subject->getIdentity() !== null ) { $serialized += $subject->getIdentity()->toJson(); }
			if ( $subject->id->isSequential() && $subject->getIdentity() === null ) {
				throw new \InvalidArgumentException( 'New entities require identity metadata' );
			}

			// A Subject nobody named stores no label.
			if ( $subject->label !== null ) {
				$serialized['label'] = $subject->label->text;
			}

			if ( $subject->getFieldKeyVersion() !== 0 ) { $serialized['fieldKeyVersion'] = $subject->getFieldKeyVersion(); }
			if ( $subject->getFieldNotes() !== [] ) { $serialized['fieldNotes'] = (object)$subject->getFieldNotes(); }
			if ( $subject->getSourceSnapshot() !== null ) {
				$serialized['sourceSnapshot'] = $subject->getSourceSnapshot()->toJson();
			}
            $serialized['schema'] = $subject->getSchemaReference()->toJson();
			$serialized['statements'] = $this->serializeStatementList( $subject->getStatements() );

			$serializedSubjects[$subject->id->text] = $serialized;
		}

		return (object)$serializedSubjects;
	}

	private function serializeStatementList( StatementList $statementList ): object {
		return (object)array_map(
			$this->serializeStatement( ... ),
			$statementList->asArray()
		);
	}

	private function serializeStatement( Statement $statement ): array {
		return [
			'propertyType' => $statement->getPropertyType(),
			'value' => $statement->getValue()->toScalars(),
		];
	}

}
