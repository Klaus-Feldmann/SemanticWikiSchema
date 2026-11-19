<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application;

use AEWI\SWS\Domain\Subject\SubjectId;
use AEWI\SWS\Infrastructure\IdGenerator;

/**
 * Mints batches of fresh, mutually distinct Subject IDs for client-side wiring of interlinked
 * imports. Generation stays server-owned so the ID scheme can evolve without breaking importers.
 */
readonly class SubjectIdMinter {

	public function __construct(
		private IdGenerator $idGenerator,
		private ?\AEWI\SWS\Application\Identity\IdentityRegistry $identities = null,
		private string $owner = '',
	) {
	}

	/**
	 * @return list<SubjectId> exactly $count distinct ids
	 */
	public function mint( int $count, ?string $requestKey = null ): array {
		if ( $count < 1 || $count > 1000 ) { throw new \InvalidArgumentException( 'Invalid ID batch size' ); }
		if ( $this->identities !== null ) {
			$requestKey ??= bin2hex( random_bytes( 16 ) );
			if ( strlen( $requestKey ) > 120 || $requestKey === '' ) { throw new \InvalidArgumentException( 'Invalid batch request key' ); }
			$ids = [];
			for ( $i = 0; $i < $count; $i++ ) {
				$id = $this->identities->reserveForOwner( \AEWI\SWS\Domain\Identity\IdentityKind::Entity, 'batch:' . $requestKey . ':' . $count . ':' . $i, $this->owner );
				$ids[] = new SubjectId( $id->text );
			}
			return $ids;
		}
		$ids = [];

		while ( count( $ids ) < $count ) {
			$id = SubjectId::createNew( $this->idGenerator );
			$ids[$id->text] = $id;
		}

		return array_values( $ids );
	}

}
