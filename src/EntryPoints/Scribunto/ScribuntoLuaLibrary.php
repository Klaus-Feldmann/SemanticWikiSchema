<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\Scribunto;

use Exception;
use InvalidArgumentException;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LibraryBase;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\EntryPoints\ParserAuthority;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\EntryPoints\CypherErrorMessage;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\EntryPoints\Lua\CypherQueryRunner;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\EntryPoints\Lua\SparqlQueryRunner;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\EntryPoints\SparqlErrorMessage;
use ProfessionalWiki\SWS\SWSExtension;

class ScribuntoLuaLibrary extends LibraryBase {

	private ?SubjectDataLookup $subjectDataLookup = null;
    private array $effectiveReaders = [];
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

		if ( $options !== null && ( isset( $options['page'] ) || isset( $options['subject'] ) ) ) {
			$this->incrementExpensiveFunctionCount();
		}

		return $this->getSubjectDataLookup()->getValue( $this->getTitle(), $propertyName, $options );
	}

	public function getAll( ?string $propertyName = null, ?array $options = null ): array {
		$this->checkType( 'mw.sws.getAll', 1, $propertyName, 'string' );

		if ( $options !== null && ( isset( $options['page'] ) || isset( $options['subject'] ) ) ) {
			$this->incrementExpensiveFunctionCount();
		}

		return $this->getSubjectDataLookup()->getAll( $this->getTitle(), $propertyName, $options );
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

		if ( $schema === null ) {
			return [ null ];
		}

		return [ $this->getSchemaLuaSerializer()->toLuaTable( $schema ) ];
	}

    public function hasAutomaticAnnotations(): array { return [ true ]; }

    public function collectFields(?array $options = null): array {
        $o=$options ?? [];
        foreach($o as $key=>$value) {
            if(!in_array($key,['page','subject','field','language','origin','includeImages'],true) || ($key==='includeImages' ? !is_bool($value) : !is_string($value))) {
                throw new LuaError('SWS: invalid field lookup option');
            }
        }
        if(isset($o['page'],$o['subject'])) { throw new LuaError('SWS: specify page or subject, not both'); }
        $title=isset($o['page']) ? \MediaWiki\Title\Title::newFromText($o['page']) : $this->getTitle();
        if(!$title || $title->isExternal() || !$title->canExist()) { throw new LuaError('SWS: invalid lookup page'); }
        $extension=SWSExtension::getInstance();
        $resolver=$extension->newSubjectResolver($this->getParser());
        $subject=isset($o['subject']) ? $resolver->resolveById($o['subject']) : $resolver->resolveMainByTitle($title);
        if(!$subject) { return [ [ 'fields'=>[], 'missing'=>true ] ]; }
        $authority=ParserAuthority::of($this->getParser());
        $schema=$extension->getSchemaResolverFor($authority)->getSchema($subject->getSchemaReference());
        if(!$schema) { throw new LuaError('SWS: schema unavailable'); }
        $dependencies=new \ProfessionalWiki\SWS\Infrastructure\ParserPageDependencyRecorder($this->getParser());
        $dependencies->recordDependencyOnSchema($subject->getSchemaReference());
        $services=\MediaWiki\MediaWikiServices::getInstance();
        $language=$o['language'] ?? $services->getContentLanguage()->getCode();
        if(!preg_match('/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/',$language)) { throw new LuaError('SWS: invalid language'); }
        $key=json_encode([$subject->id->text,$title->getPrefixedText(),$language]);
        try {
            if(!isset($this->effectiveReaders[$key])) {
                $this->incrementExpensiveFunctionCount();
                $context=new \MediaWiki\Context\DerivativeContext(\MediaWiki\Context\RequestContext::getMain());
                $context->setUser($services->getUserFactory()->newFromUserIdentity($this->getParser()->getUserIdentity()));
                $this->effectiveReaders[$key]=\ProfessionalWiki\SWS\Application\Semantic\FieldSnapshot::reader($schema,$subject,
                    static function() use($context,$title,$subject,$language,$dependencies): array {
                        if(!class_exists(\SWSBaseBridge\ExternalValuesLookup::class)) { throw new \RuntimeException('SWS source bridge unavailable'); }
                        return (new \SWSBaseBridge\ExternalValuesLookup($context))->read($title,[
                            'pageid'=>$title->getArticleID(),'subject'=>$subject->id->text,'language'=>$language,'presentation'=>false
                        ],$dependencies);
                    }, fn(string $name) => $this->getSubjectDataLookup()->getAll($title,$name,['subject'=>$subject->id->text])[0]);
            }
            $result=$this->effectiveReaders[$key]->collect($o['field'] ?? null,$o['origin'] ?? 'all',$language,$o['includeImages'] ?? false);
            $result['subject']=isset($o['subject']) ? $this->getSubjectDataLookup()->getSubjectData($o['subject'])[0]
                : $this->getSubjectDataLookup()->getMainSubjectData($title)[0];
            return [ self::fieldsToLua($result) ];
        } catch (\Throwable $e) { throw new LuaError('SWS: '.$e->getMessage()); }
    }

    private static function fieldsToLua(mixed $value): mixed {
        if(!is_array($value)) { return $value; }
        $list=array_is_list($value);$out=[];
        foreach($value as $key=>$item) { $out[$list ? $key+1 : $key]=self::fieldsToLua($item); }
        return $out;
    }

}
