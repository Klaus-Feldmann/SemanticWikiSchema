<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Infrastructure;

use InvalidArgumentException;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceNormalizer;
use ProfessionalWiki\SWS\SWSExtension;

/**
 * Derives the name from the Schema page's title, so MediaWiki's own rules apply: the first character
 * is capitalized only where `$wgCapitalLinks` says so, and in the content language rather than by byte.
 */
class TitleBasedSchemaReferenceNormalizer implements SchemaReferenceNormalizer {

	/**
	 * A wiki names few Schemas while every Subject read asks after one, and parsing a title is neither
	 * free nor cached by MediaWiki outside NS_MAIN. Kept for the life of this normalizer, which
	 * SWSExtension makes the process.
	 *
	 * @var array<string, SchemaName>
	 */
	private array $namesOfSchemaPages = [];

	public function __construct(
		private readonly TitleFactory $titleFactory,
	) {
	}

	public function normalize( SchemaReference $reference ): SchemaReference {
		if ( !$reference->isLocal() || $reference->id !== null ) {
			return $reference;
		}

		return SchemaReference::local( $this->rememberedNameOfSchemaPage( $reference->name ) );
	}

	private function rememberedNameOfSchemaPage( SchemaName $name ): SchemaName {
		$written = $name->getText();

		if ( !array_key_exists( $written, $this->namesOfSchemaPages ) ) {
			$this->namesOfSchemaPages[$written] = $this->nameOfSchemaPage( $name );
		}

		return $this->namesOfSchemaPages[$written];
	}

	/**
	 * The whole name titles the page, and is never split into a namespace or interwiki prefix, so
	 * "Help:Person" names the page Schema:Help:Person. A name that titles no page, or whose page is
	 * titled as no Schema may be named, is left as written for the lookup to report as missing.
	 */
	private function nameOfSchemaPage( SchemaName $name ): SchemaName {
		$title = $this->titleFactory->makeTitleSafe( SWSExtension::NS_SCHEMA, $name->getText() );

		if ( $title === null ) {
			return $name;
		}

		try {
			return new SchemaName( $title->getText() );
		} catch ( InvalidArgumentException ) {
			return $name;
		}
	}

}
