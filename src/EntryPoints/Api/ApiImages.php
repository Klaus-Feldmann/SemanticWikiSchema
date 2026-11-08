<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Api;

use ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Context\DerivativeContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\Images\ImageResults;
use Throwable;

/** Read-only file selection/preview; never accepts an arbitrary remote API URL. */
final class ApiImages extends ApiBase {
	public function execute(): void {
		$this->getMain()->setCacheMode( 'private' );
		$p = $this->extractRequestParams();
		$query = trim( $p['query'] );
		if ( $query === '' || strlen( $query ) > 500 ) { $this->dieWithError( 'sws-image-invalid-query', 'invalid-query' ); }
		$title = Title::makeTitleSafe( 6, $query );
		if ( $title === null ) { $this->dieWithError( 'sws-image-invalid-query', 'invalid-query' ); }
		$params = [ 'action' => 'query', 'prop' => 'imageinfo', 'iiprop' => 'url|size|mime', 'iiurlwidth' => 150, 'iiurlheight' => 150 ];
		if ( $p['mode'] === 'resolve' ) {
			$params['titles'] = $p['repository'] === 'commons' ? 'File:' . $query : $title->getPrefixedText();
		} else {
			$params += [ 'generator' => 'prefixsearch', 'gpssearch' => $query, 'gpsnamespace' => 6, 'gpslimit' => 10 ];
		}
		try {
			if ( $p['repository'] === 'commons' ) {
				// Require wiki read access even though Commons itself is public.
				if ( !$this->getAuthority()->authorizeRead( 'read', $title ) ) { throw new \RuntimeException(); }
				$services = MediaWikiServices::getInstance();
				$raw = $services->getHttpRequestFactory()->get( 'https://commons.wikimedia.org/w/api.php?' . http_build_query( $params + [ 'format' => 'json', 'formatversion' => 2 ] ),
					[ 'timeout' => 10, 'connectTimeout' => 3, 'userAgent' => 'SWS-Images/0.1 (' . $services->getMainConfig()->get( 'Server' ) . ')' ], __METHOD__ );
				$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
			} else {
				$context = new DerivativeContext( $this->getContext() );
				$context->setRequest( new DerivativeRequest( $this->getRequest(), $params ) );
				$api = new ApiMain( $context, false, true ); $api->execute();
				$data = $api->getResult()->getResultData();
			}
			if ( !is_array( $data ) || isset( $data['error'] ) ) { throw new \RuntimeException(); }
			$pages = array_values( array_filter( $data['query']['pages'] ?? [], 'is_array' ) );
			if ( $p['repository'] === 'local' ) {
				$pages = array_values( array_filter( $pages, function ( array $page ): bool {
					$title = Title::newFromText( $page['title'] ?? '' );
					return $title !== null && $this->getAuthority()->authorizeRead( 'read', $title );
				} ) );
			}
			$items = ImageResults::fromPages( $pages, $p['repository'] );
			$this->getResult()->addValue( null, $this->getModuleName(), [ 'version' => 1, 'repository' => $p['repository'],
				'mode' => $p['mode'], 'query' => $query, 'items' => $items ] );
		} catch ( Throwable ) {
			$this->dieWithError( 'sws-image-unavailable', 'unavailable' );
		}
	}
	public function getAllowedParams(): array {
		return [ 'repository' => [ self::PARAM_TYPE => [ 'local', 'commons' ], self::PARAM_REQUIRED => true ],
			'mode' => [ self::PARAM_TYPE => [ 'search', 'resolve' ], self::PARAM_DFLT => 'search' ],
			'query' => [ self::PARAM_TYPE => 'string', self::PARAM_REQUIRED => true ] ];
	}
	public function isWriteMode(): bool { return false; }
	protected function getExamplesMessages(): array { return []; }
}
