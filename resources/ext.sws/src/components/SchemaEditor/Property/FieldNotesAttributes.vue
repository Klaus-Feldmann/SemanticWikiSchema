<template>
 <CdxField :hide-label="true">
  <CdxCheckbox :model-value="!!property.fieldNotes" @update:model-value="toggle">{{ $i18n( 'sws-notes-allow' ).text() }}</CdxCheckbox>
  <template v-if="property.fieldNotes">
   <CdxSelect :selected="property.fieldNotes.type" :menu-items="types" :aria-label="$i18n( 'sws-notes-type' ).text()" @update:selected="setType" />
   <CdxCheckbox :model-value="property.fieldNotes.annotate === true" @update:model-value="annotate">{{ $i18n( 'sws-notes-annotate' ).text() }}</CdxCheckbox>
   <small>{{ $i18n( 'sws-notes-smw-help' ).text() }}</small>
  </template>
 </CdxField>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import { CdxField, CdxCheckbox, CdxSelect } from '@wikimedia/codex';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
const props = defineProps<{ property: PropertyDefinition }>();
const emit = defineEmits<{ 'update:property': [ Partial<PropertyDefinition> ] }>();
const types = computed( () => [ { value: 'text', label: mw.message( 'sws-property-type-text' ).text() }, { value: 'wikitext', label: mw.message( 'sws-property-type-wikitext' ).text() } ] );
function toggle( enabled: boolean ): void { emit( 'update:property', { fieldNotes: enabled ? { type: 'text', annotate: false } : undefined } ); }
function setType( type: string | number | null ): void { if ( type === 'text' || type === 'wikitext' ) { emit( 'update:property', { fieldNotes: { ...props.property.fieldNotes, type } } ); } }
function annotate( enabled: boolean ): void { emit( 'update:property', { fieldNotes: { type: props.property.fieldNotes?.type || 'text', annotate: enabled } } ); }
</script>
