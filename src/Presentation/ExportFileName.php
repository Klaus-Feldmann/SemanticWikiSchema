<?php

declare(strict_types=1);
namespace AEWI\SWS\Presentation;

/** Only the human-readable filename stem is configurable; IDs and format suffixes stay technical. */
final class ExportFileName {
    public static function forDataView(?string $entityId, int $pageId): string {
        $key = $entityId === null ? 'sws-export-page-filename' : 'sws-export-entity-filename';
        $fallback = $entityId === null ? 'sws-page-$1-data-view' : 'sws-entity-$1-data-view';
        return self::fromTemplate(wfMessage($key)->inContentLanguage()->text(), $entityId ?? (string)$pageId, $fallback);
    }
    public static function fromTemplate(string $template, string $id, string $fallback): string {
        // Validate the template before expansion: no paths, quotes, CR/LF or arbitrary placeholders.
        if (strlen($template) > 120 || substr_count($template, '$1') !== 1 ||
            !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]*\z/', str_replace('$1', 'ID', $template))) {
            $template = $fallback;
        }
        return str_replace('$1', rawurlencode($id), $template);
    }
}
