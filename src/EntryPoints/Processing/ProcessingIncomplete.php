<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

/** An incomplete result must never replace a previously complete semantic snapshot. */
final class ProcessingIncomplete extends \RuntimeException {
    public function __construct( string $message, public readonly string $state = 'failed' ) {
        parent::__construct( $message );
    }
}
