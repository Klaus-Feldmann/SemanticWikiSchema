<?php

declare( strict_types = 1 );

// Controlled host only. DataViewLookup, StoredSourceLookup, API handlers and exports remain real.
namespace MediaWiki\Content {
	class JsonContent {
		public function __construct( protected string $mText, string $model = '' ) {}
		public function getText(): string { return $this->mText; }
	}
}
namespace MediaWiki\Revision {
	class RevisionRecord {
		public const DELETED_TEXT = 1;
		public bool $readable = true;
		public function __construct( private int $id, private int $pageId, private array $slots ) {}
		public function getId(): int { return $this->id; }
		public function getPageId(): int { return $this->pageId; }
		public function hasSlot( string $name ): bool { return isset( $this->slots[$name] ); }
		public function userCan( mixed ...$args ): bool { return $this->readable; }
		public function getContent( string $name ): mixed { return $this->readable ? ( $this->slots[$name] ?? null ) : null; }
	}
}
namespace Wikimedia\Rdbms { interface IDBAccessObject { public const READ_LATEST = 1; } }
namespace Wikimedia\ParamValidator { class ParamValidator { public const PARAM_TYPE = 1, PARAM_DEFAULT = 2, PARAM_REQUIRED = 3; } }
namespace MediaWiki\Context { class RequestContext extends TestContext { public static function getMain(): self { return new self(); } } }
namespace MediaWiki\Rest {
	interface ResponseInterface {}
	class Response implements ResponseInterface {
		public array $headers = [];
		public mixed $body;
		public int $status = 200;
		public function setHeader( string $key, string $value ): void { $this->headers[$key] = $value; }
		public function setBody( mixed $value ): void { $this->body = $value; }
	}
	class StringStream { public function __construct( private string $value ) {} public function __toString(): string { return $this->value; } }
	class SimpleHandler {
		public const PARAM_SOURCE = 'source';
		public function __construct( public array $params, public mixed $authority ) {}
		public function getAuthority(): mixed { return $this->authority; }
		public function getValidatedParams(): array { return $this->params; }
		public function getResponseFactory(): self { return $this; }
		public function create(): Response { return new Response(); }
		public function createHttpError( int $status, array $data ): Response { $r = new Response(); $r->status = $status; $r->body = json_encode( $data ); return $r; }
		public function applyCacheControl( ResponseInterface $response ): void {}
	}
}
namespace {
	class ApiBase {
		public const PARAM_TYPE = 1, PARAM_REQUIRED = 2, PARAM_MIN = 3, PARAM_DFLT = 4;
		public array $output = [];
		public function __construct( public array $params, public mixed $context ) {}
		public function getMain(): self { return $this; }
		public function setCacheMode( string $mode ): void {}
		public function extractRequestParams(): array { return $this->params; }
		public function getAuthority(): mixed { return $this->context->getAuthority(); }
		public function getContext(): mixed { return $this->context; }
		public function getResult(): self { return $this; }
		public function getModuleName(): string { return 'result'; }
		public function addValue( mixed $parent, string $module, mixed $value ): void { $this->output[$module] = $value; }
		public function dieWithError( mixed ...$args ): never { throw new \RuntimeException( 'Unavailable' ); }
	}
}
