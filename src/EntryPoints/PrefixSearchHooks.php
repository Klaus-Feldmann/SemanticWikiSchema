<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints;

use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;

/** Global title search; independent of whether this page has Subjects. */
final class PrefixSearchHooks {
    private static function namespaces(): array {
        $services = MediaWikiServices::getInstance();
        $names = $services->getContentLanguage()->getNamespaces();
        $configured = $services->getMainConfig()->get( 'SWSPrefixSearchNamespaces' );
        $ids = $configured === null ? array_keys( $names ) : $configured;
        $result = [];
        foreach ( $ids as $id ) {
            if ( is_int( $id ) && $id >= 0 && isset( $names[$id] ) && !isset( $result[$id] ) ) {
                $result[$id] = [ 'id' => $id, 'label' => $id === 0 ? wfMessage( 'article' )->text() : $names[$id] ];
            }
        }
        return array_values( $result );
    }

    public static function onBeforePageDisplay( OutputPage $out, $skin ): void {
        $config = MediaWikiServices::getInstance()->getMainConfig();
        $out->addJsConfigVars( 'wgSWSPrefixSearchNamespaces', self::namespaces() );
        $out->addJsConfigVars( 'wgSWSPrefixSearchSubstringFallback', $config->get( 'SWSPrefixSearchSubstringFallback' ) );
        if ( !$config->get( 'SWSPrefixSearchEnabled' ) || self::namespaces() === [] ) {
            return;
        }
        $out->addModules( 'ext.sws.prefixSearch' );
        $out->addModuleStyles( 'ext.sws.prefixSearch.styles' );
    }

    public static function onSkinPageReadyConfig( $context, array &$config ): void {
        if ( MediaWikiServices::getInstance()->getMainConfig()->get( 'SWSPrefixSearchEnabled' ) && self::namespaces() !== [] ) {
            // The existing form remains usable without JS; SWS owns typeahead when enabled.
            $config['search'] = false;
        }
    }
}
