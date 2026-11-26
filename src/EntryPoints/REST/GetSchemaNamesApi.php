<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\StringStream;
use MediaWiki\Title\Title;
use AEWI\SWS\SWSExtension;
use Wikimedia\ParamValidator\ParamValidator;

class GetSchemaNamesApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function run( string $search ): Response {
        $extension = SWSExtension::getInstance();
        $details = $this->getValidatedParams()['details'] ?? false;
        $schemaNames = [];
        // Inspect bounded search candidates, never the whole Schema catalogue. The published
        // schema lookup also rejects malformed or withdrawn definitions on otherwise readable pages.
        foreach ( $extension->getSchemaNameLookup()->getSchemaNamesMatching( $search, 40 ) as $title ) {
            try {
                $schema = $extension->getSchemaLookup()->getSchema(
                    new \AEWI\SWS\Domain\Schema\SchemaName( $title->getText() )
                );
            } catch ( \InvalidArgumentException ) { continue; }
            if ( $schema === null ) { continue; }
            if ( $details ) {
                $page = Title::newFromLinkTarget( $title );
                $identity = $schema->getIdentity();
                $schemaNames[] = [
                    'pageId' => $page->getArticleID(),
                    'title' => $page->getPrefixedText(),
                    'url' => $page->getFullURL(),
                    'description' => $schema->getDescription(),
                ] + ( $identity?->toJson() ?? [] );
            } else {
                $schemaNames[] = $schema->getName()->getText();
            }
            if ( count( $schemaNames ) >= 10 ) { break; }
        }

		$response = $this->getResponseFactory()->create();
		$response->setBody( new StringStream( json_encode( $schemaNames ) ) );
		$response->setHeader( 'Content-Type', 'application/json' );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'search' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Case-insensitive search prefix matched against Schema names.',
			],
			'details' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
		];
	}

}
