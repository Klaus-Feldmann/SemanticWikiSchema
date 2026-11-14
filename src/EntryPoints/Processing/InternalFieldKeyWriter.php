<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Schema\FieldKeyChanges;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\SWSExtension;
use Wikimedia\Rdbms\IDBAccessObject;

/** Only advances schema-declared key changes, using the current CAS parent and read-only actor. */
final class InternalFieldKeyWriter {
    public function apply( Title $title ): void {
        if ( ProcessingContext::current() !== null ) { throw new ProcessingIncomplete( 'Recursive field-key transfer refused' ); }
        $services = MediaWikiServices::getInstance();
        $revision = $services->getRevisionLookup()->getRevisionByTitle( $title, 0, IDBAccessObject::READ_LATEST );
        if ( !$revision || !$revision->hasSlot( 'sws' ) ) { return; }
        $user = ProcessingIdentity::get();
        $policy = SWSExtension::getInstance()->getRevisionPolicy();
        if ( !$user->authorizeRead( 'read', $title ) || !$revision->userCan( RevisionRecord::DELETED_TEXT, $user ) ||
            !$policy->revisionIsReadableBy( $revision, $user ) || $policy->publishedRevision( $revision )?->getId() !== $revision->getId() ) {
            throw new ProcessingIncomplete( 'Field-key transfer revision is not publishable', 'pending' );
        }
        $content = $revision->getContent( 'sws', RevisionRecord::FOR_THIS_USER, $user );
        if ( !$content instanceof SubjectContent ) { throw new ProcessingIncomplete( 'Field-key transfer requires a readable subject slot' ); }
        $raw = json_decode( $content->getText(), true, 512, JSON_THROW_ON_ERROR );
        $subjects = $content->getPageSubjects();
        if ( !is_array( $raw ) || !is_array( $raw['subjects'] ?? null ) || count( $raw['subjects'] ) !== count( $subjects->getAllSubjects()->asArray() ) ) {
            throw new ProcessingIncomplete( 'Incomplete subject slot; field-key transfer refused' );
        }
        $next = $raw; $schemaRevisions = []; $schemas = [];
        foreach ( $subjects->getAllSubjects()->asArray() as $subject ) {
            $ref = $subject->getSchemaReference();
            if ( !$ref->isLocal() ) { throw new ProcessingIncomplete( 'Remote field-key transfer is unsupported' ); }
            $key = json_encode( $ref->toJson(), JSON_THROW_ON_ERROR );
            if ( !isset( $schemas[$key] ) ) {
            $schemaTitle = Title::makeTitleSafe( SWSExtension::NS_SCHEMA, $ref->name->getText() );
            if ( !$schemaTitle || !$user->authorizeRead( 'read', $schemaTitle ) ) { throw new ProcessingIncomplete( 'Field-key schema is unreadable', 'denied' ); }
            $schemaRevision = $services->getRevisionLookup()->getRevisionByTitle( $schemaTitle, 0, IDBAccessObject::READ_LATEST );
            if ( !$schemaRevision || !$schemaRevision->userCan( RevisionRecord::DELETED_TEXT, $user ) || !$policy->revisionIsReadableBy( $schemaRevision, $user ) || $policy->publishedRevision( $schemaRevision )?->getId() !== $schemaRevision->getId() ) {
                throw new ProcessingIncomplete( 'Field-key schema is not publishable', 'pending' );
            }
            $schemaContent = $schemaRevision->getContent( 'main', RevisionRecord::FOR_THIS_USER, $user );
            if ( !$schemaContent instanceof \ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent ) { throw new ProcessingIncomplete( 'Invalid field-key schema content' ); }
            $schema = SWSExtension::getInstance()->getPersistenceSchemaDeserializer()->deserialize( $ref->name, $schemaContent->getText() );
            $schemaRevisions[$key] = [ $schemaTitle, $schemaRevision->getId() ];
            $schemas[$key] = $schema;
            }
            $schema = $schemas[$key];
            $id = $subject->id->text;
            $next['subjects'][$id] = FieldKeyChanges::transfer( $raw['subjects'][$id], $schema->getFieldKeyChanges() );
        }
        if ( $next === $raw ) { return; }
        if ( $services->getReadOnlyMode()->getReason() !== false ) { throw new ProcessingIncomplete( 'Field-key transfer paused: read-only', 'pending' ); }
        // A changed slot needs a fresh source-processing receipt, not the prior revision's seal.
        unset( $next['sourceRefresh'] );
        $decoded = new SubjectContent( json_encode( $next, JSON_THROW_ON_ERROR ) );
        $canonical = json_decode( SubjectContent::newFromData( $decoded->getPageSubjects() )->getText(), false, 512, JSON_THROW_ON_ERROR );
        $envelope = json_decode( $content->getText(), false, 512, JSON_THROW_ON_ERROR );
        $envelope->subjects = $canonical->subjects;
        unset( $envelope->sourceRefresh );
        $updated = new SubjectContent( json_encode( $envelope, JSON_THROW_ON_ERROR ) );
        $updater = $services->getWikiPageFactory()->newFromTitle( $title )->newPageUpdater( $user );
        $parent = $updater->grabParentRevision();
        if ( !$parent || $parent->getId() !== $revision->getId() || $parent->getSha1() !== $revision->getSha1() ) {
            throw new ProcessingIncomplete( 'Field-key transfer base changed', 'superseded' );
        }
        foreach ( $schemaRevisions as [ $schemaTitle, $id ] ) {
            if ( $services->getRevisionLookup()->getRevisionByTitle( $schemaTitle, 0, IDBAccessObject::READ_LATEST )?->getId() !== $id ) {
                throw new ProcessingIncomplete( 'Field-key schema changed during transfer', 'superseded' );
            }
        }
        SourceWriteScope::run( $title, $updated->getPageSubjects(), static function () use ( $updater, $updated ): void {
            $updater->setContent( 'sws', $updated );
            $updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Transfer SWS field keys' ), EDIT_UPDATE | EDIT_INTERNAL );
            if ( !$updater->wasSuccessful() ) { throw new ProcessingIncomplete( 'Field-key transfer could not be saved', 'superseded' ); }
        } );
    }
}
