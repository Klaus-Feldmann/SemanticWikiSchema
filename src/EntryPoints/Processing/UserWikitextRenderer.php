<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\{ParserOptions,ParserOutput};
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/** Parse original Subject strings with MediaWiki, never by inserting them as raw HTML. */
final class UserWikitextRenderer {
    public function render( Title $title, array $values, User $user, ?int $revisionId = null ): ParserOutput {
        foreach ( $values as $value ) {
            if ( !is_string( $value ) ) { throw new ProcessingIncomplete( 'SWS: invalid Subject wikitext value.' ); }
        }
        return FieldWikitextContext::run( static function () use ( $title, $values, $user, $revisionId ): ParserOutput {
            $parser = MediaWikiServices::getInstance()->getParserFactory()->create();
            return $parser->parse( implode( "\n\n", $values ), $title, ParserOptions::newFromUser( $user ), true, true, $revisionId );
        } );
    }
}
