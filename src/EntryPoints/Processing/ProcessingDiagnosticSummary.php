<?php

declare( strict_types = 1 );
namespace AEWI\SWS\EntryPoints\Processing;

/** Pure metadata projection. Never returns source values, notes, receipts, signatures or raw errors. */
final class ProcessingDiagnosticSummary {
    public static function observation( array|false $record, int $revision, ?bool $stampCurrent ): array {
        if ( $record === false ) { return [ 'status' => 'unknown', 'reason' => 'no-recent-observation', 'retentionSeconds' => 86400 ]; }
        $matches = ( $record['revision'] ?? null ) === $revision;
        $state = in_array( $record['state'] ?? '', [ 'pending', 'prepared', 'failed', 'superseded', 'complete', 'complete-empty' ], true ) ? $record['state'] : 'unknown';
        $status = !$matches || $stampCurrent === false ? 'stale' :
            ( in_array( $state, [ 'complete', 'complete-empty' ], true ) && $stampCurrent === null ? 'unknown' : $state );
        return [ 'status' => $status, 'observedState' => $state, 'observedRevision' => $record['revision'] ?? null,
            'observedAt' => $record['at'] ?? null, 'matchesPublishedRevision' => $matches,
            'dependenciesCurrent' => $stampCurrent, 'evidence' => 'transient-processing-observation', 'retentionSeconds' => 86400,
            'completionStage' => $record['completionStage'] ?? null ];
    }

    public static function source( ?array $snapshot, string $language, ?string $field = null ): array {
        $data = $snapshot['languages'][$language] ?? null;
        if ( !is_array( $data ) ) { return [ 'status' => 'not-recorded', 'language' => $language, 'fields' => [], 'identities' => [] ]; }
        $fields = [];
        foreach ( $data['fields'] as $name => $entry ) {
            if ( $field !== null && (string)$name !== $field ) { continue; }
            $fields[] = [ 'field' => (string)$name ] + self::entry( $entry );
        }
        $identities = [];
        foreach ( $data['identities'] as $source => $entry ) { $identities[] = [ 'source' => (string)$source ] + self::entry( $entry ); }
        return [ 'status' => 'recorded', 'language' => $language, 'lastAttemptAt' => $data['attemptedAt'],
            'baseRevision' => $data['baseRevisionId'],
            'requestedFieldRecorded' => $field === null ? null : array_key_exists( $field, $data['fields'] ),
            'fields' => $fields, 'identities' => $identities ];
    }

    private static function entry( array $entry ): array {
        $attempt = $entry['lastAttempt'];
        $status = $attempt['status'] ?? 'unknown';
        $reason = $attempt['reason'] ?? null;
        // Only normalized reason codes, never arbitrary upstream exception text or URLs.
        $reason = is_string( $reason ) && preg_match( '/\A[a-z0-9][a-z0-9_-]{0,95}\z/', $reason ) ? $reason : null;
        return [ 'status' => $status, 'reason' => $reason, 'source' => $attempt['source'] ?? null,
            'propertyId' => $attempt['propertyId'] ?? null, 'lastSuccessAt' => $entry['lastSuccess']['at'] ?? null,
            'hasRetainedSuccess' => ( $entry['lastSuccess'] ?? null ) !== null && $status !== 'resolved' ];
    }
}
