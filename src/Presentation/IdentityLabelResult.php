<?php
declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Presentation;
use ProfessionalWiki\SWS\Application\SubjectLabelLookupResult;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
/** Own saved metadata only; caller authorizes and selects the published entity before calling. */
final class IdentityLabelResult {
 public static function serialize( SubjectLabelLookupResult $result, ?Subject $subject, ?string $language ): array {
  $identity = $subject?->getIdentity();
  return [ 'id'=>$result->id, 'label'=>$identity?->label( $language ) ?? $result->label, 'pageTitle'=>$result->pageTitle ]
   + ( $identity === null ? [] : [ 'description'=>$identity->description( $language ) ] + $identity->toJson() );
 }
}
