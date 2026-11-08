<template>
	<LinkedCalendarDate
		v-if="parsedIso !== null"
		:iso="parsedIso"
		:property="property" />
	<span v-else>{{ rawValue }}</span>
</template>

<script setup lang="ts">
import LinkedCalendarDate from './LinkedCalendarDate.vue';
import { computed } from 'vue';
import { ValueType } from '@/domain/Value.ts';
import { DateProperty, parseStrictDate } from '@/domain/propertyTypes/Date.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

const props = defineProps<ValueDisplayProps<DateProperty>>();

const rawValue = computed( (): string => {
	if ( props.value.type !== ValueType.String ) {
		return '';
	}
	return props.value.parts[ 0 ] ?? '';
} );

const parsedIso = computed( (): string | null => {
	const raw = rawValue.value;
	return raw !== '' && parseStrictDate( raw ) !== null ? raw : null;
} );
</script>
