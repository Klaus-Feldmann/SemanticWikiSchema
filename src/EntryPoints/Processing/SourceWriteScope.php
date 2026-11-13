<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;

/** Request-local overlay during the internal revision write; never changes RequestContext authority. */
final class SourceWriteScope {

	private static ?array $active = null;

	public static function run( Title $title, PageSubjects $subjects, callable $write ): mixed {
		if ( self::$active !== null ) { throw new ProcessingIncomplete( 'Recursive source snapshot write refused.' ); }
		self::$active = [ $title, $subjects ];
		try { return $write(); } finally { self::$active = null; }
	}

	public static function subjectsFor( Title $title ): ?PageSubjects {
		return self::$active !== null && self::$active[0]->isSamePageAs( $title ) ? self::$active[1] : null;
	}

}
