<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use RuntimeException;
use MediaWiki\Parser\ParserOutput;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;

/** Request-local and strictly scoped; never changes RequestContext or public API authority. */
final class ProcessingContext {
    private static ?self $active = null;
    public ?Parser $parser = null;
    public ?string $failure = null;
    public ?PageSubjects $subjects = null;
    public ?ParserOutput $output = null;
    public bool $useStoredSources = false;

    public function __construct( public readonly Title $title ) {}

    public static function current(): ?self { return self::$active; }

    public function run( callable $callback ): mixed {
        if ( self::$active !== null ) { throw new RuntimeException( 'SemanticWikiSchema: recursive internal processing.' ); }
        self::$active = $this;
        try { return $callback(); } finally { self::$active = null; }
    }

    public function observe( Parser $parser ): void {
        if ( $this->title->isSamePageAs( $parser->getTitle() ) && !$parser->getOptions()->isMessage() ) {
            $this->parser ??= $parser;
        }
    }

    public function currentProperty( Title $title, string $name ): mixed {
        return $this->title->isSamePageAs( $title ) ? ( $this->output ?? $this->parser?->getOutput() )?->getPageProperty( $name ) : null;
    }
}
