<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\EntryPoints\Semantic;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\SWS\Application\Semantic\{AnnotationPlan,AnnotationPlanner,FieldSnapshot};
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIncomplete;

/** Called only inside the authorized full revision worker, never on the anonymous view path. */
final class AutomaticAnnotations {
    public function apply(Title $title, ParserOutput $output, ?Parser $parser, Schema $schema, PageSubjects $pageSubjects, Subject $subject, User $user, \Closure $resolveSchema): void {
        // Non-wikitext handlers do not have to invoke the wikitext parser. Their full
        // rendered revision and authorized subject snapshot are validated by the worker.
        if ($parser === null && $title->getContentModel() === 'wikitext'
            || $parser !== null && !$parser->getTitle()->isSamePageAs($title)) {
            throw new ProcessingIncomplete('SemanticWikiSchema: missing full revision parser context.');
        }
        $recorder = new RevisionDependencies($title,$output);
        $context = new DerivativeContext(RequestContext::getMain());
        $context->setUser($user);
        $language = MediaWikiServices::getInstance()->getContentLanguage()->getCode();
        $config = (new WikiAnnotationConfig())->read($parser,$output,$user);
        $planner = new AnnotationPlanner($config);
        $subjects = $pageSubjects->getAllSubjects();
        $pending = [[$subject, $schema]];
        $visited = [$subject->id->text => true];
        $rows = [];
        $objects = [];
        for ($index = 0; $index < count($pending); $index++) {
            [$current, $currentSchema] = $pending[$index];
            $recorder->recordDependencyOnSchema($current->getSchemaReference());
            $reader = FieldSnapshot::reader($currentSchema,$current,static function () use ($title,$current,$currentSchema,$language,$context,$recorder): array {
                return \ProfessionalWiki\SWS\EntryPoints\Source\SourceFieldLookup::read(
                    $context, $title, $current, $currentSchema, $language, $recorder
                );
            });
            $snapshot = $reader->collectForAnnotations($language, \ProfessionalWiki\SWS\Application\Source\SourceDataMode::usesStore());
            $subjectPlan = $planner->build($snapshot);
            foreach ( ( new \ProfessionalWiki\SWS\Application\Semantic\FieldNoteAnnotations() )->build( $snapshot, $config ) as $row ) { $rows[$row] = true; }
            foreach ($subjectPlan->rows as $row) {
                $rows[$row] = true;
            }
            array_push($objects, ...$subjectPlan->objects);

            foreach ($current->getReferencedSubjects()->asArray() as $targetId) {
                if (isset($visited[$targetId->text])) {
                    continue;
                }
                $visited[$targetId->text] = true;
                $target = $subjects->getSubject($targetId);
                if ($target === null || !$target->getSchemaReference()->isLocal()) {
                    continue;
                }
                $targetSchema = $resolveSchema($target->getSchemaReference());
                if ($targetSchema->subjectsAreDependent()) {
                    $pending[] = [$target, $targetSchema];
                }
            }
        }
        $plan = new AnnotationPlan(array_keys($rows), $objects);
        // Track property-definition dependencies, including not-yet-created definitions.
        $names = [];
        foreach ($plan->rows as $row) { $names[explode('=',$row,2)[0]] = true; }
        foreach ($plan->objects as $args) { foreach (array_slice($args,1) as $row) { $names[explode('=',$row,2)[0]] = true; } }
        foreach (array_keys($names) as $name) {
            $propertyTitle = Title::makeTitleSafe(SMW_NS_PROPERTY,$name);
            if ($propertyTitle !== null) { $recorder->recordDependencyOn($propertyTitle); }
        }
        (new SmwAnnotationApplier())->apply($title,$output,$plan);
    }
}
