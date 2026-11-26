<?php

declare( strict_types = 1 );

namespace MediaWiki\Rest\Module {
	class Module {}
}

namespace MediaWiki\Rest {
	interface RequestInterface {}
	class Handler {
		public function __construct( private bool $registered, private bool $write ) {}
		public function needsWriteAccess(): bool {
			return $this->write;
		}
		public function getAuthority(): self {
			return $this;
		}
		public function getUser(): self {
			return $this;
		}
		public function isRegistered(): bool {
			return $this->registered;
		}
	}
	class HttpException extends \Exception {}
}

namespace MediaWiki\Session {
	class CookieSessionProvider {}
}

namespace MediaWiki\Context {
	class RequestContext {
		public static self $current;
		public function __construct( private object $provider, private ?string $header ) {}
		public static function getMain(): self {
			return self::$current;
		}
		public function getRequest(): self {
			return $this;
		}
		public function getSession(): self {
			return $this;
		}
		public function getProvider(): object {
			return $this->provider;
		}
		public function getHeader( string $name ): ?string {
			return $this->header;
		}
	}
}

namespace {
	use AEWI\SWS\EntryPoints\REST\BrowserSessionAccess;
	use MediaWiki\Context\RequestContext;
	use MediaWiki\Rest\Handler;
	use MediaWiki\Rest\Module\Module;
	use MediaWiki\Rest\RequestInterface;
	use MediaWiki\Session\CookieSessionProvider;

	require __DIR__ . '/../src/EntryPoints/REST/BrowserSessionAccess.php';

	$browser = new CookieSessionProvider();
	$other = new stdClass();
	$cases = [
		[ '/sws/v0/subject/E1', true, true, $browser, null, true ],
		[ '/sws/v0/subject/E1', false, false, $browser, null, true ],
		[ '/sws/v0/subject/E1', false, true, $browser, null, false ],
		[ '/sws/v0/subject/E1', true, true, $other, 'Bearer test', false ],
		[ '/sws/v0/subject/E1', false, false, $browser, 'Bearer test', false ],
		[ '/sws/v0/subject/E1', true, true, $other, null, false ],
		[ '/swsbase/v1/subject/E1/export', true, false, $other, 'Bearer test', false ],
		[ '/sai/v0/subject/E1', true, true, $other, 'Bearer test', true ],
		[ '/v1/page/Test', true, true, $other, 'Bearer test', true ],
	];
	foreach ( $cases as [ $path, $registered, $write, $provider, $header, $expected ] ) {
		RequestContext::$current = new RequestContext( $provider, $header );
		$error = null;
		$result = BrowserSessionAccess::onRestCheckCanExecute(
			new Module(), new Handler( $registered, $write ), $path, new class implements RequestInterface {}, $error
		);
		if ( $result !== $expected || ( $expected ? $error !== null : $error?->getCode() !== 403 ) ) {
			throw new RuntimeException( 'Unexpected access for ' . $path );
		}
	}
	echo count( $cases ) . " browser REST access checks passed.\n";
}
