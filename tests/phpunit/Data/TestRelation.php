<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Data;

use AEWI\SWS\Domain\Relation\Relation;
use AEWI\SWS\Domain\Relation\RelationId;
use AEWI\SWS\Domain\Subject\SubjectId;
use AEWI\SWS\Tests\TestDoubles\IncrementalIdGenerator;

/**
 * @see \AEWI\SWS\Domain\Relation\Relation
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
