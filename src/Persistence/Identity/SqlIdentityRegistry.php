<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\Identity;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ProfessionalWiki\SWS\Application\Identity\IdentityRegistry;
use ProfessionalWiki\SWS\Domain\Identity\{IdentityKind, LegacyIdentity, PersistentId};

/**
 * Serializes each kind through its counter row. Reservations and aliases form one atomic operation. With an ambient
 * transaction, IDs remain internal until the caller commits and publishes its response. Deletion of a page never deletes a reservation or decrements its counter.
 */
final readonly class SqlIdentityRegistry implements IdentityRegistry {
	public function __construct( private IdentityConnection $db ) {
	}

	public function reserve( IdentityKind $kind, string $requestKey ): PersistentId {
		$this->validateRequestKey( $requestKey );
		return $this->transaction( $kind, fn () => $this->reserveLocked( $kind, 'request:' . $requestKey ) );
	}

	public function findReservation( IdentityKind $kind, string $requestKey ): ?PersistentId {
		$this->validateRequestKey( $requestKey );
		$hash = hash( 'sha256', $kind->value . "\0request:" . $requestKey );
		$row = $this->db->fetchRow( 'SELECT swsi_id FROM ' . $this->db->table( 'sws_identity' ) .
			' WHERE swsi_request=' . $this->db->quote( $hash ) );
		if ( $row === null ) { return null; }
		$id = PersistentId::parse( $row['swsi_id'] );
		$this->assertReserved( $id, $kind );
		return $id;
	}

	private function validateRequestKey( string $requestKey ): void {
		if ( $requestKey === '' || strlen( $requestKey ) > 255 || preg_match( '/[\x00-\x1f\x7f]/', $requestKey ) === 1 ) {
			throw new InvalidArgumentException( 'Invalid identity reservation request key' );
		}
	}

	public function reserveForOwner( IdentityKind $kind, string $requestKey, string $owner ): PersistentId {
		$this->validateRequestKey( $requestKey );
		if ( preg_match( '/^[a-f0-9]{64}\z/', $owner ) !== 1 ) { throw new InvalidArgumentException( 'Invalid reservation owner' ); }
		return $this->transaction( $kind, fn () => $this->reserveLocked( $kind, 'owner:' . $owner . ':' . $requestKey, $owner ) );
	}

	public function isReservedForOwner( PersistentId $id, string $owner ): bool {
		$row = $this->db->fetchRow( 'SELECT swsi_owner FROM ' . $this->db->table( 'sws_identity' ) .
			' WHERE swsi_id=' . $this->db->quote( $id->text ) );
		return $owner !== '' && $row !== null && hash_equals( $row['swsi_owner'], $owner );
	}

	public function canCreate( PersistentId $id, string $owner ): bool {
		$row = $this->db->fetchRow( 'SELECT swsi_owner,swsi_used FROM ' . $this->db->table( 'sws_identity' ) .
			' WHERE swsi_id=' . $this->db->quote( $id->text ), true );
		return $owner !== '' && $row !== null && hash_equals( $row['swsi_owner'], $owner ) && (int)$row['swsi_used'] === 0;
	}

	public function markUsed( PersistentId $id ): void {
		$this->assertReserved( $id, $id->kind(), true );
		$this->db->execute( 'UPDATE ' . $this->db->table( 'sws_identity' ) . ' SET swsi_used=1 WHERE swsi_id=' . $this->db->quote( $id->text ) );
	}

	public function assignLegacy( LegacyIdentity $legacy ): PersistentId {
		return $this->transaction( $legacy->kind, function () use ( $legacy ): PersistentId {
			$existing = $this->lookupLegacy( $legacy, true );
			if ( $existing !== null ) {
				return $existing;
			}
			$id = $this->reserveLocked( $legacy->kind, 'legacy:' . $legacy->hash() );
			$this->linkLocked( $legacy, $id );
			return $id;
		} );
	}

	public function findLegacy( LegacyIdentity $legacy ): ?PersistentId {
		return $this->lookupLegacy( $legacy, false );
	}

	private function lookupLegacy( LegacyIdentity $legacy, bool $forUpdate ): ?PersistentId {
		$row = $this->db->fetchRow( 'SELECT * FROM ' . $this->db->table( 'sws_identity_alias' ) .
			' WHERE swsia_hash=' . $this->db->quote( $legacy->hash() ), $forUpdate );
		if ( $row === null ) {
			return null;
		}
		// Store the full original too: a hash collision/corrupt row must never silently resolve.
		if ( $row['swsia_kind'] !== $legacy->kind->value || $row['swsia_origin'] !== $legacy->origin ||
			$row['swsia_legacy'] !== base64_encode( $legacy->key ) ) {
			throw new RuntimeException( 'Conflicting legacy identity mapping' );
		}
		$id = PersistentId::parse( $row['swsia_target'] );
		$this->assertReserved( $id, $legacy->kind, $forUpdate );
		return $id;
	}

	public function linkLegacy( LegacyIdentity $legacy, PersistentId $target ): void {
		if ( $legacy->kind !== $target->kind() ) {
			throw new InvalidArgumentException( 'Legacy identity kind differs from target kind' );
		}
		$this->transaction( $legacy->kind, function () use ( $legacy, $target ): void {
			$this->linkLocked( $legacy, $target );
		} );
	}

	private function linkLocked( LegacyIdentity $legacy, PersistentId $target ): void {
		$this->assertReserved( $target, $legacy->kind, true );
		$existing = $this->lookupLegacy( $legacy, true );
		if ( $existing !== null ) {
			if ( !$existing->equals( $target ) ) {
				throw new RuntimeException( 'A legacy identity cannot be retargeted' );
			}
			return;
		}
		$this->insert( 'sws_identity_alias', [
			'swsia_hash' => $legacy->hash(), 'swsia_kind' => $legacy->kind->value,
			'swsia_origin' => $legacy->origin, 'swsia_legacy' => base64_encode( $legacy->key ),
			'swsia_target' => $target->text,
		] );
	}

	private function assertReserved( PersistentId $target, IdentityKind $kind, bool $forUpdate = false ): void {
		$row = $this->db->fetchRow( 'SELECT swsi_kind, swsi_number FROM ' . $this->db->table( 'sws_identity' ) .
			' WHERE swsi_id=' . $this->db->quote( $target->text ), $forUpdate );
		if ( $target->kind() !== $kind || $row === null || $row['swsi_kind'] !== $kind->value ||
			(string)$row['swsi_number'] !== (string)$target->number ) {
			throw new RuntimeException( 'Target is not a valid local identity reservation' );
		}
	}

	private function reserveLocked( IdentityKind $kind, string $key, string $owner = '' ): PersistentId {
		$hash = hash( 'sha256', $kind->value . "\0" . $key );
		$row = $this->db->fetchRow( 'SELECT swsi_id,swsi_owner FROM ' . $this->db->table( 'sws_identity' ) .
			' WHERE swsi_request=' . $this->db->quote( $hash ), true );
		if ( $row !== null ) {
			if ( !hash_equals( $row['swsi_owner'], $owner ) ) { throw new RuntimeException( 'Reservation owner mismatch' ); }
			$id = PersistentId::parse( $row['swsi_id'] );
			$this->assertReserved( $id, $kind, true );
			return $id;
		}
		$counter = $this->db->fetchRow( 'SELECT swsic_value FROM ' . $this->db->table( 'sws_id_counter' ) .
			' WHERE swsic_kind=' . $this->db->quote( $kind->value ), true );
		$value = $counter['swsic_value'] ?? null;
		if ( !is_int( $value ) && !( is_string( $value ) && preg_match( '/^(0|[1-9][0-9]{0,17})\z/', $value ) === 1 ) ) {
			throw new RuntimeException( 'Invalid or missing identity counter' );
		}
		if ( $value < 0 || $value >= PersistentId::MAX_NUMBER ) {
			throw new RuntimeException( 'Identity counter exhausted' );
		}
		$id = $kind->fromNumber( (int)$value + 1 );
		$this->db->execute( 'UPDATE ' . $this->db->table( 'sws_id_counter' ) . ' SET swsic_value=' . $id->number .
			' WHERE swsic_kind=' . $this->db->quote( $kind->value ), true );
		$this->insert( 'sws_identity', [ 'swsi_id' => $id->text, 'swsi_kind' => $kind->value,
			'swsi_number' => $id->number, 'swsi_request' => $hash, 'swsi_owner' => $owner ] );
		return $id;
	}

	/** Lock before any reads: also avoids a SQLite read-to-write snapshot upgrade race. */
	private function transaction( IdentityKind $kind, Closure $operation ): mixed {
		$this->db->begin();
		try {
			$this->db->execute( 'UPDATE ' . $this->db->table( 'sws_id_counter' ) .
				' SET swsic_value=swsic_value WHERE swsic_kind=' . $this->db->quote( $kind->value ) );
			$result = $operation();
			$this->db->commit();
			return $result;
		} catch ( Throwable $error ) {
			$this->db->rollback();
			throw $error;
		}
	}

	/** @param array<string, int|string> $row */
	private function insert( string $table, array $row ): void {
		$this->db->execute( 'INSERT INTO ' . $this->db->table( $table ) . ' (' . implode( ',', array_keys( $row ) ) .
			') VALUES (' . implode( ',', array_map( fn ( $value ) => $this->db->quote( (string)$value ), $row ) ) . ')' );
	}
}
