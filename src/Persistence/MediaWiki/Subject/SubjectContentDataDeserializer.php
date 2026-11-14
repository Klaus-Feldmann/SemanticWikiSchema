<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki\Subject;

use ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceParser;
use Psr\Log\LoggerInterface;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectIdParser;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Domain\Source\SourceSnapshot;

class SubjectContentDataDeserializer {

	public function __construct(
		private readonly StatementDeserializer $statementDeserializer,
		private readonly LoggerInterface $logger,
		private readonly SchemaReferenceParser $schemaReferenceParser,
	) {
	}

	/**
	 * Mirrors @see SubjectContentDataSerializer::serialize
	 */
	public function deserialize( string $json ): PageSubjects {
		$jsonArray = json_decode( $json, true );

		// MediaWiki's JSON content model accepts any valid JSON, so a slot can hold a scalar or a bare
		// string that no SWS write path produces. A read shows the Subjects it can rather than throw.
		if ( !is_array( $jsonArray ) ) {
			$jsonArray = [];
		}

		$version = $jsonArray['formatVersion'] ?? 1;
		if ( !in_array( $version, [1, 2], true ) ) { throw new \InvalidArgumentException( 'Unsupported subject content version' ); }
		if ( $version === 1 ) {
			foreach ( array_keys( $jsonArray['subjects'] ?? [] ) as $id ) {
				if ( str_starts_with( (string)$id, 'E' ) ) { throw new \InvalidArgumentException( 'E IDs require subject format version 2' ); }
			}
		}
		$subjects = $this->deserializeSubjects( $jsonArray );

		$mainSubject = $jsonArray['mainSubject'] ?? null;

		if ( !is_string( $mainSubject ) || !$this->isLocalId( $mainSubject, 'mainSubject' ) ) {
			return new PageSubjects(
				null,
				$subjects,
			);
		}

		$mainSubjectId = new SubjectId( $mainSubject );

		return new PageSubjects(
			$subjects->getSubject( $mainSubjectId ),
			$subjects->without( $mainSubjectId ),
		);
	}

	private function deserializeSubjects( array $jsonArray ): SubjectMap {
		$subjects = [];

		foreach ( $jsonArray['subjects'] ?? [] as $id => $subject ) {
			// A Subject id that looks like a decimal integer comes back from json_decode as an int key.
			$id = (string)$id;

			if ( $this->isLocalId( $id, 'subject key' ) ) {
				$subjects[] = $this->deserializeSubject( $id, $subject );
			}
		}

		return new SubjectMap( ...$subjects );
	}

	/**
	 * A local slot holds local Subjects only (ADR 23): a Subject from another Source is fetched from
	 * that Source, never stored here. Anything else in the slot is skipped rather than refused, because
	 * imported or hand-edited content must still render — and because the subject-to-page index reads
	 * these same keys as bare local ids (ADR 32), so admitting one here would put a Subject in the graph
	 * that nothing can find its page for.
	 */
	private function isLocalId( string $id, string $field ): bool {
		if ( SubjectId::isValidLocalId( $id ) ) {
			return true;
		}

		$this->logger->warning(
			"SWS: skipping $field '$id' in a Subject slot: not a Subject id of this wiki"
		);

		return false;
	}

	private function deserializeSubject( string $id, array $jsonArray ): Subject {
		if ( str_starts_with( $id, 'E' ) && !array_key_exists( 'identityLanguage', $jsonArray ) ) { throw new \InvalidArgumentException( 'New entity identity metadata missing' ); }
		return new Subject(
			id: new SubjectId( $id ),
			fieldKeyVersion: \ProfessionalWiki\SWS\Domain\Schema\FieldKeyChanges::version( $jsonArray['fieldKeyVersion'] ?? 0 ),
			label: $this->deserializeLabel( $jsonArray['label'] ?? null ),
			schema: $this->schemaReferenceParser->fromJson( $jsonArray['schema'] ),
			statements: $this->buildStatementList( $jsonArray ),
			identity: array_intersect( array_keys( $jsonArray ), [ 'identityLanguage', 'labels', 'descriptions' ] ) !== [] ? \ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity::fromJson( $jsonArray ) : null,
            fieldNotes: \ProfessionalWiki\SWS\Domain\FieldNotes::validate( $jsonArray['fieldNotes'] ?? [] ),
			sourceSnapshot: array_key_exists( 'sourceSnapshot', $jsonArray )
				? SourceSnapshot::fromJson( $jsonArray['sourceSnapshot'] ) : null,
		);
	}

	private function deserializeLabel( mixed $label ): ?SubjectLabel {
		return is_string( $label ) ? SubjectLabel::fromText( $label ) : null;
	}

	private function buildStatementList( array $jsonArray ): StatementList {
		$statements = [];

		foreach ( $jsonArray['statements'] ?? [] as $propertyName => $value ) {
			if ( $value !== null ) {
				// A property named like a decimal integer comes back from json_decode as an int key.
				$statements[] = $this->statementDeserializer->deserialize( (string)$propertyName, $value );
			}
		}

		return new StatementList( $statements );
	}

	/**
	 * The ids the JSON holds, read without deserializing, so that a Subject too broken to deserialize
	 * still has an id. Ids no caller could ask about are left out, since nothing can be answered with
	 * them.
	 *
	 * Static and dependency-free: update.php rebuilds the subject -> page index on wikis whose SWS
	 * configuration is not readable yet.
	 *
	 * @return string[]
	 */
	public static function deserializeSubjectIds( string $json ): array {
		// Cast so that content that is not a JSON object becomes an array without the key, leaving one
		// thing to check.
		$data = (array)json_decode( $json, true );
		$version = $data['formatVersion'] ?? 1;
		if ( !in_array( $version, [1, 2], true ) ) { return []; }
		$subjects = $data['subjects'] ?? null;

		if ( !is_array( $subjects ) ) {
			return [];
		}

		// A Subject id that looks like a decimal integer comes back from json_decode as an int key.
		// The local Source's grammar, and the same rule deserialize() applies: only local ids are
		// stored in a local slot, and a qualified id is longer than the index column, so admitting one
		// would truncate rather than record it.
		return array_values( array_filter(
			array_map( 'strval', array_keys( $subjects ) ),
			static fn ( $id ) => SubjectId::isValidLocalId( $id ) && ( $version === 2 || !str_starts_with( $id, 'E' ) )
		) );
	}

}
