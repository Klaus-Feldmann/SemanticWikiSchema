<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Persistence\MediaWiki;

use InvalidArgumentException;
use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\Domain\Layout\DisplayRule;
use AEWI\SWS\Domain\Layout\DisplayRules;
use AEWI\SWS\Domain\Layout\Layout;
use AEWI\SWS\Domain\Layout\LayoutName;
use AEWI\SWS\Domain\Schema\SchemaReferenceParser;

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
			schema: $this->schemaReferenceParser->fromJson( $data['schema'] ),
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
