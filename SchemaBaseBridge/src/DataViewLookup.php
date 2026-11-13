<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use MediaWiki\Context\IContextSource;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\SWSExtension;
use RuntimeException;
use Throwable;

/** Current, private, read-authorized snapshot. No parser execution, SMW dependency or writes. */
final class DataViewLookup {
	private ?StoredSourceLookup $stored = null;
	public function __construct( private readonly IContextSource $context ) {}
	private function store(): StoredSourceLookup { return $this->stored ??= new StoredSourceLookup( $this->context ); }

	public function read( Title $title, string $language, ?string $subjectId = null ): array {
		if ( !$this->context->getAuthority()->authorizeRead( 'read', $title ) ||
			preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $language ) !== 1 ) {
			throw new RuntimeException( 'Data view unavailable' );
		}
		$extension = SWSExtension::getInstance();
		$sourceMode = \ProfessionalWiki\SWS\Application\Source\SourceDataMode::current();
		$page = $sourceMode === 'store' ? $this->store()->page( $title ) :
			$extension->newPageSubjectsLookup()->getPageSubjects( new PageId( $title->getArticleID() ) );
		$subjects = $page->getAllSubjects()->asArray();
		if ( $subjectId !== null ) {
			$subjects = array_filter( $subjects, static fn ( $s ): bool => $s->id->text === $subjectId );
			if ( $subjects === [] ) { throw new RuntimeException( 'Subject unavailable on this page' ); }
		}
		$identities = $this->readIdentities( $title, $language );
		$pageDescription = ( new PageDescriptionLookup() )->read( $title, $this->context->getAuthority() );
		$relationCache = [];
		$resolveRelation = function ( string $id ) use ( $extension, $sourceMode, &$relationCache ): array {
			if ( isset( $relationCache[$id] ) ) { return $relationCache[$id]; }
			try {
				$subjectId = $extension->getSubjectIdParser( true )->parse( $id );
				if ( $subjectId === null ) { return [ 'id' => $id, 'status' => 'unavailable' ]; }
				$host = $extension->newSubjectHostingPageResolver( $this->context->getAuthority() )->resolveReadableHostingPage( $subjectId );
				if ( $host !== null ) {
					$targetTitle = Title::newFromID( $host->getId()->id );
					$targetPage = $sourceMode === 'store'
						? ( $targetTitle !== null ? $this->store()->page( $targetTitle ) : \ProfessionalWiki\SWS\Domain\Page\PageSubjects::newEmpty() )
						: $extension->newPageSubjectsLookup()->getPageSubjects( $host->getId() );
					$target = $targetPage->getAllSubjects()->getSubject( $subjectId );
					if ( $target !== null ) {
						return $relationCache[$id] = [ 'id' => $subjectId->text, 'status' => 'resolved', 'pageId' => $host->getId()->id,
							'pageTitle' => $host->getTitle(), 'label' => $target->getLabel()?->text ??
							( $targetPage->isMainSubject( $subjectId ) ? $host->getTitle() : $target->getSchemaReference()->getText() ),
							'url' => Title::newFromID( $host->getId()->id )?->getFullURL() ];
					}
				}
			} catch ( Throwable ) { /* Do not expose unreadable target metadata or internal failures. */ }
			return $relationCache[$id] = [ 'id' => $id, 'status' => 'unavailable' ];
		};
		$views = [];
		foreach ( $subjects as $subject ) {
			try {
				$schema = $extension->getSchemaResolverFor( $this->context->getAuthority() )->getSchema( $subject->getSchemaReference() );
			} catch ( Throwable ) { $schema = null; }
			try {
				$params = [ 'pageid' => $title->getArticleID(), 'subject' => $subject->id->text, 'language' => $language ];
				$external = $schema === null ? [ 'fields' => [] ] : ( $sourceMode === 'store'
					? $this->store()->fields( $title, $params, null, $subject, $schema )
					: ( new ExternalValuesLookup( $this->context ) )->read( $title, $params, null, $subject ) );
			} catch ( Throwable ) {
				$external = [ 'fields' => [] ];
				foreach ( $schema?->getAllProperties()->asMap() ?? [] as $name => $property ) {
					if ( $property->getExternalProperty() !== null && !$property->isSchemaExecution() ) {
						$external['fields'][] = [ 'name' => $name, 'status' => 'unavailable', 'values' => [], 'binding' => $property->getExternalProperty()->toJson() ];
					}
				}
			}
			$view = DataViewAssembler::subject( $subject, $schema, $external, $resolveRelation, $sourceMode );
			foreach ( $view['fields'] as &$field ) {
				foreach ( $field['ownValues'] as &$value ) {
					if ( $field['type'] === 'page' && is_string( $value['value'] ) ) {
						$value['pageTitle'] = $value['value'];
						$value['url'] = Title::newFromText( $value['value'] )?->getFullURL();
					}
				}
				unset( $value );
				if ( $field['effectiveOrigin'] === 'own' ) { $field['effectiveValues'] = $field['ownValues']; }
			}
			unset( $field );
			$view['isMainSubject'] = $page->isMainSubject( $subject->id );
			$view['displayName'] = $subject->getLabel()?->text ??
				( $view['isMainSubject'] ? $title->getText() : $subject->getSchemaReference()->getText() );
			$views[] = $view;
		}
		return [ 'version' => 1, 'scope' => 'current', 'readOnly' => true,
			'sourceDataMode' => $sourceMode,
			'retrievedAt' => gmdate( 'c' ), 'language' => $language,
			'page' => [ 'id' => $title->getArticleID(), 'title' => $title->getPrefixedText(), 'url' => $title->getFullURL() ],
			'mainSubjectId' => $page->getMainSubject()?->id->text, 'identities' => $identities,
			'descriptions' => DescriptionPresentation::groups( $identities ),
			'pageDescription' => $pageDescription,
			'displayDescription' => DescriptionPresentation::select( $identities, $pageDescription ),
			'complete' => $pageDescription['status'] !== 'unavailable' && array_filter( $views, static fn ( array $v ): bool => !$v['complete'] ) === [] &&
				array_filter( $identities, static fn ( array $i ): bool => ( $i['descriptionStatus'] ?? '' ) === 'unavailable' &&
					!( $sourceMode === 'store' && ( $i['recorded'] ?? false ) ) ) === [],
			'subjects' => $views ];
	}
	/** Shared identity/description read: no Subject fields or source claims needed. */
	public function readIdentities( Title $title, string $language ): array {
		if ( !$this->context->getAuthority()->authorizeRead( 'read', $title ) ||
			preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $language ) !== 1 ) {
			throw new RuntimeException( 'Identity descriptions unavailable' );
		}
		if ( \ProfessionalWiki\SWS\Application\Source\SourceDataMode::usesStore() ) {
			return $this->store()->identities( $title, $language );
		}
		$identities = [];
		$gateway = new EntityGateway( $this->context );
		$identityLookup = new PageIdentityLookup();
		foreach ( [ 'wikidata', 'wikibase-local' ] as $source ) {
			try {
				$identity = $source === 'wikidata' ? $identityLookup->wikidata( $title ) :
					$identityLookup->wikibase( $title, $this->context->getAuthority() );
				if ( ( $identity['status'] ?? '' ) === 'resolved' ) {
					try {
					$entity = $gateway->entities( $source, [ $identity['id'] ], $language, false )[$identity['id']] ?? [];
					$identity['description'] = $entity['descriptions'][$language] ?? null;
					$identity['descriptionStatus'] = $entity === [] ? 'unavailable' : ( $identity['description'] === null ? 'missing' : 'resolved' );
					} catch ( Throwable ) { $identity['description'] = null; $identity['descriptionStatus'] = 'unavailable'; }
				}
				$identities[$source] = $identity;
			} catch ( Throwable ) { $identities[$source] = [ 'status' => 'unavailable' ]; }
		}
		return $identities;
	}
}
