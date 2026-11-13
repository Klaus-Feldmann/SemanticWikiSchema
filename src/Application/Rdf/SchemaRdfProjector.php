<?php
declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Application\Rdf;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Rdf\{Iri, Quad, QuadList, RdfNamespaces, RdfLiteralFactory};
final readonly class SchemaRdfProjector {
 public function __construct( private RdfNamespaces $namespaces ) {}
 public function project( Schema $schema ): QuadList {
  $resource = $schema->getId() === null ? $this->namespaces->schemaClass( $schema->getName() ) : $this->namespaces->identifiedSchemaClass( $schema->getId() );
  $graph = new Iri( $resource->value . '/metadata' );
  $quads = [ new Quad( $resource, $this->namespaces->rdfType(), new Iri( RdfNamespaces::RDFS . 'Class' ), $graph ) ];
  if ( $schema->getIdentity() !== null ) { $quads = array_merge( $quads, IdentityRdfMetadata::quads( $resource, $schema->getIdentity(), $graph ) ); }
  else {
   $quads[] = new Quad( $resource, $this->namespaces->rdfsLabel(), RdfLiteralFactory::typed( $schema->getName()->getText(), 'string' ), $graph );
   if ( $schema->getDescription() !== '' ) { $quads[] = new Quad( $resource, new Iri( RdfNamespaces::RDFS . 'comment' ), RdfLiteralFactory::typed( $schema->getDescription(), 'string' ), $graph ); }
  }
  return new QuadList( ...$quads );
 }
}
