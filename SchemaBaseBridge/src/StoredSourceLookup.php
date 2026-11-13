<?php

declare( strict_types = 1 );

namespace SWSBaseBridge;

use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\PageDependencyRecorder;
use ProfessionalWiki\SWS\Application\Source\StoredSourceValues;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\SWSExtension;
use RuntimeException;
use Throwable;

/** Authorized reads of persisted source data. Never calls wbgetentities, parses or writes. */
final class StoredSourceLookup {

	private ?\ProfessionalWiki\SWS\EntryPoints\Source\PublishedSubjectsLookup $published = null;
	private array $access = [];
	private EntityGateway $links;

	public function __construct( private readonly IContextSource $context, ?\ProfessionalWiki\SWS\EntryPoints\Source\PublishedSubjectsLookup $published = null ) {
		$this->published = $published;
		// Only url() is used: local title/permission lookup, not source-value retrieval.
		$this->links = new EntityGateway( $context );
	}

	public function page( Title $title ): PageSubjects {
		return ( $this->published ??= new \ProfessionalWiki\SWS\EntryPoints\Source\PublishedSubjectsLookup( $this->context ) )->page( $title );
	}

	public function fields( Title $title, array $params, ?PageDependencyRecorder $dependencies = null, ?Subject $selected = null, ?Schema $selectedSchema = null ): array {
		$language = $params['language'];
		if ( preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $language ) !== 1 ) { throw new RuntimeException( 'Invalid data language' ); }
		$page = $this->page( $title );
		$id = $params['subject'] ?? $selected?->id->text;
		$subject = $id !== null ? ( is_string( $id ) && SubjectId::isValidLocalId( $id )
			? $page->getAllSubjects()->getSubject( new SubjectId( $id ) ) : null ) : $page->getMainSubject();
		$dependencies?->recordDependencyOn( $title );
		if ( $subject === null ) {
			return [ 'version' => 1, 'scope' => 'current', 'pageId' => $title->getArticleID(), 'mainSubjectId' => null, 'fields' => [] ];
		}
		$schema = $selectedSchema ?? SWSExtension::getInstance()->getSchemaResolverFor( $this->context->getAuthority() )->getSchema( $subject->getSchemaReference() );
		$dependencies?->recordDependencyOnSchema( $subject->getSchemaReference() );
		if ( $schema === null ) {
			return [ 'version' => 1, 'scope' => 'current', 'pageId' => $title->getArticleID(), 'mainSubjectId' => $subject->id->text, 'fields' => [] ];
		}
		$data = StoredSourceValues::read( $subject, $schema, $language, $title->getArticleID(), $this->fallbacks() );
		foreach ( $data['fields'] as &$field ) {
			if ( !$this->readableResult( $field['source'], $field ) ) {
				$field = [ 'name' => $field['name'], 'source' => $field['source'], 'propertyId' => $field['propertyId'],
					'propertyType' => $field['propertyType'], 'storageMode' => 'store', 'recorded' => true,
					'status' => 'unavailable', 'sourceStatus' => 'unavailable', 'reason' => 'source-not-readable', 'values' => [] ];
				continue;
			}
			// Links can be rebuilt from IDs locally when a wiki's URL configuration changes.
			$field['propertyUrl'] = $this->link( $field['source'], $field['propertyId'] );
			if ( isset( $field['entityId'] ) ) { $field['entityUrl'] = $this->link( $field['source'], $field['entityId'] ); }
			foreach ( $field['values'] as &$value ) {
				if ( ( $value['kind'] ?? null ) === 'entity' ) { $value['url'] = $this->link( $field['source'], $value['id'] ); }
			}
			unset( $value );
		}
		unset( $field );
		if ( $params['presentation'] ?? false ) {
			$data['presentation'] = GraphFieldPresentation::fromSchema( $schema, Title::newFromText( 'SWSGRAPHTITLETOKEN' )?->getFullURL(), $subject, $language );
		}
		return $data;
	}

	public function identities( Title $title, string $language ): array {
		if ( preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $language ) !== 1 ) { throw new RuntimeException( 'Invalid data language' ); }
		$subject = $this->page( $title )->getMainSubject();
		$storedLanguage = $subject !== null ? StoredSourceValues::language( $subject, $language, $this->fallbacks() ) : null;
		$stored = $storedLanguage !== null ? $subject?->getSourceSnapshot()?->forLanguage( $storedLanguage ) : null;
		$result = [];
		foreach ( [ 'wikidata', 'wikibase-local' ] as $source ) {
			$entry = $stored['identities'][$source] ?? null;
			$attempt = $entry['lastAttempt'] ?? null;
			$success = $entry['lastSuccess']['result'] ?? null;
			$identity = $success ?? $attempt ?? [ 'status' => 'unavailable', 'reason' => 'snapshot-not-available' ];
			if ( ( $identity['status'] ?? null ) === 'resolved' &&
				( !isset( $identity['id'] ) || !$this->readableResult( $source, [ 'entityId' => $identity['id'] ] ) ) ) {
				$result[$source] = [ 'source' => $source, 'status' => 'unavailable', 'recorded' => true, 'reason' => 'source-not-readable' ];
				continue;
			}
			$identity['source'] = $source;
			$identity['recorded'] = $entry !== null;
			$identity['storageMode'] = 'store';
			$identity['language'] = $storedLanguage;
			$identity['sourceStatus'] = $attempt['status'] ?? 'unavailable';
			$identity['lastAttemptedAt'] = $stored['attemptedAt'] ?? null;
			$identity['lastSuccessfulAt'] = $entry['lastSuccess']['at'] ?? null;
			$identity['retained'] = $success !== null && $identity['sourceStatus'] !== 'resolved';
			if ( ( $identity['status'] ?? null ) === 'resolved' ) {
				$identity['url'] = $this->link( $source, $identity['id'] );
				$description = $entry['description']['lastSuccess']['result'] ?? null;
				if ( $description !== null && ( $description['source'] !== $source || $description['id'] !== $identity['id'] ) ) { $description = null; }
				$text = $description['value'] ?? $identity['description'] ?? null;
				$descriptionLanguage = $description['language'] ?? $identity['descriptionLanguage'] ?? $storedLanguage;
				if ( is_array( $text ) ) { $descriptionLanguage = $text['language'] ?? $descriptionLanguage; $text = $text['value'] ?? null; }
				$identity['description'] = is_string( $text ) && trim( $text ) !== '' ? [ 'value' => $text, 'language' => $descriptionLanguage ] : null;
				$identity['descriptionStatus'] = $identity['description'] !== null ? 'resolved' : ( $identity['sourceStatus'] === 'resolved' ? 'missing' : 'unavailable' );
				$identity['descriptionSourceStatus'] = $entry['description']['lastAttempt']['status'] ??
					( $identity['sourceStatus'] !== 'resolved' ? $identity['sourceStatus'] : $identity['descriptionStatus'] );
				$identity['descriptionLastSuccessfulAt'] = $entry['description']['lastSuccess']['at'] ?? $entry['lastSuccess']['at'] ?? null;
				$identity['descriptionRetained'] = $identity['description'] !== null && $identity['descriptionSourceStatus'] !== 'resolved';
			} else {
				// Never expose identifiers hidden inside a failed or unauthorized result.
				$identity = array_intersect_key( $identity, array_flip( [ 'source', 'status', 'recorded', 'storageMode', 'reason', 'sourceStatus', 'lastAttemptedAt' ] ) );
			}
			$result[$source] = $identity;
		}
		return $result;
	}

	private function fallbacks(): array {
		return [ MediaWikiServices::getInstance()->getContentLanguage()->getCode(), ...SWSExtension::getInstance()->getDataLanguages() ];
	}

	private function link( string $source, string $id ): ?string {
		$key = "$source:$id";
		if ( !array_key_exists( $key, $this->access ) ) {
			try { $this->access[$key] = $this->links->url( $source, $id ); }
			catch ( Throwable ) { $this->access[$key] = null; }
		}
		return $this->access[$key];
	}

	private function readableResult( string $source, array $result ): bool {
		if ( $source === 'wikidata' ) { return true; }
		if ( $source !== 'wikibase-local' ) { return false; }
		$walk = function ( array $data ) use ( &$walk, $source ): bool {
			foreach ( $data as $key => $value ) {
				if ( preg_match( '/\AP[1-9][0-9]*\z/', (string)$key ) === 1 && $this->link( $source, (string)$key ) === null ) { return false; }
				if ( in_array( $key, [ 'id', 'entityId', 'propertyId', 'property' ], true ) && is_string( $value ) &&
					preg_match( '/\A[QP][1-9][0-9]*\z/', $value ) === 1 && $this->link( $source, $value ) === null ) { return false; }
				if ( is_array( $value ) && !$walk( $value ) ) { return false; }
			}
			return true;
		};
		return $walk( $result );
	}

}
