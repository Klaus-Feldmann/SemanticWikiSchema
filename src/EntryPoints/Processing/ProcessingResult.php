<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

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
        public readonly array $stamp
    ) {}

    public static function seal( array $snapshot ): array {
        $snapshot['signature'] = hash_hmac( 'sha256', json_encode( $snapshot, JSON_THROW_ON_ERROR ),
            MediaWikiServices::getInstance()->getMainConfig()->get( 'SecretKey' ) );
        return $snapshot;
    }

    public static function isCurrent( Title $title, mixed $stamp ): bool {
        if ( !is_array( $stamp ) || !isset( $stamp['signature'], $stamp['revision'], $stamp['dependencies'] ) ) { return false; }
        $signature = $stamp['signature'];
        unset( $stamp['signature'] );
        if ( !hash_equals( self::seal( $stamp )['signature'], $signature ) || $stamp['page'] !== $title->getArticleID() ) { return false; }
        $lookup = MediaWikiServices::getInstance()->getRevisionLookup();
        $revision = $lookup->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );
        if ( !$revision || $revision->getId() !== $stamp['revision'] || $revision->getSha1() !== $stamp['sha1'] ) { return false; }
        foreach ( $stamp['dependencies'] as $dependency ) {
            $page = Title::makeTitleSafe( $dependency['namespace'], $dependency['title'] );
            if ( $page === null ) { return false; }
            $current = $lookup->getRevisionByTitle( $page, 0, IDBAccessObject::READ_LATEST );
            if ( ( $current?->getId() ?? 0 ) !== $dependency['revision'] ) { return false; }
        }
        return true;
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
        if ( !self::isCurrent( $this->title, $this->stamp ) ) {
            throw new ProcessingIncomplete( 'SemanticWikiSchema: revision or dependency changed during processing; retry required.', 'superseded' );
        }
    }
}
