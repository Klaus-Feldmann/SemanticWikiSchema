<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Domain\Relation;

use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Domain\Subject\SubjectId;

readonly class Relation {

	public function __construct(
		public RelationId $id,
		public SubjectId $targetId,
	) {
	}

	public function withPropertyName( PropertyName $propertyName ): TypedRelation {
		return new TypedRelation(
			id: $this->id,
			targetId: $this->targetId,
			propertyName: $propertyName,
		);
	}

}
