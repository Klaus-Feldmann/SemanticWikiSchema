<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\Content;

use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use AEWI\SWS\Application\Search\MissingMainDescription;
use AEWI\SWS\Domain\Page\PageSubjects;

/** Adds derived category metadata from the current subject slot, for any main content model. */
final class MainDescriptionCategory {
	public const MESSAGE = 'sws-entity-description-missing-category';

	public static function apply( PageSubjects $subjects, ParserOutput $output ): void {
		if ( !MissingMainDescription::appliesTo( $subjects ) ) {
			return;
		}
		$name = trim( wfMessage( self::MESSAGE )->inContentLanguage()->text() );
		if ( $name === '' || $name === '-' ) {
			return;
		}
		$category = Title::newFromText( $name, 14 );
		if ( $category !== null && $category->getNamespace() === 14 && !$category->isExternal() ) {
			$output->addCategory( $category->getDBkey(), '' );
		}
	}
}
