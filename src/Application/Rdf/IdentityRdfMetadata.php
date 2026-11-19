<?php
declare( strict_types = 1 );
namespace AEWI\SWS\Application\Rdf;
use AEWI\SWS\Domain\Identity\LocalizedIdentity;
use AEWI\SWS\Domain\Rdf\{Iri, Quad, RdfNamespaces, RdfLiteralFactory};
final class IdentityRdfMetadata {
 public static function quads( Iri $resource, LocalizedIdentity $identity, Iri $graph ): array {
  $quads = [];
  foreach ( [ RdfNamespaces::RDFS . 'label'=>$identity->labels, RdfNamespaces::RDFS . 'comment'=>$identity->descriptions ] as $predicate=>$terms ) {
   foreach ( $terms as $language=>$text ) {
    if ( trim( $text ) !== '' ) { $quads[] = new Quad( $resource, new Iri( $predicate ), RdfLiteralFactory::languageTagged( $text, $language ), $graph ); }
   }
  }
  return $quads;
 }
}
