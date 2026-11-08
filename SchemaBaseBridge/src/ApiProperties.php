<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\DerivativeRequest;
use RuntimeException;
use Throwable;
use Wikibase\Repo\WikibaseRepo;

/** Read-only property search/resolution, with no client-selected remote URL. */
final class ApiProperties extends ApiBase {
	public function execute(): void {
		$this->getMain()->setCacheMode( 'private' );
		$p = $this->extractRequestParams();
		$query = trim( $p['query'] );
		if ( preg_match( '/\Ap[1-9][0-9]*\z/i', $query ) === 1 ) {
			$query = strtoupper( $query );
		}
		if ( $query === '' || strlen( $query ) > 256 ||
			( $p['mode'] === 'resolve' && preg_match( '/\AP[1-9][0-9]*\z/', $query ) !== 1 ) ||
			preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $p['language'] ) !== 1
		) {
			$this->dieWithError( 'swsbasebridge-property-invalid', 'invalidinput' );
		}
		$language = $p['language'];
		$params = $p['mode'] === 'resolve' ? [ 'action' => 'wbgetentities', 'ids' => $query,
			'props' => 'labels|descriptions|datatype', 'languages' => $language, 'languagefallback' => 1 ] :
			[ 'action' => 'wbsearchentities', 'search' => $query, 'type' => 'property',
				'language' => $language, 'uselang' => $language, 'limit' => 10 ];
		try {
			if ( $p['source'] === 'wikidata' ) {
				$data = $this->remote( $params );
				$link = static fn ( string $id ): string => 'https://www.wikidata.org/wiki/Property:' . $id;
			} else {
				if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
					throw new RuntimeException( 'Local repository unavailable' );
				}
				$context = new DerivativeContext( $this->getContext() );
				$context->setRequest( new DerivativeRequest( $this->getRequest(), $params ) );
				$api = new ApiMain( $context, false, true );
				$api->execute();
				$data = $api->getResult()->getResultData();
				$link = function ( string $id ): ?string {
					$entityId = WikibaseRepo::getEntityIdParser()->parse( $id );
					$title = WikibaseRepo::getEntityTitleLookup()->getTitleForId( $entityId );
					return $title !== null && $this->getAuthority()->authorizeRead( 'read', $title )
						? $title->getFullURL() : null;
				};
			}
			if ( isset( $data['error'] ) ) {
				throw new RuntimeException( 'Property lookup failed' );
			}
			$items = $p['mode'] === 'resolve'
				? PropertyResults::fromEntity( $data, $query, $language, $link )
				: PropertyResults::fromSearch( $data, $link );
			if ($p['fieldtype'] !== null) {
                foreach ($items as &$item) { $item['compatible']=isset($item['datatype']) ? PropertyDatatypePolicy::supports($p['fieldtype'],$item['datatype']) : null; }
                unset($item);
            }
            $this->getResult()->addValue( null, $this->getModuleName(), [ 'version' => 1,
				'source' => $p['source'], 'mode' => $p['mode'], 'query' => $query, 'items' => $items ] );
		} catch ( Throwable ) {
			wfDebugLog( 'SchemaBaseBridge', 'Property lookup failed for source ' . $p['source'] );
			$this->dieWithError( 'swsbasebridge-property-unavailable', 'unavailable' );
		}
	}

	private function remote( array $params ): array {
		$services = MediaWikiServices::getInstance();
		$cache = $services->getMainWANObjectCache();
		$agent = 'SWSBaseBridge/0.4 (' . $services->getMainConfig()->get( 'Server' ) . ')';
		return $cache->getWithSetCallback(
			$cache->makeKey( 'SchemaBaseBridge', 'properties', hash( 'sha256', json_encode( $params ) ) ),
			60,
			static function () use ( $services, $params, $agent ): array {
				$url = 'https://www.wikidata.org/w/api.php?' . http_build_query( $params + [ 'format' => 'json' ] );
				// An anonymous server request: local cookies/OAuth credentials are not forwarded.
				$raw = $services->getHttpRequestFactory()->get( $url,
					[ 'timeout' => 8, 'connectTimeout' => 3, 'userAgent' => $agent ], __METHOD__ );
				$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
				if ( !is_array( $data ) || isset( $data['error'] ) ||
					( !isset( $data['search'] ) && !isset( $data['entities'] ) )
				) {
					throw new RuntimeException( 'Wikidata property lookup unavailable' );
				}
				return $data;
			}
		);
	}

	public function getAllowedParams(): array {
		return [
			'fieldtype'=>[self::PARAM_TYPE=>'string',self::PARAM_DFLT=>null],
            'source' => [ self::PARAM_TYPE => [ 'wikidata', 'wikibase-local' ], self::PARAM_REQUIRED => true ],
			'query' => [ self::PARAM_TYPE => 'string', self::PARAM_REQUIRED => true ],
			'mode' => [ self::PARAM_TYPE => [ 'search', 'resolve' ], self::PARAM_DFLT => 'search' ],
			'language' => [ self::PARAM_TYPE => 'string', self::PARAM_DFLT => $this->getLanguage()->getCode() ],
		];
	}
	public function isWriteMode(): bool { return false; }
	protected function getExamplesMessages(): array { return []; }
}
