<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SubjectSlotReader;

/** A signed storage receipt, not an authorization to publish parser or SMW output. */
final class SourceRefreshReceipt {

	public const KEY = 'sourceRefresh';

	public static function attach( SubjectContent $content, ProcessingResult $preparation ): SubjectContent {
		$raw = json_decode( $content->getText(), true, 512, JSON_THROW_ON_ERROR );
		unset( $raw[self::KEY] );
		$receipt = [ 'version' => 1, 'kind' => 'sws-source-refresh', 'page' => $preparation->title->getArticleID(),
			'parent' => $preparation->revision->getId(), 'parentSha1' => $preparation->revision->getSha1(),
			'dependencies' => $preparation->stamp['dependencies'], 'languages' => SourceSnapshotCapture::languages(),
			'contentHash' => hash( 'sha256', json_encode( $raw, JSON_THROW_ON_ERROR ) ) ];
		$raw[self::KEY] = ProcessingResult::seal( $receipt );
		return new SubjectContent( json_encode( $raw, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR ) );
	}

    /** Only the maintenance writer may attest an already-proved reference-only migration. */
    public static function attachMigrated(Title $title,RevisionRecord $parent,SubjectContent $next,array $dependencies):SubjectContent {
        if (!\ProfessionalWiki\SWS\Application\IdentityMigration\MigrationWriteScope::active() || !self::isCurrent($title,$parent) || !ProcessingResult::dependenciesCurrent($dependencies)) {
            throw new ProcessingIncomplete('Unverified migration source basis');
        }
        // Preserve object/list distinctions from the signed migration plan. The receipt hash
        // keeps its existing associative-array contract so earlier valid receipts remain readable.
        $raw=\ProfessionalWiki\SWS\Application\IdentityMigration\ReferenceRewriter::object($next->getText());unset($raw->{self::KEY});
        $hashData=json_decode($next->getText(),true,512,JSON_THROW_ON_ERROR);unset($hashData[self::KEY]);
        $receipt=['version'=>1,'kind'=>'sws-source-refresh','page'=>$title->getArticleID(),'parent'=>$parent->getId(),'parentSha1'=>$parent->getSha1(),
            'dependencies'=>$dependencies,'languages'=>SourceSnapshotCapture::languages(),'contentHash'=>hash('sha256',json_encode($hashData,JSON_THROW_ON_ERROR))];
        $raw->{self::KEY}=ProcessingResult::seal($receipt);
        return new SubjectContent(json_encode($raw,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    }

    /** Carries a verified source basis through a metadata-only CAS edit. No new source result is invented. */
    public static function carryMetadata( \MediaWiki\Title\Title $title, RevisionRecord $parent, SubjectContent $next ): SubjectContent {
        if ( !\ProfessionalWiki\SWS\Application\Source\SourceDataMode::usesStore() || !self::isCurrent( $title, $parent ) ) { return $next; }
        $previous = SubjectSlotReader::read( $parent );
        if ( $previous === null || \ProfessionalWiki\SWS\Application\Identity\IdentityChangeClassifier::subjects( $previous->getText(), $next->getText() ) !== 'metadata' ) { return $next; }
        $old = json_decode( $previous->getText(), true, 512, JSON_THROW_ON_ERROR );
        $raw = json_decode( $next->getText(), true, 512, JSON_THROW_ON_ERROR );
        unset( $raw[self::KEY] );
        $receipt = $old[self::KEY];
        unset( $receipt['signature'] );
        $receipt['parent'] = $parent->getId();
        $receipt['parentSha1'] = $parent->getSha1();
        $receipt['contentHash'] = hash( 'sha256', json_encode( $raw, JSON_THROW_ON_ERROR ) );
        $raw[self::KEY] = ProcessingResult::seal( $receipt );
        return new SubjectContent( json_encode( $raw, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR ) );
    }

    /** Full processing seals still bind exact revisions; only source reuse may tolerate schema metadata. */
    private static function sourceDependenciesCurrent( array $dependencies ): bool {
        $lookup = MediaWikiServices::getInstance()->getRevisionLookup();
        foreach ( $dependencies as $dependency ) {
            $title = Title::makeTitleSafe( $dependency['namespace'], $dependency['title'] );
            if ( $title === null ) { return false; }
            $current = $lookup->getRevisionByTitle( $title, 0, \Wikimedia\Rdbms\IDBAccessObject::READ_LATEST );
            if ( ( $current?->getId() ?? 0 ) === $dependency['revision'] ) { continue; }
            $old = $lookup->getRevisionById( $dependency['revision'] );
            if ( $current === null || $old === null || $old->getPageId() !== $current->getPageId() ||
                $title->getNamespace() !== \ProfessionalWiki\SWS\SWSExtension::NS_SCHEMA ) { return false; }
            $user = ProcessingIdentity::get();
            $policy = \ProfessionalWiki\SWS\SWSExtension::getInstance()->getRevisionPolicy();
            if ( !$user->authorizeRead( 'read', $title ) || !$old->userCan( RevisionRecord::DELETED_TEXT, $user ) ||
                !$current->userCan( RevisionRecord::DELETED_TEXT, $user ) || !$policy->revisionIsReadableBy( $old, $user ) ||
                !$policy->revisionIsReadableBy( $current, $user ) || $policy->publishedRevision( $current )?->getId() !== $current->getId() ) { return false; }
            $a = $old->getContent( 'main', RevisionRecord::FOR_THIS_USER, $user );
            $b = $current->getContent( 'main', RevisionRecord::FOR_THIS_USER, $user );
            if ( !$a instanceof \ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent || !$b instanceof \ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent ||
                !in_array( \ProfessionalWiki\SWS\Application\Identity\IdentityChangeClassifier::schema( $a->getText(), $b->getText() ), ['metadata','unchanged'], true ) ) { return false; }
            // Other slots (for example associated executable code) are never presentation metadata.
            $roles = $old->getSlotRoles(); $newRoles = $current->getSlotRoles(); sort($roles); sort($newRoles);
            if ( $roles !== $newRoles ) { return false; }
            foreach ( $roles as $role ) { if ( $role !== 'main' && $old->getSlot($role)->getSha1() !== $current->getSlot($role)->getSha1() ) { return false; } }
        }
        return true;
    }

	public static function isCurrent( Title $title, ?RevisionRecord $revision ): bool {
		if ( $revision === null ) { return false; }
		try {
			$content = SubjectSlotReader::read( $revision );
			if ( $content === null ) { return false; }
			$raw = json_decode( $content->getText(), true, 512, JSON_THROW_ON_ERROR );
			$receipt = $raw[self::KEY] ?? null;
			if ( !is_array( $receipt ) || ( $receipt['version'] ?? null ) !== 1 || ( $receipt['kind'] ?? null ) !== 'sws-source-refresh' ||
				!is_string( $receipt['signature'] ?? null ) || ( $receipt['page'] ?? null ) !== $title->getArticleID() ||
				( $receipt['parent'] ?? null ) !== $revision->getParentId() || !is_array( $receipt['dependencies'] ?? null ) ||
				( $receipt['languages'] ?? null ) !== SourceSnapshotCapture::languages() ) { return false; }
			$signature = $receipt['signature']; unset( $receipt['signature'], $raw[self::KEY] );
			if ( !hash_equals( ProcessingResult::seal( $receipt )['signature'], $signature ) ||
				!hash_equals( $receipt['contentHash'], hash( 'sha256', json_encode( $raw, JSON_THROW_ON_ERROR ) ) ) ) { return false; }
			$lookup = MediaWikiServices::getInstance()->getRevisionLookup();
			$parent = $lookup->getRevisionById( $receipt['parent'] );
			if ( $parent === null || $parent->getSha1() !== $receipt['parentSha1'] || $parent->getPageId() !== $revision->getPageId() ) { return false; }
			// A copied receipt on a revision that also changes article/module content is invalid.
			$roles = $revision->getSlotRoles(); $oldRoles = $parent->getSlotRoles(); sort( $roles ); sort( $oldRoles );
			if ( $roles !== $oldRoles ) { return false; }
			foreach ( $roles as $role ) {
				if ( $role !== 'sws' && $revision->getSlot( $role )->getSha1() !== $parent->getSlot( $role )->getSha1() ) { return false; }
			}
			return self::sourceDependenciesCurrent( $receipt['dependencies'] );
		} catch ( \Throwable ) { return false; }
	}

}
