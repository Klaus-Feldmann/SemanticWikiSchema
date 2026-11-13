<?php

declare( strict_types = 1 );

namespace IdentityHarness;

use LogicException;
use PDO;
use ProfessionalWiki\SWS\Persistence\Identity\IdentityConnection;

/** Real SQLite transport for the same production registry SQL; not a simulated database. */
final class SqliteIdentityConnection implements IdentityConnection {
	public PDO $pdo;
	public ?string $failOn = null;
	private bool $active = false;
	private bool $ownsTransaction = false;
	public function __construct( string $path ) {
		$this->pdo = new PDO( 'sqlite:' . $path, null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
		$this->pdo->exec( 'PRAGMA busy_timeout=30000' );
	}
	public function begin(): void {
		if ( $this->active ) { throw new LogicException( 'Reentrant identity scope' ); }
		$this->ownsTransaction = !$this->pdo->inTransaction();
		if ( $this->ownsTransaction ) { $this->pdo->beginTransaction(); }
		else { $this->pdo->exec( 'SAVEPOINT sws_identity' ); }
		$this->active = true;
	}
	public function commit(): void {
		if ( $this->ownsTransaction ) { $this->pdo->commit(); }
		else { $this->pdo->exec( 'RELEASE SAVEPOINT sws_identity' ); }
		$this->active = false;
	}
	public function rollback(): void {
		if ( !$this->active ) { return; }
		if ( $this->ownsTransaction ) { $this->pdo->rollBack(); }
		else { $this->pdo->exec( 'ROLLBACK TO SAVEPOINT sws_identity' ); $this->pdo->exec( 'RELEASE SAVEPOINT sws_identity' ); }
		$this->active = false;
	}
	public function execute( string $sql ): void {
		if ( $this->failOn !== null && str_contains( $sql, $this->failOn ) ) {
			throw new \RuntimeException( 'Injected SQL failure' );
		}
		$this->pdo->exec( $sql );
	}
	public function fetchRow( string $sql, bool $forUpdate = false ): ?array {
		$row = $this->pdo->query( $sql )->fetch( PDO::FETCH_ASSOC );
		return $row === false ? null : $row;
	}
	public function quote( string $text ): string { return $this->pdo->quote( $text ); }
	public function table( string $name ): string {
		if ( !in_array( $name, [ 'sws_id_counter', 'sws_identity', 'sws_identity_alias' ], true ) ) {
			throw new LogicException( 'Unknown identity table' );
		}
		return '"test_' . $name . '"';
	}
	public function insertIgnore( string $table, array $row ): void {
		$this->execute( 'INSERT OR IGNORE INTO ' . $this->table( $table ) . ' (' . implode( ',', array_keys( $row ) ) .
			') VALUES (' . implode( ',', array_map( fn ( $value ) => $this->quote( (string)$value ), $row ) ) . ')' );
	}
}
