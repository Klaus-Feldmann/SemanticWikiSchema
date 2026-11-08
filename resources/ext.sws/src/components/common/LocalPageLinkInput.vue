<template>
	<CdxLookup
		:selected="selected"
		:input-value="modelValue"
		:menu-items="items"
		:aria-label="inputLabel"
		@update:input-value="input"
		@update:selected="choose">
		<template #no-results>
			{{ $i18n( 'sws-url-page-hint' ).text() }}
		</template>
	</CdxLookup>
</template>

<script setup lang="ts">
import { ref, onBeforeUnmount } from 'vue';
import { CdxLookup, type MenuItemData } from '@wikimedia/codex';
import { SWSServices } from '@/SWSServices';
defineProps<{ modelValue: string; inputLabel: string }>();
const emit = defineEmits<{ 'update:modelValue': [ string ] }>();
const search = SWSServices.getPageTitleSearch();
const selected = ref<string | null>( null );
const items = ref<MenuItemData[]>( [] );
let sequence = 0;
let timer: ReturnType<typeof setTimeout> | undefined;
function input( value: string | number ): void {
	const text = String( value );
	emit( 'update:modelValue', text );
	clearTimeout( timer );
	const current = ++sequence;
	items.value = [];
	if ( !text.trim() ) {
		return;
	}
	timer = setTimeout( async () => {
		try {
			const results = await search.searchPageTitles( text, 10 );
			if ( sequence === current ) {
				items.value = results.map( ( page ) => ( { value: page.title, label: page.title } ) );
			}
		} catch {
			if ( sequence === current ) {
				items.value = [];
			}
		}
	}, 200 );
}
function choose( value: string | number | null ): void {
	selected.value = value === null ? null : String( value );
	if ( value !== null ) {
		emit( 'update:modelValue', String( value ) );
	}
}
onBeforeUnmount( () => {
	sequence++;
	clearTimeout( timer );
} );
</script>
