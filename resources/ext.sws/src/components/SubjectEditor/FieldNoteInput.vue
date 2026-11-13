<template>
 <div class="ext-sws-field-note-input">
  <CdxButton v-if="!opened && !modelValue" weight="quiet" @click="opened = true">{{ $i18n( 'sws-notes-add' ).text() }}</CdxButton>
  <CdxField v-else>
   <template #label>{{ $i18n( 'sws-notes-label' ).text() }}</template>
   <CdxTextArea :model-value="modelValue" @update:model-value="emit( 'update:modelValue', String( $event ) )" />
  </CdxField>
 </div>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxField, CdxTextArea } from '@wikimedia/codex';
defineProps<{ modelValue: string }>();
const emit = defineEmits<{ 'update:modelValue': [ string ] }>();
const opened = ref( false );
</script>
