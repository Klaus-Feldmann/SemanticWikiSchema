<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\Property\WikitextValueProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;

/** All saved field metadata is staged before the existing complete-revision commit gate. */
final class UserWikitextProcessor {
    public function apply( Title $title, ParserOutput $output, PageSubjects $subjects, User $user,
        \Closure $resolveSchema, ?int $revisionId = null ): void {
        foreach ( $subjects->getAllSubjects()->asArray() as $subject ) {
            if ( !$subject->getSchemaReference()->isLocal() ) { continue; }
            $schema = $resolveSchema( $subject->getSchemaReference() );
            foreach ( $schema->getAllProperties()->asMap() as $name => $property ) {
                if ( $property->isSchemaExecution() ) { continue; }
                $values = [];
                if ( $property instanceof WikitextValueProperty ) {
                    $statement = $subject->getStatements()->getStatement( new PropertyName( $name ) );
                    $values = $statement?->getValue()->toScalars() ?? [];
                }
                if ( ( $property->getFieldNotes()['type'] ?? null ) === 'wikitext' ) {
                    $note = $subject->getFieldNotes()[$name] ?? '';
                    if ( trim( $note ) !== '' ) { $values[] = $note; }
                }
                if ( $values === [] ) { continue; }
                $rendered = ( new UserWikitextRenderer() )->render( $title, $values, $user, $revisionId );
                if ( ProcessingContext::current()?->failure !== null ) {
                    throw new ProcessingIncomplete( 'SWS: Subject wikitext failed to parse; previous derived data retained.' );
                }
                // Source/template sort keys take priority when the same category is repeated.
                $existingCategories = $output->getCategoryMap();
                $existingSemantic = $output->getExtensionData( 'smwdata' );
                $fieldSemantic = $rendered->getExtensionData( 'smwdata' );
                $output->mergeTrackingMetaDataFrom( $rendered );
                $output->mergeHtmlMetaDataFrom( $rendered );
                foreach ( $existingCategories as $category => $sort ) { $output->addCategory( (string)$category, $sort ); }
                if ( $existingSemantic !== null ) {
                    if ( $fieldSemantic !== null ) { $existingSemantic->importDataFrom( $fieldSemantic ); }
                    $output->setExtensionData( 'smwdata', $existingSemantic );
                } elseif ( $fieldSemantic !== null ) { $output->setExtensionData( 'smwdata', $fieldSemantic ); }
            }
        }
    }
}
