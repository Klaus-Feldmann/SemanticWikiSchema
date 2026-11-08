<template>
	<CdxField
		:is-fieldset="true"
		:messages="fieldMessages"
		:status="violationStatus( fieldMessages )"
		:optional="property.required === false">
		<template #label>
			{{ label }}
			<CdxIcon
				v-if="property.description"
				v-tooltip="property.description"
				:icon="cdxIconInfo"
				size="small" />
		</template>
		<div v-for="( text, index ) in rows" :key="index">
			<CdxField
				:hide-label="true"
				:messages="inputMessages[ index ] || {}"
				:status="violationStatus( inputMessages[ index ] || {} )">
				<template #label>
					{{ label }}
				</template>
				<LocalPageInput
					:model-value="text"
					:input-label="label || property.name.toString()"
					:namespace="property.pageNamespace"
					@update:model-value="( value, selected ) => update( index, value, selected )" />
			</CdxField>
			<CdxButton v-if="property.multiple" @click="remove( index )">
				{{ $i18n( 'sws-page-remove' ).text() }}
			</CdxButton>
		</div>
		<CdxButton v-if="property.multiple" @click="add">
			{{ $i18n( 'sws-page-add' ).text() }}
		</CdxButton>
	</CdxField>
</template>

<script setup lang="ts">
import { computed, toRef, ref, watch } from 'vue';
import { CdxField, CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import LocalPageInput from '@/components/common/LocalPageInput.vue';
import { PageType, type PageProperty } from '@/domain/propertyTypes/Page';
import { ValueType, type Value } from '@/domain/Value';
import type { ValueInputProps, ValueInputEmits, ValueInputExposes } from './ValueInputContract';
import { useStringValueInput } from '@/composables/useStringValueInput';
import { violationStatus } from '@/composables/useServerViolations';
import { SWSServices } from '@/SWSServices';
import { pageTitleInput } from '@/presentation/localPages';
const props = withDefaults( defineProps<ValueInputProps<PageProperty>>(), { label: '', modelValue: undefined } );
const emit = defineEmits<ValueInputEmits>();
const { displayValues, fieldMessages, inputMessages, onInput, getCurrentValue } = useStringValueInput(
	toRef( props, 'modelValue' ), toRef( props, 'property' ), emit,
	SWSServices.getPropertyTypeRegistry().getType( PageType.typeName ), toRef( props, 'serverViolations' ) );
const draft = ref<string[] | null>( null );
const canonicalDraft = ref<string[] | null>( null );
const rows = computed( () => draft.value ?? ( displayValues.value.length ? displayValues.value : [ '' ] ) );
function canonicalRows(): string[] {
	return [ ...( canonicalDraft.value ?? ( displayValues.value.length ? displayValues.value : [ '' ] ) ) ];
}
watch( () => props.modelValue, ( value ) => {
	if ( canonicalDraft.value && JSON.stringify( canonicalDraft.value.filter( ( text ) => text.trim() ) ) !==
		JSON.stringify( value?.type === ValueType.String ? value.parts : [] ) ) {
		draft.value = null;
		canonicalDraft.value = null;
	}
} );
function update( index: number, text: string, selected = false ): void {
	const values = [ ...rows.value ];
	const canonical = canonicalRows();
	values[ index ] = text;
	canonical[ index ] = text.trim() ? pageTitleInput( text, selected ? undefined : props.property.pageNamespace ) || text : text;
	draft.value = values;
	canonicalDraft.value = canonical;
	onInput( canonical );
}
function add(): void {
	canonicalDraft.value = [ ...canonicalRows(), '' ];
	draft.value = [ ...rows.value, '' ];
	onInput( canonicalDraft.value );
}
function remove( index: number ): void {
	canonicalDraft.value = canonicalRows().filter( ( _, i ) => i !== index );
	draft.value = rows.value.filter( ( _, i ) => i !== index );
	onInput( canonicalDraft.value );
}
function currentValue(): Value | undefined {
	return getCurrentValue();
}
function problem(): string | null {
	return rows.value.some( ( text ) => text.trim() && !pageTitleInput( text, props.property.pageNamespace ) ) ?
		mw.message( 'sws-invalid-page' ).text() : null;
}
defineExpose<ValueInputExposes>( { getCurrentValue: currentValue, unparseableInputMessage: problem } );
</script>
