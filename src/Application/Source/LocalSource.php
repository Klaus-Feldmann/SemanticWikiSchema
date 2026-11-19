<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Source;

use Closure;
use AEWI\SWS\Application\SchemaLookup;
use AEWI\SWS\Application\SubjectLookup;
use AEWI\SWS\Domain\Schema\Schema;
use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\Domain\Source\Source;
use AEWI\SWS\Domain\Subject\Subject;
use AEWI\SWS\Domain\Subject\SubjectId;
use AEWI\SWS\Domain\Subject\SubjectIdList;
use AEWI\SWS\Domain\Subject\SubjectMap;

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
