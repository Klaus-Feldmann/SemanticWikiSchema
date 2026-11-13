<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Source;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;

/**
 * Versioned, immutable source data attached to one Subject. Each language has its own processing
 * context: updating German must not claim that an older English snapshot was also refreshed.
 * Last success and last attempt are separate, including their complete provenance.
 */
final readonly class SourceSnapshot {

	public const int VERSION = 1;

	private function __construct( private array $data ) {
	}

	public static function fromJson( mixed $data ): self {
		self::assertJsonData( $data );
		if ( !is_array( $data ) || ( $data['version'] ?? null ) !== self::VERSION ||
			!is_string( $data['subjectId'] ?? null ) || !SubjectId::isValidLocalId( $data['subjectId'] ) ||
			!is_array( $data['languages'] ?? null ) || $data['languages'] === []
		) {
			throw new InvalidArgumentException( 'Invalid or unsupported source snapshot' );
		}
		foreach ( $data['languages'] as $language => $snapshot ) {
			self::assertLanguage( $language );
			if ( !is_array( $snapshot ) || !is_int( $snapshot['pageId'] ?? null ) || $snapshot['pageId'] < 1 ||
				!is_int( $snapshot['baseRevisionId'] ?? null ) || $snapshot['baseRevisionId'] < 1 ||
				!is_array( $snapshot['schemaRevisions'] ?? null ) || $snapshot['schemaRevisions'] === [] ||
				!is_array( $snapshot['fields'] ?? null ) || !is_array( $snapshot['identities'] ?? null )
			) {
				throw new InvalidArgumentException( 'Invalid source snapshot context' );
			}
			foreach ( $snapshot['schemaRevisions'] as $pageId => $revisionId ) {
				if ( preg_match( '/\A[1-9][0-9]*\z/', (string)$pageId ) !== 1 || !is_int( $revisionId ) || $revisionId < 1 ) {
					throw new InvalidArgumentException( 'Invalid source schema revision' );
				}
			}
			self::assertTimestamp( $snapshot['attemptedAt'] ?? null );
			foreach ( $snapshot['fields'] as $name => $entry ) {
				self::assertEntry( $entry, $snapshot['attemptedAt'], static function ( array $result ) use ( $name ): bool {
					$field = new SourceFieldResult( $result );
					if ( $field->data['name'] !== (string)$name ) {
						throw new InvalidArgumentException( 'Source field key does not match its name' );
					}
					return $field->isUsable();
				} );
			}
			foreach ( $snapshot['identities'] as $source => $entry ) {
				self::assertEntry( $entry, $snapshot['attemptedAt'],
					static fn ( array $result ): bool => self::usableIdentity( (string)$source, $result ) );
				if ( isset( $entry['description'] ) ) {
					self::assertEntry( $entry['description'], $snapshot['attemptedAt'], static function ( array $description ) use ( $source ): bool {
						if ( ( $description['source'] ?? null ) !== (string)$source ) { throw new InvalidArgumentException( 'Description source mismatch' ); }
						return self::usableDescription( $description );
					} );
				}
			}
		}
		// Reconstruct plain JSON data: PHP references supplied by a caller must not mutate this object.
		return new self( json_decode( json_encode( $data, JSON_THROW_ON_ERROR ), true, 512, JSON_THROW_ON_ERROR ) );
	}

	/**
	 * $fields is the complete set of bound fields attempted in this language, including failures.
	 * Omitted fields are removed in this language; other languages retain their own context.
	 * A failure/empty result never replaces a last success or rewrites its source identity.
	 *
	 * @param array<int, int> $schemaRevisions Schema page IDs mapped to their revision IDs.
	 * @param array[] $fields Normalized field results, with their SWS propertyType added.
	 * @param array<string, array> $identities Source key mapped to its identity/description result.
	 */
	public static function recordAttempt(
		SubjectId $subjectId,
		int $pageId,
		int $baseRevisionId,
		array $schemaRevisions,
		string $language,
		string $attemptedAt,
		array $fields,
		array $identities = [],
		?self $previous = null,
	): self {
		self::assertTimestamp( $attemptedAt );
		if ( $previous !== null && $previous->getSubjectId() !== $subjectId->text ) {
			throw new InvalidArgumentException( 'Source snapshot belongs to another Subject' );
		}
		$old = $previous?->forLanguage( $language );
		if ( $old !== null && ( $old['baseRevisionId'] > $baseRevisionId || $old['attemptedAt'] > $attemptedAt ) ) {
			throw new InvalidArgumentException( 'Source attempt predates the stored snapshot' );
		}
		$storedFields = [];
		foreach ( $fields as $data ) {
			if ( !is_array( $data ) ) {
				throw new InvalidArgumentException( 'Source field result must be an object' );
			}
			$result = new SourceFieldResult( $data );
			$name = $result->data['name'];
			if ( array_key_exists( $name, $storedFields ) ) {
				throw new InvalidArgumentException( 'Duplicate source field result' );
			}
			$usable = $result->isUsable();
			if ( !$usable && $data['status'] === 'resolved' ) {
				$data['status'] = 'missing';
				$data['reason'] ??= 'no-usable-value';
			}
			$storedFields[$name] = self::entry( $data, $usable, $attemptedAt, $old['fields'][$name] ?? null );
		}
		$storedIdentities = [];
		foreach ( $identities as $source => $result ) {
			$usable = self::usableIdentity( (string)$source, $result );
			$storedIdentities[$source] = self::entry( $result, $usable, $attemptedAt, $old['identities'][$source] ?? null );
			$prior = $old['identities'][$source] ?? null;
			$previousDescription = $prior['description'] ?? null;
			if ( $previousDescription === null && isset( $prior['lastSuccess'] ) ) {
				$oldResult = self::descriptionResult( $prior['lastSuccess']['result'], $language );
				$previousDescription = self::entry( $oldResult, self::usableDescription( $oldResult ), $prior['lastSuccess']['at'], null );
			}
			$description = self::descriptionResult( $result, $language );
			$storedIdentities[$source]['description'] = self::entry( $description, self::usableDescription( $description ), $attemptedAt, $previousDescription );
		}
		$data = $previous?->toJson() ?? [ 'version' => self::VERSION, 'subjectId' => $subjectId->text, 'languages' => [] ];
		$data['languages'][$language] = [
			'pageId' => $pageId, 'baseRevisionId' => $baseRevisionId, 'schemaRevisions' => $schemaRevisions,
			'attemptedAt' => $attemptedAt, 'fields' => $storedFields, 'identities' => $storedIdentities,
		];
		return self::fromJson( $data );
	}

	private static function entry( array $result, bool $usable, string $at, ?array $previous ): array {
		return [ 'lastAttempt' => $result,
			'lastSuccess' => $usable ? [ 'at' => $at, 'result' => $result ] : ( $previous['lastSuccess'] ?? null ),
		];
	}

	private static function assertEntry( mixed $entry, string $attemptedAt, callable $validate ): void {
		if ( !is_array( $entry ) || !is_array( $entry['lastAttempt'] ?? null ) || !array_key_exists( 'lastSuccess', $entry ) ) {
			throw new InvalidArgumentException( 'Invalid source snapshot entry' );
		}
		$usableAttempt = $validate( $entry['lastAttempt'] );
		if ( $usableAttempt && $entry['lastSuccess'] !== [ 'at' => $attemptedAt, 'result' => $entry['lastAttempt'] ] ) {
			throw new InvalidArgumentException( 'Successful source attempt must be the stored last success' );
		}
		if ( $entry['lastSuccess'] !== null ) {
			$success = $entry['lastSuccess'];
			if ( !is_array( $success ) || !is_array( $success['result'] ?? null ) ) {
				throw new InvalidArgumentException( 'Invalid last successful source result' );
			}
			self::assertTimestamp( $success['at'] ?? null );
			if ( $success['at'] > $attemptedAt || !$validate( $success['result'] ) ) {
				throw new InvalidArgumentException( 'Last successful source result must have a usable value' );
			}
		}
	}

	private static function usableIdentity( string $source, mixed $result ): bool {
		if ( !SubjectId::isValidSourceKey( $source ) || !is_array( $result ) ||
			( $result['source'] ?? null ) !== $source ||
			!in_array( $result['status'] ?? null, [ 'resolved', 'missing', 'unavailable', 'unsupported' ], true )
		) {
			throw new InvalidArgumentException( 'Invalid source identity result' );
		}
		if ( $result['status'] !== 'resolved' ) {
			return false;
		}
		if ( !is_string( $result['id'] ?? null ) || preg_match( '/\AQ[1-9][0-9]*\z/', $result['id'] ) !== 1 ) {
			throw new InvalidArgumentException( 'Resolved source identity requires a valid entity ID' );
		}
		return true;
	}

	private static function descriptionResult( array $identity, string $language ): array {
		$value = $identity['description'] ?? null;
		$actualLanguage = is_array( $value ) ? ( $value['language'] ?? $language ) : ( $identity['descriptionLanguage'] ?? $language );
		$value = is_array( $value ) ? ( $value['value'] ?? null ) : $value;
		$status = ( $identity['status'] ?? 'unavailable' ) === 'resolved'
			? ( is_string( $value ) && trim( $value ) !== '' ? 'resolved' : 'missing' ) : ( $identity['status'] ?? 'unavailable' );
		if ( ( $identity['descriptionStatus'] ?? null ) === 'unavailable' ) { $status = 'unavailable'; }
		return [ 'source' => $identity['source'], 'id' => $identity['id'] ?? null, 'status' => $status,
			'language' => $actualLanguage, 'value' => $status === 'resolved' ? $value : null ];
	}

	private static function usableDescription( array $result ): bool {
		if ( !is_string( $result['source'] ?? null ) || !SubjectId::isValidSourceKey( $result['source'] ) ||
			!in_array( $result['status'] ?? null, [ 'resolved', 'missing', 'unavailable', 'unsupported' ], true ) ) {
			throw new InvalidArgumentException( 'Invalid stored description state' );
		}
		self::assertLanguage( $result['language'] ?? null );
		if ( $result['status'] !== 'resolved' ) { return false; }
		if ( !is_string( $result['id'] ?? null ) || preg_match( '/\AQ[1-9][0-9]*\z/', $result['id'] ) !== 1 ||
			!is_string( $result['value'] ?? null ) || trim( $result['value'] ) === '' ) {
			throw new InvalidArgumentException( 'Resolved description requires its entity and text' );
		}
		return true;
	}

	private static function assertLanguage( mixed $language ): void {
		if ( !is_string( $language ) || preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $language ) !== 1 ) {
			throw new InvalidArgumentException( 'Invalid source snapshot language' );
		}
	}

	private static function assertTimestamp( mixed $at ): void {
		$date = is_string( $at ) ? DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s\Z', $at, new DateTimeZone( 'UTC' ) ) : false;
		if ( !$date || $date->format( 'Y-m-d\TH:i:s\Z' ) !== $at ) {
			throw new InvalidArgumentException( 'Source timestamp must be a valid UTC timestamp' );
		}
	}

	/** Reject objects/resources/non-finite values rather than let serialization quietly change them. */
	public static function assertJsonData( mixed $value, int $depth = 0 ): void {
		if ( $depth > 64 ) {
			throw new InvalidArgumentException( 'Source data is too deeply nested' );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				self::assertJsonData( $item, $depth + 1 );
			}
		} elseif ( !is_null( $value ) && !is_string( $value ) && !is_int( $value ) && !is_bool( $value ) &&
			!( is_float( $value ) && is_finite( $value ) )
		) {
			throw new InvalidArgumentException( 'Source data must contain only JSON values' );
		}
	}

	public function getSubjectId(): string {
		return $this->data['subjectId'];
	}

	public function forLanguage( string $language ): ?array {
		return $this->data['languages'][$language] ?? null;
	}

	public function toJson(): array {
		return $this->data;
	}

}
