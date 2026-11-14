<?php

declare( strict_types = 1 );

// Controlled boundary doubles for standalone storage tests. They do not bootstrap or modify a wiki.
// PageUpdater's CAS contract was checked against MediaWiki REL1_47/includes/Storage/PageUpdater.php.
namespace MediaWiki\Content {
	class Content {}
	class JsonContent extends Content {
		public function __construct( protected string $mText, private string $model = '' ) {}
		public function getText(): string { return $this->mText; }
		public function serialize(): string { return $this->mText; }
		public function getModel(): string { return $this->model; }
	}
	interface IContentHandlerFactory {}
}
namespace MediaWiki\Permissions {
	interface Authority {
		public function authorizeRead( string $action, mixed $title ): bool;
		public function authorizeWrite( string $action, mixed $title ): bool;
	}
}
namespace MediaWiki\CommentStore {
	class CommentStoreComment {
		public static function newUnsavedComment( string $comment ): self { return new self(); }
	}
}
namespace MediaWiki\Title {
	class Title {
		public function __construct( public string $name = 'Test', public int $id = 943, public int $namespace = 0 ) {}
		public function getArticleID(): int { return $this->id; }
		public function getId(): int { return $this->id; }
		public function getText(): string { return $this->name; }
		public function getDBkey(): string { return str_replace( ' ', '_', $this->name ); }
		public function getPrefixedText(): string { return $this->namespace === 0 ? $this->name : 'Schema:' . $this->name; }
		public function getNamespace(): int { return $this->namespace; }
		public function canExist(): bool { return true; }
		public function getContentModel(): string { return 'wikitext'; }
		public function exists( int $flags = 0 ): bool { return true; }
		public function isSamePageAs( self $other ): bool { return $this->id === $other->id; }
		public function getLatestRevID(): int { return \MediaWiki\MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $this )?->getId() ?? 0; }
		public function invalidateCache(): void {}
		public static function makeTitleSafe( int $ns, string $text ): self { return new self( $text, 901, $ns ); }
		public static function newFromID( int $id ): self { return new self( 'Test', $id ); }
	}

	class TitleFactory { public function newFromID(int $id): ?Title { return Title::newFromID($id); } }
}
namespace Wikimedia\Rdbms {
	interface IDBAccessObject { public const READ_LATEST = 1; }
}
namespace MediaWiki\Page {
	interface PageIdentity {}
	class WikiPageFactory {
		public function __construct( public ?\WikiPage $page ) {}
		public function newFromID( int $id ): ?\WikiPage { return $this->page; }
		public function newFromTitle( mixed $title ): ?\WikiPage { return $this->page; }
	}
}
namespace MediaWiki\Revision {
	class RevisionAccessException extends \RuntimeException {}
	class RevisionRecord {
		public const FOR_THIS_USER = 1; public const DELETED_TEXT = 2; public const RAW = 3;
		public bool $readable = true;
		public function __construct( private int $id, private int $pageId, public array $content, private int $parentId = 0 ) {}
		public function getId(): int { return $this->id; }
		public function getPageId(): int { return $this->pageId; }
		public function getParentId(): int { return $this->parentId; }
		public function getSha1(): string { return sha1( implode( '|', array_map( static fn ( $content ) => $content->getText(), $this->content ) ) ); }
		public function getSlotRoles(): array { return array_keys( $this->content ); }
		public function userCan( mixed ...$args ): bool { return $this->readable; }
		public function hasSlot( string $slot ): bool { return array_key_exists( $slot, $this->content ); }
		public function getContent( string $slot, int $flags = 0, mixed $authority = null ): ?\MediaWiki\Content\Content {
			if ( !$this->readable ) { throw new RevisionAccessException( 'Suppressed slot' ); }
			return $this->content[$slot] ?? null;
		}
		public function getSlot( string $slot, int $flags = 0, mixed $authority = null ): object {
			return new class( $this->getContent( $slot, $flags, $authority ) ) {
				public function __construct( private ?\MediaWiki\Content\Content $content ) {}
				public function getContent(): ?\MediaWiki\Content\Content { return $this->content; }
				public function getSha1(): string { return sha1( $this->content->getText() ); }
				public function isInherited(): bool { return false; }
			};
		}
	}
	class RevisionLookup {
		public static ?\Closure $byTitle = null;
		public static ?\Closure $byId = null;
		public function __construct( private \WikiPage $page ) {}
		public function getRevisionByPageId( int $id, int $rev = 0, int $flags = 0 ): ?RevisionRecord { return $this->page->revision; }
		public function getRevisionByTitle( mixed $title, int $rev = 0, int $flags = 0 ): ?RevisionRecord { return self::$byTitle !== null ? ( self::$byTitle )( $title ) : $this->page->revision; }
		public function getRevisionById( int $id ): ?RevisionRecord { return self::$byId !== null ? ( self::$byId )( $id ) : null; }
	}
}
namespace {
	class WikiPage {
		public ?\Closure $beforeSave = null;
		public int $saveCalls = 0;
		public array $lastWrittenSlots = [];
		public static ?\Closure $afterSave = null;
		public mixed $lastActor = null;
		public function __construct( public ?\MediaWiki\Revision\RevisionRecord $revision ) {}
		public function getTitle(): \MediaWiki\Title\Title { return new \MediaWiki\Title\Title(); }
		public function newPageUpdater( mixed $performer ): \MediaWiki\Storage\PageUpdater { $this->lastActor = $performer; return new \MediaWiki\Storage\PageUpdater( $this ); }
		public function doSecondaryDataUpdates( array $params ): void { if ( class_exists( '\\D2Harness\\State', false ) ) { \D2Harness\State::$published[] = $params; } }
	}
}
namespace MediaWiki\Storage {
	class PageUpdater {
		private bool $grabbed = false;
		private ?\MediaWiki\Revision\RevisionRecord $parent = null;
		private ?\MediaWiki\Revision\RevisionRecord $newRevision = null;
		private array $slots = [];
		private bool $success = false;
		public function __construct( private \WikiPage $page ) {}
		public function grabParentRevision(): ?\MediaWiki\Revision\RevisionRecord {
			if ( !$this->grabbed ) { $this->parent = $this->page->revision; $this->grabbed = true; }
			return $this->parent;
		}
		public function setContent( string $slot, \MediaWiki\Content\Content $content ): void { $this->slots[$slot] = $content; }
		public function saveRevision( mixed $comment, int $flags ): void {
			$this->page->saveCalls++;
			$parent = $this->grabParentRevision();
			if ( $this->page->beforeSave !== null ) { ( $this->page->beforeSave )( $this->page ); }
			if ( $parent === null || $parent->getId() !== $this->page->revision?->getId() ) { return; }
			$this->success = true;
			$this->page->lastWrittenSlots = $this->slots;
			$content = array_replace( $parent->content, $this->slots );
			$changed = false;
			foreach ( $this->slots as $slot => $next ) {
				$prior = $parent->content[$slot] ?? null;
				if ( !$prior instanceof \MediaWiki\Content\JsonContent || $prior->getText() !== $next->getText() ) { $changed = true; }
			}
			if ( $changed ) {
				$this->newRevision = new \MediaWiki\Revision\RevisionRecord( $parent->getId() + 1, $parent->getPageId(), $content, $parent->getId() );
				$this->page->revision = $this->newRevision;
				if ( \WikiPage::$afterSave !== null ) { ( \WikiPage::$afterSave )( $this->page, $this->newRevision ); }
			}
		}
		public function wasSuccessful(): bool { return $this->success; }
		public function getNewRevision(): ?\MediaWiki\Revision\RevisionRecord { return $this->newRevision; }
		public function getStatus(): object { return new class { public function getWikiText(): string { return 'edit-conflict'; } }; }
	}
}
namespace ProfessionalWiki\SWS {
	// Only the deserializer factory is substituted; SubjectContent, repository and saver are real.
	class SWSExtension {
		public const NS_SCHEMA = 7480;
		public static mixed $schema = null;
		public static mixed $policy = null;
		public static ?\Closure $resolver = null;
		public function getDataLanguages(): array { return []; }
		public function getRevisionPolicy(): mixed { return self::$policy; }
		public function getPersistenceSchemaDeserializer(): mixed { return new \ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaPersistenceDeserializer( \ProfessionalWiki\SWS\Tests\Data\TestSources::newPropertyTypeRegistry() ); }
		public function getSchemaResolverFor( mixed $authority ): mixed { return new class { public function getSchema( mixed $ref ): mixed { return SWSExtension::$schema; } }; }
		public function newSubjectResolver( mixed $parser ): mixed { return ( self::$resolver )(); }
		public function newHookPageRebuilder(): mixed { return new class { public function rebuild( mixed $title ): void {} }; }
		public static \ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer $deserializer;
		public static function getInstance(): self { return new self(); }
		public function newSubjectContentDataDeserializer(): \ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer { return self::$deserializer; }
	}
}
