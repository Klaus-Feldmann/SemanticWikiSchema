<?php

declare( strict_types = 1 );
namespace AEWI\SWS\EntryPoints\Processing;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IDBAccessObject;

final class ProcessingResult {
    public const STAMP = 'semantic-sws-complete-processing-v1';

    public function __construct(
        public readonly Title $title,
        public readonly RevisionRecord $revision,
        public readonly ParserOutput $output,
        public readonly ParserOptions $options,
        public readonly array $stamp,
        public readonly array $sourceSnapshots = [],
        public readonly bool $isSourcePreparation = false
    ) {}

    public static function seal( array $snapshot ): array {
        $snapshot['signature'] = hash_hmac( 'sha256', json_encode( $snapshot, JSON_THROW_ON_ERROR ),
            MediaWikiServices::getInstance()->getMainConfig()->get( 'SecretKey' ) );
        return $snapshot;
    }

    public static function isCurrent( Title $title, mixed $stamp, bool $allowSourcePreparation = false ): bool {
        return self::currentMismatch( $title, $stamp, $allowSourcePreparation ) === null;
    }

    private static function currentMismatch( Title $title, mixed $stamp, bool $allowSourcePreparation ): ?array {
        if ( !is_array( $stamp ) || !isset( $stamp['signature'], $stamp['revision'], $stamp['dependencies'] ) ) {
            return [ 'reason' => 'missing-stamp' ];
        }
        if ( ( $stamp['stage'] ?? 'complete' ) !== 'complete' && !$allowSourcePreparation ) {
            return [ 'reason' => 'incomplete-stage' ];
        }
        if ( isset( $stamp['sourceDataMode'] ) && $stamp['sourceDataMode'] !== \AEWI\SWS\Application\Source\SourceDataMode::current() ) {
            return [ 'reason' => 'source-data-mode-changed' ];
        }
        $signature = $stamp['signature'];
        unset( $stamp['signature'] );
        if ( !hash_equals( self::seal( $stamp )['signature'], $signature ) || $stamp['page'] !== $title->getArticleID() ) {
            return [ 'reason' => 'invalid-stamp' ];
        }
        $lookup = MediaWikiServices::getInstance()->getRevisionLookup();
        $revision = $lookup->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );
        if ( !$revision || $revision->getId() !== $stamp['revision'] || $revision->getSha1() !== $stamp['sha1'] ) {
            return [ 'reason' => 'page-revision-or-content-changed',
                'expectedRevision' => $stamp['revision'], 'currentRevision' => $revision?->getId() ?? 0 ];
        }
        return self::dependencyMismatch( $stamp['dependencies'] );
    }

    public static function dependenciesCurrent( array $dependencies ): bool {
        return self::dependencyMismatch( $dependencies ) === null;
    }

    private static function dependencyMismatch( array $dependencies ): ?array {
        $lookup = MediaWikiServices::getInstance()->getRevisionLookup();
        foreach ( $dependencies as $dependency ) {
            $page = Title::makeTitleSafe( $dependency['namespace'], $dependency['title'] );
            if ( $page === null ) {
                return [ 'reason' => 'invalid-dependency-title',
                    'target' => $dependency['namespace'] . ':' . $dependency['title'],
                    'expectedRevision' => $dependency['revision'] ];
            }
            $current = $lookup->getRevisionByTitle( $page, 0, IDBAccessObject::READ_LATEST )?->getId() ?? 0;
            if ( $current !== $dependency['revision'] ) {
                return [ 'reason' => 'dependency-revision-changed', 'target' => $page->getPrefixedText(),
                    'expectedRevision' => $dependency['revision'], 'currentRevision' => $current ];
            }
        }
        return null;
    }

    /** Bind the user-defined assertions too; a copied stamp must not authorize a partial container.
     * SMW's own bookkeeping (_MDAT, _SKEY, etc.) may legitimately be added by DataUpdater later.
     */
    public static function semanticHash( $data ): string {
        $properties = [];
        if ( $data !== null ) {
            foreach ( $data->getProperties() as $property ) {
                if ( !$property->isUserDefined() ) { continue; }
                $values = array_map( static fn ( $value ) => $value->getSerialization(), $data->getPropertyValues( $property ) );
                sort( $values );
                $properties[$property->getKey()] = $values;
            }
            foreach ( $data->getSubSemanticData() as $subData ) {
                $properties['@sub:' . $subData->getSubject()->getSubobjectName()] = self::semanticHash( $subData );
            }
        }
        ksort( $properties );
        return hash( 'sha256', json_encode( $properties, JSON_THROW_ON_ERROR ) );
    }

    public function assertCurrent(): void {
        $mismatch = self::currentMismatch( $this->title, $this->stamp, $this->isSourcePreparation );
        if ( $mismatch !== null ) {
            $title = $this->title->getPrefixedText();
            LoggerFactory::getInstance( 'SWS' )->warning(
                'SWS refresh for {page}: {reason} at {target} (expected revision {expectedRevision}, current {currentRevision}).',
                $mismatch + [ 'page' => $title, 'target' => $title,
                    'expectedRevision' => $this->revision->getId(), 'currentRevision' => 'unknown' ]
            );
            throw new ProcessingIncomplete( 'SemanticWikiSchema: revision or dependency changed during processing; retry required.', 'superseded' );
        }
    }
}
