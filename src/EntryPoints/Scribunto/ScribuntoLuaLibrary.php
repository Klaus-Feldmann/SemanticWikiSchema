<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\Scribunto;

use Exception;
use InvalidArgumentException;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use AEWI\SWS\Domain\Schema\SchemaReference;
use AEWI\SWS\EntryPoints\ParserAuthority;
use AEWI\SWS\Infrastructure\ParserPageDependencyRecorder;
use AEWI\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException;
use AEWI\SWS\GraphDatabasePlugins\Neo4j\EntryPoints\CypherErrorMessage;
use AEWI\SWS\GraphDatabasePlugins\Neo4j\EntryPoints\Lua\CypherQueryRunner;
use AEWI\SWS\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryException;
use AEWI\SWS\GraphDatabasePlugins\Sparql\EntryPoints\Lua\SparqlQueryRunner;
use AEWI\SWS\GraphDatabasePlugins\Sparql\EntryPoints\SparqlErrorMessage;
use AEWI\SWS\SWSExtension;

class ScribuntoLuaLibrary extends LibraryBase {

	private ?SubjectDataLookup $subjectDataLookup = null;
	private ?\AEWI\SWS\EntryPoints\EffectiveFieldLookup $effectiveFields = null;
	private ?SchemaLuaSerializer $schemaLuaSerializer = null;
	private ?CypherQueryRunner $cypherQueryRunner = null;
	private ?SparqlQueryRunner $sparqlQueryRunner = null;

	private function getSubjectDataLookup(): SubjectDataLookup {
		if ( $this->subjectDataLookup === null ) {
			$this->subjectDataLookup = new SubjectDataLookup(
				SWSExtension::getInstance()->newSubjectResolver( $this->getParser() ),
			);
		}

		return $this->subjectDataLookup;
	}

	private function getSchemaLuaSerializer(): SchemaLuaSerializer {
		if ( $this->schemaLuaSerializer === null ) {
			$this->schemaLuaSerializer = new SchemaLuaSerializer();
		}
		return $this->schemaLuaSerializer;
	}

	private function getCypherQueryRunner(): CypherQueryRunner {
		if ( $this->cypherQueryRunner === null ) {
			$this->cypherQueryRunner = SWSExtension::getInstance()->newCypherQueryRunner( $this->getParser() );
		}

		return $this->cypherQueryRunner;
	}

	private function getSparqlQueryRunner(): SparqlQueryRunner {
		if ( $this->sparqlQueryRunner === null ) {
			$this->sparqlQueryRunner = SWSExtension::getInstance()->newSparqlQueryRunner( $this->getParser() );
		}

		return $this->sparqlQueryRunner;
	}

	public function register(): array {
		$lib = [
			'getValue' => [ $this, 'getValue' ],
			'getAll' => [ $this, 'getAll' ],
			'getMainSubject' => [ $this, 'getMainSubject' ],
			'getSubject' => [ $this, 'getSubject' ],
			'getSubjects' => [ $this, 'getSubjects' ],
			'getSchema' => [ $this, 'getSchema' ],
            'collectFields' => [ $this, 'collectFields' ],
            'hasAutomaticAnnotations' => [ $this, 'hasAutomaticAnnotations' ],
		];

		$neo4jFunctions = SWSExtension::getInstance()->getNeo4jPlugin()?->getLuaLibraryFunctionNames() ?? [];
		foreach ( $neo4jFunctions as $name ) {
			$lib[$name] = [ $this, $name ];
		}

		$sparqlFunctions = SWSExtension::getInstance()->getFirstSparqlPlugin()?->getLuaLibraryFunctionNames() ?? [];
		foreach ( $sparqlFunctions as $name ) {
			$lib[$name] = [ $this, $name ];
		}

		return $this->getEngine()->registerInterface(
			__DIR__ . '/mw.sws.lua', $lib, []
		);
	}

	public function getValue( ?string $propertyName = null, ?array $options = null ): array {
		$this->checkType( 'mw.sws.getValue', 1, $propertyName, 'string' );

		if ( ( $options['origin'] ?? 'all' ) === 'own' && ( isset( $options['page'] ) || isset( $options['subject'] ) ) ) {
			$this->incrementExpensiveFunctionCount();
		}

		if ( ( $options['origin'] ?? 'all' ) === 'own' ) {
			return $this->getSubjectDataLookup()->getValue( $this->getTitle(), $propertyName, $options );
		}
		$result = $this->collectFields( [ ...( $options ?? [] ), 'field' => $propertyName ] )[0];
		$values = array_map( static fn ( array $v ) => $v['origin'] === 'own' && is_scalar( $v['metadata']['raw'] ?? null )
			&& ( is_bool( $v['metadata']['raw'] ) || is_int( $v['metadata']['raw'] ) || is_float( $v['metadata']['raw'] ) )
			? $v['metadata']['raw'] : $v['value'], array_values( $result['fields'][1]['values'] ?? [] ) );
		return [ $values[0] ?? null ];
	}

	public function getAll( ?string $propertyName = null, ?array $options = null ): array {
		$this->checkType( 'mw.sws.getAll', 1, $propertyName, 'string' );

		if ( ( $options['origin'] ?? 'all' ) === 'own' && ( isset( $options['page'] ) || isset( $options['subject'] ) ) ) {
			$this->incrementExpensiveFunctionCount();
		}

		if ( ( $options['origin'] ?? 'all' ) === 'own' ) {
			return $this->getSubjectDataLookup()->getAll( $this->getTitle(), $propertyName, $options );
		}
		$result = $this->collectFields( [ ...( $options ?? [] ), 'field' => $propertyName ] )[0];
		$values = array_map( static fn ( array $v ) => $v['origin'] === 'own' && is_scalar( $v['metadata']['raw'] ?? null )
			&& ( is_bool( $v['metadata']['raw'] ) || is_int( $v['metadata']['raw'] ) || is_float( $v['metadata']['raw'] ) )
			? $v['metadata']['raw'] : $v['value'], array_values( $result['fields'][1]['values'] ?? [] ) );
		return [ $values === [] ? null : array_combine( range( 1, count( $values ) ), $values ) ];
	}

	public function getMainSubject( ?string $pageName = null ): array {
		$this->checkTypeOptional( 'mw.sws.getMainSubject', 1, $pageName, 'string', null );

		if ( $pageName !== null ) {
			$this->incrementExpensiveFunctionCount();
		}

		return $this->getSubjectDataLookup()->getMainSubjectData( $this->getTitle(), $pageName );
	}

	public function getSubject( ?string $subjectId = null ): array {
		$this->checkType( 'mw.sws.getSubject', 1, $subjectId, 'string' );
		$this->incrementExpensiveFunctionCount();

		return $this->getSubjectDataLookup()->getSubjectData( $subjectId );
	}

	public function getSubjects( ?string $pageName = null ): array {
		$this->checkTypeOptional( 'mw.sws.getSubjects', 1, $pageName, 'string', null );

		if ( $pageName !== null ) {
			$this->incrementExpensiveFunctionCount();
		}

		return $this->getSubjectDataLookup()->getSubjectsData( $this->getTitle(), $pageName );
	}

	public function query( ?string $cypher = null, ?array $params = null ): array {
		$this->checkType( 'mw.sws.query', 1, $cypher, 'string' );
		$this->checkTypeOptional( 'mw.sws.query', 2, $params, 'table', null );
		$this->incrementExpensiveFunctionCount();

		try {
			$rows = $this->getCypherQueryRunner()->run( $cypher, $params ?? [] );
		} catch ( QueryException $e ) {
			$message = CypherErrorMessage::for( $e );
			throw new LuaError( $this->getParser()->msg( $message->key, ...$message->params )->text() );
		} catch ( Exception $e ) {
			throw new LuaError( $e->getMessage() );
		}

		return [ $rows ];
	}

	public function sparqlQuery( ?string $sparql = null ): array {
		$this->checkType( 'mw.sws.sparqlQuery', 1, $sparql, 'string' );
		$this->incrementExpensiveFunctionCount();

		try {
			$document = $this->getSparqlQueryRunner()->run( $sparql ?? '' );
		} catch ( SparqlQueryException $e ) {
			$message = SparqlErrorMessage::for( $e );
			throw new LuaError( $this->getParser()->msg( $message->key, ...$message->params )->text() );
		} catch ( Exception $e ) {
			throw new LuaError( $e->getMessage() );
		}

		return [ $document ];
	}

	/**
	 * @param string|mixed[]|null $schemaReference A Schema name, or the `{source, name}` table a
	 *  sourced Subject's `schema` field holds, so that `nw.getSchema( subject.schema )` works for
	 *  either (ADR 23).
	 */
	public function getSchema( string|array|null $schemaReference = null ): array {
		if ( !is_array( $schemaReference ) ) {
			$this->checkType( 'mw.sws.getSchema', 1, $schemaReference, 'string' );
		}

		$this->incrementExpensiveFunctionCount();

		$extension = SWSExtension::getInstance();

		try {
			$reference = $extension->getSchemaReferenceParser()->fromJson( $schemaReference );
		} catch ( InvalidArgumentException ) {
			return [ null ];
		}

		$schema = $extension->getSchemaResolverFor( ParserAuthority::of( $this->getParser() ) )->getSchema( $reference );

		// Also track a missing local Schema: creating it must refresh pages that read it.
		// Successful name/alias reads keep depending on the resolved, stable S-ID page.
		if ( $reference->isLocal() ) {
			( new ParserPageDependencyRecorder( $this->getParser() ) )
				->recordDependencyOnSchema( $schema?->getReference() ?? $reference );
		}

		if ( $schema === null ) {
			return [ null ];
		}

        return [ $this->getSchemaLuaSerializer()->toLuaTable( $schema ) ];
	}

    public function hasAutomaticAnnotations(): array { return [ true ]; }

	public function collectFields( ?array $options = null ): array {
		$options ??= [];
		// The expensive-function limit also bounds reads of other local pages.
		if ( isset( $options['page'] ) || isset( $options['subject'] ) ) { $this->incrementExpensiveFunctionCount(); }
		try {
			$this->effectiveFields ??= \AEWI\SWS\EntryPoints\ParserFieldLookup::create( $this->getParser() );
			return [ self::fieldsToLua( $this->effectiveFields->read( $this->getTitle(), $options ) ) ];
		} catch ( \InvalidArgumentException|\RuntimeException $e ) {
			if ( $processing = \AEWI\SWS\EntryPoints\Processing\ProcessingContext::current() ) { $processing->failure ??= $e->getMessage(); }
			throw new LuaError( 'SWS: ' . $e->getMessage() );
		}
	}

    private static function fieldsToLua(mixed $value): mixed {
        // JSON identity maps remain objects in stored/read-model data, but Scribunto
        // accepts PHP arrays as Lua tables, not stdClass. Convert only at this boundary.
        if($value instanceof \stdClass) {
            $out=[];
            foreach(get_object_vars($value)as$key=>$item) { $out[$key]=self::fieldsToLua($item); }
            return $out;
        }
        if(!is_array($value)) { return $value; }
        $list=array_is_list($value);$out=[];
        foreach($value as $key=>$item) { $out[$list ? $key+1 : $key]=self::fieldsToLua($item); }
        return $out;
    }

}
