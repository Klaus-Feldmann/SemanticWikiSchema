<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Search;

use AEWI\SWS\Domain\Page\PageSubjects;

/** A maintenance hint for own main-entity metadata, never a schema validation error. */
final class MissingMainDescription {
	public static function appliesTo( PageSubjects $subjects ): bool {
		$main = $subjects->getMainSubject();
		if ( $main === null ) {
			return false;
		}
		foreach ( $main->getIdentity()?->descriptions ?? [] as $description ) {
			if ( trim( $description ) !== '' ) {
				return false;
			}
		}
		return true;
	}
}
