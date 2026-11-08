<template>
	<div>
		<div v-for="( entry, key ) in entries" :key="key">
			<template v-if="!props.property.hideUrlCaption && ( entry.caption || entry.captionPage )">
				<a v-if="entry.captionPage && wikiPageUrl( entry.captionPage )" :href="wikiPageUrl( entry.captionPage )!">{{ entry.caption || entry.captionPage }}</a>
				<span v-else>{{ entry.caption }}</span><span>: </span>
			</template>
			<a
				v-if="isHttpUrl( entry.url )"
				:href="entry.url"
				:title="entry.url">{{ entry.label || formatUrlForDisplay( entry.url ) }}</a>
			<span v-else>{{ entry.label || entry.url }}</span>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { urlEntries } from '@/domain/Value';
import { UrlProperty, formatUrlForDisplay, isHttpUrl } from '@/domain/propertyTypes/Url';
import { wikiPageUrl } from '@/presentation/wikiLinks';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract';
const props = defineProps<ValueDisplayProps<UrlProperty & { hideUrlCaption?: boolean }>>();
const entries = computed( () => urlEntries( props.value ) );
</script>
