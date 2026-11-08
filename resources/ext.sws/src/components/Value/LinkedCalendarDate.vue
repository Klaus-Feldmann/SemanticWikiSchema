<template>
	<time :datetime="iso">
		<!-- Vue 3 supports keyed fragments; avoid wrapper-induced whitespace. -->
		<!-- eslint-disable-next-line vue/no-v-for-template-key -->
		<template v-for="( part, index ) in parts" :key="index">
			<WikiPageLink
				v-if="part.type === 'year'"
				:title="yearTitle"
				:namespace="property.wikiLink?.namespace"
				:enabled="property.wikiLink?.values !== false">{{ part.value }}</WikiPageLink>
			<template v-else>{{ part.value }}</template>
		</template>
	</time>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import WikiPageLink from '@/components/common/WikiPageLink.vue';
const props = defineProps<{ iso: string; property: PropertyDefinition; withTime?: boolean }>();
const yearTitle = computed( () => {
	const date = new Date( props.withTime ? props.iso : props.iso + 'T00:00:00Z' );
	return String( props.withTime ? date.getFullYear() : date.getUTCFullYear() );
} );
const parts = computed( () => new Intl.DateTimeFormat( undefined, props.withTime ? {
	year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', timeZoneName: 'short'
} : { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' } ).formatToParts( new Date( props.withTime ? props.iso : props.iso + 'T00:00:00Z' ) ) );
</script>
