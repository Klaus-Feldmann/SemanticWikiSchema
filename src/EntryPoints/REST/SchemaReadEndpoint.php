<?php
declare( strict_types = 1 );
namespace AEWI\SWS\EntryPoints\REST;
use AEWI\SWS\Domain\Identity\SchemaId;
use AEWI\SWS\Domain\Schema\{Schema, SchemaReference};
use AEWI\SWS\SWSExtension;
trait SchemaReadEndpoint {
 protected function readSchemaReference( string $name, bool $identified ): ?Schema {
  $extension = SWSExtension::getInstance();
  $reference = $identified ? SchemaReference::identified( new SchemaId( $name ) ) : $extension->getSchemaReferenceParser()->localName( $name );
  return $extension->getSchemaResolverFor( $this->getAuthority() )->getSchema( $reference );
 }
 public function getParamSettings(): array {
  return [ 'schemaName'=>[ self::PARAM_SOURCE=>'path', \Wikimedia\ParamValidator\ParamValidator::PARAM_TYPE=>'string', \Wikimedia\ParamValidator\ParamValidator::PARAM_REQUIRED=>true ] ];
 }
}
