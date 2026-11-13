<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Source;

use MediaWiki\Context\IContextSource;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\PageDependencyRecorder;
use ProfessionalWiki\SWS\Application\Source\{ SourceDataMode, StoredSourceValues };
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Subject\Subject;

/** Shared source contract for direct readers and SMW. No retrieval in store mode. */
final class SourceFieldLookup {
	public static function read( IContextSource $context, Title $title, Subject $subject, Schema $schema,
		string $language, ?PageDependencyRecorder $dependencies = null, ?PublishedSubjectsLookup $published = null ): array {
		$dependencies?->recordDependencyOn( $title );
		$dependencies?->recordDependencyOnSchema( $subject->getSchemaReference() );
		if ( SourceDataMode::usesStore() ) {
			if ( class_exists( \SWSBaseBridge\StoredSourceLookup::class ) ) {
				return ( new \SWSBaseBridge\StoredSourceLookup( $context, $published ) )->fields( $title,
					[ 'subject' => $subject->id->text, 'language' => $language ], $dependencies, $subject, $schema );
			}
			// A disabled optional bridge cannot revalidate local Wikibase permissions.
			$data = StoredSourceValues::read( $subject, $schema, $language, $title->getArticleID() );
			foreach ( $data['fields'] as &$field ) {
				if ( $field['source'] === 'wikibase-local' ) {
					$field = [ 'name' => $field['name'], 'source' => $field['source'], 'propertyId' => $field['propertyId'],
						'propertyType' => $field['propertyType'], 'status' => 'unavailable', 'sourceStatus' => 'unavailable',
						'reason' => 'source-bridge-unavailable', 'values' => [], 'storageMode' => 'store', 'recorded' => false ];
				}
			}
			return $data;
		}
		if ( !class_exists( \SWSBaseBridge\ExternalValuesLookup::class ) ) {
			throw new \RuntimeException( 'SWS source bridge unavailable' );
		}
		return ( new \SWSBaseBridge\ExternalValuesLookup( $context ) )->read( $title,
			[ 'pageid' => $title->getArticleID(), 'subject' => $subject->id->text, 'language' => $language, 'presentation' => false ],
			$dependencies, $subject );
	}
}
