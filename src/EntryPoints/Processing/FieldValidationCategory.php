<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\Processing;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use AEWI\SWS\Domain\Page\PageSubjects;

final class FieldValidationCategory {
	public const MESSAGE = 'sws-field-validation-category';
	private const PROPERTY = 'sws-field-validation-revision';

	/** Use the saved Subjects and their schemas during secondary data updates. */
	public static function apply(
		ParserOutput $output, PageSubjects $subjects, int $revisionId, callable $validate
	): void {
		$category = self::category();
		if ( $category === null ) {
			return;
		}
		$all = $subjects->getAllSubjects();
		foreach ( $all->asArray() as $subject ) {
			if ( !$subject->getSchemaReference()->isLocal() ) {
				continue;
			}
			foreach ( $validate( $subject, $all ) as $violation ) {
				if ( $violation->propertyName !== null && $violation->code !== 'unregistered-type' ) {
					$output->addCategory( $category->getDBkey(), '' );
					$output->setPageProperty( self::PROPERTY, (string)$revisionId );
					return;
				}
			}
		}
	}

	/** Reuse the published decision in ordinary views without validating on every page parse. */
	public static function showPublished( ParserOutput $output, Title $title, ?int $revisionId ): void {
		$category = self::category();
		if ( $category === null || !$revisionId ) {
			return;
		}
		$properties = MediaWikiServices::getInstance()->getPageProps()->getProperties( $title, self::PROPERTY );
		if ( (string)( $properties[$title->getArticleID()] ?? '' ) === (string)$revisionId ) {
			$output->addCategory( $category->getDBkey(), '' );
		}
	}

	private static function category(): ?Title {
		$name = trim( wfMessage( self::MESSAGE )->inContentLanguage()->text() );
		if ( $name === '' || $name === '-' ) {
			return null;
		}
		$title = Title::newFromText( $name, 14 );
		return $title !== null && $title->getNamespace() === 14 && !$title->isExternal() ? $title : null;
	}
}
