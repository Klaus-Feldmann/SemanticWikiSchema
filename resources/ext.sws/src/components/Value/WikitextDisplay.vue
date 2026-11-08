<template><pre class="ext-sws-wikitext-source">{{ source }}</pre></template>

<script setup lang="ts">
import { computed } from 'vue';
import { ValueType } from '@/domain/Value';
import type { WikitextProperty } from '@/domain/propertyTypes/Wikitext';
import type { ValueDisplayProps } from './ValueDisplayContract';
const props = defineProps<ValueDisplayProps<WikitextProperty>>();
const source = computed( () => props.value.type === ValueType.String ? props.value.parts.join( '\n' ) : '' );
</script>

<style scoped>.ext-sws-wikitext-source { white-space: pre-wrap; overflow-wrap: anywhere; }</style>
