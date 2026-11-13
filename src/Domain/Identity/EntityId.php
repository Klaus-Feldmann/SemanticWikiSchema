<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Identity;

final readonly class EntityId extends PersistentId {
	protected const string PREFIX = 'E';

	public function kind(): IdentityKind {
		return IdentityKind::Entity;
	}
}
