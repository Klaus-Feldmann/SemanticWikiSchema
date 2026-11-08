<template>
	<div>
		<span v-if="loading">{{ $i18n( 'sws-derived-loading' ).text() }}</span>
		<span v-else-if="failed">{{ $i18n( 'sws-derived-unavailable' ).text() }}</span>
		<ExternalStatementDisplay
			v-else-if="field"
			:field="field"
			:property="property" />
		<span v-else>{{ $i18n( 'sws-image-no-claim' ).text() }}</span>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Subject } from '@/domain/Subject';
import type { ImageProperty } from '@/domain/propertyTypes/Image';
import { useExternalStatements } from '@/composables/useExternalStatements';
import ExternalStatementDisplay from '@/components/SubjectsManager/ExternalStatementDisplay.vue';
const props = defineProps<{ subject: Subject; property: ImageProperty }>();
const { fields, loading, failed } = useExternalStatements( computed( () => props.subject ), computed( () => [ props.property ] ) );
const field = computed( () => {
	const value = fields.value.get( props.property.name.toString() );
	return value?.status === 'missing' ? undefined : value;
} );
</script>
