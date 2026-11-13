<?php
declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\REST;
use ProfessionalWiki\SWS\Domain\Identity\SchemaId;
use ProfessionalWiki\SWS\Domain\Schema\{Schema, SchemaName};
use ProfessionalWiki\SWS\SWSExtension;
class GetJsonSchemaByIdApi extends GetJsonSchemaApi {
 protected function findSchema( string $schemaName ): ?Schema {
  try { $id = new SchemaId( $schemaName ); } catch ( \InvalidArgumentException ) { return null; }
  $schema = SWSExtension::getInstance()->getSchemaLookup()->getSchema( new SchemaName( $id->text ) );
  return $schema?->getId()?->equals( $id ) ? $schema : null;
 }
}
