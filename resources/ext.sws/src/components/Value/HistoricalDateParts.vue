<template>
	<span><span v-for="( part, index ) in parts" :key="index"><WikiPageLink
		v-if="part.yearTitle"
		:title="part.yearTitle"
		:namespace="property.wikiLink?.namespace"
		:enabled="property.wikiLink?.values !== false">{{ part.text }}</WikiPageLink><template v-else>{{ part.text }}</template></span></span>
</template>

<script setup lang="ts">
import WikiPageLink from '@/components/common/WikiPageLink.vue';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { DatePart } from '@/composables/useExternalStatements';
defineProps<{ parts: DatePart[]; property: PropertyDefinition }>();
</script>
