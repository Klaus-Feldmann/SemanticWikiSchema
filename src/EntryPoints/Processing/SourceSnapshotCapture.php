<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Source\SourceSnapshot;
use ProfessionalWiki\SWS\SWSExtension;

/** Collects all configured bindings, including those overridden by author values. */
final class SourceSnapshotCapture {

	public static function hash( array $snapshots ): string {
		return hash( 'sha256', json_encode( array_map( static fn ( SourceSnapshot $snapshot ): array => $snapshot->toJson(), $snapshots ), JSON_THROW_ON_ERROR ) );
	}

	public static function languages(): array {
		$language = MediaWikiServices::getInstance()->getContentLanguage()->getCode();
		return array_values( array_unique( [ $language, ...SWSExtension::getInstance()->getDataLanguages() ] ) );
	}

	/** @return SourceSnapshot[] */
	public function capture( Title $title, RevisionRecord $revision, PageSubjects $subjects, User $user, \Closure $resolveSchema ): array {
		$context = new DerivativeContext( RequestContext::getMain() );
		$context->setUser( $user );
		$collector = \MediaWiki\Registration\ExtensionRegistry::getInstance()->isLoaded( 'SchemaBaseBridge' ) &&
			class_exists( \SWSBaseBridge\SourceSnapshotCollector::class ) ? new \SWSBaseBridge\SourceSnapshotCollector( $context ) : null;
		$lookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$snapshots = [];
		foreach ( $subjects->getAllSubjects()->asArray() as $subject ) {
			$schema = $resolveSchema( $subject->getSchemaReference() );
			$schemaTitle = Title::makeTitleSafe( SWSExtension::NS_SCHEMA, $subject->getSchemaName()->getText() );
			$schemaRevision = $lookup->getRevisionByTitle( $schemaTitle, 0, \Wikimedia\Rdbms\IDBAccessObject::READ_LATEST );
			if ( !$schemaRevision ) { throw new ProcessingIncomplete( 'Source schema disappeared during collection.', 'superseded' ); }
			$fallback = [];
			foreach ( $schema->getAllProperties()->asMap() as $name => $property ) {
				if ( !$property->isSchemaExecution() && ( $binding = $property->getExternalProperty() ) !== null ) {
					$fallback[] = [ 'name' => (string)$name, 'source' => $binding->source, 'propertyId' => $binding->id,
						'propertyType' => $property->getPropertyType(), 'status' => 'unavailable', 'reason' => 'bridge-unavailable', 'values' => [] ];
				}
			}
			if ( $collector === null && $fallback === [] && $subject->getSourceSnapshot() === null ) { continue; }
			$snapshot = $subject->getSourceSnapshot();
			foreach ( self::languages() as $language ) {
				$data = $collector !== null ? $collector->collect( $title, $schema, $language ) : [ 'fields' => $fallback, 'identities' => [
					'wikidata' => [ 'source' => 'wikidata', 'status' => 'unavailable', 'reason' => 'bridge-unavailable' ],
					'wikibase-local' => [ 'source' => 'wikibase-local', 'status' => 'unavailable', 'reason' => 'bridge-unavailable' ],
				] ];
				$snapshot = SourceSnapshot::recordAttempt( $subject->id, $title->getArticleID(), $revision->getId(),
					[ $schemaTitle->getArticleID() => $schemaRevision->getId() ], $language, gmdate( 'Y-m-d\TH:i:s\Z' ),
					$data['fields'], $data['identities'], $snapshot );
			}
			$snapshots[] = $snapshot;
		}
		return $snapshots;
	}

}
