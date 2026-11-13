<template>
 <div v-if="text || sourceNote || sourcePending" class="ext-sws-field-notes">
  <div v-if="text">
   <small><strong>{{ $i18n( 'sws-notes-label' ).text() }}:</strong></small>
   <WikitextDisplay v-if="property.fieldNotes?.type === 'wikitext'" :property="noteProperty" :value="newWikitextValue( text )" :note="true" />
   <span v-else class="ext-sws-field-notes__text">{{ text }}</span>
  </div>
  <div v-if="sourceNote"><small><strong>{{ $i18n( 'sws-notes-source-label' ).text() }}:</strong> {{ $i18n( 'sws-notes-source-' + sourceNote.status, sourceNote.source === 'wikidata' ? 'Wikidata' : 'Wikibase', sourceNote.propertyId ).text() }}</small></div>
  <div v-else-if="sourcePending"><small>{{ $i18n( 'sws-source-pending' ).text() }}</small></div>
 </div>
</template>
<script setup lang="ts">
import { computed } from 'vue';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { TextProperty } from '@/domain/propertyTypes/Text';
import type { ExternalField } from '@/composables/useExternalStatements';
import { newWikitextValue } from '@/domain/propertyTypes/Wikitext';
import WikitextDisplay from '@/components/Value/WikitextDisplay.vue';
import { sourceNoteForField } from '@/presentation/sourceState';
const props = defineProps<{ property: PropertyDefinition; text: string; source?: ExternalField }>();
const noteProperty = computed<TextProperty>( () => ( { ...props.property, type: 'wikitextValue', multiple: false, uniqueItems: false } ) );
const sourceNote = computed( () => sourceNoteForField( props.source ) );
const sourcePending = computed( () => props.source?.storageMode === 'store' && props.source.recorded === false );
</script>
<style scoped>
.ext-sws-field-notes { margin-block-start: 0.4em; padding-inline-start: 0.7em; border-inline-start: 2px solid var(--border-color-subtle, #72777d); }
.ext-sws-field-notes__text { display: block; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
