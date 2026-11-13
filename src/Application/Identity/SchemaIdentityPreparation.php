<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Application\Identity;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\Identity\{IdentityKind, LocalizedIdentity};

final readonly class SchemaIdentityPreparation {
	public function __construct( private IdentityRegistry $registry, private string $owner, private string $language ) {}

	public function create( string $json, string $requestKey ): array {
		$object = json_decode( $json, false, 512, JSON_THROW_ON_ERROR );
		$data = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
		if ( !is_object( $object ) || !is_array( $data['propertyDefinitions'] ?? null ) ) {
			throw new InvalidArgumentException( 'Schema property definitions missing' );
		}
		if ( !in_array( $data['formatVersion'] ?? 1, [1, 2], true ) ) { throw new InvalidArgumentException( 'Unsupported schema content version' ); }
		$identity = array_key_exists( 'labels', $data ) ? LocalizedIdentity::fromJson( $data ) : new LocalizedIdentity(
			$this->language, isset( $data['label'] ) && is_string( $data['label'] ) ? [ $this->language=>$data['label'] ] : [],
			isset( $data['description'] ) && is_string( $data['description'] ) && $data['description'] !== '' ? [ $this->language=>$data['description'] ] : []
		);
		if ( $identity->labels === [] || !array_filter( $identity->labels, static fn ( $text ) => trim( $text ) !== '' ) ) {
			throw new InvalidArgumentException( 'At least one Schema label is required' );
		}
		$id = $this->registry->reserveForOwner( IdentityKind::Schema, 'schema:' . $requestKey, $this->owner );
		$object->formatVersion = 2; $object->id = $id->text;
		unset( $object->label );
		foreach ( $identity->toJson() as $key => $value ) { $object->$key = $value; }
		return [ 'id'=>$id, 'json'=>json_encode( $object, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR ) ];
	}
}
