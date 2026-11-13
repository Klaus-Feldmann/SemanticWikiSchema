<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Source;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;

/** Raw JSON edits may show server data, but may neither forge nor clear it. */
final class SourceSnapshotEditGuard {

	public static function preserve( PageSubjects $proposed, PageSubjects $previous ): void {
		foreach ( $proposed->getAllSubjects()->asArray() as $subject ) {
			$prior = $previous->getAllSubjects()->getSubject( $subject->id );
			$stored = $prior?->getSourceSnapshot();
			$offered = $subject->getSourceSnapshot();
			if ( $offered !== null && $offered->toJson() !== $stored?->toJson() ) {
				throw new InvalidArgumentException( 'Source snapshots are managed by the server' );
			}
			if ( $stored !== null && !$prior->getSchemaReference()->equals( $subject->getSchemaReference() ) ) {
				throw new InvalidArgumentException( 'Cannot reassign a cached Subject to another Schema' );
			}
			$subject->setSourceSnapshot( $stored );
		}
	}

}
