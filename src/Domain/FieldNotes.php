<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain;

/** Only author text is accepted here. Source states are derived, never accepted from a writer. */
final class FieldNotes {
    public static function validate( array $notes ): array {
        $result = [];
        foreach ( $notes as $name => $text ) {
            \ProfessionalWiki\SWS\Domain\Schema\PropertyNameRules::assertValid( (string)$name );
            if ( !is_string( $text ) ) { throw new \InvalidArgumentException( 'Field notes must be strings' ); }
            if ( trim( $text ) !== '' ) { $result[(string)$name] = $text; }
        }
        return $result;
    }
    public static function forSchema( array $notes, array $previous, ?\ProfessionalWiki\SWS\Domain\Schema\Schema $schema ): array {
        $notes = self::validate( $notes );
        foreach ( $notes as $name => $text ) {
            if ( ( $previous[$name] ?? null ) === $text ) { continue; }
            if ( !$schema || !$schema->hasProperty( $name ) || $schema->getProperty( $name )->getFieldNotes() === null || $schema->getProperty( $name )->isSchemaExecution() ) {
                throw new \InvalidArgumentException( 'Notes are not enabled for field: ' . $name );
            }
        }
        return $notes;
    }

}
