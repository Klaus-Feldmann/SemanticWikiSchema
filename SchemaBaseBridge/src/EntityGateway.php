<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use MediaWiki\Api\ApiMain;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use RuntimeException;
use Wikibase\Repo\WikibaseRepo;

/** Read-only, source-qualified entity access. No cross-source fallback or forwarded remote credentials. */
final class EntityGateway {
	/** Per-collection reuse only; a new instance is created for every logical refresh. */
	private array $collected = [];
	private array $failed = [];
	public function __construct( private readonly IContextSource $context, private readonly bool $refresh = false ) {}

	public function url( string $source, string $id ): ?string {
		if ( preg_match( '/\A[QP][1-9][0-9]*\z/', $id ) !== 1 ) {
			return null;
		}
		if ( $source === 'wikidata' ) {
			return 'https://www.wikidata.org/wiki/' . ( $id[0] === 'P' ? 'Property:' : '' ) . $id;
		}
		if ( $source !== 'wikibase-local' || !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			return null;
		}
		$title = WikibaseRepo::getEntityTitleLookup()->getTitleForId( WikibaseRepo::getEntityIdParser()->parse( $id ) );
		return $title !== null && $this->context->getAuthority()->authorizeRead( 'read', $title )
			? $title->getFullURL() : null;
	}

	/** RDF concept URI; the existing url() remains the human-facing source page URL. */
	public function conceptUri( string $source, string $id ): ?string {
		if ( preg_match( '/\AQ[1-9][0-9]*\z/', $id ) !== 1 ) { return null; }
		if ( $source === 'wikidata' ) { return 'http://www.wikidata.org/entity/' . $id; }
		if ( $source === 'wikibase-local' && $this->url( $source, $id ) !== null ) {
			return WikibaseRepo::getItemVocabularyBaseUri() . $id;
		}
		return null;
	}

	public function entities( string $source, array $ids, string $language, bool $claims ): array {
		$ids = array_values( array_unique( array_filter( $ids, fn ( string $id ): bool => $this->url( $source, $id ) !== null ) ) );
		$result = [];
		if ( $this->refresh ) {
			$pending = [];
			foreach ( $ids as $id ) {
				$key = "$source|$language|" . (int)$claims . "|$id";
				if ( isset( $this->failed[$key] ) ) { throw new RuntimeException( 'Source request already failed in this collection' ); }
				if ( array_key_exists( $key, $this->collected ) ) {
					if ( $this->collected[$key] !== null ) { $result[$id] = $this->collected[$key]; }
				} else { $pending[] = $id; }
			}
			$ids = $pending;
		}
		foreach ( array_chunk( $ids, 50 ) as $batch ) {
			$params = [ 'action' => 'wbgetentities', 'ids' => implode( '|', $batch ),
				'props' => 'labels|descriptions|datatype' . ( $claims ? '|claims' : '' ),
				'languages' => $language, 'languagefallback' => 1 ];
			try {
				if ( $source === 'wikidata' ) {
					$services = MediaWikiServices::getInstance();
					$cache = $services->getMainWANObjectCache();
					$fetch = static function () use ( $services, $params ): array {
							$url = 'https://www.wikidata.org/w/api.php?' . http_build_query( $params + [ 'format' => 'json' ] );
							$raw = $services->getHttpRequestFactory()->get( $url, [ 'timeout' => 10, 'connectTimeout' => 3,
								'userAgent' => 'SWSBaseBridge/0.5 (' . $services->getMainConfig()->get( 'Server' ) . ')' ], __METHOD__ );
							$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
							if ( !is_array( $data ) || isset( $data['error'] ) || !isset( $data['entities'] ) ) {
								throw new RuntimeException( 'External entity lookup unavailable' );
							}
							return $data;
						};
					$data = $this->refresh ? $fetch() : $cache->getWithSetCallback(
						$cache->makeKey( 'SchemaBaseBridge', 'entity-view', hash( 'sha256', json_encode( $params ) ) ), 60, $fetch );
				} else {
					$context = new DerivativeContext( $this->context );
					$context->setRequest( new DerivativeRequest( $this->context->getRequest(), $params ) );
					$api = new ApiMain( $context, false, true );
					$api->execute();
					$data = $api->getResult()->getResultData();
				}
				if ( isset( $data['error'] ) || !isset( $data['entities'] ) ) {
					throw new RuntimeException( 'Entity lookup unavailable' );
				}
			} catch ( \Throwable $e ) {
				if ( $this->refresh ) {
					foreach ( $batch as $id ) { $this->failed["$source|$language|" . (int)$claims . "|$id"] = true; }
				}
				throw $e;
			}
			foreach ( $batch as $id ) {
				$entity = $data['entities'][$id] ?? null;
				if ( $this->refresh ) { $this->collected["$source|$language|" . (int)$claims . "|$id"] = null; }
				if ( is_array( $entity ) && !array_key_exists( 'missing', $entity ) && ( $entity['id'] ?? null ) === $id ) {
					$result[$id] = $entity;
					if ( $this->refresh ) {
						$this->collected["$source|$language|" . (int)$claims . "|$id"] = $entity;
						if ( $claims ) { $this->collected["$source|$language|0|$id"] = $entity; }
					}
				}
			}
		}
		return $result;
	}
}
