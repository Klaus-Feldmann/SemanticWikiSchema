<template>
	<div>
		<!-- Vue 3 supports keyed fragments; avoid wrapper-induced whitespace. -->
		<!-- eslint-disable-next-line vue/no-v-for-template-key -->
		<template v-for="( text, index ) in values" :key="index">
			<template v-if="index > 0">
				{{ separator }}
			</template>
			<WikiPageLink
				:title="text"
				:namespace="property.wikiLink?.namespace"
				:enabled="property.wikiLink?.values !== false" />
		</template>
	</div>
</template>

<script setup lang="ts">
import { ValueType } from '@/domain/Value';
import { computed } from 'vue';
import type { ValueDisplayProps } from './ValueDisplayContract';
import type { TextProperty } from '@/domain/propertyTypes/Text';
import WikiPageLink from '@/components/common/WikiPageLink.vue';
const separator = ', ';
const props = defineProps<ValueDisplayProps<TextProperty>>();
const values = computed( () => props.value.type === ValueType.String ? props.value.parts.filter( ( part ) => part.trim() !== '' ) : [] );
</script>
