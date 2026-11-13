<template>
	<CdxField
		:optional="property.required === false"
		:status="error ? 'error' : 'default'"
		:messages="error ? { error } : {}">
		<template #label>
			{{ label }}
		</template>
		<p>{{ $i18n( 'sws-date-calendar-note' ).text() }}</p>
		<p v-if="unparsed">
			{{ unparsed }}
		</p>
		<div class="ext-sws-historical-date-input">
			<CdxTextInput
				:model-value="year"
				:aria-label="$i18n( 'sws-historical-year' ).text()"
				:placeholder="$i18n( 'sws-historical-year' ).text()"
				@update:model-value="set( 'year', $event )" />
			<CdxSelect
				:selected="era"
				:menu-items="eras"
				:aria-label="$i18n( 'sws-historical-era' ).text()"
				@update:selected="set( 'era', $event )" />
			<CdxTextInput
				:model-value="month"
				:aria-label="$i18n( 'sws-historical-month' ).text()"
				:placeholder="$i18n( 'sws-historical-month' ).text()"
				@update:model-value="set( 'month', $event )" />
			<CdxTextInput
				:model-value="day"
				:aria-label="$i18n( 'sws-historical-day' ).text()"
				:placeholder="$i18n( 'sws-historical-day' ).text()"
				@update:model-value="set( 'day', $event )" />
		</div>
	</CdxField>
</template>

<script setup lang="ts">
import { computed, ref, shallowRef, watch } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import CdxSelect from '@/components/common/ContentWidthSelect.vue';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { newStringValue, ValueType, type Value } from '@/domain/Value';
import type { ValueInputProps, ValueInputEmits, ValueInputExposes } from './ValueInputContract';
import { parseHistoricalDate } from '@/domain/propertyTypes/historicalDate';
const props = withDefaults( defineProps<ValueInputProps<PropertyDefinition>>(), { label: '' } );
const emit = defineEmits<ValueInputEmits>();
const year = ref( '' );
const month = ref( '' );
const day = ref( '' );
const era = ref( 'ce' );
const unparsed = ref( '' );
const lastValidValue = shallowRef<Value | undefined>( props.modelValue );
const eras = [ { value: 'ce', label: mw.msg( 'sws-historical-ce' ) }, { value: 'bce', label: mw.msg( 'sws-historical-bce' ) } ];
watch( () => props.modelValue, ( value ) => {
	const raw = value?.type === ValueType.String ? value.parts[ 0 ] || '' : '';
	// Do not erase incomplete input when the parent echoes our undefined value.
	if ( raw === encoded.value ) {
		lastValidValue.value = value;
		return;
	}
	if ( !raw && error.value ) {
		return;
	}
	lastValidValue.value = value;
	const date = parseHistoricalDate( raw );
	unparsed.value = raw && !date ? raw : '';
	year.value = date ? String( Math.abs( date.year ) ) : '';
	month.value = date?.month === undefined ? '' : String( date.month );
	day.value = date?.day === undefined ? '' : String( date.day );
	era.value = date && date.year < 0 ? 'bce' : 'ce';
} );
const encoded = computed( () => ( era.value === 'bce' ? '-' : '' ) + pad( year.value, 4 ) + ( month.value ? '-' + pad( month.value, 2 ) : '' ) + ( day.value ? '-' + pad( day.value, 2 ) : '' ) );
const localError = computed( () => {
	if ( unparsed.value || ( year.value || month.value || day.value ) && ( ( !/^[0-9]{1,4}$/.test( year.value ) || Number( year.value ) === 0 ) || day.value && !month.value || !parseHistoricalDate( encoded.value ) ) ) {
		return mw.msg( 'sws-historical-invalid' );
	}
	return null;
} );
const error = computed( () => localError.value || ( props.serverViolations?.length ? mw.msg( 'sws-field-' + props.serverViolations[ 0 ].code ) : null ) );
function set( field: string, value: string | number ): void {
	unparsed.value = '';
	if ( field === 'year' ) {
		year.value = String( value );
	}
	if ( field === 'month' ) {
		month.value = String( value );
	}
	if ( field === 'day' ) {
		day.value = String( value );
	}
	if ( field === 'era' ) {
		era.value = value === 'bce' ? 'bce' : 'ce';
	}
	emit( 'clear-server-violation', { propertyName: props.property.name.toString(), valuePartIndex: null } );
	if ( localError.value === null ) {
		lastValidValue.value = year.value ? newStringValue( encoded.value ) : undefined;
		emit( 'update:modelValue', lastValidValue.value );
	}
}
function pad( text: string, length: number ): string {
	return ( '0000' + text ).slice( -Math.max( length, text.length ) );
}

function getCurrentValue(): Value | undefined {
	return lastValidValue.value;
}
// Initialise only after computed values exist.
const initial = props.modelValue?.type === ValueType.String ? props.modelValue.parts[ 0 ] || '' : '';
const parsed = parseHistoricalDate( initial );
if ( parsed ) {
	year.value = String( Math.abs( parsed.year ) );
	era.value = parsed.year < 0 ? 'bce' : 'ce';
	month.value = parsed.month === undefined ? '' : String( parsed.month );
	day.value = parsed.day === undefined ? '' : String( parsed.day );
} else {
	unparsed.value = initial;
}
defineExpose<ValueInputExposes>( { getCurrentValue, unparseableInputMessage: () => localError.value } );
</script>

<style scoped>
.ext-sws-historical-date-input {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5em;
}
</style>
