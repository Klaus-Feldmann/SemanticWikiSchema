<template>
	<figure class="ext-sws-image-preview">
		<a
			v-if="info"
			:href="info.descriptionUrl"
			target="_blank"
			rel="noopener noreferrer">
			<img
				v-if="info.thumbnailUrl && !broken"
				:src="info.thumbnailUrl"
				:alt="info.filename"
				:width="info.width"
				:height="info.height"
				loading="lazy"
				@error="broken = true">
			<span v-else>{{ info.filename }}</span>
		</a>
		<span v-else>{{ filename }}</span>
		<figcaption>
			<span v-if="info?.thumbnailUrl && !broken">{{ info.filename }} · </span>
			<span v-if="repository">{{ repository === 'commons' ? 'Commons' : projectName() }}</span>
			<span v-if="loading"> · {{ $i18n( 'sws-image-loading' ).text() }}</span>
			<span v-else-if="failed || broken || ( info && !info.thumbnailUrl )"> · {{ $i18n( 'sws-image-unavailable' ).text() }}</span>
			<span v-else-if="!info"> · {{ $i18n( 'sws-image-missing' ).text() }}</span>
		</figcaption>
	</figure>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { projectName } from '@/presentation/imageSources';
import { parseImageReference, resolveImage, type ImageInfo } from '@/persistence/ImageLookup';
const props = defineProps<{ reference: string }>();
const info = ref<ImageInfo | null>( null );
const loading = ref( false );
const failed = ref( false );
const broken = ref( false );
const repository = computed( () => parseImageReference( props.reference )?.repository );
const filename = computed( () => parseImageReference( props.reference )?.filename || props.reference );
let sequence = 0;
onBeforeUnmount( () => sequence++ );
watch( () => props.reference, async ( reference ) => {
	const ticket = ++sequence;
	info.value = null;
	failed.value = false;
	broken.value = false;
	loading.value = true;
	try {
		const result = await resolveImage( reference );
		if ( ticket === sequence ) {
			info.value = result;
		}
	} catch {
		if ( ticket === sequence ) {
			failed.value = true;
		}
	} finally {
		if ( ticket === sequence ) {
			loading.value = false;
		}
	}
}, { immediate: true } );
</script>

<style scoped>
.ext-sws-image-preview {
	margin: 0 0 12px;
	overflow-wrap: anywhere;
}

.ext-sws-image-preview img {
	display: block;
	max-width: min( 150px, 100% );
	max-height: 150px;
	object-fit: contain;
	height: auto;
}

.ext-sws-image-preview figcaption {
	font-size: 0.8em;
}
</style>
