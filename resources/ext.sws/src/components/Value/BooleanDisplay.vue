<template>
	<div>
		{{ displayText }}
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { ValueType } from '@/domain/Value.ts';
import { BooleanProperty } from '@/domain/propertyTypes/Boolean.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

const props = defineProps<ValueDisplayProps<BooleanProperty>>();

const displayText = computed( (): string => {
	if ( props.value.type !== ValueType.Boolean ) {
		return '';
	}

	return props.value.boolean ?
		mw.message( 'sws-boolean-true' ).text() :
		mw.message( 'sws-boolean-false' ).text();
} );
</script>
