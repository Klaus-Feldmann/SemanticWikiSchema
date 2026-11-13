<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki\Identity;

use LogicException;
use ProfessionalWiki\SWS\Persistence\Identity\IdentityConnection;
use Wikimedia\Rdbms\IDatabase;

/** Uses the ordinary primary connection. An ambient transaction owns the final commit. */
final class MediaWikiIdentityConnection implements IdentityConnection {
	private const ATOMIC = __CLASS__;
	private bool $active = false;
	private mixed $scope = null;
	public function __construct( private IDatabase $db ) {
	}

	public function begin(): void {
		if ( $this->active ) { throw new LogicException( 'Reentrant identity allocation scope' ); }
		$this->scope = $this->db->startAtomic( self::ATOMIC, IDatabase::ATOMIC_CANCELABLE );
		$this->active = true;
	}

	public function commit(): void {
		if ( !$this->active ) { throw new LogicException( 'No identity allocation scope' ); }
		$this->db->endAtomic( self::ATOMIC );
		$this->active = false;
	}
	public function rollback(): void {
		if ( $this->active ) { $this->db->cancelAtomic( self::ATOMIC, $this->scope ); $this->active = false; }
	}
	public function execute( string $sql ): void { $this->db->query( $sql, __METHOD__ ); }
	public function fetchRow( string $sql, bool $forUpdate = false ): ?array {
		if ( $forUpdate && $this->db->getType() !== 'sqlite' ) { $sql .= ' FOR UPDATE'; }
		$row = $this->db->query( $sql, __METHOD__ )->fetchRow();
		return $row === false ? null : $row;
	}
	public function quote( string $text ): string { return $this->db->addQuotes( $text ); }
	public function table( string $name ): string { return $this->db->tableName( $name ); }
}
