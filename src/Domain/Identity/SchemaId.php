<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Identity;

final readonly class SchemaId extends PersistentId {
	protected const string PREFIX = 'S';

	public function kind(): IdentityKind {
		return IdentityKind::Schema;
	}
}
