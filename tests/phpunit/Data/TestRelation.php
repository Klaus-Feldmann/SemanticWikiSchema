<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Data;

use ProfessionalWiki\SWS\Domain\Relation\Relation;
use ProfessionalWiki\SWS\Domain\Relation\RelationId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Tests\TestDoubles\IncrementalIdGenerator;

/**
 * @see \ProfessionalWiki\SWS\Domain\Relation\Relation
 */
class TestRelation {

	public const string DEFAULT_TARGET_ID = 'srt555555555555';

	public static function build(
		string|RelationId|null $id = null,
		string $targetId = self::DEFAULT_TARGET_ID, // TODO: also generate predictable IDs
	): Relation {
		return new Relation(
			self::defaultId( $id ),
			new SubjectId( $targetId ),
		);
	}

	private static function defaultId( string|RelationId|null $id ): RelationId {
		if ( $id === null ) {
			return self::newUniqueId();
		}

		if ( is_string( $id ) ) {
			return new RelationId( $id );
		}

		return $id;
	}

	public static function newUniqueId(): RelationId {
		static $generator = new IncrementalIdGenerator();
		return RelationId::createNew( $generator );
	}

}
