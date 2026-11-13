import type { SchemaSummary } from '@/application/SchemaLookup';
export function schemaSummaryLabel( summary: SchemaSummary ): string {
	const lang = typeof mw === 'undefined' ? '' : mw.config?.get( 'wgUserLanguage' );
	return summary.labels?.[ lang ] || summary.labels?.[ summary.identityLanguage ?? '' ] || summary.name;
}
export function schemaSummaryDescription( summary: SchemaSummary ): string {
	const lang = typeof mw === 'undefined' ? '' : mw.config?.get( 'wgUserLanguage' );
	return summary.descriptions?.[ lang ] || summary.descriptions?.[ summary.identityLanguage ?? '' ] || summary.description;
}
