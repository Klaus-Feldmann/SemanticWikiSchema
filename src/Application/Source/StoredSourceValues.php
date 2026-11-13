<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Source;

use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Subject\Subject;

/** Network-free adapter to the existing field-reader contract, used by the staged worker. */
final class StoredSourceValues {

	public static function language( Subject $subject, string $requested, array $fallbacks = [] ): ?string {
		$languages = array_keys( $subject->getSourceSnapshot()?->toJson()['languages'] ?? [] );
		foreach ( array_unique( [ $requested, explode( '-', $requested )[0], ...$fallbacks, ...$languages ] ) as $language ) {
			if ( in_array( $language, $languages, true ) ) { return $language; }
		}
		return null;
	}

	public static function read( Subject $subject, Schema $schema, string $language, int $pageId, array $fallbacks = [] ): array {
		$storedLanguage = self::language( $subject, $language, $fallbacks );
		$stored = $storedLanguage !== null ? $subject->getSourceSnapshot()?->forLanguage( $storedLanguage ) : null;
		$fields = [];
		foreach ( $schema->getAllProperties()->asMap() as $name => $property ) {
			$binding = $property->getExternalProperty();
			if ( $binding === null || $property->isSchemaExecution() ) { continue; }
			$entry = $stored['fields'][$name] ?? null;
			$matches = static fn ( ?array $result ): bool => $result !== null &&
				( $result['source'] ?? null ) === $binding->source && ( $result['propertyId'] ?? null ) === $binding->id &&
				( $result['propertyType'] ?? null ) === $property->getPropertyType();
			$attempt = $entry['lastAttempt'] ?? null;
			$success = $entry['lastSuccess']['result'] ?? null;
			$field = $matches( $attempt ) ? $attempt : [ 'name' => (string)$name, 'source' => $binding->source,
				'propertyId' => $binding->id, 'propertyType' => $property->getPropertyType(),
				'status' => 'unavailable', 'reason' => 'snapshot-not-available', 'values' => [] ];
			$field['sourceStatus'] = $field['status'];
			$field['storageMode'] = 'store';
			$field['recorded'] = $matches( $attempt );
			$field['language'] = $storedLanguage;
			$field['languageFallback'] = $storedLanguage !== null && $storedLanguage !== $language;
			if ( $stored !== null ) { $field['lastAttemptedAt'] = $stored['attemptedAt']; }
			$field['lastAttempt'] = $field;
			$field['values'] = [];
			if ( $matches( $success ) ) {
				$field = array_replace( $field, $success, [ 'sourceStatus' => $field['sourceStatus'], 'lastAttempt' => $field['lastAttempt'],
					'lastSuccessfulAt' => $entry['lastSuccess']['at'], 'lastAttemptedAt' => $stored['attemptedAt'],
					'retained' => $entry['lastSuccess']['at'] !== $stored['attemptedAt'] || $field['sourceStatus'] !== 'resolved' ] );
			}
			$fields[] = $field;
		}
		return [ 'version' => 1, 'scope' => 'current', 'pageId' => $pageId, 'mainSubjectId' => $subject->id->text, 'fields' => $fields,
			'storage' => [ 'mode' => 'store', 'available' => $stored !== null, 'requestedLanguage' => $language,
				'language' => $storedLanguage, 'languageFallback' => $storedLanguage !== null && $storedLanguage !== $language,
				'lastAttemptedAt' => $stored['attemptedAt'] ?? null, 'baseRevisionId' => $stored['baseRevisionId'] ?? null ] ];
	}

}
