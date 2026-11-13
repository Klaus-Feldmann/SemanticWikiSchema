<template>
	<CdxField :status="problem ? 'error' : 'default'" :messages="problem ? { error: problem } : {}">
		<template #label>{{ $i18n( 'sws-property-type-template' ).text() }}</template>
		<CdxTextArea :model-value="property.wikitext" @update:model-value="emit( 'update:property', { wikitext: String( $event ) } )" />
		<template #help-text>{{ $i18n( 'sws-template-help' ).text() }}</template>
	</CdxField>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { CdxField, CdxTextArea } from '@wikimedia/codex';
import type { WikitextProperty } from '@/domain/propertyTypes/Wikitext';
import type { AttributesEditorProps, AttributesEditorEmits, AttributesEditorExposes } from './AttributesEditorContract';
const props = defineProps<AttributesEditorProps<WikitextProperty>>();
const emit = defineEmits<AttributesEditorEmits<WikitextProperty>>();
const problem = computed( () => !props.property.wikitext.trim() ? mw.message( 'sws-wikitext-empty' ).text() : null );
defineExpose<AttributesEditorExposes>( { unparseableInputMessage: () => problem.value } );
</script>
