<template>
	<div>
		<CdxField :optional="true">
			<template #label>
				{{ $i18n( 'sws-page-namespace' ).text() }}
			</template>
			<CdxSelect
				:selected="property.pageNamespace ?? null"
				:menu-items="namespaces"
				:default-label="$i18n( 'sws-page-all-namespaces' ).text()"
				@update:selected="emit( 'update:property', { pageNamespace: $event === null || $event === '' ? undefined : Number( $event ), ... ( Number( $event ) !== 14 ? { assignCategories: false } : {} ) } )" />
		</CdxField>
		<CdxField v-if="property.pageNamespace === 14">
			<CdxCheckbox :model-value="property.assignCategories ?? false"
				@update:model-value="emit( 'update:property', { assignCategories: $event } )">
				{{ $i18n( 'sws-page-assign-categories' ).text() }}
			</CdxCheckbox>
			<template #help-text>{{ $i18n( 'sws-page-assign-categories-help' ).text() }}</template>
		</CdxField>
		<TextAttributesEditor
			ref="textEditor"
			:property="property"
			@update:property="emit( 'update:property', $event )" />
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { CdxField, CdxCheckbox } from '@wikimedia/codex';
import CdxSelect from '@/components/common/ContentWidthSelect.vue';
import TextAttributesEditor from './TextAttributesEditor.vue';
import type { PageProperty } from '@/domain/propertyTypes/Page';
import type { AttributesEditorProps, AttributesEditorEmits, AttributesEditorExposes } from './AttributesEditorContract';
defineProps<AttributesEditorProps<PageProperty>>();
const emit = defineEmits<AttributesEditorEmits<PageProperty>>();
const textEditor = ref<AttributesEditorExposes>();
const localNamespaces = mw.config.get( 'wgFormattedNamespaces' ) || {};
const configuredNamespaces = mw.config.get( 'wgSWSPrefixSearchNamespaces' );
const searchNamespaces = ( Array.isArray( configuredNamespaces ) ? configuredNamespaces : [] ) as { id: number; label: string }[];
const namespaces = computed( () => [ { value: '', label: mw.message( 'sws-page-all-namespaces' ).text() },
	...Object.keys( localNamespaces ).filter( ( id ) => Number( id ) >= 0 )
		.map( ( id ) => ( { value: Number( id ), label: searchNamespaces.find( ( ns ) => ns.id === Number( id ) )?.label || String( localNamespaces[ Number( id ) ] ) || mw.message( 'article' ).text() } ) ) ] );
defineExpose<AttributesEditorExposes>( {
	unparseableInputMessage: () => textEditor.value?.unparseableInputMessage?.() ?? null
} );
</script>
