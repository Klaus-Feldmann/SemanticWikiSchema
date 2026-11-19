<?php

declare( strict_types = 1 );
namespace AEWI\SWS\EntryPoints\Processing;

use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use AEWI\SWS\Application\Source\SourceDataMode;
use AEWI\SWS\SWSExtension;
use RuntimeException;
use Wikimedia\Rdbms\IDBAccessObject;

/** Reads only authorized published slots and existing operational metadata; no parse, fetch or rebuild. */
final class ProcessingDiagnosticLookup {
    public function __construct( private readonly IContextSource $context ) {}

    public function read( Title $title, string $language, ?string $entity = null, ?string $field = null ): array {
        $authority = $this->context->getAuthority();
        if ( !$authority->authorizeRead( 'read', $title ) ) { throw new RuntimeException( 'Unavailable' ); }
        $extension = SWSExtension::getInstance();
        $services = MediaWikiServices::getInstance();
        $latest = $services->getRevisionLookup()->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );
        $policy = $extension->getRevisionPolicy();
        $published = $latest === null ? null : $policy->publishedRevision( $latest );
        if ( $published === null || $published->getPageId() !== $title->getArticleID() ||
            !$policy->revisionIsReadableBy( $published, $authority ) || !$published->userCan( RevisionRecord::DELETED_TEXT, $authority ) ) {
            throw new RuntimeException( 'Unavailable' );
        }
        $latestReadable = $policy->revisionIsReadableBy( $latest, $authority ) && $latest->userCan( RevisionRecord::DELETED_TEXT, $authority );
        $record = ProcessingState::read( $title );
        // A transient record for a draft/suppressed revision must not disclose its metadata.
        if ( $record !== false && ( $record['revision'] ?? null ) !== $published->getId() ) { $record = false; }
        $stamp = $record['stamp'] ?? null;
        try { $current = is_array( $stamp ) ? ProcessingResult::isCurrent( $title, $stamp ) : null; }
        catch ( \Throwable ) { $current = null; }
        $observation = ProcessingDiagnosticSummary::observation( $record, $published->getId(), $current );
        $entities = [];
        $hasSlot = $published->hasSlot( 'sws' );
        if ( $hasSlot ) {
            $content = $extension->newSubjectContentRepository( $authority )->getSubjectContentByRevisionId( $published->getId() );
            if ( $content === null ) { throw new RuntimeException( 'Unavailable' ); }
            foreach ( $content->getPageSubjects()->getAllSubjects()->asArray() as $subject ) {
                if ( $entity !== null && $subject->id->text !== $entity ) { continue; }
                $snapshot = $subject->getSourceSnapshot()?->toJson();
                $entities[] = [ 'id' => $subject->id->text, 'schema' => $subject->getSchemaReference()->toJson(),
                    'sources' => ProcessingDiagnosticSummary::source( $snapshot, $language, $field ) ];
            }
        }
        if ( $entity !== null && $entities === [] ) { throw new RuntimeException( 'Unavailable' ); }
        $smwObserved = $current === true && ( $record['completionStage'] ?? null ) === 'smw-store' &&
            in_array( $record['state'] ?? '', [ 'complete', 'complete-empty' ], true );
        return [ 'version' => 1, 'readOnly' => true, 'checkedAt' => gmdate( 'Y-m-d\TH:i:s\Z' ),
            'page' => [ 'id' => $title->getArticleID(), 'title' => $title->getPrefixedText(),
                'latestRevision' => $latestReadable ? $latest->getId() : null, 'publishedRevision' => $published->getId(), 'hasSwsSlot' => $hasSlot ],
            'mode' => SourceDataMode::current(), 'processing' => $observation,
            'sourceEvidence' => [ 'scope' => 'stored-snapshot-only', 'liveCacheInspected' => false ],
            'queue' => [ 'status' => 'unknown', 'reason' => 'queue-not-inspected' ],
            'smw' => [ 'status' => !$services->hasService( 'SMW.ParserDataFactory' ) ? 'not-installed' :
                ( $smwObserved ? 'write-observed' : 'unknown' ), 'reason' => $smwObserved ? 'successful-store-hook' : 'no-current-write-evidence',
                'storeReadBackVerified' => false ],
            'rdf' => [ 'status' => 'unknown', 'reason' => 'remote-store-not-read-back' ],
            'selection' => [ 'entity' => $entity, 'language' => $language, 'field' => $field ], 'entities' => $entities ];
    }
}
