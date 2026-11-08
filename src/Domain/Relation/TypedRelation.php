<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Relation;

use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;

readonly class TypedRelation {

	public function __construct(
		public RelationId $id,
		public SubjectId $targetId,
		public PropertyName $propertyName,
	) {
	}

}
