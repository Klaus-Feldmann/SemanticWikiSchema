<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/** Operational metadata only, no article text, Q/P values or rendered HTML. */
final class ProcessingState {
    public static function record( Title $title, string $state, ?int $revision = null ): void {
        $cache = MediaWikiServices::getInstance()->getMainObjectStash();
        $cache->set( $cache->makeKey( 'semantic-sws-processing', $title->getArticleID() ), [
            'state' => $state, 'revision' => $revision, 'at' => wfTimestampNow()
        ], 86400 );
    }

    public static function read( Title $title ): array|false {
        $cache = MediaWikiServices::getInstance()->getMainObjectStash();
        return $cache->get( $cache->makeKey( 'semantic-sws-processing', $title->getArticleID() ) );
    }
}
