<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Identity;

enum IdentityKind: string {
	case Schema = 'schema';
	case Entity = 'entity';
	case Relation = 'relation';

	public function fromNumber( int $number ): PersistentId {
		return match ( $this ) {
			self::Schema => SchemaId::fromNumber( $number ),
			self::Entity => EntityId::fromNumber( $number ),
			self::Relation => RelationResourceId::fromNumber( $number ),
		};
	}
}
