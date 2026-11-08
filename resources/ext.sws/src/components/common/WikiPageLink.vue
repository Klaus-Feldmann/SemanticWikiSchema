<template>
	<a v-if="href !== null" :href="href"><slot>{{ title }}</slot></a>
	<span v-else><slot>{{ title }}</slot></span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { wikiPageUrl } from '@/presentation/wikiLinks';
const props = withDefaults( defineProps<{ title: string; namespace?: string; enabled: boolean }>(), { namespace: '' } );
const href = computed( () => props.enabled !== false ? wikiPageUrl( props.title, props.namespace ) : null );
</script>
