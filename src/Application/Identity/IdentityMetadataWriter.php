<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Identity;

use AEWI\SWS\Domain\Identity\LocalizedIdentity;
use AEWI\SWS\Domain\Subject\Subject;

final class IdentityMetadataWriter {
	public static function initialize( Subject $subject, string $language, ?array $metadata ): Subject {
		if ( $metadata !== null ) { $subject->setIdentity( LocalizedIdentity::fromJson( $metadata ) ); }
		elseif ( $subject->id->isSequential() && $subject->getIdentity() === null ) {
			$subject->setIdentity( new LocalizedIdentity( $language, $subject->getLabel() === null ? [] : [ $language=>$subject->getLabel()->text ] ) );
		}
		return $subject;
	}
}
