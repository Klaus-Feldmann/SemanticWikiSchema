<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints;

use MediaWiki\Context\IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\PageDependencyRecorder;
use ProfessionalWiki\SWS\Application\Semantic\FieldSnapshot;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\EntryPoints\Source\{ PublishedSubjectsLookup, SourceFieldLookup };
use ProfessionalWiki\SWS\SWSExtension;

/** Bounded reads of one local entity; no scans, writes, or independent source requests. */
final class EffectiveFieldLookup {
	private PublishedSubjectsLookup $published;
	private array $readers = [];

	public function __construct( private readonly IContextSource $context, private readonly ?PageDependencyRecorder $dependencies = null,
		private readonly ?\Closure $relationLabel = null, private readonly ?\Closure $canonicalId = null ) {
		$this->published = new PublishedSubjectsLookup( $context );
	}

	public function read( Title $currentTitle, array $options = [] ): array {
		foreach ( $options as $key => $value ) {
			if ( !in_array( $key, [ 'page', 'subject', 'field', 'language', 'origin', 'includeImages' ], true ) ||
				( $key === 'includeImages' ? !is_bool( $value ) : !is_string( $value ) ) ) {
				throw new \InvalidArgumentException( 'Invalid field lookup option' );
			}
		}
		if ( isset( $options['page'], $options['subject'] ) ) { throw new \InvalidArgumentException( 'Specify page or subject, not both' ); }
		$language = $options['language'] ?? MediaWikiServices::getInstance()->getContentLanguage()->getCode();
		if ( preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $language ) !== 1 ) { throw new \InvalidArgumentException( 'Invalid data language' ); }
		if ( !in_array( $options['origin'] ?? 'all', [ 'all', 'own', 'external' ], true ) ) { throw new \InvalidArgumentException( 'Invalid origin' ); }
		$extension = SWSExtension::getInstance();
		$title = isset( $options['page'] ) ? Title::newFromText( $options['page'] ) : $currentTitle;
		$id = null;
		if ( isset( $options['subject'] ) ) {
			if ( !SubjectId::isValidLocalId( $options['subject'] ) ) { return $this->missing(); }
			$id = new SubjectId( $options['subject'] );
            if ( $this->canonicalId !== null ) { $id = ( $this->canonicalId )( $id ); }
			$host = $extension->newSubjectHostingPageResolver( $this->context->getAuthority() )->resolveReadableHostingPage( $id );
			if ( $host === null ) { return $this->missing(); }
			$title = Title::newFromID( $host->getId()->id );
		}
		if ( $title === null || $title->isExternal() || !$title->canExist() ) { throw new \InvalidArgumentException( 'Invalid lookup page' ); }
		if ( !$this->context->getAuthority()->authorizeRead( 'read', $title ) ) { return $this->missing(); }
		$this->dependencies?->recordDependencyOn( $title );
		if ( $title->getArticleID() === 0 ) { return $this->missing(); }
		$page = $this->published->page( $title );
		$subject = $id !== null ? $page->getAllSubjects()->getSubject( $id ) : $page->getMainSubject();
		if ( $subject === null ) { return $this->missing(); }
		// This interface reads the local stock. Sourced schemas retain their separate legacy interfaces.
		if ( !$subject->getSchemaReference()->isLocal() ) { throw new \RuntimeException( 'Local field lookup requires a local schema' ); }
		$schema = $extension->getSchemaResolverFor( $this->context->getAuthority() )->getSchema( $subject->getSchemaReference() );
		$this->dependencies?->recordDependencyOnSchema( $schema?->getReference() ?? $subject->getSchemaReference() );
		if ( $schema === null ) { throw new \RuntimeException( 'Schema unavailable' ); }
        if ( isset($options['field']) && $schema->getId() !== null && !array_key_exists($options['field'],$schema->getAllProperties()->asMap()) ) {
            \ProfessionalWiki\SWS\Application\IdentityMigration\MigrationFieldRepairScope::record(
                $schema->getId()->text,$options['field'],$title->getPrefixedText(),$currentTitle->getPrefixedText()
            );
        }
		$key = json_encode( [ $title->getArticleID(), $subject->id->text, $language ], JSON_THROW_ON_ERROR );
		if ( !isset( $this->readers[$key] ) ) {
			$display = function ( string $name ) use ( $subject ): array {
				$value = $subject->getStatements()->getStatement( new \ProfessionalWiki\SWS\Domain\Schema\PropertyName( $name ) )?->getValue();
				if ( $value instanceof \ProfessionalWiki\SWS\Domain\Value\MonolingualTextValue ) { return $value->getTexts(); }
				if ( $value instanceof \ProfessionalWiki\SWS\Domain\Value\RelationValue ) {
					return array_map( fn ( $relation ) => $this->relationLabel !== null
						? ( $this->relationLabel )( $relation ) : $this->relationName( $relation->targetId ), $value->relations );
				}
				return [];
			};
			$this->readers[$key] = FieldSnapshot::reader( $schema, $subject,
				fn (): array => SourceFieldLookup::read( $this->context, $title, $subject, $schema, $language, $this->dependencies, $this->published ), $display );
		}
		$result = $this->readers[$key]->collect( $options['field'] ?? null, $options['origin'] ?? 'all', $language, $options['includeImages'] ?? false );
		foreach ( $result['fields'] as &$field ) {
			$field['sourceNote'] = \ProfessionalWiki\SWS\Application\Semantic\FieldNoteAnnotations::sourceText( $field );
		}
		unset( $field );
		$result['subject'] += ( $subject->getIdentity()?->toJson() ?? [] );
        $result['schemaIdentity'] = $schema->getId() === null ? null : [ 'id'=>$schema->getId()->text ] + $schema->getIdentity()->toJson();
        $result['subject']['label'] = $subject->getIdentity()?->label( $language ) ?? \ProfessionalWiki\SWS\Domain\Subject\SubjectDisplayName::forSubject( $subject, $page, $title->getPrefixedText() );
		$result['subject']['storedLabel'] = $subject->getLabel()?->text;
		$result['subject']['isMainSubject'] = $page->isMainSubject( $subject->id );
		$result['sourceDataMode'] = \ProfessionalWiki\SWS\Application\Source\SourceDataMode::current();
		$result['page'] = [ 'id' => $title->getArticleID(), 'title' => $title->getPrefixedText() ];
		return $result;
	}

	private function relationName( SubjectId $id ): string {
		$host = SWSExtension::getInstance()->newSubjectHostingPageResolver( $this->context->getAuthority() )->resolveReadableHostingPage( $id );
		$title = $host !== null ? Title::newFromID( $host->getId()->id ) : null;
		if ( $title === null ) { return ''; }
		try {
			$this->dependencies?->recordDependencyOn( $title );
			$page = $this->published->page( $title );
			$target = $page->getAllSubjects()->getSubject( $id );
			return $target === null ? '' : \ProfessionalWiki\SWS\Domain\Subject\SubjectDisplayName::forSubject( $target, $page, $title->getPrefixedText() );
		} catch ( \RuntimeException ) { return ''; }
	}

	private function missing(): array { return [ 'version' => 1, 'readOnly' => true, 'missing' => true, 'fields' => [] ]; }
}
