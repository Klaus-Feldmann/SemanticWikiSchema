<template>
	<WikiPageLink :title="property.wikiLink?.labelTarget || property.name.toString()" :enabled="property.wikiLink?.label !== false">
		{{ property.name.toString() }}
	</WikiPageLink>
</template>

<script setup lang="ts">
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import WikiPageLink from './WikiPageLink.vue';
defineProps<{ property: PropertyDefinition }>();
</script>
