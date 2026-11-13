<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectIdParser;
use ProfessionalWiki\SWS\Infrastructure\IdGenerator;

/**
 * The id a Subject about to be created gets: the one the caller minted for it, or a fresh one where
 * they minted none. Minting up front is what lets relations between Subjects be wired before any of
 * them exists, so every create endpoint offers it and they all have to answer the same questions
 * about a supplied id — which is why they ask them here.
 */
readonly class NewSubjectIdResolver {

	public function __construct(
		private SubjectIdParser $subjectIdParser,
		private IdGenerator $idGenerator,
		private PageIdentifiersLookup $pageIdentifiersLookup,
		private ?\ProfessionalWiki\SWS\Application\Identity\IdentityRegistry $identities = null,
		private string $owner = '',
	) {
	}

	/**
	 * A Subject is only ever created in the local Source, so a caller-supplied id must be a local one.
	 *
	 * @throws InvalidArgumentException When $suppliedId is malformed or names another Source.
	 */
	public function resolve( ?string $suppliedId ): SubjectId {
		if ( $suppliedId === null ) {
			if ( $this->identities !== null ) {
				$id = $this->identities->reserveForOwner( \ProfessionalWiki\SWS\Domain\Identity\IdentityKind::Entity, bin2hex( random_bytes( 16 ) ), $this->owner );
				return new SubjectId( $id->text );
			}
			return SubjectId::createNew( $this->idGenerator );
		}

		$subjectId = $this->subjectIdParser->parseOrThrow( $suppliedId );

		if ( !$subjectId->isLocal() ) {
			throw new InvalidArgumentException( "Subjects can only be created in the local Source: '$suppliedId'" );
		}

		if ( $this->identities !== null && !$this->isInUse( $subjectId ) ) {
			if ( !$subjectId->isSequential() || !$this->identities->canCreate( new \ProfessionalWiki\SWS\Domain\Identity\EntityId( $subjectId->text ), $this->owner ) ) {
				throw new InvalidArgumentException( 'New entity IDs must be reserved by the current author' );
			}
		}
		return $subjectId;
	}

	/**
	 * Early unfiltered index check; the page-save index enforces unique hosts for E IDs on the
	 * primary database. A replica miss is not relied on as proof of availability.
	 */
	public function isInUse( SubjectId $id ): bool {
		return $this->pageIdentifiersLookup->getPageIdOfSubject( $id ) !== null;
	}

}
