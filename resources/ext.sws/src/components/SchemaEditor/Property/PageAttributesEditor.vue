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
				@update:selected="emit( 'update:property', { pageNamespace: $event === null || $event === '' ? undefined : Number( $event ) } )" />
		</CdxField>
		<TextAttributesEditor
			ref="textEditor"
			:property="property"
			@update:property="emit( 'update:property', $event )" />
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { CdxField, CdxSelect } from '@wikimedia/codex';
import TextAttributesEditor from './TextAttributesEditor.vue';
import type { PageProperty } from '@/domain/propertyTypes/Page';
import type { AttributesEditorProps, AttributesEditorEmits, AttributesEditorExposes } from './AttributesEditorContract';
defineProps<AttributesEditorProps<PageProperty>>();
const emit = defineEmits<AttributesEditorEmits<PageProperty>>();
const textEditor = ref<AttributesEditorExposes>();
const localNamespaces = mw.config.get( 'wgFormattedNamespaces' ) || {};
const namespaces = computed( () => [ { value: '', label: mw.message( 'sws-page-all-namespaces' ).text() },
	...Object.keys( localNamespaces ).filter( ( id ) => Number( id ) >= 0 )
		.map( ( id ) => ( { value: Number( id ), label: String( localNamespaces[ Number( id ) ] ) || mw.message( 'sws-page-main-namespace' ).text() } ) ) ] );
defineExpose<AttributesEditorExposes>( {
	unparseableInputMessage: () => textEditor.value?.unparseableInputMessage?.() ?? null
} );
</script>
