<?php
declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\REST;
use ProfessionalWiki\SWS\Domain\Identity\SchemaId;
use ProfessionalWiki\SWS\Domain\Schema\{Schema, SchemaReference};
use ProfessionalWiki\SWS\SWSExtension;
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
