<?php

declare( strict_types = 1 );

// Controlled MediaWiki boundary, with real SQLite underneath the production MW adapter.
namespace Wikimedia\Rdbms {
	interface IDatabase { public const ATOMIC_CANCELABLE = 'cancelable'; }
}
namespace MediaWiki\Installer {
	class DatabaseUpdater {
		public array $tables = [];
		public array $updates = [];
		public function __construct( private \IdentityMwHarness\Database $db ) {}
		public function getDB(): \IdentityMwHarness\Database { return $this->db; }
		public function addExtensionTable( string $name, string $sql ): void { $this->tables[$name] = $sql; }
		public function addExtensionField( mixed ...$args ): void {}
		public function addPostDatabaseUpdateMaintenance( mixed ...$args ): void {}
		public function addExtensionUpdate( array $update ): void { $this->updates[] = $update; }
	}
}
namespace IdentityMwHarness {
	class Result {
		public function __construct( private \PDOStatement $statement ) {}
		public function fetchRow(): array|false { return $this->statement->fetch( \PDO::FETCH_ASSOC ); }
	}
	class Database implements \Wikimedia\Rdbms\IDatabase {
		public \PDO $pdo;
		public array $flags = [];
		private array $scopes = [];
		public function __construct() { $this->pdo = new \PDO( 'sqlite::memory:', null, null, [ \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION ] ); }
		public function getType(): string { return 'sqlite'; }
		public function trxLevel(): int { return $this->pdo->inTransaction() ? 1 : 0; }
		public function startAtomic( string $name, string $flags = '' ): void {
			$this->flags[] = $flags;
			$point = 'atomic_' . count( $this->scopes );
			$own = !$this->pdo->inTransaction();
			if ( $own ) { $this->pdo->beginTransaction(); } else { $this->pdo->exec( 'SAVEPOINT ' . $point ); }
			$this->scopes[] = [$own, $point];
		}
		public function endAtomic( string $name ): void {
			[$own,$point] = array_pop( $this->scopes );
			if ( $own ) { $this->pdo->commit(); } else { $this->pdo->exec( 'RELEASE SAVEPOINT ' . $point ); }
		}
		public function cancelAtomic( string $name ): void {
			[$own,$point] = array_pop( $this->scopes );
			if ( $own ) { $this->pdo->rollBack(); } else { $this->pdo->exec( 'ROLLBACK TO SAVEPOINT ' . $point ); $this->pdo->exec( 'RELEASE SAVEPOINT ' . $point ); }
		}
		public function addQuotes( string $value ): string { return $this->pdo->quote( $value ); }
		public function tableName( string $name ): string { return '"mw_' . $name . '"'; }
		public function query( string $sql, string $caller ): Result { return new Result( $this->pdo->query( $sql ) ); }
		public function insert( string $table, array $row, string $caller, array $options ): void {
			if ( $options !== ['IGNORE'] ) { throw new \RuntimeException( 'Expected IGNORE seed' ); }
			$this->pdo->exec( 'INSERT OR IGNORE INTO ' . $this->tableName( $table ) . ' (' . implode( ',', array_keys( $row ) ) .
				') VALUES (' . implode( ',', array_map( fn ( $v ) => $this->addQuotes( (string)$v ), $row ) ) . ')' );
		}
	}
}
namespace {
	require __DIR__ . '/bootstrap.php';
	use ProfessionalWiki\SWS\EntryPoints\SWSHooks;
	use ProfessionalWiki\SWS\Persistence\MediaWiki\Identity\MediaWikiIdentityConnection;
	use ProfessionalWiki\SWS\Persistence\Identity\SqlIdentityRegistry;
	use ProfessionalWiki\SWS\Domain\Identity\{IdentityKind, LegacyIdentity};
	$count = 0;
	function mwIdentityCheck( bool $ok, string $why ): void { global $count; $count++; if ( !$ok ) { throw new RuntimeException( $why ); } }
	$db = new IdentityMwHarness\Database();
	$updater = new MediaWiki\Installer\DatabaseUpdater( $db );
	SWSHooks::onLoadExtensionSchemaUpdates( $updater );
	foreach ( ['sws_id_counter','sws_identity','sws_identity_alias'] as $table ) {
		mwIdentityCheck( isset( $updater->tables[$table] ) && is_file( $updater->tables[$table] ), 'Updater registers shipped table' );
		$db->pdo->exec( str_replace( '/*_*/', 'mw_', file_get_contents( $updater->tables[$table] ) ) );
	}
	mwIdentityCheck( in_array( [[SWSHooks::class,'initializeIdentityCounters']], $updater->updates, true ), 'Updater queues counter initialization' );
	SWSHooks::initializeIdentityCounters( $updater );
	mwIdentityCheck( (int)$db->pdo->query( 'SELECT COUNT(*) FROM mw_sws_id_counter' )->fetchColumn() === 3, 'Real updater initializer seeds all kinds' );
	$connection = new MediaWikiIdentityConnection( $db );
	$registry = new SqlIdentityRegistry( $connection );
	mwIdentityCheck( $registry->reserve( IdentityKind::Schema, 'actor:1/new-schema' )->text === 'S1', 'Production MW adapter reservation' );
	SWSHooks::initializeIdentityCounters( $updater );
	mwIdentityCheck( $registry->reserve( IdentityKind::Schema, 'actor:1/next-schema' )->text === 'S2', 'Repeated update never resets a counter' );
	mwIdentityCheck( $registry->reserve( IdentityKind::Schema, 'actor:1/new-schema' )->text === 'S1', 'Retry resolves through MW adapter' );
	$legacy = new LegacyIdentity( IdentityKind::Entity, 'aewi', 'sF1fB3ABijgJJP9' );
	mwIdentityCheck( $registry->assignLegacy( $legacy )->text === 'E1', 'Legacy assignment through MW adapter' );
	mwIdentityCheck( $registry->findLegacy( $legacy )?->text === 'E1', 'Alias read through MW adapter' );
	mwIdentityCheck( $db->trxLevel() === 0, 'Reservation committed before returning' );
	mwIdentityCheck( array_unique( $db->flags ) === [Wikimedia\Rdbms\IDatabase::ATOMIC_CANCELABLE], 'Explicit cancellable atomic scope' );
	$db->pdo->exec( "CREATE TRIGGER mw_fail_alias BEFORE INSERT ON mw_sws_identity_alias BEGIN SELECT RAISE(ABORT, 'fail alias'); END" );
	try {
		$registry->assignLegacy( new LegacyIdentity( IdentityKind::Entity, 'aewi', 'will-fail' ) );
		throw new LogicException( 'Failure was not raised' );
	} catch ( PDOException $error ) { mwIdentityCheck( true, 'Injected failure surfaced' ); }
	mwIdentityCheck( $db->trxLevel() === 0, 'Production adapter cancelled failed atomic scope' );
	mwIdentityCheck( $registry->reserve( IdentityKind::Entity, 'actor:1/after-failure' )->text === 'E2', 'No partial reservation after failed alias write' );
	$db->pdo->beginTransaction();
	$db->pdo->exec( "UPDATE mw_sws_id_counter SET swsic_value=swsic_value WHERE swsic_kind='schema'" );
	$pending = $registry->reserve( IdentityKind::Schema, 'ambient-new-schema' );
	mwIdentityCheck( $db->pdo->inTransaction(), 'Allocation preserves the outer transaction' );
	$db->pdo->rollBack();
	mwIdentityCheck( $registry->findReservation( IdentityKind::Schema, 'ambient-new-schema' ) === null, 'Unpublished automatic allocation is discarded with the page transaction' );
	mwIdentityCheck( $registry->reserve( IdentityKind::Schema, 'actor:1/new-schema' )->text === 'S1', 'Previously confirmed ID remains reserved' );
	foreach ( ['mysql','postgres'] as $platform ) {
		$lockingDb = new class( $platform ) extends IdentityMwHarness\Database {
			public array $queries = [];
			public function __construct( private string $platform ) { parent::__construct(); }
			public function getType(): string { return $this->platform; }
			public function query( string $sql, string $caller ): IdentityMwHarness\Result {
				$this->queries[] = $sql;
				// SQL recording boundary, not a real MySQL/PostgreSQL engine.
				return parent::query( preg_replace( '/ FOR UPDATE$/', '', $sql ), $caller );
			}
		};
		foreach ( ['sws_id_counter','sws_identity','sws_identity_alias'] as $table ) {
			$lockingDb->pdo->exec( str_replace( '/*_*/', 'mw_', file_get_contents( __DIR__ . '/../sql/sqlite/' . $table . '.sql' ) ) );
		}
		SWSHooks::initializeIdentityCounters( new MediaWiki\Installer\DatabaseUpdater( $lockingDb ) );
		$locked = new SqlIdentityRegistry( new MediaWikiIdentityConnection( $lockingDb ) );
		$locked->reserve( IdentityKind::Entity, 'current-counter' );
		mwIdentityCheck( count( array_filter( $lockingDb->queries, static fn ( $sql ) => str_contains( $sql, 'SELECT swsic_value' ) && str_ends_with( $sql, ' FOR UPDATE' ) ) ) === 1, 'Counter reads use current locked rows on ' . $platform );
		$locked->reserve( IdentityKind::Entity, 'current-counter' );
		mwIdentityCheck( count( array_filter( $lockingDb->queries, static fn ( $sql ) => str_contains( $sql, 'SELECT swsi_id,swsi_owner' ) && str_ends_with( $sql, ' FOR UPDATE' ) ) ) === 2, 'Retry uses current reservation rows on ' . $platform );
		$lockingDb->queries = [];
		$locked->findReservation( IdentityKind::Entity, 'current-counter' );
		mwIdentityCheck( array_filter( $lockingDb->queries, static fn ( $sql ) => str_ends_with( $sql, ' FOR UPDATE' ) ) === [], 'Ordinary lookup does not add write locks on ' . $platform );
	}
	print "$count MediaWiki updater/adapter boundary checks passed (controlled host with actual SQLite; not a running MediaWiki).\n";
}
