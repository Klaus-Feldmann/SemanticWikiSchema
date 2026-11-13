<?php
declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Application\Identity;
use ProfessionalWiki\SWS\Domain\Identity\{IdentityKind, LegacyIdentity, SchemaId};
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
/** Read-only compatibility resolution. Persistence and historical readers retain the original IDs. */
final readonly class IdentityBoundaryResolver {
 public function __construct( private IdentityRegistry $registry, private string $origin ) {}
 public function entity( SubjectId $id ): SubjectId {
  if ( !$id->isLocal() || $id->isSequential() ) { return $id; }
  $canonical = $this->registry->findLegacy( new LegacyIdentity( IdentityKind::Entity, $this->origin, $id->text ) );
  return $canonical === null ? $id : new SubjectId( $canonical->text );
 }
 public function schema( SchemaReference $reference ): SchemaReference {
  if ( !$reference->isLocal() || $reference->id !== null ) { return $reference; }
  $canonical = $this->registry->findLegacy( new LegacyIdentity( IdentityKind::Schema, $this->origin, $reference->name->getText() ) );
  return $canonical instanceof SchemaId ? SchemaReference::identified( $canonical ) : $reference;
 }
}
