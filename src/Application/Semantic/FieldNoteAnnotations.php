<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Application\Semantic;

/** Notes are a separate projection. Never turn a source state into a field value. */
final class FieldNoteAnnotations {
    public static function sourceText( array $field ): ?string {
        if (($field['external']['storageMode'] ?? '') === 'store' && !($field['external']['recorded'] ?? false)) { return null; }
        $status = $field['sourceStatus'] ?? $field['status'] ?? '';
        if ( !in_array( $status, [ 'unavailable', 'missing', 'unsupported', 'manual' ], true ) || !isset( $field['externalProperty'] ) ) { return null; }
        $binding = $field['externalProperty'];
        return wfMessage( 'sws-notes-source-' . $status, $binding['source'] === 'wikidata' ? 'Wikidata' : 'Wikibase', $binding['id'] )->inContentLanguage()->text();
    }
    public function build( array $result, array $config ): array {
        $fields = array_column( $result['fields'], null, 'name' );
        $mapping = $config['properties'][$result['schema']['name']] ?? [];
        $suffix = null;
        $rows = [];
        foreach ( $result['schema']['properties'] as $definition ) {
            if ( !( $definition['fieldNotes']['annotate'] ?? false ) || $definition['type'] === 'wikitext' ) { continue; }
            $name = $definition['name'];
            $suffix ??= wfMessage( 'sws-notes-property-suffix' )->inContentLanguage()->text();
            if ( trim( $suffix ) === '' ) { throw new \InvalidArgumentException( 'The note property suffix must not be empty' ); }
            $target = trim( ( $mapping[$name] ?? $name ) . $suffix );
            // Use exactly the same validation as ordinary mapped property names.
            SmwMappingConfig::resolve( [ 'properties' => [ 'notes' => [ 'note' => $target ] ] ] );
            $values = [];
            $author = $result['subject']['fieldNotes'][$name] ?? '';
            if ( trim( $author ) !== '' ) { $values[] = $author; }
            $sourceField = $fields[$name] ?? null;
            // Images are not scalar annotations. Reuse an already-read source snapshot only;
            // never fetch one solely to manufacture a note for a skipped image field.
            if ( $sourceField === null && isset( $definition['externalProperty'], $result['sourceFields'][$name] )
                && ( $result['subject']['statements'][$name]['values'] ?? [] ) === [] ) {
                $raw = $result['sourceFields'][$name]; $binding = $definition['externalProperty'];
                if ( ( $raw['source'] ?? null ) !== $binding['source'] || ( $raw['propertyId'] ?? null ) !== $binding['id'] ) {
                    throw new \InvalidArgumentException( 'Mismatched note source snapshot' );
                }
                $sourceField = [ 'status' => $raw['status'] ?? null, 'sourceStatus' => $raw['sourceStatus'] ?? $raw['status'] ?? null, 'external' => $raw, 'externalProperty' => $binding ];
            }
            $source = $sourceField !== null ? self::sourceText( $sourceField ) : null;
            if ( $source !== null ) { $values[] = $source; }
            foreach ( array_unique( $values ) as $value ) { $rows[] = $target . '=' . $value; }
        }
        return $rows;
    }
}
