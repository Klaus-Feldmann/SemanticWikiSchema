<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use MediaWiki\Context\IContextSource;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\PageDependencyRecorder;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\SWSExtension;
use RuntimeException;
use Throwable;

/** Shared read-only API/Lua lookup. No writes and no cross-source fallback. */
final class ExternalValuesLookup {
	public function __construct( private readonly IContextSource $context ) {}

	public function read( Title $title, array $p, ?PageDependencyRecorder $dependencies = null, ?\ProfessionalWiki\SWS\Domain\Subject\Subject $selectedSubject = null ): array {
		if ( !$this->context->getAuthority()->authorizeRead( 'read', $title ) ||
			preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $p['language'] ) !== 1 ) {
			throw new RuntimeException( 'SWSBaseBridge: page or language unavailable' );
		}
		$dependencies?->recordDependencyOn( $title );
		$extension = SWSExtension::getInstance();
		$subject = $selectedSubject ?? $extension->newPageSubjectsLookup()->getPageSubjects( new PageId( $p['pageid'] ) )->getMainSubject();
		$fields = [];
		$presentation = null;
		if ( $subject !== null && ( $p['subject'] === null || $subject->id->text === $p['subject'] ) ) {
			$schema = $extension->getSchemaResolverFor( $this->context->getAuthority() )->getSchema( $subject->getSchemaReference() );
			$dependencies?->recordDependencyOnSchema( $subject->getSchemaReference() );
			$groups = [];
			$fieldTypes = [];
			if ( ( $p['presentation'] ?? false ) && $schema !== null ) {
				$presentation = GraphFieldPresentation::fromSchema( $schema,
					Title::newFromText( 'SWSGRAPHTITLETOKEN' )?->getFullURL(), $subject, $p['language'] );
			}
			foreach ( $schema?->getAllProperties()->asMap() ?? [] as $name => $property ) {
				if ( $property->getPropertyType() === 'wikitext' ) { continue; }
				$fieldTypes[$name] = $property->getPropertyType();
				$binding = $property->getExternalProperty();
				if ( $binding !== null ) {
					$groups[$binding->source][$name] = $binding->id;
				}
			}
			$gateway = new EntityGateway( $this->context );
			$identities = new PageIdentityLookup();
			foreach ( $groups as $source => $bindings ) {
				$sourceFields = [];
				try {
					$identity = $source === 'wikidata' ? $identities->wikidata( $title ) : $identities->wikibase( $title, $this->context->getAuthority() );
					if ( ( $identity['status'] ?? null ) !== 'resolved' ) {
						foreach ( $bindings as $name => $propertyId ) {
							$sourceFields[] = [ 'name' => $name, 'source' => $source, 'propertyId' => $propertyId,
								'status' => 'unavailable', 'values' => [] ];
						}
						array_push( $fields, ...$sourceFields );
						continue;
					}
					$entityId = $identity['id'];
					$entities = $gateway->entities( $source, [ $entityId, ...array_values( $bindings ) ], $p['language'], true );
					$targetIds = [];
					foreach ( $bindings as $propertyId ) {
						$targetIds = array_merge( $targetIds, EntityStatementView::targetIds( EntityStatementView::claims( $entities[$entityId] ?? [], $propertyId ) ) );
					}
					$targets = $gateway->entities( $source, $targetIds, $p['language'], false );
					foreach ( $bindings as $name => $propertyId ) {
						$property = $entities[$propertyId] ?? [];
						$claims = EntityStatementView::claims( $entities[$entityId] ?? [], $propertyId );
						$isImage = ( $fieldTypes[$name] ?? '' ) === 'image';
						$isTime = ( $fieldTypes[$name] ?? '' ) === 'historicalDate';
						$datatype = $property['datatype'] ?? '';
                        $isText = in_array($datatype,['monolingualtext','string','external-id'],true);
                        $supported = PropertyDatatypePolicy::supports($fieldTypes[$name] ?? '',$datatype);
						$status = !isset( $entities[$entityId] ) || $property === [] ? 'unavailable' :
							( $claims === [] ? 'missing' : ( $supported ? 'resolved' : 'unsupported' ) );
						$values = $status === 'resolved' && !$isTime ? ( $isImage ? ImageClaimView::values( $claims ) : ( $isText ? TextClaimView::values($claims,$datatype) :
							EntityStatementView::values( $claims, $targets, $p['language'], fn ( string $id ): ?string => $gateway->url( $source, $id ) ) ) ) : [];
						if ( $status === 'resolved' && !$isTime && $values === [] ) { $status = 'unavailable'; }
						$timeResult = null;
						if ( $isTime && $supported && isset( $entities[$entityId] ) ) {
							$options = $schema->getProperty( new \ProfessionalWiki\SWS\Domain\Schema\PropertyName( $name ) )->getWikiLink() ?? [];
							$timeResult = TimeClaimView::result( $claims, $source, $p['language'], (string)( $GLOBALS['wgLanguageCode'] ?? 'de' ), $options );
							$status = $timeResult['status'];
							$values = $timeResult['values'];
						}
						$sourceFields[] = [ 'name' => $name, 'source' => $source, 'entityId' => $entityId, 'propertyId' => $propertyId,
							'propertyLabel' => $property['labels'][$p['language']]['value'] ?? $propertyId,
							'propertyUrl' => $gateway->url( $source, $propertyId ), 'datatype'=>$datatype, 'status' => $status, 'values' => $values,
							...( $timeResult === null ? [] : [ 'candidates' => $timeResult['candidates'], 'reason' => $timeResult['reason'] ?? null ] ) ];
					}
					array_push( $fields, ...$sourceFields );
				} catch ( Throwable ) {
					wfDebugLog( 'SchemaBaseBridge', 'Derived entity values unavailable for ' . $source );
					foreach ( $bindings as $name => $propertyId ) {
						$fields[] = [ 'name' => $name, 'source' => $source, 'propertyId' => $propertyId, 'status' => 'unavailable', 'values' => [] ];
					}
				}
			}
		}
		return [ 'version' => 1, 'scope' => 'current',
			'pageId' => $p['pageid'], 'mainSubjectId' => $subject?->id->text, 'fields' => $fields,
			...( ( $p['presentation'] ?? false ) ? [ 'presentation' => $presentation ] : [] ) ];
	}
}
