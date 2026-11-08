<template>
	<a
		v-if="href"
		:href="href"
		:class="{ new: info?.valid && !info.exists }">{{ title }}</a>
	<span v-else>{{ title }}</span>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { localPageInfo, type LocalPageInfo } from '@/presentation/localPages';
import { pageTitleInput } from '@/presentation/localPages';
const props = defineProps<{ title: string }>();
const info = ref<LocalPageInfo>();
let sequence = 0;
watch( () => props.title, async ( title ) => {
	const current = ++sequence;
	info.value = undefined;
	try {
		const result = await localPageInfo( title );
		if ( sequence === current ) {
			info.value = result;
		}
	} catch { /* No red link on a failed lookup. */ }
}, { immediate: true } );
const href = computed( () => {
	if ( info.value && !info.value.valid ) {
		return null;
	}
	const title = info.value?.title || props.title;
	if ( info.value && !info.value.exists ) {
		return mw.util.getUrl( title, { action: 'edit', redlink: '1' } );
	}
	return pageTitleInput( title ) ? mw.util.getUrl( title ) : null;
} );
</script>
