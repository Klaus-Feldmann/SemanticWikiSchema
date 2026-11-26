<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\REST;

use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Module\Module;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Session\CookieSessionProvider;

final class BrowserSessionAccess {

	public static function onRestCheckCanExecute(
		Module $module,
		Handler $handler,
		string $path,
		RequestInterface $request,
		?HttpException &$error
	): bool {
		// SAI dispatches the same handlers on its own routes after its approval checks.
		if ( !preg_match( '~^/(?:sws|swsbase)/v[0-9]+/~', $path ) ) {
			return true;
		}

		$wikiRequest = RequestContext::getMain()->getRequest();
		$registered = $handler->getAuthority()->getUser()->isRegistered();
		$browserSession = $wikiRequest->getSession()->getProvider() instanceof CookieSessionProvider;
		if (
			!$wikiRequest->getHeader( 'Authorization' )
			&& ( !$registered || $browserSession )
			&& ( !$handler->needsWriteAccess() || $registered )
		) {
			return true;
		}

		$error = new HttpException( 'Use a MediaWiki browser session or Semantic AI.', 403 );
		return false;
	}
}
