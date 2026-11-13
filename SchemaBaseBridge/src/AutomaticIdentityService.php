<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use Throwable;

/** Read-only page metadata. No schema names, manual fields or duplicate stored IDs. */
final class AutomaticIdentityService {
	/** @param array<string, callable(): array> $lookups */
	public function resolve( ?string $mainSubjectId, array $lookups ): array {
		if ( $mainSubjectId === null ) {
			return [];
		}
		$sources = [];
		foreach ( $lookups as $source => $lookup ) {
			try {
				$value = $lookup();
				$status = $value['status'] ?? 'error';
				if ( $status === 'resolved' ) {
					$id = $value['id'] ?? null;
					$url = $value['url'] ?? null;
					if ( !is_string( $id ) || preg_match( '/\AQ[1-9][0-9]*\z/', $id ) !== 1
						|| !is_string( $url ) || !str_starts_with( $url, 'https://' ) && !str_starts_with( $url, 'http://' ) ) {
						throw new \UnexpectedValueException( 'Invalid source identity' );
					}
					$sources[$source] = [ 'source' => $source, 'status' => 'resolved', 'id' => $id,
						'url' => $url, 'origin' => $value['origin'] ?? 'page' ] +
						array_intersect_key( $value, array_flip( [ 'sourceStatus', 'retained', 'descriptionRetained', 'lastAttemptedAt', 'lastSuccessfulAt', 'storageMode' ] ) );
				} else {
					if ( $status !== 'missing' && function_exists( 'wfDebugLog' ) ) {
						wfDebugLog( 'SchemaBaseBridge', 'Identity source ' . $source . ': ' . $status );
					}
					$sources[$source] = [ 'source' => $source,
						'status' => in_array( $status, [ 'missing', 'unavailable', 'error' ], true ) ? $status : 'error',
						'id' => null, 'url' => null, 'origin' => null ];
				}
			} catch ( Throwable ) {
				if ( function_exists( 'wfDebugLog' ) ) {
					wfDebugLog( 'SchemaBaseBridge', 'Identity lookup failed for source ' . $source );
				}
				$sources[$source] = [ 'source' => $source, 'status' => 'error', 'id' => null, 'url' => null, 'origin' => null ];
			}
		}
		return [ $mainSubjectId => $sources ];
	}
}
