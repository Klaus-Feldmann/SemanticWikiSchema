<template><div /></template>

<script setup lang="ts">
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { AttributesEditorProps, AttributesEditorEmits } from './AttributesEditorContract';
defineProps<AttributesEditorProps<PropertyDefinition>>();
defineEmits<AttributesEditorEmits<PropertyDefinition>>();
</script>
