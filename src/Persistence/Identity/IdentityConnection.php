<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\Identity;

/** Small SQL boundary. All tables and identity algorithms live in SqlIdentityRegistry. */
interface IdentityConnection {
	/** Opens a cancellable scope; a surrounding page transaction retains ownership. */
	public function begin(): void;
	public function commit(): void;
	public function rollback(): void;
	public function execute( string $sql ): void;
	/** Current locking reads in a write scope avoid stale ambient MVCC snapshots. @return array<string, mixed>|null */
	public function fetchRow( string $sql, bool $forUpdate = false ): ?array;
	public function quote( string $text ): string;
	public function table( string $name ): string;
}
