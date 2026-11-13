<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Identity;

use ProfessionalWiki\SWS\Domain\Identity\{IdentityKind, LegacyIdentity, PersistentId};

interface IdentityRegistry {
	/** Caller supplies a stable, actor/operation-scoped request key, reused only for retries. */
	public function reserve( IdentityKind $kind, string $requestKey ): PersistentId;
	/** Resolve a caller-scoped reservation; an ID alone never grants page-write rights. */
	public function findReservation( IdentityKind $kind, string $requestKey ): ?PersistentId;
	public function reserveForOwner( IdentityKind $kind, string $requestKey, string $owner ): PersistentId;
	public function isReservedForOwner( PersistentId $id, string $owner ): bool;
	/** A fresh reservation only: a deleted entity is restored, never created again under its old ID. */
	public function canCreate( PersistentId $id, string $owner ): bool;
	/** Internal revision/index write; participates in the same primary transaction. */
	public function markUsed( PersistentId $id ): void;
	public function findLegacy( LegacyIdentity $legacy ): ?PersistentId;
	/** Atomic and idempotent assignment. Does not create or change a wiki page. */
	public function assignLegacy( LegacyIdentity $legacy ): PersistentId;
	/** Additional alias, never silently retargeted. Target must already be reserved locally. */
	public function linkLegacy( LegacyIdentity $legacy, PersistentId $target ): void;
}
