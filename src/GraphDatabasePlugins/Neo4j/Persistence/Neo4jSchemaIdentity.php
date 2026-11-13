<?php
declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence;
use ProfessionalWiki\SWS\Domain\Schema\{Schema, SchemaReference};
final class Neo4jSchemaIdentity {
 public static function label( ?Schema $schema, SchemaReference $reference ): string {
  $name = ( $schema?->getName() ?? $reference->name )->getText();
  if ( $schema?->getId() !== null ) { return 'sws#schema-id/' . ( $reference->source === null ? '' : $reference->source . '/' ) . $schema->getId()->text; }
  return $reference->source === null ? $name : 'sws#schema-source/' . $reference->source . '/' . $name;
 }
}
