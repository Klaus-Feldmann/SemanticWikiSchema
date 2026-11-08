<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use SMW\MediaWiki\Jobs\ContentParserFactory;
use SMW\Parser\ContentParser;

/** Optional adapter, loaded only when SMW has registered its factory. */
final class SmwContentParserFactory extends ContentParserFactory {
    public function __construct( private readonly ContentParserFactory $delegate ) {}

    public function newContentParser( Title $title ): ContentParser {
        if ( !InternalPageProcessor::manages( $title ) ) { return $this->delegate->newContentParser( $title ); }
        return new SmwContentParser( $title, MediaWikiServices::getInstance()->getParser() );
    }
}
