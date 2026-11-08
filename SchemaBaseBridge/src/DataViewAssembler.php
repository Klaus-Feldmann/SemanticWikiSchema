<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use ProfessionalWiki\SWS\Domain\HistoricalDate;
use ProfessionalWiki\SWS\Domain\HistoricalCalendar;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\SWS\Domain\Subject\Subject;

/** Lossless read model. Never feed this response into a subject replacement endpoint. */
final class DataViewAssembler {
	public static function subject( Subject $subject, ?Schema $schema, array $external, callable $resolveRelation ): array {
		$statements = [];
		foreach ( $subject->getStatements()->asArray() as $statement ) {
			$statements[$statement->getPropertyName()->text] = $statement;
		}
		$derived = [];
		foreach ( $external['fields'] ?? [] as $field ) { $derived[$field['name']] = $field; }
		$properties = $schema?->getAllProperties()->asMap() ?? [];
		$fields = [];
		foreach ( array_unique( [ ...array_keys( $properties ), ...array_keys( $statements ) ] ) as $name ) {
			$property = $properties[$name] ?? null;
			if ( $property?->isSchemaExecution() ) { continue; }
			$statement = $statements[$name] ?? null;
			$type = $property?->getPropertyType() ?? $statement->getPropertyType();
			$raw = $statement?->getValue()->toScalars();
			$parts = $raw === null ? [] : ( is_array( $raw ) && array_is_list( $raw ) ? $raw : [ $raw ] );
			$own = [];
			foreach ( $parts as $part ) {
				if ( $part === null || $part === '' ) { continue; }
				$value = [ 'kind' => $type, 'value' => $part ];
				if ( $type === 'select' && $property instanceof SelectProperty ) {
					$option = is_string( $part ) ? $property->getOption( $part ) : null;
					$value += [ 'id' => $part, 'label' => $option?->getLabel(), 'status' => $option === null ? 'unresolved' : 'resolved' ];
				}
				if ( $type === 'historicalDate' && is_string( $part ) ) {
					$date = HistoricalDate::parse( $part );
					$value += $date === null ? [ 'status' => 'unresolved' ] : [ 'year' => $date->year,
						'month' => $date->month, 'day' => $date->day, 'precision' => $date->precision(),
						'calendarConvention' => 'wikipedia', 'bounds' => $date->bounds(),
						'calendar' => $date->year === 1582 && ( $date->month === null || $date->month === 10 && $date->day === null ) ? 'mixed' : HistoricalCalendar::calendar( $date->year, $date->month ?? 1, $date->day ?? 1 ) ];
				}
				if ( $type === 'image' && is_string( $part ) ) {
					$image = \ProfessionalWiki\SWS\Domain\ImageReference::parse( $part );
					$value += $image === null ? [ 'status' => 'unresolved' ] : [ 'repository' => $image->repository, 'filename' => $image->filename ];
				}
				if ( $type === 'date' || $type === 'dateTime' ) { $value['calendar'] = 'gregorian'; }
				if ( $type === 'relation' && is_array( $part ) && is_string( $part['target'] ?? null ) ) {
					$value['target'] = $resolveRelation( $part['target'] );
				}
				$own[] = $value;
			}
			$remote = $derived[$name] ?? null;
			$remoteValues = $remote['values'] ?? [];
			$effectiveOrigin = $own !== [] ? 'own' : ( $remoteValues !== [] ? 'external' : null );
			$ownProblem = array_filter( $own, static fn ( array $v ): bool =>
				in_array( $v['status'] ?? '', [ 'unresolved', 'unavailable' ], true ) ||
				isset( $v['target'] ) && ( $v['target']['status'] ?? '' ) !== 'resolved' );
			$status = $ownProblem !== [] ? 'unresolved' : ( $effectiveOrigin !== null ? 'resolved' : ( $remote['status'] ?? 'empty' ) );
			if ( $schema === null ) { $status = 'schema-unavailable'; }
			$fields[] = [ 'name' => $name, 'type' => $type, 'order' => count( $fields ),
				'schemaDefined' => $property !== null, 'description' => $property?->getDescription(),
				'wikiLink' => $property?->getWikiLink(), 'externalProperty' => $property?->getExternalProperty()?->toJson(), 'ownValues' => $own,
				'external' => $remote, 'effectiveOrigin' => $effectiveOrigin,
				'effectiveValues' => $effectiveOrigin === 'own' ? $own : $remoteValues, 'status' => $status ];
		}
		return [ 'id' => $subject->id->text, 'label' => $subject->getLabel()?->text,
			'schema' => $subject->getSchemaReference()->toJson(), 'schemaStatus' => $schema === null ? 'unavailable' : 'resolved',
			'fields' => $fields, 'complete' => $schema !== null && array_filter( $fields, static fn ( array $f ): bool =>
				in_array( $f['status'], [ 'unresolved', 'schema-unavailable' ], true ) ||
				in_array( $f['external']['status'] ?? '', [ 'unavailable', 'unsupported', 'error' ], true ) ) === [] ];
	}
}
