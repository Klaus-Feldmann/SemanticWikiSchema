<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Domain\Relation;

use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Domain\Subject\SubjectId;

readonly class TypedRelation {

	public function __construct(
		public RelationId $id,
		public SubjectId $targetId,
		public PropertyName $propertyName,
	) {
	}

}
