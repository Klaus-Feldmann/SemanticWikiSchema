<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Persistence;

use GenerateSchemaChangeSql;
use GenerateSchemaSql;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\SWS\Domain\GraphDatabase\GraphStoreName;

/**
 * The per-DBMS SQL that update.php applies is generated from the abstract schema and committed
 * alongside it, so the two can drift: an edit to either one alone leaves installs creating a table that
 * no longer matches what the code expects. Regenerating and comparing catches that.
 *
 * @coversNothing
 */
class DatabaseSchemaTest extends MediaWikiIntegrationTestCase {

	/**
	 * The longest name a store may be called and the width of the column its runs are filed under are
	 * declared in two places that know nothing about each other. Narrowing the column alone would leave
	 * accepted names the records cannot hold whole, and every lookup for such a store would then match
	 * nothing. Read off the abstract schema rather than off a per-DBMS file, so this holds wherever the
	 * suite runs — only MySQL materialises the width at all.
	 */
	public function testTheStoreNameLimitIsTheWidthOfTheColumnItIsFiledUnder(): void {
		$schema = json_decode(
			(string)file_get_contents( dirname( __DIR__, 3 ) . '/sql/sws_rebuild_runs.json' ),
			true
		);

		$columns = array_column( $schema[0]['columns'], null, 'name' );

		$this->assertSame(
			GraphStoreName::MAX_LENGTH,
			$columns['swsrr_store']['type'] === 'binary' ? $columns['swsrr_store']['options']['length'] : null,
			'GraphStoreName::MAX_LENGTH and the swsrr_store column width have to agree'
		);
	}

	/**
	 * @dataProvider tableProvider
	 */
	public function testGeneratedSqlMatchesTheAbstractSchema( string $table, string $databaseType ): void {
		$extensionPath = dirname( __DIR__, 3 );
		$generatedPath = $this->getNewTempFile();

		$script = new GenerateSchemaSql();
		$script->loadWithArgv( [
			'--json=' . $extensionPath . '/sql/' . $table . '.json',
			'--sql=' . $generatedPath,
			'--type=' . $databaseType,
			'--quiet',
		] );
		$script->execute();

		$this->assertSame(
			self::withoutSourcePath( (string)file_get_contents(
				$extensionPath . '/sql/' . $databaseType . '/' . $table . '.sql'
			) ),
			self::withoutSourcePath( (string)file_get_contents( $generatedPath ) ),
			'run `make dbschema` to regenerate the ' . $databaseType . ' schema of ' . $table
		);
	}

	/**
	 * The patch that adds a column to an already-installed table is generated the same way and drifts the
	 * same way, and an install that has the table but not the column is exactly what it exists for.
	 *
	 * @dataProvider databaseTypeProvider
	 */
	public function testGeneratedPhasePatchMatchesTheAbstractSchemaChange( string $databaseType ): void {
		$extensionPath = dirname( __DIR__, 3 );
		$generatedPath = $this->getNewTempFile();

		$script = new GenerateSchemaChangeSql();
		$script->loadWithArgv( [
			'--json=' . $extensionPath . '/sql/abstractSchemaChanges/patch-sws_rebuild_runs-swsrr_phase.json',
			'--sql=' . $generatedPath,
			'--type=' . $databaseType,
			'--quiet',
		] );
		$script->execute();

		$this->assertSame(
			self::withoutSourcePath( (string)file_get_contents(
				$extensionPath . '/sql/' . $databaseType . '/patch-sws_rebuild_runs-swsrr_phase.sql'
			) ),
			self::withoutSourcePath( (string)file_get_contents( $generatedPath ) ),
			'run `make dbschema` to regenerate the ' . $databaseType . ' schema change'
		);
	}

	public function tableProvider(): iterable {
		yield 'rebuild runs, mysql' => [ 'sws_rebuild_runs', 'mysql' ];
		yield 'rebuild runs, sqlite' => [ 'sws_rebuild_runs', 'sqlite' ];
		yield 'rebuild runs, postgres' => [ 'sws_rebuild_runs', 'postgres' ];
		yield 'subject page, mysql' => [ 'sws_subject_page', 'mysql' ];
		yield 'subject page, sqlite' => [ 'sws_subject_page', 'sqlite' ];
		yield 'subject page, postgres' => [ 'sws_subject_page', 'postgres' ];
		yield 'subject, mysql' => [ 'sws_subject', 'mysql' ];
		yield 'subject, sqlite' => [ 'sws_subject', 'sqlite' ];
		yield 'subject, postgres' => [ 'sws_subject', 'postgres' ];
		yield 'relation, mysql' => [ 'sws_relation', 'mysql' ];
		yield 'relation, sqlite' => [ 'sws_relation', 'sqlite' ];
		yield 'relation, postgres' => [ 'sws_relation', 'postgres' ];
	}

	public function databaseTypeProvider(): iterable {
		yield 'mysql' => [ 'mysql' ];
		yield 'sqlite' => [ 'sqlite' ];
		yield 'postgres' => [ 'postgres' ];
	}

	/**
	 * The generator writes the JSON's path into a header comment, and that path depends on the working
	 * directory it ran from. Only the schema itself is being compared.
	 */
	private static function withoutSourcePath( string $sql ): string {
		return (string)preg_replace( '/^-- Source: .*$/m', '-- Source:', $sql );
	}

}
