<template>
	<LinkedCalendarDate
		v-if="parsedIso !== null"
		:iso="parsedIso"
		:property="property"
		:with-time="true" />
	<span v-else>{{ rawValue }}</span>
</template>

<script setup lang="ts">
import LinkedCalendarDate from './LinkedCalendarDate.vue';
import { computed } from 'vue';
import { ValueType } from '@/domain/Value.ts';
import { DateTimeProperty, parseStrictDateTime } from '@/domain/propertyTypes/DateTime.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

const props = defineProps<ValueDisplayProps<DateTimeProperty>>();

const rawValue = computed( (): string => {
	if ( props.value.type !== ValueType.String ) {
		return '';
	}
	return props.value.parts[ 0 ] ?? '';
} );

const parsedIso = computed( (): string | null => {
	const raw = rawValue.value;
	return raw !== '' && parseStrictDateTime( raw ) !== null ? raw : null;
} );
</script>
