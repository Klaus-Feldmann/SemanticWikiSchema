<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Application;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\Property\WikitextProperty;

/** No parser calls or storage writes: only explicit programs in schema order. */
final class SchemaWikitext {
	public static function programs( Schema $schema ): array {
		$result = [];
		foreach ( $schema->getAllProperties()->asMap() as $property ) {
			if ( $property instanceof WikitextProperty && $property->isSchemaExecution() ) {
				$result[] = $property->getWikitext();
			}
		}
		return $result;
	}
	public static function append( string $source, array $programs ): string {
		return $programs === [] ? $source : $source . "\n\n" . implode( "\n", $programs );
	}
}
