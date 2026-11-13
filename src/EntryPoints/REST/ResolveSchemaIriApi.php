<?php
declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\REST;
use MediaWiki\Rest\{Response, SimpleHandler};
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\SWSExtension;
class ResolveSchemaIriApi extends SimpleHandler {
 use ReadOnlyEndpoint;
 use SchemaReadEndpoint;
 public function __construct( private readonly bool $identified ) {}
 public function run( string $schemaName ): Response {
  try { $schema = $this->readSchemaReference( $schemaName, $this->identified ); }
  catch ( \InvalidArgumentException ) { return $this->getResponseFactory()->createHttpError( 400, [ 'message'=>'Invalid schema reference' ] ); }
  if ( $schema === null ) { return $this->getResponseFactory()->createHttpError( 404, [ 'message'=>'Schema not found' ] ); }
  $accept = $this->getRequest()->getHeaderLine( 'Accept' );
  $path = $schema->getId() === null ? '/sws/v0/schema/{schemaName}' : '/sws/v0/schema-id/{schemaName}';
  if ( str_contains( $accept, 'application/trig' ) || str_contains( $accept, 'text/turtle' ) ) {
   $url = $this->getRouter()->getRouteUrl( $path . '/rdf', [ 'schemaName'=>$schema->getName()->getText() ], [ 'format'=>str_contains( $accept, 'application/trig' ) ? 'trig' : 'turtle' ] );
  } elseif ( str_contains( $accept, 'application/json' ) ) {
   $url = $this->getRouter()->getRouteUrl( $path, [ 'schemaName'=>$schema->getName()->getText() ] );
  } else {
   $url = Title::makeTitleSafe( SWSExtension::NS_SCHEMA, $schema->getName()->getText() )?->getCanonicalURL();
  }
  if ( $url === null ) { return $this->getResponseFactory()->createHttpError( 404, [ 'message'=>'Schema not found' ] ); }
  $response = $this->getResponseFactory()->createSeeOther( $url );
  $response->addHeader( 'Vary', 'Accept' );
  return $response;
 }
}
