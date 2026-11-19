<?php

declare(strict_types=1);
namespace AEWI\SWS\EntryPoints\Semantic;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use AEWI\SWS\Application\PageDependencyRecorder;
use AEWI\SWS\Domain\Schema\SchemaReference;
use AEWI\SWS\SWSExtension;

/** Full revision output is a merge of slot outputs, not necessarily Parser::getOutput(). */
final class RevisionDependencies implements PageDependencyRecorder {
    public function __construct(private Title $current, private ParserOutput $output) {}
    public function recordDependencyOn(Title $page): void {
        if ($page->canExist() && !$page->isSamePageAs($this->current)) {
            $this->output->addTemplate($page,$page->getArticleID(),$page->getLatestRevID());
        }
    }
    public function recordDependencyOnSchema(SchemaReference $schema): void {
        $title=$schema->isLocal() ? Title::makeTitleSafe(SWSExtension::NS_SCHEMA,$schema->name->getText()) : null;
        if ($title!==null) { $this->recordDependencyOn($title); }
    }
}
