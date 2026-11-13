<?php

declare( strict_types = 1 );

// Host boundaries only. The actual processor, capture, patcher, writer, receipt and hooks are loaded.
namespace D2Harness {
	class State {
		public static \WikiPage $page;
		public static mixed $schemaRevision;
		public static array $history = [], $published = [], $collected = [], $plans = [], $queued = [], $statuses = [];
		public static bool $smw = false, $publishedPolicy = true, $parserFailure = false, $swsFailure = false;
		public static bool $bridge = true;
		public static bool $readOnly = false;
		public static string $q = 'Q41390', $value = 'Quellenwert';
		public static string $mode = 'store';
		public static int $liveReads = 0;
		public static ?string $foreignHtml = null;
		public static ?\Closure $afterCollect = null;
	}
}
namespace MediaWiki\Revision { class SlotRecord { public const MAIN = 'main'; } }
namespace MediaWiki\Registration {
	class ExtensionRegistry {
		public static function getInstance(): self { return new self(); }
		public function isLoaded( string $name ): bool { return $name === 'SchemaBaseBridge' && \D2Harness\State::$bridge; }
	}
}
namespace MediaWiki\User {
	class User implements \MediaWiki\Permissions\Authority {
		public static function newSystemUser( string $name, array $options ): self { return new self(); }
		public function getName(): string { return 'SemanticSWS internal processor'; }
		public function isSystemUser(): bool { return true; }
		public function authorizeRead( string $action, mixed $title ): bool { return true; }
		public function authorizeWrite( string $action, mixed $title ): bool { return false; }
	}
}
namespace MediaWiki\Context {
	interface IContextSource { public function getAuthority(): mixed; }
	class RequestContext implements IContextSource {
		public static function getMain(): self { return new self(); }
		public function getAuthority(): mixed { return new \MediaWiki\User\User(); }
	}
	class DerivativeContext extends RequestContext {
		private mixed $user;
		public function __construct( mixed $context ) {}
		public function setUser( mixed $user ): void { $this->user = $user; }
		public function getAuthority(): mixed { return $this->user; }
	}
}
namespace MediaWiki\Parser {
	class ParserOptions {
		public static function newFromUser( mixed $user ): self { return new self(); }
		public function isMessage(): bool { return false; }
	}
	class ParserOutputLinkTypes { public const TEMPLATE = 1; }
	class ParserOutput {
		public array $data = [], $props = [], $links = [];
		public function getContentHolderText(): string { return \D2Harness\State::$foreignHtml ?? (\D2Harness\State::$parserFailure ? '<strong class="error">Broken parser</strong>' : 'Complete'); }
		public function getExtensionData( string $key ): mixed { return $this->data[$key] ?? null; }
		public function setExtensionData( string $key, mixed $data ): void { $this->data[$key] = $data; }
		public function getPageProperty( string $key ): mixed { return $this->props[$key] ?? null; }
		public function setPageProperty( string $key, mixed $data ): void { $this->props[$key] = $data; }
		public function getLinkList( mixed $type ): array { return $this->links; }
		public function updateCacheExpiry( int $expiry ): void {}
		public function getCategoryNames(): array { return []; }
		public function addCategory( string $name, string $sort ): void {}
		public function addTemplate( mixed $title, int $id, int $revision ): void { $this->links[] = [ 'link' => $title, 'revid' => $revision ]; }
	}
	class Parser {
		public const OT_HTML = 1;
		public ?\MediaWiki\Title\Title $title = null;
		public ParserOutput $output;
		public function __construct() { $this->output = new ParserOutput(); }
		public function startExternalParse( mixed $title, mixed $options, int $type ): void { $this->title = $title; }
		public function getTitle(): mixed { return $this->title; }
		public function getOptions(): mixed { return new ParserOptions(); }
		public function getOutput(): ParserOutput { return $this->output; }
		public function msg( mixed ...$args ): mixed { return new class { public function text(): string { return ''; } }; }
	}
}
namespace MediaWiki\Deferred\LinksUpdate { class LinksUpdate {} }
namespace MediaWiki\Deferred {
	class DeferredUpdates {
		public static function addCallableUpdate( callable $callback ): void { \D2Harness\State::$queued[] = $callback; }
	}
}
namespace MediaWiki\Logger {
	class LoggerFactory { public static function getInstance( string $name ): mixed { return new class { public function error( mixed ...$args ): void {} }; } }
}
namespace MediaWiki {
	class MediaWikiServices {
		public static function getInstance(): self { return new self(); }
		public function getMainConfig(): mixed { return new class { public function get( string $name ): mixed { return match ( $name ) { 'SecretKey' => 'controlled-fixture-key', 'SWSSourceDataMode' => \D2Harness\State::$mode, default => null }; } }; }
		public function getContentLanguage(): mixed { return new class { public function getCode(): string { return 'de'; } }; }
		public function getReadOnlyMode(): mixed { return new class { public function getReason(): string|false { return \D2Harness\State::$readOnly ? 'Controlled maintenance' : false; } }; }
		public function getRevisionLookup(): mixed { return new \MediaWiki\Revision\RevisionLookup( \D2Harness\State::$page ); }
		public function getWikiPageFactory(): mixed { return new \MediaWiki\Page\WikiPageFactory( \D2Harness\State::$page ); }
		public function hasService( string $name ): bool { return $name === 'SMW.ParserDataFactory' && \D2Harness\State::$smw; }
		public function getParserFactory(): mixed { return new class { public function create(): mixed { return new \MediaWiki\Parser\Parser(); } }; }
		public function getRevisionRenderer(): mixed { return new class {
			public function getRenderedRevision( mixed $revision, mixed $options, mixed $user, array $params ): mixed {
				return new class {
					public function getRevisionParserOutput( array $options ): mixed {
						$parser = new \MediaWiki\Parser\Parser();
						$context = \ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingContext::current();
						$parser->startExternalParse( $context->title, null, 1 );
						$context->observe( $parser );
						if ( \D2Harness\State::$swsFailure ) { $context->failure = 'SWS schema resolution failed'; }
						$parser->output->setPageProperty( 'unlinkedwikibase_id', \D2Harness\State::$q );
						return $parser->output;
					}
				};
			}
		}; }
		public function getMainObjectStash(): mixed { return new class {
			public function makeKey( mixed ...$args ): string { return implode( ':', $args ); }
			public function set( mixed ...$args ): void {}
		}; }
		public function getJobQueueGroup(): mixed { return new class { public function push( mixed $job ): void { \D2Harness\State::$queued[] = $job; } }; }
		public function getConnectionProvider(): mixed { return new class { public function getPrimaryDatabase(): mixed { return new class {
			public function expr( mixed ...$args ): int { return 0; }
			public function newSelectQueryBuilder(): mixed { return new class {
				public function __call( string $name, array $args ): mixed { return $this; }
				public function fetchFieldValues(): array { return [ 943 ]; }
			}; }
		}; } }; }
	}
}
namespace SMW {
	class ParserData {
		public function __construct( mixed ...$args ) {}
		public function getSemanticData(): mixed { return new \D2Harness\Semantic(); }
	}
}
namespace D2Harness {
	class Semantic {
		public function isEmpty(): bool { return false; }
		public function getProperties(): array { return []; }
		public function getSubSemanticData(): array { return []; }
		public function setExtensionData( mixed ...$args ): void {}
	}
}
namespace ProfessionalWiki\SWS\EntryPoints\Content { class SchemaContent extends \MediaWiki\Content\JsonContent {} }
namespace ProfessionalWiki\SWS\EntryPoints\Semantic {
	class WikiAnnotationConfig { public function read( mixed ...$args ): array { return [ 'properties' => [] ]; } }
	class SmwAnnotationApplier {
		public function apply( mixed $title, mixed $output, mixed $plan ): void {
			\D2Harness\State::$plans[] = $plan->rows;
			$output->setExtensionData( 'smwdata', new \D2Harness\Semantic() );
		}
	}
}
namespace SWSBaseBridge {
	class ExternalValuesLookup {
		public function __construct( mixed $context ) {}
		public function read( mixed $title, array $params, mixed $dependencies, mixed $subject ): array {
			\D2Harness\State::$liveReads++;
			$fields = [];
			foreach ( \ProfessionalWiki\SWS\SWSExtension::$schema->getAllProperties()->asMap() as $name => $property ) {
				if ( ( $binding = $property->getExternalProperty() ) === null ) { continue; }
				$fields[] = [ 'name' => $name, 'source' => $binding->source, 'propertyId' => $binding->id,
					'status' => 'resolved', 'values' => [ [ 'kind' => 'text', 'value' => 'Live-Wert' ] ] ];
			}
			return [ 'version' => 1, 'scope' => 'current', 'mainSubjectId' => $subject->id->text, 'fields' => $fields ];
		}
	}
	// The collector's HTTP/type contract is tested separately with the actual collector and gateway.
	class SourceSnapshotCollector {
		public function __construct( mixed $context ) {}
		public function collect( mixed $title, mixed $schema, string $language ): array {
			$identity = \ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingContext::current()->currentProperty( $title, 'unlinkedwikibase_id' );
			\D2Harness\State::$collected[] = [ $language, $identity ];
			$fields = [];
			foreach ( $schema->getAllProperties()->asMap() as $name => $property ) {
				if ( ( $binding = $property->getExternalProperty() ) === null ) { continue; }
				$status = \D2Harness\State::$statuses[$name] ?? 'resolved';
				$fields[] = [ 'name' => $name, 'source' => $binding->source, 'propertyId' => $binding->id, 'entityId' => $identity,
					'propertyType' => $property->getPropertyType(), 'status' => $status,
					'values' => $status === 'resolved' ? [ [ 'kind' => 'text', 'value' => \D2Harness\State::$value ] ] : [] ];
			}
			if ( \D2Harness\State::$afterCollect !== null ) { ( \D2Harness\State::$afterCollect )(); }
			return [ 'fields' => $fields, 'identities' => [] ];
		}
	}
}
namespace {
	class Job {
		public const JOB_NO_EXPLICIT_TRX_ROUND = 1;
		public bool $removeDuplicates = false;
		public int $executionFlags = 0;
		public function __construct( public string $type, public mixed $title, public array $params ) {}
		public function setLastError( string $error ): void {}
	}
	define( 'EDIT_UPDATE', 1 ); define( 'EDIT_INTERNAL', 2 ); define( 'SMW_NS_PROPERTY', 102 );
	function wfTimestampNow(): string { return '20261111190000'; }
	function wfMessage( string $key, mixed ...$args ): mixed { return new class( $key ) {
		public function __construct( private string $key ) {}
		public function inContentLanguage(): self { return $this; }
		public function text(): string { return $this->key === 'sws-notes-property-suffix' ? ' (AM)' : $this->key; }
	}; }
}
