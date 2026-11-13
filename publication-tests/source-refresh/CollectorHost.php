<?php

declare( strict_types = 1 );

namespace CollectorHarness {
	class State {
		public static array $entities = [], $requests = [], $cache = [], $cachedQ = [], $failIds = [], $denied = [];
		public static bool $localEnabled = true;
		public static int $cacheCalls = 0;
		public static string $mode = 'store';
		public static mixed $revision = null, $publishedId = null, $shortdesc = null;
		public static array $revisions = [];
		public static bool $publish = true;
		public static int $identityLookups = 0;
		public static array $dependencies = [], $parserOptions = [];
		public static function response( string $source, array $params ): array {
			$ids = explode( '|', $params['ids'] ); self::$requests[] = [ $source, $ids, $params ];
			if ( array_intersect( self::$failIds, $ids ) !== [] ) { throw new \RuntimeException( 'Controlled source failure' ); }
			$result = [];
			foreach ( $ids as $id ) { $result[$id] = self::$entities[$source][$id] ?? [ 'id' => $id, 'missing' => '' ]; }
			return [ 'entities' => $result ];
		}
	}
}
namespace MediaWiki\Context {
	interface IContextSource { public function getAuthority(): mixed; public function getRequest(): mixed; }
	class TestContext implements IContextSource {
		public function getAuthority(): mixed { return new class implements \MediaWiki\Permissions\Authority {
			public function authorizeRead( string $action, mixed $title ): bool { return !in_array( $title->getText(), \CollectorHarness\State::$denied, true ); }
		}; }
		public function getRequest(): mixed { return new \stdClass(); }
	}
	class DerivativeContext extends TestContext {
		private mixed $request;
		private mixed $authority = null;
		public function __construct( mixed $context ) {}
		public function setRequest( mixed $request ): void { $this->request = $request; }
		public function getRequest(): mixed { return $this->request; }
		public function setUser( mixed $user ): void { $this->authority = $user; }
		public function setAuthority( mixed $authority ): void { $this->authority = $authority; }
		public function getAuthority(): mixed { return $this->authority ?? parent::getAuthority(); }
	}
}
namespace MediaWiki\Permissions { interface Authority {} }
namespace MediaWiki\Request {
	class DerivativeRequest { public function __construct( mixed $request, public array $params ) {} }
}
namespace MediaWiki\Api {
	class ApiMain {
		private array $result;
		public function __construct( private mixed $context, mixed ...$args ) {}
		public function execute(): void { $this->result = \CollectorHarness\State::response( 'wikibase-local', $this->context->getRequest()->params ); }
		public function getResult(): self { return $this; }
		public function getResultData(): array { return $this->result; }
	}
}
namespace MediaWiki\Registration {
	class ExtensionRegistry {
		public static function getInstance(): self { return new self(); }
		public function isLoaded( string $name ): bool { return $name === 'UnlinkedWikibase' || $name === 'WikibaseRepository' && \CollectorHarness\State::$localEnabled; }
	}
}
namespace MediaWiki\Title {
	class Title {
		public function __construct( private string $name = 'Artikel' ) {}
		public function getArticleID(): int { return 943; }
		public function getText(): string { return $this->name; }
		public function getPrefixedText(): string { return $this->name; }
		public function getFullURL(): string { return 'https://local.example/wiki/' . $this->name; }
		public function getPageLanguage(): mixed { return new class { public function getCode(): string { return 'de'; } }; }
		public static function newFromText( string $name ): self { return new self( $name ); }
		public static function newFromID( int $id ): ?self { return $id === 943 ? new self() : null; }
		public function getContentModel(): string { return 'wikitext'; }
		public function canExist(): bool { return !str_starts_with($this->name,'Spezial:'); }
		public function isExternal(): bool { return str_starts_with($this->name,'extern:'); }
		public function getLatestRevID(): int { return \CollectorHarness\State::$revision?->getId() ?? 0; }
		public static function makeTitleSafe(int $ns, string $text): self { return new self('Schema:'.$text); }
		public static function newMainPage(): self { return new self(); }
		public function isSamePageAs( mixed $other ): bool { return $this->name === $other->name; }
	}
}
namespace MediaWiki\Parser {
	class Parser {
        public int $expensive = 0;
        public function __construct(private mixed $title = null, private mixed $authority = null) {}
        public function getTitle(): mixed { return $this->title ?? new \MediaWiki\Title\Title(); }
        public function getPage(): mixed { return $this->getTitle(); }
        public function getUserIdentity(): mixed { return $this->authority ?? (new \MediaWiki\Context\TestContext())->getAuthority(); }
        public function getOptions(): mixed { return new class { public function getOption($name): mixed { \CollectorHarness\State::$parserOptions[]=$name; return null; } }; }
        public function getOutput(): mixed { return new class { public function addTemplate($title,$id,$rev): void { \CollectorHarness\State::$dependencies[]=$title->getPrefixedText(); } }; }
        public function incrementExpensiveFunctionCount(): bool { $this->expensive++; return true; }
    }
	class ParserOutput {
		public function __construct( public array $props ) {}
		public function addTemplate(mixed $title,mixed ...$args): void { \CollectorHarness\State::$dependencies[]=$title->getPrefixedText(); }
		public function getPageProperty( string $name ): mixed { return $this->props[$name] ?? null; }
	}
}
namespace MediaWiki\WikiMap { class WikiMap { public static function getCurrentWikiId(): string { return 'aewi'; } } }
namespace MediaWiki {
	class MediaWikiServices {
		public static function getInstance(): self { return new self(); }
		public function getUserFactory(): mixed { return new class { public function newFromUserIdentity($identity): mixed { return $identity; } }; }
		public function getMainConfig(): mixed { return new class { public function get( string $key ): mixed { return match ( $key ) { 'WikiSchemaSmwMapping' => [], 'SWSSourceDataMode' => \CollectorHarness\State::$mode, 'SWSBaseBridgeLocalSiteId' => 'aewi', 'Server' => 'https://local.example', default => null }; } }; }
		public function getContentLanguage(): mixed { return new class { public function getCode(): string { return 'de'; } }; }
		public function getPageProps(): mixed { return new class { public function getProperties( mixed $title, string $name ): array {
			if ( $name === 'shortdesc' ) { return \CollectorHarness\State::$shortdesc === null ? [] : [ 943 => \CollectorHarness\State::$shortdesc ]; }
			\CollectorHarness\State::$identityLookups++;
			return \CollectorHarness\State::$cachedQ;
		} }; }
		public function getRevisionLookup(): mixed { return new class {
			public function getRevisionByTitle( mixed ...$args ): mixed { return \CollectorHarness\State::$revision; }
			public function getRevisionById( int $id ): mixed { return \CollectorHarness\State::$revisions[$id] ?? null; }
		}; }
		public function getMainWANObjectCache(): mixed { return new class {
			public function makeKey( mixed ...$args ): string { return implode( ':', $args ); }
			public function getWithSetCallback( string $key, int $ttl, callable $fetch ): array {
				\CollectorHarness\State::$cacheCalls++;
				return \CollectorHarness\State::$cache[$key] ??= $fetch();
			}
		}; }
		public function getHttpRequestFactory(): mixed { return new class {
			public function get( string $url, array $options, string $method ): string {
				parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
				return json_encode( \CollectorHarness\State::response( 'wikidata', $params ), JSON_THROW_ON_ERROR );
			}
		}; }
	}
}
namespace Wikibase\Repo {
	class WikibaseRepo {
		public static function getEntityIdParser(): mixed { return new class { public function parse( string $id ): string { return $id; } }; }
		public static function getEntityTitleLookup(): mixed { return new class { public function getTitleForId( mixed $id ): mixed { return new \MediaWiki\Title\Title( is_string( $id ) ? $id : $id->getSerialization() ); } }; }
		public static function getStore(): mixed { return new class {
			public function getEntityByLinkedTitleLookup(): self { return $this; }
			public function getEntityIdForLinkedTitle( mixed ...$args ): mixed { return new class { public function getSerialization(): string { return 'Q500'; } }; }
		}; }
	}
}
namespace ProfessionalWiki\SWS {
	class SWSExtension {
		public const NS_SCHEMA = 7480;
		public static mixed $resolver;
		public static mixed $schema;
		public static mixed $deserializer;
		public static function getInstance(): self { return new self(); }
		public function getSchemaResolverFor( mixed $authority ): mixed { return new class { public function getSchema( mixed $ref ): mixed { return SWSExtension::$schema; } }; }
		public function newSubjectResolver(mixed $parser): mixed { return self::$resolver; }
        public function newIdentityBoundaryResolver(): mixed { return new class { public function entity($id) { return $id; } }; }
        public function newSubjectHostingPageResolver(mixed $authority): mixed { return new class($authority) {
            public function __construct(private mixed $authority) {}
            public function resolveReadableHostingPage($id): mixed {
                $title=new \MediaWiki\Title\Title();
                if (!$this->authority->authorizeRead('read',$title) || !\CollectorHarness\State::$revision->getContent('sws')->getPageSubjects()->getAllSubjects()->getSubject($id)) { return null; }
                return new class { public function getId(): mixed { return new \ProfessionalWiki\SWS\Domain\Page\PageId(943); } public function getTitle(): string { return 'Artikel'; } };
            }
        }; }
        public function getDataLanguages(): array { return []; }
		public function newPageSubjectsLookup(): self { return $this; }
		public function getPageSubjects( mixed $id ): mixed { return \CollectorHarness\State::$revision->getContent( 'sws' )->getPageSubjects(); }
		public function newSubjectContentDataDeserializer(): mixed { return self::$deserializer; }
		public function getRevisionPolicy(): mixed { return new class {
			public function publishedRevision( mixed $revision ): mixed { return \CollectorHarness\State::$publish ? ( \CollectorHarness\State::$revisions[\CollectorHarness\State::$publishedId ?? $revision->getId()] ?? null ) : null; }
			public function revisionIsReadableBy( mixed $revision, mixed $authority ): bool { return $revision->readable; }
		}; }
		public function newSubjectContentRepository( mixed $authority ): mixed { return new class {
			public function getSubjectContentByRevisionId( int $id ): mixed { return \CollectorHarness\State::$revisions[$id]?->getContent( 'sws' ); }
		}; }
		public function getRdfNamespaces(): mixed { return new \ProfessionalWiki\SWS\Domain\Rdf\RdfNamespaces( 'https://local.example' ); }
		public function getRdfSerializer(): mixed { return new \ProfessionalWiki\SWS\Infrastructure\Rdf\HardfRdfSerializer( $this->getRdfNamespaces()->prefixMap(), new \Psr\Log\NullLogger() ); }
	}
}
