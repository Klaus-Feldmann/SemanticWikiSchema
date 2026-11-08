<template>
	<CdxField
		:is-fieldset="true"
		:messages="fieldMessages"
		:status="violationStatus( fieldMessages )"
		:optional="props.property.required === false"
	>
		<template #label>
			{{ props.label }}
			<CdxIcon
				v-if="props.property.description"
				v-tooltip="props.property.description"
				:icon="cdxIconInfo"
				class="ext-sws-value-input__description-icon"
				size="small"
			/>
		</template>
		<div v-for="( text, index ) in rows" :key="index">
			<CdxField
				:hide-label="true"
				:messages="inputMessages[ index ] || {}"
				:status="violationStatus( inputMessages[ index ] || {} )">
				<template #label>
					{{ label }}
				</template>
				<CdxTextArea :model-value="text" @update:model-value="update( index, String( $event ) )" />
			</CdxField>
			<CdxButton v-if="property.multiple" @click="remove( index )">
				{{ $i18n( 'sws-wikitext-remove' ).text() }}
			</CdxButton>
		</div>
		<CdxButton v-if="property.multiple" @click="add">
			{{ $i18n( 'sws-wikitext-add' ).text() }}
		</CdxButton>
	</CdxField>
</template>

<script setup lang="ts">
import { CdxField, CdxIcon, CdxTextArea, CdxButton } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { toRef, computed } from 'vue';
import { WikitextProperty, WikitextType, newWikitextValue } from '@/domain/propertyTypes/Wikitext';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract';
import { useStringValueInput } from '@/composables/useStringValueInput.ts';
import { violationStatus } from '@/composables/useServerViolations.ts';
import { SWSServices } from '@/SWSServices.ts';
const props = withDefaults(
	defineProps<ValueInputProps<WikitextProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const propertyType = SWSServices.getPropertyTypeRegistry().getType( WikitextType.typeName );

const {
	displayValues,
	fieldMessages,
	inputMessages,
	onInput,
	getCurrentValue
} = useStringValueInput( toRef( props, 'modelValue' ), toRef( props, 'property' ), emit, propertyType, toRef( props, 'serverViolations' ), newWikitextValue );

const rows = computed( () => displayValues.value.length ? displayValues.value : [ '' ] );
function update( index: number, text: string ): void {
	const values = [ ...rows.value ];
	values[ index ] = text;
	onInput( values );
}
function add(): void {
	onInput( [ ...rows.value, '' ] );
}
function remove( index: number ): void {
	onInput( rows.value.filter( ( _, i ) => i !== index ) );
}

defineExpose<ValueInputExposes>( {
	getCurrentValue: getCurrentValue
} );

</script>
