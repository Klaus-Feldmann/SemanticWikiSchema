<template>
	<WikiPageLink :title="property.wikiLink?.labelTarget || property.name.toString()" :enabled="property.wikiLink?.label !== false">
		{{ propertyDisplayName( property ) }}
	</WikiPageLink>
</template>

<script setup lang="ts">
import { propertyDisplayName } from '@/presentation/propertyDisplayName';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import WikiPageLink from './WikiPageLink.vue';
defineProps<{ property: PropertyDefinition }>();
</script>
