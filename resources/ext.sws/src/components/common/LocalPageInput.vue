<template>
	<CdxField
		:hide-label="true"
		:messages="message ? { [ status ]: message } : {}"
		:status="message && status !== 'notice' ? status : 'default'">
		<template #label>
			{{ inputLabel }}
		</template>
		<template v-if="message && status === 'notice'" #description>
			{{ message }}
		</template>
		<CdxSelect
			v-if="namespace === undefined && scopes.length"
			:selected="selectedNamespace"
			:menu-items="scopes"
			:aria-label="$i18n( 'sws-prefixsearch-namespace' ).text()"
			@update:selected="changeNamespace" />
		<CdxLookup
			:selected="selected"
			:input-value="modelValue"
			:menu-items="items"
			:aria-label="inputLabel"
			@update:input-value="input"
			@update:selected="choose">
			<template #no-results>
				{{ $i18n( 'sws-page-no-results' ).text() }}
			</template>
		</CdxLookup>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue';
import { CdxLookup, CdxField, type MenuItemData } from '@wikimedia/codex';
import CdxSelect from '@/components/common/ContentWidthSelect.vue';
import { SWSServices } from '@/SWSServices';
import { lookupLocalPages, pageTitleInput } from '@/presentation/localPages';
const props = defineProps<{ modelValue: string; inputLabel: string; namespace?: number }>();
const emit = defineEmits<{ 'update:modelValue': [ string, boolean?, number? ] }>();
const search = SWSServices.getPageTitleSearch();
const configuredScopes = mw.config.get( 'wgSWSPrefixSearchNamespaces' );
const scopes = Array.isArray( configuredScopes ) ? configuredScopes.map( ( scope: { id: number; label: string } ) => ( { value: scope.id, label: scope.label } ) ) : [];
const selectedNamespace = ref<number | null>( props.namespace ?? ( scopes[ 0 ]?.value ?? null ) );
function changeNamespace( value: string | number | null ): void {
	selectedNamespace.value = value === null ? null : Number( value );
	input( props.modelValue );
}
const selected = ref<string | null>( null );
const items = ref<MenuItemData[]>( [] );
const message = ref( '' );
const status = ref<'error' | 'warning' | 'notice'>( 'notice' );
let sequence = 0;
let timer: ReturnType<typeof setTimeout> | undefined;
function input( value: string | number ): void {
	const text = String( value );
	selected.value = null;
	emit( 'update:modelValue', text, false, props.namespace ?? selectedNamespace.value ?? undefined );
	clearTimeout( timer );
	const current = ++sequence;
	items.value = [];
	message.value = '';
	if ( !text.trim() ) {
		return;
	}
	const scope = props.namespace ?? selectedNamespace.value ?? undefined;
	const title = pageTitleInput( text, scope );
	if ( !title ) {
		status.value = 'error';
		message.value = mw.message( 'sws-invalid-page' ).text();
		return;
	}
	timer = setTimeout( async () => {
		const [ suggestions, info ] = await Promise.all( [ search.searchPageTitles( scope === undefined ? title : text, 10, scope ).catch( () => null ), lookupLocalPages( [ title ] ).catch( () => null ) ] );
		if ( current !== sequence ) {
			return;
		}
		if ( suggestions !== null ) {
			items.value = suggestions.map( ( page ) => ( { value: page.title, label: page.displayTitle || page.title } ) );
		}
		if ( info === null || suggestions === null ) {
			status.value = 'warning';
			message.value = mw.message( 'sws-page-search-failed' ).text();
		} else if ( !info[ 0 ].valid ) {
			status.value = 'error';
			message.value = mw.message( 'sws-invalid-page' ).text();
		} else if ( !info[ 0 ].exists ) {
			status.value = 'notice';
			message.value = mw.message( 'sws-page-missing' ).text();
		}
	}, 200 );
}
function choose( value: string | number | null ): void {
	selected.value = value === null ? null : String( value );
	if ( value !== null ) {
		sequence++;
		clearTimeout( timer );
		message.value = '';
		emit( 'update:modelValue', String( value ), true );
	}
}
watch( () => props.namespace, () => { input( props.modelValue ); } );
onBeforeUnmount( () => {
	sequence++;
	clearTimeout( timer );
} );
</script>
