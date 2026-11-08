<template>
	<div
		v-if="visibleSources.length"
		class="ext-sws-external-identity"
		aria-live="polite">
		<span v-for="( source, index ) in visibleSources" :key="source">
			<span v-if="index > 0"> · </span>
			<strong>{{ $i18n( 'sws-auto-identity-' + source ).text() }}:</strong>&nbsp;
			<a :href="identities![ source ].url!">{{ identities![ source ].id }}</a>
		</span>
	</div>
</template>

<script setup lang="ts">
import { readDescriptionGroups, type EntityDescription } from '@/presentation/entityDescriptions';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { Subject } from '@/domain/Subject';
const emit = defineEmits<{ descriptions: [ groups: EntityDescription[] ] }>();
const props = defineProps<{ subject: Subject }>();
const sources = [ 'wikidata', 'wikibase-local' ] as const;
type Source = typeof sources[number];
interface Identity { status: 'resolved' | 'missing' | 'unavailable' | 'error'; id: string | null; url: string | null }
const identities = ref<Record<Source, Identity> | null>( null );
const visibleSources = computed( () => sources.filter( ( source ) => identities.value?.[ source ].status === 'resolved' ) );
const historical = computed( () => !!mw.config.get( 'wgDiffOldId' ) ||
	new URLSearchParams( window.location.search ).has( 'oldid' ) ||
	new URLSearchParams( window.location.search ).has( 'diff' ) ||
	( !!mw.config.get( 'wgRevisionId' ) && !!mw.config.get( 'wgCurRevisionId' ) &&
		mw.config.get( 'wgRevisionId' ) !== mw.config.get( 'wgCurRevisionId' ) ) );
let controller: AbortController | null = null;
onBeforeUnmount( () => controller?.abort() );
function validIdentity( value: unknown ): value is Identity {
	if ( value === null || typeof value !== 'object' ) {
		return false;
	}
	const v = value as Identity;
	return v.status === 'resolved' ? typeof v.id === 'string' && /^Q[1-9][0-9]*$/.test( v.id ) && v.id === v.id.trim() &&
		typeof v.url === 'string' && /^https?:\/\//.test( v.url ) :
		[ 'missing', 'unavailable', 'error' ].includes( v.status ) && v.id === null && v.url === null;
}
watch( () => props.subject, async () => {
	controller?.abort();
	identities.value = null;
	emit( 'descriptions', [] );
	if ( historical.value ) {
		return;
	}
	const pageId = props.subject.getPageIdentifiers().getPageId();
	if ( !pageId ) {
		return;
	}
	const current = new AbortController();
	controller = current;
	try {
		const url = new URL( mw.util.wikiScript( 'api' ), window.location.href );
		url.search = new URLSearchParams( { action: 'swsbaseidentity', pageid: String( pageId ),
			descriptions: '1', language: String( mw.config.get( 'wgUserLanguage' ) || mw.config.get( 'wgContentLanguage' ) || 'de' ),
			format: 'json', formatversion: '2' } ).toString();
		const response = await fetch( url, { credentials: 'same-origin', cache: 'no-store', signal: current.signal } );
		const body = await response.json();
		const data = body.swsbaseidentity;
		if ( !response.ok || body.error || data?.version !== 2 || data.pageId !== pageId || data.scope !== 'current' ) {
			throw new Error( 'Identity lookup unavailable' );
		}
		if ( data.mainSubjectId !== props.subject.getId().text ) {
			return;
		}
		const item = data.identities?.[ props.subject.getId().text ];
		if ( !item || !sources.every( ( source ) => validIdentity( item[ source ] ) ) ) {
			throw new Error( 'Invalid identity response' );
		}
		if ( !current.signal.aborted ) {
			identities.value = item;
			const groups = data.displayDescription === undefined ? readDescriptionGroups( data.descriptions, item ) :
				readDescriptionGroups( data.displayDescription === null ? [] : [ data.displayDescription ], item,
					typeof data.pageDescription?.url === 'string' ? { id: pageId, url: data.pageDescription.url } : undefined );
			const preferred = groups.find( ( group ) => group.sources.some( ( source ) => source.source === 'page' ) ) ||
				groups.find( ( group ) => group.sources.some( ( source ) => source.source === 'wikibase-local' ) ) || groups[ 0 ];
			emit( 'descriptions', preferred ? [ preferred ] : [] );
		}
	} catch {
		// Missing or unavailable identities do not produce an empty row or a user-facing message.
	}
}, { immediate: true, deep: true } );
</script>
