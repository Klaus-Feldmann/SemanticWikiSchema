<template>
	<div class="ext-sws-derived-value">
		<span>
			<strong>{{ field.source === 'wikidata' ? 'Wikidata' : 'Wikibase' }}:</strong>&nbsp;
			<a
				v-if="field.propertyUrl"
				:href="field.propertyUrl"
				:title="field.propertyId">{{ field.propertyLabel || field.propertyId }}</a>
			<span v-else>{{ field.propertyLabel || field.propertyId }}</span>
		</span>
		<div v-if="field.status === 'resolved' && hasImages" class="ext-sws-derived-image">
			<div v-for="( value, index ) in field.values" :key="index">
				<ImagePreview v-if="value.kind === 'image' && value.reference" :reference="value.reference" />
				<span v-else>{{ $i18n( 'sws-derived-' + value.kind ).text() }}</span>
			</div>
		</div>
		<span v-else-if="field.status === 'resolved'">{{ wordSeparator }}<span v-for="( value, index ) in field.values" :key="index">
			<span v-if="index > 0">; </span>
			<HistoricalDateParts
				v-if="value.kind === 'time'"
				:parts="value.parts || []"
				:property="property"
				:title="value.calendar" />
            <span v-else-if="value.kind === 'text'" :lang="value.language">
                <a v-if="textHref( value )" :href="textHref( value )">{{ value.value }}</a><span v-else>{{ value.value }}</span>
                <small v-if="value.language"> ({{ value.language }})</small>
            </span>
            <a
                v-else-if="value.kind === 'entity'"
				:href="valueHref( value )"
				:title="value.id">{{ value.label }}</a>
			<span v-else>{{ $i18n( 'sws-derived-' + value.kind ).text() }}</span>
		</span></span>
		<span v-else>{{ wordSeparator }}{{ $i18n( 'sws-derived-' + field.status ).text() }}</span>
		<small v-if="field.retained"> {{ $i18n( 'sws-source-retained' ).text() }}</small>
		<small v-if="field.languageFallback && field.language"> {{ $i18n( 'sws-source-language-fallback', field.language ).text() }}</small>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import HistoricalDateParts from '@/components/Value/HistoricalDateParts.vue';
import ImagePreview from '@/components/Value/ImagePreview.vue';
import type { ExternalField, ExternalEntityValue } from '@/composables/useExternalStatements';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { wikiPageUrl } from '@/presentation/wikiLinks';
const props = defineProps<{ field: ExternalField; property: PropertyDefinition }>();
// Keep a normal wrapping space despite template whitespace condensation.
const wordSeparator = ' ';
const hasImages = computed( () => props.field.values.some( ( value ) => value.kind === 'image' ) );
function textHref( value: ExternalEntityValue ): string | undefined {
    return props.property.wikiLink?.externalTarget === 'local' && props.property.wikiLink?.values !== false && value.value ?
        wikiPageUrl( value.value, props.property.wikiLink.namespace ) ?? undefined : undefined;
}
function valueHref( value: ExternalEntityValue ): string | undefined {
	if ( props.property.wikiLink?.externalTarget === 'local' && value.label?.trim() && value.label.trim() !== value.id ) {
		return wikiPageUrl( value.label, props.property.wikiLink.namespace ) ?? value.url;
	}
	return value.url;
}
</script>

<style scoped>
.ext-sws-derived-image {
	float: right;
	max-width: 100%;
	margin-inline-start: 12px;
}

.ext-sws-derived-value::after {
	content: '';
	display: block;
	clear: both;
}
</style>
