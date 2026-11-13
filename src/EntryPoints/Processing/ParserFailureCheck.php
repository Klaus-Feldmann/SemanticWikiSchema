<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

/** @deprecated Legacy helper; no longer used to decide whether SWS may publish data.
 * Confirmed core sort-key warnings and Cite reference messages are not failed data computations.
 * Unknown errors remain blocked. This does not alter the HTML or hide the warning.
 */
final class ParserFailureCheck {
    public const OLD_KEY = 'SWSOLDKEY7CB32';
    public const NEW_KEY = 'SWSNEWKEY9AD46';

    public static function hasFailure(string $html, ?string $sortWarningTemplate): bool {
        // Never whitelist an actual Scribunto error, even if it appears inside other markup.
        if (preg_match('/class=["\x27][^"\x27]*\bscribunto-error\b/i',$html)) { return true; }
        // Cite reports article reference syntax problems with this dedicated class.
        // These messages do not invalidate independently prepared semantic values.
        // Only a leaf span with the exact Cite class is ignored; nested/unknown errors stay blocked.
        $html = preg_replace_callback(
            '/<span\b[^>]*\bclass=(["\x27])([^"\x27]*)\1[^>]*>((?:(?!<\/?span\b).)*?)<\/span\s*>/is',
            static function (array $match): string {
                $classes = preg_split('/\s+/', trim($match[2])) ?: [];
                if (!in_array('error', $classes, true) || !in_array('mw-ext-cite-error', $classes, true)) {
                    return $match[0];
                }
                if (preg_match('/class=["\x27][^"\x27]*\b(?:error|scribunto-error)\b/i', $match[3])) {
                    return $match[0];
                }
                return '';
            },
            $html
        ) ?? $html;
        if ($sortWarningTemplate !== null && substr_count($sortWarningTemplate,self::OLD_KEY)===1 && substr_count($sortWarningTemplate,self::NEW_KEY)===1) {
            $plain=static fn(string $value): string => html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8');
            $pattern='/\A'.str_replace([self::OLD_KEY,self::NEW_KEY],['.+?','.+?'],preg_quote($plain($sortWarningTemplate),'/')).'\z/us';
            // No nested spans: an unrecognized or structurally changed warning stays conservative.
            $html=preg_replace_callback('/<span\b[^>]*\bclass=["\x27]error["\x27][^>]*>((?:(?!<\/?span\b).)*?)<\/span\s*>/is',
                static fn(array $m): string => preg_match($pattern,$plain($m[1]))===1 ? '' : $m[0],$html) ?? $html;
        }
        return preg_match('/class=["\x27][^"\x27]*\b(?:error|scribunto-error)\b/i',$html)===1;
    }
}
