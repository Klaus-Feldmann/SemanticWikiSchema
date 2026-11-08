<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\Content;

use MediaWiki\Content\JsonContent;

class SchemaContent extends JsonContent {

	public const string CONTENT_MODEL_ID = 'SWSSchema';

	public function __construct( string $text, string $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct(
			$text,
			$modelId
		);
	}

}
