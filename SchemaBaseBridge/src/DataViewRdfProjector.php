<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use ProfessionalWiki\SWS\Domain\HistoricalDate;
use ProfessionalWiki\SWS\Domain\LanguageTag;
use ProfessionalWiki\SWS\Domain\Rdf\{Iri, Literal, Quad, QuadList, RdfNamespaces, RdfTerm};
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;

/** Read-time snapshot only. No graph-store projection or writes to research data. */
final class DataViewRdfProjector {
	private array $quads = [];
	private string $root;
	private string $vocabulary;
	private Iri $metadataGraph;

	public function __construct( private readonly RdfNamespaces $namespaces, private readonly ?\Closure $entityIriLookup = null ) {
		$this->vocabulary = $namespaces->baseUri . '/ontology/data-view/';
	}

	public function project( array $view ): QuadList {
		$this->quads = [];
		$id = $view['page']['id'];
		$scope = count( $view['subjects'] ) === 1 ? $view['subjects'][0]['id'] : 'all';
		$this->root = $this->namespaces->baseUri . '/export/page/' . $id . '/' . rawurlencode( $scope ) . '/view';
		$this->metadataGraph = new Iri( $this->root . '/graph/metadata' );
		// A typed object/list representation retains every attribute, null, empty list and ordering.
		$root = $this->walk( $view, [] );
		$this->add( $root, RdfNamespaces::RDF . 'type', $this->term( 'Snapshot' ), $this->metadataGraph );
		$this->add( $root, $this->vocabulary . 'mediaType', $this->literal( 'application/json' ), $this->metadataGraph );
		// A portable exact JSON representation supplements, rather than replaces, the RDF statements.
		$this->add( $root, $this->vocabulary . 'snapshotJson', new Literal( json_encode( $view,
			JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION ),
			new Iri( RdfNamespaces::RDF . 'JSON' ) ), $this->metadataGraph );
		$page = new Iri( $this->namespaces->baseUri . '/page/' . $id );
		$effectiveGraph = new Iri( $this->root . '/graph/effective' );
		$this->add( $page, $this->vocabulary . 'dataView', $root, $this->metadataGraph );
		$this->add( $page, RdfNamespaces::RDF . 'type', new Iri( $this->namespaces->baseUri . '/ontology/Page' ), $this->metadataGraph );
		$this->add( $page, $this->namespaces->baseUri . '/ontology/pageName', $this->literal( $view['page']['title'] ), $this->metadataGraph );
		foreach ( $view['subjects'] as $si => $subject ) {
			$subjectIri = $this->namespaces->subject( new SubjectId( $subject['id'] ) );
			$this->add( $page, $this->namespaces->baseUri . '/ontology/hasSubject', $subjectIri, $this->metadataGraph );
			if ( $subject['isMainSubject'] ?? false ) {
				$this->add( $page, $this->namespaces->baseUri . '/ontology/mainSubject', $subjectIri, $this->metadataGraph );
			}
            try {
                $reference = \ProfessionalWiki\SWS\Domain\Schema\SchemaReference::fromJson( $subject['schema'], 'local' );
                if ( isset( $subject['schemaIdentity']['id'] ) ) { $reference = \ProfessionalWiki\SWS\Domain\Schema\SchemaReference::identified( new \ProfessionalWiki\SWS\Domain\Identity\SchemaId( $subject['schemaIdentity']['id'] ), $reference->source ); }
                // A foreign schema reference retains its source in a disjoint class-IRI namespace.
                $this->add( $subjectIri, RdfNamespaces::RDF . 'type', $reference->id === null ? $this->namespaces->schemaClass( $reference->name, $reference->source ) : $this->namespaces->identifiedSchemaClass( $reference->id, $reference->source ), $effectiveGraph );
            } catch ( \InvalidArgumentException ) { /* Incomplete data views retain their status instead of inventing a type. */ }
            if ( isset( $subject['identityLanguage'], $subject['labels'], $subject['descriptions'] ) ) {
                foreach ( \ProfessionalWiki\SWS\Application\Rdf\IdentityRdfMetadata::quads( $subjectIri,
                    \ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity::fromJson( [ 'identityLanguage'=>$subject['identityLanguage'], 'labels'=>(array)$subject['labels'], 'descriptions'=>(array)$subject['descriptions'] ] ), $effectiveGraph ) as $quad ) { $this->quads[] = $quad; }
            }

			if ( is_string( $subject['displayName'] ?? null ) ) {
				$this->add( $subjectIri, RdfNamespaces::RDFS . 'label', $this->literal( $subject['displayName'] ), $effectiveGraph );
			}
			if ( ( $subject['isMainSubject'] ?? false ) && is_string( $view['displayDescription']['text'] ?? null ) ) {
				$this->add( $subjectIri, RdfNamespaces::DCTERMS . 'description', $this->literal( $view['displayDescription']['text'],
					$view['displayDescription']['language'] ?? null ), $effectiveGraph );
			}
			foreach ( $subject['fields'] as $fi => $field ) {
				$property = $this->namespaces->property( $field['name'] );
				$fieldNode = $this->node( [ 'k:subjects', 'i:' . $si, 'k:fields', 'i:' . $fi ] );
				$this->add( $subjectIri, $this->vocabulary . 'field', $fieldNode, $this->metadataGraph );
				$this->add( $property, RdfNamespaces::RDFS . 'label', $this->literal( $field['name'] ), $this->metadataGraph );
				foreach ( $field['effectiveValues'] as $value ) {
					$object = $this->valueTerm( $field['type'], $value, $field['effectiveOrigin'] === 'external' ? ( $field['external']['source'] ?? null ) : null );
					if ( $object !== null ) {
						$this->add( $subjectIri, $property->value, $object, $effectiveGraph );
						$label = $value['label'] ?? $value['target']['label'] ?? null;
						if ( $object instanceof Iri && is_string( $label ) ) {
							$this->add( $object, RdfNamespaces::RDFS . 'label', $this->literal( $label ), $this->metadataGraph );
							if ( isset( $value['url'] ) && $value['url'] !== $object->value && Iri::isSafeAbsolute( $value['url'] ) ) {
								$this->add( $object, RdfNamespaces::RDFS . 'seeAlso', new Iri( $value['url'] ), $this->metadataGraph );
							}
						}
					}
				}
				foreach ( [ 'own' => $field['ownValues'], 'external' => $field['external']['values'] ?? [] ] as $origin => $values ) {
					$source = $origin === 'own' ? 'own' : ( $field['external']['source'] ?? 'external' );
					$graph = new Iri( $this->root . '/graph/' . rawurlencode( $source ) );
					foreach ( $values as $vi => $value ) {
						$object = $this->valueTerm( $field['type'], $value, $source );
						if ( $object === null ) { continue; }
						$path = [ 'k:subjects', 'i:' . $si, 'k:fields', 'i:' . $fi,
							...( $origin === 'own' ? [ 'k:ownValues' ] : [ 'k:external', 'k:values' ] ), 'i:' . $vi ];
						$record = $this->node( $path );
						// Reified source statements preserve precedence when Turtle drops graph names.
						$this->add( $record, RdfNamespaces::RDF . 'type', new Iri( RdfNamespaces::RDF . 'Statement' ), $graph );
						$this->add( $record, RdfNamespaces::RDF . 'subject', $subjectIri, $graph );
						$this->add( $record, RdfNamespaces::RDF . 'predicate', $property, $graph );
						$this->add( $record, RdfNamespaces::RDF . 'object', $object, $graph );
						$this->add( $record, $this->vocabulary . 'origin', $this->literal( $source ), $graph );
						$sourceUrl = $origin === 'own' ? $view['page']['url'] :
							( $field['external']['entityUrl'] ?? $view['identities'][$source]['url'] ?? null );
						if ( is_string( $sourceUrl ) && Iri::isSafeAbsolute( $sourceUrl ) ) {
							$this->add( $record, 'http://www.w3.org/ns/prov#wasDerivedFrom', new Iri( $sourceUrl ), $graph );
						}
					}
				}
			}
		}
		return new QuadList( ...$this->quads );
	}

	private function valueTerm( string $type, array $value, ?string $source = null ): ?RdfTerm {
		$kind = $value['kind'] ?? $type;
		if ( in_array( $kind, [ 'novalue', 'somevalue' ], true ) ) { return null; }
		if ($kind === 'text') { return $this->literal($value['value'],$value['language'] ?? null); }
        if ( $kind === 'entity' ) {
			$uri = $source !== null && $this->entityIriLookup !== null ? ( $this->entityIriLookup )( $source, $value['id'] ) : null;
			if ( $uri === null && $source === 'wikidata' && preg_match( '/\AQ[1-9][0-9]*\z/', $value['id'] ) === 1 ) {
				$uri = 'http://www.wikidata.org/entity/' . $value['id'];
			}
			return $this->iriOrLiteral( $uri ?? $value['url'] ?? $value['id'] );
		}
		if ( $kind === 'time' ) {
			$date = $value['displayDate'] ?? null;
			if ( !is_array( $date ) || !isset( $date['year'] ) ) { return null; }
			$text = sprintf( '%s%04d', $date['year'] < 0 ? '-' : '', abs( $date['year'] ) );
			if ( isset( $date['month'] ) ) { $text .= sprintf( '-%02d', $date['month'] ); }
			if ( isset( $date['day'] ) ) { $text .= sprintf( '-%02d', $date['day'] ); }
			return new Literal( $text, new Iri( HistoricalDate::RDF_DATATYPE ) );
		}
		$raw = $value['value'] ?? null;
		if ( $type === 'image' ) { return $this->literal( $value['reference'] ?? $raw ); }
		if ( $type === 'select' ) { return $this->literal( $value['label'] ?? $raw ); }
		if ( $type === 'url' ) { return $this->iriOrLiteral( is_array( $raw ) ? $raw['url'] : $raw ); }
		if ( $type === 'page' ) { return $this->iriOrLiteral( $value['url'] ?? $raw ); }
		if ( $type === 'relation' ) {
			$id = $value['target']['id'] ?? ( is_array( $raw ) ? $raw['target'] ?? null : null );
			return is_string( $id ) ? $this->namespaces->subject( new SubjectId( $id ) ) : null;
		}
		if ( $type === 'historicalDate' && is_string( $raw ) ) { return new Literal( $raw, new Iri( HistoricalDate::RDF_DATATYPE ) ); }
		if ( in_array( $type, [ 'date', 'dateTime' ], true ) && is_string( $raw ) ) { return new Literal( $raw, new Iri( RdfNamespaces::XSD . $type ) ); }
		if ( $type === 'monolingualText' && is_array( $raw ) && isset( $raw['text'] ) ) { return $this->literal( $raw['text'], $raw['language'] ?? null ); }
		if ( $raw === null ) { return null; }
		return $this->scalar( $raw );
	}

	private function walk( mixed $value, array $path ): RdfTerm {
		if ( !is_array( $value ) && !is_object( $value ) ) { return $this->scalar( $value ); }
		$isList = is_array( $value ) && array_is_list( $value );
		$node = $this->node( $path );
		$this->add( $node, RdfNamespaces::RDF . 'type', $isList ? new Iri( RdfNamespaces::RDF . 'Seq' ) : $this->term( 'Object' ), $this->metadataGraph );
		foreach ( (array)$value as $key => $part ) {
			$property = $isList ? RdfNamespaces::RDF . '_' . ( $key + 1 ) : $this->vocabulary . 'key/' . rawurlencode( (string)$key );
			$this->add( $node, $property, $this->walk( $part, [ ...$path, ( $isList ? 'i:' : 'k:' ) . $key ] ), $this->metadataGraph );
		}
		return $node;
	}

	private function scalar( mixed $value ): RdfTerm {
		if ( $value === null ) { return $this->term( 'Null' ); }
		if ( is_bool( $value ) ) { return new Literal( $value ? 'true' : 'false', new Iri( RdfNamespaces::XSD . 'boolean' ) ); }
		if ( is_int( $value ) || is_float( $value ) ) { return new Literal( json_encode( $value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION ),
			new Iri( RdfNamespaces::XSD . ( is_int( $value ) ? 'integer' : 'double' ) ) ); }
		if ( is_string( $value ) ) { return $this->literal( $value ); }
		return new Literal( json_encode( $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE ), new Iri( RdfNamespaces::RDF . 'JSON' ) );
	}
	private function iriOrLiteral( string $value ): RdfTerm { return Iri::isSafeAbsolute( $value ) ? new Iri( $value ) : $this->literal( $value ); }
	private function literal( string $text, ?string $language = null ): Literal {
		$language = $language !== null && LanguageTag::isValid( $language ) ? $language : null;
		return new Literal( $text, new Iri( $language === null ? RdfNamespaces::XSD . 'string' : RdfNamespaces::RDF . 'langString' ), $language );
	}
	private function term( string $name ): Iri { return new Iri( $this->vocabulary . $name ); }
	private function node( array $path ): Iri { return new Iri( $this->root . ( $path === [] ? '' : '/node/' . implode( '/', array_map( 'rawurlencode', $path ) ) ) ); }
	private function add( Iri $subject, string $predicate, RdfTerm $object, Iri $graph ): void { $this->quads[] = new Quad( $subject, new Iri( $predicate ), $object, $graph ); }
}
