<?php
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\EntryPoints\Processing\ParserFailureCheck as Check;
final class ParserFailureCheckTest extends TestCase {
    public function testGermanAndEnglishCoreWarning(): void {
        foreach ([
            ['Achtung: Der Sortierungsschlüssel „'.Check::NEW_KEY.'“ überschreibt den vorher verwendeten Schlüssel „'.Check::OLD_KEY.'“.','Achtung: Der Sortierungsschlüssel „Gödel_Kurt“ überschreibt den vorher verwendeten Schlüssel „Godel, Kurt“.'],
            ['Warning: Default sort key "'.Check::NEW_KEY.'" overrides earlier default sort key "'.Check::OLD_KEY.'".','Warning: Default sort key &quot;Gödel_Kurt&quot; overrides earlier default sort key &quot;Godel, Kurt&quot;.'],
        ] as [$template,$message]) {
            $html='<p><span class="error">'.$message.'</span></p>';
            self::assertFalse(Check::hasFailure($html,$template));
            self::assertTrue(Check::hasFailure($html,null));
            self::assertTrue(Check::hasFailure($html.'<span class="error">Another failure</span>',$template));
            self::assertTrue(Check::hasFailure($html.'<strong class="error"><span class="scribunto-error">Lua failed</span></strong>',$template));
        }
    }
    public function testOtherErrorsRemainBlocked(): void {
        foreach (['<span class="error">Unknown problem</span>',"<strong class='error'>Source failure</strong>",'<span class="scribunto-error mw-scribunto-error-x">Lua</span>'] as $html) {
            self::assertTrue(Check::hasFailure($html,'Warning '.Check::OLD_KEY.' / '.Check::NEW_KEY));
        }
        self::assertFalse(Check::hasFailure('<p>Ordinary content</p>',null));
    }
    public function testIncompleteOrChangedTemplateCannotWhitelist(): void {
        self::assertTrue(Check::hasFailure('<span class="error">Unknown problem</span>',Check::OLD_KEY));
        self::assertTrue(Check::hasFailure('<span class="error"><span>Warning</span></span>','Warning '.Check::OLD_KEY.' / '.Check::NEW_KEY));
    }
}
