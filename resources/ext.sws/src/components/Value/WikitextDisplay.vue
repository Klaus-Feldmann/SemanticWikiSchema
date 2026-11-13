<template>
	<div class="ext-sws-wikitext-value">
		<span v-if="loading">{{ $i18n( 'sws-wikitext-render-loading' ).text() }}</span>
		<CdxMessage v-else-if="failed" :inline="true" type="warning">{{ $i18n( 'sws-wikitext-render-error' ).text() }}</CdxMessage>
		<!-- HTML comes exclusively from the authenticated MediaWiki parser endpoint. -->
		<!-- eslint-disable-next-line vue/no-v-html -->
		<div v-if="html !== null" ref="rendered" class="mw-parser-output" v-html="html" />
		<pre v-else-if="!loading" class="ext-sws-wikitext-source">{{ source }}</pre>
	</div>
</template>
<script setup lang="ts">
import { computed, inject, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { CdxMessage } from '@wikimedia/codex';
import { loadSavedWikitext } from '@/persistence/WikitextBatch';
import { ValueType } from '@/domain/Value';
import type { TextProperty } from '@/domain/propertyTypes/Text';
import { WikitextContextKey, type ValueDisplayProps } from './ValueDisplayContract';
const props = defineProps<ValueDisplayProps<TextProperty> & { note?: boolean }>();
const context = inject( WikitextContextKey, undefined );
const source = computed( () => props.value.type === ValueType.String ? props.value.parts.join( '\n\n' ) : '' );
const html = ref<string | null>( null );
const rendered = ref<HTMLElement>();
const loading = ref( false );
const failed = ref( false );
let sequence = 0;
onBeforeUnmount( () => { sequence++; } );
watch( () => [ context?.value.pageId, context?.value.subject, props.property.name.toString(), props.property.type, source.value, props.note ] as const, async ( [ pageid, subject, field, type, text, note ] ) => {
	const ticket = ++sequence;
	html.value = null;
	failed.value = false;
	loading.value = false;
	if ( type !== 'wikitextValue' || !pageid || !subject || text === '' ) { return; }
	loading.value = true;
	try {
		const result = await loadSavedWikitext( pageid, subject, field, note );
		if ( ticket !== sequence ) { return; }
		
		if ( !result || typeof result.html !== 'string' || result.source !== text ) { throw new Error( 'Stale or invalid wikitext rendering' ); }
		const modules = [ ...( result.modules || [] ), ...( result.styles || [] ) ];
		if ( modules.length ) { await mw.loader.using( modules ); }
		if ( ticket !== sequence ) { return; }
		html.value = result.html;
		await nextTick();
		if ( ticket === sequence && rendered.value && typeof mw.hook === 'function' ) {
			mw.hook( 'wikipage.content' ).fire( $( rendered.value ) );
		}
	} catch {
		if ( ticket === sequence ) { failed.value = true; }
	} finally {
		if ( ticket === sequence ) { loading.value = false; }
	}
}, { immediate: true } );
</script>
<style scoped>
.ext-sws-wikitext-source { white-space: pre-wrap; overflow-wrap: anywhere; }
.ext-sws-wikitext-value { overflow-wrap: anywhere; }
.ext-sws-wikitext-value :deep( p:first-child ) { margin-top: 0; }
.ext-sws-wikitext-value :deep( p:last-child ) { margin-bottom: 0; }
</style>
