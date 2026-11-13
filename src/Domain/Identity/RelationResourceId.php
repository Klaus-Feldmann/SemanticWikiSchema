<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Identity;

final readonly class RelationResourceId extends PersistentId {
	protected const string PREFIX = 'R';

	public function kind(): IdentityKind {
		return IdentityKind::Relation;
	}
}
