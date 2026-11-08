<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Layout\DisplayRule;
use ProfessionalWiki\SWS\Domain\Layout\DisplayRules;
use ProfessionalWiki\SWS\Domain\Layout\Layout;
use ProfessionalWiki\SWS\Domain\Layout\LayoutName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceParser;

class LayoutPersistenceDeserializer {

	public function __construct(
		private readonly SchemaReferenceParser $schemaReferenceParser,
	) {
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function deserialize( LayoutName $layoutName, string $json ): Layout {
		$data = json_decode( $json, true );

		if ( !is_array( $data ) ) {
			throw new InvalidArgumentException( 'Invalid JSON' );
		}

		return new Layout(
			name: $layoutName,
			schema: $this->schemaReferenceParser->localName( $data['schema'] )->name,
			type: $data['type'],
			description: $data['description'] ?? '',
			displayRules: $this->displayRulesFromJson( $data ),
			settings: $data['settings'] ?? [],
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function displayRulesFromJson( array $data ): DisplayRules {
		$rules = [];
		$displayRules = $data['displayRules'] ?? [];

		if ( !is_array( $displayRules ) ) {
			return new DisplayRules( [] );
		}

		foreach ( $displayRules as $rule ) {
			if ( is_array( $rule ) && isset( $rule['property'] ) && is_string( $rule['property'] ) ) {
				$rules[] = new DisplayRule(
					property: new PropertyName( $rule['property'] ),
					displayAttributes: $rule['displayAttributes'] ?? [],
				);
			}
		}

		return new DisplayRules( $rules );
	}

}
