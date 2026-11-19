<?php
declare( strict_types = 1 );
namespace AEWI\SWS\EntryPoints\REST;
use AEWI\SWS\Domain\Identity\SchemaId;
use AEWI\SWS\Domain\Schema\{Schema, SchemaName};
use AEWI\SWS\SWSExtension;
class GetJsonSchemaByIdApi extends GetJsonSchemaApi {
 protected function findSchema( string $schemaName ): ?Schema {
  try { $id = new SchemaId( $schemaName ); } catch ( \InvalidArgumentException ) { return null; }
  $schema = SWSExtension::getInstance()->getSchemaLookup()->getSchema( new SchemaName( $id->text ) );
  return $schema?->getId()?->equals( $id ) ? $schema : null;
 }
}
