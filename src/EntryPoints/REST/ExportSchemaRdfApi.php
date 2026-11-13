<?php
declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\REST;
use MediaWiki\Rest\{Response, SimpleHandler};
use ProfessionalWiki\SWS\Application\Rdf\SchemaRdfProjector;
use ProfessionalWiki\SWS\SWSExtension;
class ExportSchemaRdfApi extends SimpleHandler {
 use ReadOnlyEndpoint;
 use SchemaReadEndpoint { getParamSettings as private schemaParamSettings; }
 use RdfFormatNegotiation;
 public function __construct( private readonly bool $identified ) {}
 public function run( string $schemaName ): Response {
  try { $schema = $this->readSchemaReference( $schemaName, $this->identified ); }
  catch ( \InvalidArgumentException ) { $schema = null; }
  if ( $schema === null ) { return $this->getResponseFactory()->createHttpError( 404, [ 'message'=>'Schema not found' ] ); }
  $extension = SWSExtension::getInstance();
  $format = $this->resolveFormat();
  $quads = ( new SchemaRdfProjector( $extension->getRdfNamespaces() ) )->project( $schema );
  return $this->rdfResponse( $extension->getRdfSerializer()->serialize( $quads, $format ), $format, $schema->getId()?->text ?? 'schema' );
 }
 public function getParamSettings(): array {
  return $this->schemaParamSettings() + [ 'format'=>[ self::PARAM_SOURCE=>'query', \Wikimedia\ParamValidator\ParamValidator::PARAM_TYPE=>['turtle','trig'], \Wikimedia\ParamValidator\ParamValidator::PARAM_REQUIRED=>false ] ];
 }
}
