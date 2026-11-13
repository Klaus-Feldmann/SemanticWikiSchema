<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\LocalPageTitle;
use ProfessionalWiki\SWS\Domain\Schema\Property\PageProperty;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Subject\Subject;

/** Adds selected categories to a fresh full-revision output, never editing source wikitext. */
final class CategoryAssignments {
    private const string INVALID_CATEGORY = 'SWS Migration Kategorie ungültig';
    public function apply( ParserOutput $output, Schema $schema, PageSubjects $subjects,
        Subject $main, \Closure $resolveSchema ): void {
        $pending = [ [ $main, $schema ] ];
        $visited = [ $main->id->text => true ];
        $categories = [];
        for ( $i = 0; $i < count( $pending ); $i++ ) {
            [ $subject, $currentSchema ] = $pending[$i];
            foreach ( $currentSchema->getAllProperties()->asMap() as $name => $property ) {
                if ( !$property instanceof PageProperty || !$property->assignsCategories() ) {
                    continue;
                }
                $statement = $subject->getStatements()->getStatement( new \ProfessionalWiki\SWS\Domain\Schema\PropertyName( $name ) );
                $values = $statement?->getValue()->toScalars() ?? [];
                $values = array_values( array_filter( $values, static fn ( $value ): bool => !is_string( $value ) || trim( $value ) !== '' ) );
                // Inherit dynamically; never copy schema defaults into author-owned statements.
                if ( $values === [] ) { $values = $property->getDefault() ?? []; }
                foreach ( $values as $text ) {
                    $normalized = is_string( $text ) ? LocalPageTitle::normalize( $text ) : null;
                    $category = $normalized === null ? null : Title::newFromText( $normalized );
                    if ( $category === null || $category->getNamespace() !== 14 ) {
                        // Preserve the source value for later cleanup, but never execute it
                        // as a category. A maintenance category keeps imported legacy errors visible.
                        $maintenance = Title::makeTitleSafe( 14, self::INVALID_CATEGORY );
                        if ( $maintenance === null ) { throw new \LogicException( 'Invalid SWS maintenance category title' ); }
                        $categories[$maintenance->getDBkey()] = true;
                        continue;
                    }
                    $categories[$category->getDBkey()] = true;
                }
            }
            foreach ( $subject->getReferencedSubjects()->asArray() as $id ) {
                if ( isset( $visited[$id->text] ) ) { continue; }
                $visited[$id->text] = true;
                $target = $subjects->getAllSubjects()->getSubject( $id );
                if ( $target === null || !$target->getSchemaReference()->isLocal() ) { continue; }
                $targetSchema = $resolveSchema( $target->getSchemaReference() );
                if ( $targetSchema->subjectsAreDependent() ) { $pending[] = [ $target, $targetSchema ]; }
            }
        }
        // Preserve the source/template sort key for an already present category.
        $existing = array_fill_keys( $output->getCategoryNames(), true );
        foreach ( array_keys( $categories ) as $name ) {
            if ( !isset( $existing[$name] ) ) { $output->addCategory( $name, '' ); }
        }
    }
}
