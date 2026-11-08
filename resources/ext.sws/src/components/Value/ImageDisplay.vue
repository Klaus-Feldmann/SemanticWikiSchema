<template>
	<div class="ext-sws-image-display">
		<ImagePreview
			v-for="( reference, index ) in references"
			:key="index"
			:reference="reference" />
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { ValueType } from '@/domain/Value';
import type { ImageProperty } from '@/domain/propertyTypes/Image';
import type { ValueDisplayProps } from './ValueDisplayContract';
import ImagePreview from './ImagePreview.vue';
const props = defineProps<ValueDisplayProps<ImageProperty>>();
const references = computed( () => props.value.type === ValueType.String ? props.value.parts : [] );
</script>

<style scoped>
.ext-sws-image-display {
	float: right;
	max-width: 100%;
	margin-inline-start: 12px;
}
</style>
