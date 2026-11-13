<?php
declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\REST;
use MediaWiki\Rest\Response;
use ProfessionalWiki\SWS\Domain\Identity\SchemaId;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\SWSExtension;
class GetSchemaByIdApi extends GetSchemaApi {
 public function run( string $schemaName ): Response {
  try { $id = new SchemaId( $schemaName ); }
  catch ( \InvalidArgumentException ) { return $this->getResponseFactory()->createHttpError( 400, [ 'message'=>'Invalid schema ID' ] ); }
  $extension = SWSExtension::getInstance();
  $schema = $extension->getSchemaLookup()->getSchema( new SchemaName( $id->text ) );
  if ( $schema?->getId() === null || !$schema->getId()->equals( $id ) ) {
   return $this->getResponseFactory()->createHttpError( 404, [ 'message'=>'Schema not found' ] );
  }
  return $this->getResponseFactory()->createJson( [ 'schema'=>$extension->getSchemaPresentationSerializer()->toArray( $schema ) ] );
 }
}
