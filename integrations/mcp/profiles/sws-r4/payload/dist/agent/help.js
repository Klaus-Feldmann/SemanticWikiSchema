import { readFileSync } from 'node:fs';
export const HELP_VERSION = '0.6.0/patch-5';
export const HELP_TOPICS = [
    'overview',
    'sws',
    'query',
    'editing',
    'authentication',
    'backends',
];
export function helpTopic(topic) {
    return readFileSync(new URL(`../../agent-help/${topic}.md`, import.meta.url), 'utf8');
}
export function profileTemplate() {
    return readFileSync(new URL('../../agent-help/profile.wikitext', import.meta.url), 'utf8');
}
export function isLegacyDefaultProfile(text) {
    const legacy = readFileSync(new URL('../../agent-help/profile-legacy-r2.wikitext', import.meta.url), 'utf8');
    return text.trim() === legacy.trim();
}
export const SOURCE_POLICY = 'Choose search methods to suit the question: full-text search, categories and structured SWS queries can be combined from the start; there is no mandatory source order. Inspect schema fields when building field-specific filters or interpreting structured data, not before every simple search. Where available, use concise application/use-case descriptions as selection evidence, whatever their field names; no field named Anwendung, description field or search checkbox is required. Match meaning and context, not page titles alone. Read candidate documentation on demand. Known absent graph backends remain unavailable; unknown configuration is advisory and does not block a requested read-only query. Search indexes may lag behind saves; verify known candidates directly when needed. Explicit user requests take priority, and external research remains available.';
