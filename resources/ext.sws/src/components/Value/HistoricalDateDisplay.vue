<template>
	<HistoricalDateParts
		v-if="date"
		:parts="parts"
		:property="property" />
	<span v-else>{{ raw }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { ValueType } from '@/domain/Value';
import type { ValueDisplayProps } from './ValueDisplayContract';
import { parseHistoricalDate } from '@/domain/propertyTypes/historicalDate';
import { dateParts } from '@/domain/propertyTypes/datePresentation';
import HistoricalDateParts from './HistoricalDateParts.vue';
const props = defineProps<ValueDisplayProps<PropertyDefinition>>();
const raw = computed( () => props.value.type === ValueType.String ? props.value.parts[ 0 ] || '' : '' );
const date = computed( () => parseHistoricalDate( raw.value ) );
const parts = computed( () => date.value ? dateParts( date.value, props.property,
	String( mw.config.get( 'wgUserLanguage' ) || 'de' ), String( mw.config.get( 'wgContentLanguage' ) || 'de' ) ) : [] );
</script>
