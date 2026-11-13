<?php

declare( strict_types = 1 );

namespace SWSBaseBridge;

use MediaWiki\Context\IContextSource;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Source\SourceFieldResult;
use Throwable;

/** Explicit write-side collector. Own values never decide which bindings are fetched. */
final class SourceSnapshotCollector {

	private EntityGateway $gateway;
	public function __construct( private readonly IContextSource $context ) {
		$this->gateway = new EntityGateway( $context, refresh: true );
	}

	public function collect( Title $title, Schema $schema, string $language ): array {
		if ( !$this->context->getAuthority()->authorizeRead( 'read', $title ) ) {
			throw new \RuntimeException( 'Source collection page is not readable' );
		}
		$groups = [ 'wikidata' => [], 'wikibase-local' => [] ];
		foreach ( $schema->getAllProperties()->asMap() as $name => $property ) {
			if ( !$property->isSchemaExecution() && ( $binding = $property->getExternalProperty() ) !== null ) {
				$groups[$binding->source][$name] = $property;
			}
		}
		$fields = [];
		$identities = [];
		foreach ( $groups as $source => $properties ) {
			$identity = [ 'source' => $source, 'status' => 'unavailable' ];
			try {
				$lookup = new PageIdentityLookup();
				$resolved = $source === 'wikidata' ? $lookup->wikidata( $title ) : $lookup->wikibase( $title, $this->context->getAuthority() );
				$identity = [ 'source' => $source ] + $resolved;
				if ( !in_array( $identity['status'] ?? null, [ 'resolved', 'missing', 'unavailable', 'unsupported' ], true ) ) {
					$identity = [ 'source' => $source, 'status' => 'unavailable', 'reason' => 'invalid-identity' ];
				}
				$ids = array_map( static fn ( $property ): string => $property->getExternalProperty()->id, $properties );
				$entities = ( $identity['status'] ?? null ) === 'resolved'
					? $this->gateway->entities( $source, [ $identity['id'], ...array_values( $ids ) ], $language, true ) : [];
				$entity = $entities[$identity['id'] ?? ''] ?? null;
				if ( $identity['status'] === 'resolved' && $entity === null ) {
					$identity['status'] = 'unavailable';
					$identity['reason'] = 'entity-unavailable';
				}
				if ( $entity !== null ) {
					$label = $entity['labels'][$language]['value'] ?? null;
					$identity['label'] = is_string( $label ) ? $label : $identity['id'];
					$description = $entity['descriptions'][$language] ?? null;
					$actualLanguage = is_array( $description ) ? ( $description['language'] ?? $language ) : $language;
					$valid = is_array( $description ) && is_string( $description['value'] ?? null ) && is_string( $actualLanguage ) &&
						preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $actualLanguage ) === 1;
					$identity['description'] = $valid ? $description['value'] : '';
					$identity['descriptionLanguage'] = $valid ? $actualLanguage : $language;
					$identity['descriptionStatus'] = $valid ? ( trim( $description['value'] ) !== '' ? 'resolved' : 'missing' ) :
						( $description === null ? 'missing' : 'unavailable' );
				}
				$targetIds = [];
				foreach ( $ids as $id ) { array_push( $targetIds, ...EntityStatementView::targetIds( EntityStatementView::claims( $entity ?? [], $id ) ) ); }
				$targets = [];
				$targetFailure = false;
				try { $targets = $this->gateway->entities( $source, $targetIds, $language, false ); }
				catch ( Throwable ) { $targetFailure = true; }
				foreach ( $properties as $name => $property ) {
					$binding = $property->getExternalProperty();
					$field = [ 'name' => (string)$name, 'source' => $source, 'propertyId' => $binding->id,
						'propertyType' => $property->getPropertyType(), 'status' => 'unavailable', 'values' => [] ];
					try {
						if ( isset( $identity['id'] ) ) { $field['entityId'] = $identity['id']; }
						if ( $identity['status'] !== 'resolved' || $entity === null ) {
							$field['status'] = $identity['status'] === 'missing' ? 'missing' : 'unavailable';
							$field['reason'] = 'identity-' . $identity['status'];
						} else {
							$definition = $entities[$binding->id] ?? [];
							$datatype = $definition['datatype'] ?? '';
							$claims = EntityStatementView::claims( $entity, $binding->id );
							$field += [ 'datatype' => $datatype, 'propertyLabel' => $definition['labels'][$language]['value'] ?? $binding->id,
								'propertyUrl' => $this->gateway->url( $source, $binding->id ) ];
							$field['status'] = $definition === [] ? 'unavailable' :
								( !PropertyDatatypePolicy::supports( $property->getPropertyType(), $datatype ) ? 'unsupported' : ( $claims === [] ? 'missing' : 'resolved' ) );
							if ( $field['status'] === 'resolved' ) {
								if ( $property->getPropertyType() === 'historicalDate' ) {
									$time = TimeClaimView::result( $claims, $source, $language, (string)( $GLOBALS['wgLanguageCode'] ?? $language ), $property->getWikiLink() ?? [] );
									$field = array_replace( $field, $time );
								} elseif ( $property->getPropertyType() === 'image' ) {
									$field['values'] = ImageClaimView::values( $claims );
								} elseif ( in_array( $datatype, [ 'string', 'external-id', 'monolingualtext' ], true ) ) {
									$field['values'] = TextClaimView::values( $claims, $datatype );
								} else {
									$idsNeeded = EntityStatementView::targetIds( $claims );
									if ( $targetFailure || array_diff( $idsNeeded, array_keys( $targets ) ) !== [] ) {
										$field['status'] = 'unavailable'; $field['reason'] = 'target-unavailable';
									} else {
										$field['values'] = EntityStatementView::values( $claims, $targets, $language, fn ( string $id ): ?string => $this->gateway->url( $source, $id ) );
									}
								}
							}
						}
						new SourceFieldResult( $field );
					} catch ( Throwable ) {
						$field = [ 'name' => (string)$name, 'source' => $source, 'propertyId' => $binding->id,
							'propertyType' => $property->getPropertyType(), 'status' => 'unavailable', 'reason' => 'invalid-field-result', 'values' => [] ];
					}
					$fields[] = $field;
				}
			} catch ( Throwable ) {
				$identity = [ 'source' => $source, 'status' => 'unavailable', 'reason' => 'source-unavailable' ];
				foreach ( $properties as $name => $property ) {
					$fields[] = [ 'name' => (string)$name, 'source' => $source, 'propertyId' => $property->getExternalProperty()->id,
						'propertyType' => $property->getPropertyType(), 'status' => 'unavailable', 'reason' => 'source-unavailable', 'values' => [] ];
				}
			}
			$identities[$source] = $identity;
		}
		return [ 'fields' => $fields, 'identities' => $identities ];
	}

}
