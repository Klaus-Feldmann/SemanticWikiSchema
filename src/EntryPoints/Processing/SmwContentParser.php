<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use SMW\Parser\ContentParser;

final class SmwContentParser extends ContentParser {
    public function parse( ?string $text = null, bool $clear = true ): static {
        // A protected update always represents the saved full revision, never an arbitrary text
        // fragment or a serialized older main slot supplied by another job.
        $result = ( new InternalPageProcessor() )->prepare( $this->title );
        if ( $this->revision !== null && $this->revision->getId() !== $result->revision->getId() ) {
            throw new ProcessingIncomplete( 'SemanticWikiSchema: stale SMW content-parse revision.' );
        }
        $this->revision = $result->revision;
        $this->parserOutput = $result->output;
        return $this;
    }
}
