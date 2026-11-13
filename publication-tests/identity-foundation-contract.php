<?php

declare( strict_types = 1 );
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/SqliteIdentityConnection.php';

use IdentityHarness\SqliteIdentityConnection;
use ProfessionalWiki\SWS\Domain\Identity\{IdentityKind, PersistentId, SchemaId, EntityId, RelationResourceId, LegacyIdentity, IdentityReference};
use ProfessionalWiki\SWS\Persistence\Identity\SqlIdentityRegistry;

$count = 0;
function checkIdentity( bool $ok, string $why ): void {
	global $count;
	$count++;
	if ( !$ok ) { throw new RuntimeException( $why ); }
}
function refusedIdentity( Closure $test, string $why ): void {
	try { $test(); } catch ( InvalidArgumentException | RuntimeException | LogicException $e ) {
		checkIdentity( true, $why ); return;
	}
	throw new RuntimeException( 'Not refused: ' . $why );
}
function makeIdentityDatabase( string $file ): SqliteIdentityConnection {
	$db = new SqliteIdentityConnection( $file );
	foreach ( [ 'sws_id_counter', 'sws_identity', 'sws_identity_alias' ] as $table ) {
		$sql = file_get_contents( __DIR__ . '/../sql/sqlite/' . $table . '.sql' );
		$db->pdo->exec( str_replace( '/*_*/', 'test_', $sql ) );
	}
	// Same three initial rows installed by SWSHooks::initializeIdentityCounters in update.php.
	foreach ( IdentityKind::cases() as $kind ) {
		$db->insertIgnore( 'sws_id_counter', [ 'swsic_kind' => $kind->value, 'swsic_value' => 0 ] );
	}
	return $db;
}

$path = tempnam( sys_get_temp_dir(), 'sws-identity-' );
$racePath = tempnam( sys_get_temp_dir(), 'sws-identity-race-' );
try {
	foreach ( [ [new SchemaId( 'S1' ), IdentityKind::Schema], [new EntityId( 'E1' ), IdentityKind::Entity],
		[new RelationResourceId( 'R1' ), IdentityKind::Relation] ] as [$id, $kind] ) {
		checkIdentity( $id->kind() === $kind && $id->number === 1, 'Kind and numeric value' );
		checkIdentity( PersistentId::parse( $id->text )->equals( $id ), 'ID round trip' );
	}
	checkIdentity( (string)EntityId::fromNumber( PersistentId::MAX_NUMBER ) === 'E999999999999999999', 'Exact max integer' );
	foreach ( [ '', 'E0', 'E01', 'E-1', 'e1', 'E1x', "E1\n", 'E1000000000000000000', 'Q1', 'aewi:E1', 's11111111111111' ] as $bad ) {
		refusedIdentity( fn () => PersistentId::parse( $bad ), 'Strict canonical ID: ' . $bad );
	}
	refusedIdentity( fn () => new SchemaId( 'E1' ), 'Typed IDs are distinct' );
	refusedIdentity( fn () => EntityId::fromNumber( 0 ), 'No zero' );
	$ref = IdentityReference::fromJson( [ 'source' => 'aewi', 'id' => 'E1' ], 'aewi' );
	checkIdentity( $ref->toJson() === [ 'id' => 'E1' ], 'Local source canonicalization' );
	checkIdentity( IdentityReference::fromJson( [ 'source' => 'other', 'id' => 'E1' ] )->toJson() ===
		[ 'source' => 'other', 'id' => 'E1' ], 'Foreign source preserved' );
	foreach ( [ 'S1', ['name'=>'S1'], ['id'=>'E1','source'=>null], ['id'=>'Q1'], ['id'=>'E1','label'=>'Test'] ] as $bad ) {
		refusedIdentity( fn () => IdentityReference::fromJson( $bad ), 'No guessing legacy or Q IDs' );
	}
	$db = makeIdentityDatabase( $path );
	$registry = new SqlIdentityRegistry( $db );
	checkIdentity( $registry->reserve( IdentityKind::Schema, 'actor:1/create:a' )->text === 'S1', 'Schema starts at S1' );
	checkIdentity( $registry->reserve( IdentityKind::Entity, 'actor:1/create:a' )->text === 'E1', 'Independent entity series' );
	checkIdentity( $registry->reserve( IdentityKind::Relation, 'actor:1/create:a' )->text === 'R1', 'Independent relation series' );
	checkIdentity( $registry->reserve( IdentityKind::Entity, 'actor:1/create:a' )->text === 'E1', 'Retry same reservation' );
	checkIdentity( $registry->reserve( IdentityKind::Entity, 'actor:2/create:a' )->text === 'E2', 'Actor scoped operation' );
	checkIdentity( $registry->findReservation( IdentityKind::Entity, 'actor:2/create:a' )?->text === 'E2', 'Caller-scoped reservation can be verified before claim' );
	checkIdentity( $registry->findReservation( IdentityKind::Entity, 'actor:3/create:a' ) === null, 'Different actor operation does not resolve reservation' );
	$other = new SqlIdentityRegistry( new SqliteIdentityConnection( $path ) );
	checkIdentity( $other->reserve( IdentityKind::Entity, 'actor:1/create:a' )->text === 'E1', 'Durable across connections' );
	refusedIdentity( fn () => $registry->reserve( IdentityKind::Entity, '' ), 'Empty request key' );
	refusedIdentity( fn () => $registry->reserve( IdentityKind::Entity, str_repeat( 'x', 256 ) ), 'Oversized request' );
	$legacy = new LegacyIdentity( IdentityKind::Schema, 'aewi', 'S1' );
	checkIdentity( $registry->findLegacy( $legacy ) === null, 'Old S1 does not mean new S1' );
	$id = $registry->assignLegacy( $legacy );
	checkIdentity( $id->text === 'S2', 'Old S1 allocated a distinct schema ID' );
	checkIdentity( $registry->assignLegacy( $legacy )->equals( $id ), 'Migration retry stable' );
	checkIdentity( $other->findLegacy( $legacy )?->equals( $id ) === true, 'Mapping durable' );
	$foreign = new LegacyIdentity( IdentityKind::Schema, 'neowiki', 'S1' );
	checkIdentity( $registry->assignLegacy( $foreign )->text === 'S3', 'Origin distinguishes same schema name' );
	$oldEntity = new LegacyIdentity( IdentityKind::Entity, 'aewi', 'sF1fB3ABijgJJP9' );
	checkIdentity( $registry->assignLegacy( $oldEntity )->text === 'E3', 'Legacy entity gets E ID' );
	$variant = new LegacyIdentity( IdentityKind::Schema, 'aewi-neowiki', 'Personendaten' );
	checkIdentity( $registry->assignLegacy( $variant )->kind() === IdentityKind::Schema, 'Existing source keys supported' );
	$unicode = new LegacyIdentity( IdentityKind::Schema, 'aewi', 'Brünn/„Person“: ISO:9001' );
	$unicodeId = $registry->assignLegacy( $unicode );
	checkIdentity( $registry->findLegacy( $unicode )?->equals( $unicodeId ) === true, 'Exact Unicode and colons preserved' );
	$alias = new LegacyIdentity( IdentityKind::Schema, 'aewi', 'Person' );
	$registry->linkLegacy( $alias, $id );
	$registry->linkLegacy( $alias, $id );
	checkIdentity( $registry->findLegacy( $alias )?->equals( $id ) === true, 'Additional alias idempotent' );
	refusedIdentity( fn () => $registry->linkLegacy( $alias, new SchemaId( 'S1' ) ), 'Cannot retarget alias' );
	refusedIdentity( fn () => $registry->linkLegacy( $alias, new EntityId( 'E1' ) ), 'Cannot mix kinds' );
	refusedIdentity( fn () => $registry->linkLegacy( new LegacyIdentity( IdentityKind::Entity, 'aewi', 'unknown' ), new EntityId( 'E999' ) ), 'Cannot link unreserved ID' );
	foreach ( [ ['', 'x'], ['bad:origin','x'], ['aewi',''], ['aewi', "a\0b"], ['aewi', str_repeat( 'x', 1025 )] ] as [$origin,$key] ) {
		refusedIdentity( fn () => new LegacyIdentity( IdentityKind::Schema, $origin, $key ), 'Validate legacy input' );
	}
	// Deliberately corrupt full mapping data while keeping its hash; no silent misresolution.
	$db->pdo->exec( "UPDATE test_sws_identity_alias SET swsia_legacy='corrupt' WHERE swsia_hash=" . $db->quote( $unicode->hash() ) );
	refusedIdentity( fn () => $registry->findLegacy( $unicode ), 'Full original key checked' );
	// Roll back counter, reservation and alias together on a failed mapping write.
	$before = $db->pdo->query( "SELECT swsic_value FROM test_sws_id_counter WHERE swsic_kind='entity'" )->fetchColumn();
	$db->failOn = 'INSERT INTO "test_sws_identity_alias"';
	$failing = new LegacyIdentity( IdentityKind::Entity, 'aewi', 'failed-transaction' );
	refusedIdentity( fn () => $registry->assignLegacy( $failing ), 'Injected transaction failure' );
	$db->failOn = null;
	checkIdentity( (string)$db->pdo->query( "SELECT swsic_value FROM test_sws_id_counter WHERE swsic_kind='entity'" )->fetchColumn() === (string)$before, 'Failed allocation not committed or exposed' );
	checkIdentity( $registry->findLegacy( $failing ) === null, 'No partial alias' );
	checkIdentity( $registry->assignLegacy( $failing )->text === 'E4', 'Failed request can complete forward' );
	// A caller's page-save transaction is separate: rolling it back cannot undo an issued ID.
	$page = new PDO( 'sqlite:' . $path ); $page->exec( 'CREATE TABLE pages (id INTEGER)' );
	// Reserve before page writes: SQLite permits only one writer across connections.
	$issued = $registry->reserve( IdentityKind::Entity, 'page:save:will-fail' );
	$page->beginTransaction(); $page->exec( 'INSERT INTO pages VALUES (1)' ); $page->rollBack();
	checkIdentity( $other->reserve( IdentityKind::Entity, 'page:save:will-fail' )->equals( $issued ), 'Page rollback preserves issued reservation' );
	// There is intentionally no release/delete operation; an abandoned number stays reserved.
	checkIdentity( $registry->reserve( IdentityKind::Entity, 'page:next' )->number === $issued->number + 1, 'Abandoned issued ID never reused' );
	$db->pdo->exec( "UPDATE test_sws_id_counter SET swsic_value=" . PersistentId::MAX_NUMBER . " WHERE swsic_kind='relation'" );
	refusedIdentity( fn () => $registry->reserve( IdentityKind::Relation, 'overflow' ), 'Overflow refused before integer conversion' );
	checkIdentity( $registry->reserve( IdentityKind::Relation, 'actor:1/create:a' )->text === 'R1', 'Existing reservation still resolves at limit' );
	$db->begin();
	refusedIdentity( fn () => $registry->reserve( IdentityKind::Entity, 'nested' ), 'No caller transaction nesting' );
	$db->rollback();
	// Real multiprocess race starting with initialized zero counters, repeated requests and shared legacy keys.
	makeIdentityDatabase( $racePath );
	$children = [];
	for ( $worker = 0; $worker < 6; $worker++ ) {
		$pid = pcntl_fork();
		if ( $pid === -1 ) { throw new RuntimeException( 'Cannot fork concurrency test' ); }
		if ( $pid === 0 ) {
			try {
				$workerRegistry = new SqlIdentityRegistry( new SqliteIdentityConnection( $racePath ) );
				for ( $i = 0; $i < 40; $i++ ) {
					$a = $workerRegistry->reserve( IdentityKind::Entity, "worker:$worker/op:$i" );
					if ( !$a->equals( $workerRegistry->reserve( IdentityKind::Entity, "worker:$worker/op:$i" ) ) ) { exit( 11 ); }
					$workerRegistry->assignLegacy( new LegacyIdentity( IdentityKind::Schema, 'neowiki', 'Shared schema' ) );
				}
				exit( 0 );
			} catch ( Throwable $error ) { fwrite( STDERR, $error->getMessage() . "\n" ); exit( 12 ); }
		}
		$children[] = $pid;
	}
	foreach ( $children as $pid ) {
		pcntl_waitpid( $pid, $status );
		checkIdentity( pcntl_wifexited( $status ) && pcntl_wexitstatus( $status ) === 0, 'Parallel worker completed' );
	}
	$race = new SqliteIdentityConnection( $racePath );
	$rows = $race->pdo->query( "SELECT swsi_number FROM test_sws_identity WHERE swsi_kind='entity' ORDER BY swsi_number" )->fetchAll( PDO::FETCH_COLUMN );
	checkIdentity( array_map( intval(...), $rows ) === range( 1, 240 ), '240 concurrent operations: unique, contiguous committed reservations' );
	checkIdentity( (int)$race->pdo->query( "SELECT COUNT(*) FROM test_sws_identity WHERE swsi_kind='schema'" )->fetchColumn() === 1, 'Concurrent legacy assignment exactly once' );
	checkIdentity( (int)$race->pdo->query( 'SELECT COUNT(*) FROM test_sws_identity_alias' )->fetchColumn() === 1, 'No duplicate shared alias' );
	$race->pdo->exec( "DELETE FROM test_sws_id_counter WHERE swsic_kind='relation'" );
	refusedIdentity( fn () => (new SqlIdentityRegistry( $race ))->reserve( IdentityKind::Relation, 'uninitialized' ), 'Missing updater initialization fails closed' );
	// Composite database constraints remain a second line of defense.
	refusedIdentity( fn () => $race->pdo->exec( "INSERT INTO test_sws_identity (swsi_id,swsi_kind,swsi_number,swsi_request) VALUES ('E999','entity',1,'other')" ), 'Duplicate kind/number forbidden' );
	$results = [ 'checks'=>$count, 'parallelWorkers'=>6, 'parallelOperations'=>240,
		'boundary'=>'Production SqlIdentityRegistry and shipped SQLite SQL on a real SQLite database; MediaWiki adapter and MySQL/PostgreSQL require host integration tests.' ];
	print json_encode( $results, JSON_PRETTY_PRINT ) . "\n";
} finally {
	@unlink( $path ); @unlink( $racePath );
}
