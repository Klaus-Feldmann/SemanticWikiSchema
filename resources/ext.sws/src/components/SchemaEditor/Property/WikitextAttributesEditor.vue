<template>
	<div>
		<CdxField :hide-label="true">
			<CdxCheckbox :model-value="property.execute" @update:model-value="emit( 'update:property', { execute: $event } )">
				{{ $i18n( 'sws-wikitext-execute' ).text() }}
			</CdxCheckbox>
			<template #help-text>
				{{ $i18n( 'sws-wikitext-mode-help' ).text() }}
			</template>
		</CdxField>
		<CdxField
			v-if="property.execute"
			:status="problem ? 'error' : 'default'"
			:messages="problem ? { error: problem } : {}">
			<template #label>
				{{ $i18n( 'sws-property-type-wikitext' ).text() }}
			</template>
			<CdxTextArea :model-value="property.wikitext" @update:model-value="emit( 'update:property', { wikitext: String( $event ) } )" />
		</CdxField>
		<TextAttributesEditor
			v-else
			ref="textEditor"
			:property="property"
			@update:property="emit( 'update:property', $event )" />
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { CdxField, CdxCheckbox, CdxTextArea } from '@wikimedia/codex';
import TextAttributesEditor from './TextAttributesEditor.vue';
import type { WikitextProperty } from '@/domain/propertyTypes/Wikitext';
import type { AttributesEditorProps, AttributesEditorEmits, AttributesEditorExposes } from './AttributesEditorContract';
const props = defineProps<AttributesEditorProps<WikitextProperty>>();
const emit = defineEmits<AttributesEditorEmits<WikitextProperty>>();
const textEditor = ref<AttributesEditorExposes>();
const problem = computed( () => props.property.execute && !props.property.wikitext.trim() ? mw.message( 'sws-wikitext-empty' ).text() : null );
defineExpose<AttributesEditorExposes>( { unparseableInputMessage: () => problem.value ?? textEditor.value?.unparseableInputMessage?.() ?? null } );
</script>
