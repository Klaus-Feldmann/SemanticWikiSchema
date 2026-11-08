<template>
	<div>
		<!-- eslint-disable-next-line vue/no-v-for-template-key -->
		<template v-for="( title, index ) in titles" :key="index">
			<template v-if="index">
				,
			</template>
			<LocalPageLink :title="title" />
		</template>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { ValueType } from '@/domain/Value';
import type { PageProperty } from '@/domain/propertyTypes/Page';
import type { ValueDisplayProps } from './ValueDisplayContract';
import LocalPageLink from '@/components/common/LocalPageLink.vue';
const props = defineProps<ValueDisplayProps<PageProperty>>();
const titles = computed( () => props.value.type === ValueType.String ? props.value.parts.filter( ( title ) => title.trim() ) : [] );
</script>
