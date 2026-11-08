<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\Semantic;

/** Immutable plan only. Integration into ParserOutput deliberately belongs to pass B. */
final readonly class AnnotationPlan {
    public function __construct(public array $rows, public array $objects) {}
}
