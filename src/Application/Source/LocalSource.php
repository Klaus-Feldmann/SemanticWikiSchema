<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Source;

use Closure;
use ProfessionalWiki\SWS\Application\SchemaLookup;
use ProfessionalWiki\SWS\Application\SubjectLookup;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Source\Source;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectIdList;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;

/**
 * This wiki's own Subjects and Schemas: the revision slot (ADR 4) and the Schema namespace, reached as a
 * {@see Source}. It registers under the MediaWiki Wiki ID, which is what a bare Subject id resolves to.
 */
readonly class LocalSource implements Source {

	/**
	 * @param Closure(): SubjectLookup $subjectLookup Built per call rather than up front, so that
	 *   resolving a Schema through this Source costs nothing that reading a Subject needs.
	 */
	public function __construct(
		private Closure $subjectLookup,
		private SchemaLookup $schemaLookup,
		private string $baseUri,
	) {
	}

	public function getSubject( SubjectId $id ): ?Subject {
		return ( $this->subjectLookup )()->getSubject( $id );
	}

	public function getSubjects( SubjectIdList $ids ): SubjectMap {
		return ( $this->subjectLookup )()->getSubjects( $ids );
	}

	public function getSchema( SchemaName $name ): ?Schema {
		return $this->schemaLookup->getSchema( $name );
	}

	public function isEditable(): bool {
		return true;
	}

	public function isValidLocalId( string $localId ): bool {
		return SubjectId::isValidLocalId( $localId );
	}

	public function getBaseUri(): string {
		return $this->baseUri;
	}

}
