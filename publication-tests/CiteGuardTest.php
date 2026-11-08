<?php
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\EntryPoints\Processing\ParserFailureCheck as Check;
final class CiteGuardTest extends TestCase {
    public function testActualComteCiteMessageDoesNotBlockIndependentData(): void {
        $html = '<span class="reference-text">Quelle <span class="error mw-ext-cite-error" lang="de" dir="ltr">Referenzfehler: Unbekannter Parameter „details“ im <code>&lt;ref&gt;</code>-Element. Die unterstützten Parameter sind: dir, follow, group, name.</span></span>';
        self::assertFalse(Check::hasFailure($html, null));
        self::assertTrue(Check::hasFailure($html . '<strong class="error"><span class="scribunto-error">Source failed</span></strong>', null));
        self::assertTrue(Check::hasFailure($html . '<span class="error">Other failure</span>', null));
    }
    public function testClassOrderQuotesAndMultipleCiteWarnings(): void {
        self::assertFalse(Check::hasFailure("<span lang='en' class='mw-ext-cite-error other error'>Invalid ref <code>x</code></span><span class=\"error mw-ext-cite-error\">Other citation error</span>", null));
    }
    public function testSimilarUnknownClassesAndNestedErrorsRemainBlocked(): void {
        foreach ([
            '<span class="error mw-ext-cite-error-other">Unknown</span>',
            '<span class="error mw-ext-cite-error"><strong class="error">Nested error</strong></span>',
            '<span class="error mw-ext-cite-error"><span>Nested span</span></span>',
            '<span class="error mw-ext-cite-error"><span class="scribunto-error">Lua</span></span>',
        ] as $html) { self::assertTrue(Check::hasFailure($html, null)); }
    }
}
