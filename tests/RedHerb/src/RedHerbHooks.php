<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use ProfessionalWiki\SWS\Domain\Rdf\RdfLiteralFactory;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;
use ProfessionalWiki\SWS\EntryPoints\SWSRegistrar;

class RedHerbHooks {

	public static function onSWSRegistration( SWSRegistrar $registrar ): void {
		$registrar->addPropertyType( new ColorType() );
		$registrar->addNeo4jValueBuilder( ColorType::NAME, static fn( $value ) => $value->toScalars() );
		$registrar->addRdfValueMapper(
			ColorType::NAME,
			static function ( NeoValue $value ): array {
				// Guard the value shape like the core mappers do: an extension mapper is called for
				// whatever a Statement holds, so tolerate a non-array or non-scalar parts.
				$scalars = $value->toScalars();

				if ( !is_array( $scalars ) ) {
					return [];
				}

				$literals = [];

				foreach ( $scalars as $part ) {
					if ( is_scalar( $part ) ) {
						$literals[] = RdfLiteralFactory::typed( (string)$part, 'string' );
					}
				}

				return $literals;
			}
		);
		$registrar->addPagePropertyProvider( new StaticPagePropertyProvider() );
		$registrar->addGraphDatabasePlugin( 'redherb', new RedHerbGraphDatabasePlugin() );
	}

}
