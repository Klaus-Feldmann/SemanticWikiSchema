<template>
	<CdxField
		:optional="!property.required"
		:status="violationStatus( messages )"
		:messages="messages">
		<template #label>
			{{ label }}
		</template>
		<template #description>
			{{ property.description }}
		</template>
		<CdxSelect
			:selected="repository"
			:menu-items="repositories"
			:aria-label="$i18n( 'sws-image-repository' ).text()"
			@update:selected="changeRepository" />
		<CdxLookup
			v-if="direct"
			v-model:input-value="query"
			:selected="null"
			:menu-items="menuItems"
			:placeholder="$i18n( 'sws-image-search' ).text()"
			:aria-label="$i18n( 'sws-image-search' ).text()"
			@update:selected="choose">
			<template #no-results>
				{{ $i18n( busy ? 'sws-image-loading' : failed ? 'sws-image-unavailable' : 'sws-image-no-results' ).text() }}
			</template>
		</CdxLookup>
		<ImageSourceValues
			v-if="!references.length && !direct && subject && property.externalProperty?.source === repository"
			:subject="subject"
			:property="property" />
		<CdxMessage
			v-else-if="!references.length && !direct"
			type="notice"
			inline>
			{{ $i18n( property.externalProperty?.source === repository ? 'sws-image-save-subject' : 'sws-image-configure-source' ).text() }}
		</CdxMessage>
		<CdxMessage
			v-if="pending && !busy"
			type="notice"
			inline>
			{{ $i18n( 'sws-image-confirm' ).text() }}
		</CdxMessage>
		<small v-if="references.length && property.externalProperty">{{ $i18n( 'sws-derived-own' ).text() }}</small>
		<CdxMessage
			v-if="references.length && property.externalProperty"
			type="notice"
			inline>
			{{ $i18n( 'sws-image-own-priority' ).text() }}
		</CdxMessage>
		<div v-for="( reference, index ) in references" :key="reference + index">
			<ImagePreview :reference="reference" />
			<CdxButton @click="remove( index )">
				{{ $i18n( 'sws-image-remove' ).text() }}
			</CdxButton>
		</div>
	</CdxField>
</template>

<script setup lang="ts">
import { computed, inject, onBeforeUnmount, ref, toRef, watch } from 'vue';
import { CdxButton, CdxField, CdxLookup, CdxMessage } from '@wikimedia/codex';
import CdxSelect from '@/components/common/ContentWidthSelect.vue';
import { newStringValue, ValueType, type Value } from '@/domain/Value';
import type { ImageProperty, ImageSource } from '@/domain/propertyTypes/Image';
import { lookupImages, parseImageReference, type ImageInfo } from '@/persistence/ImageLookup';
import { useServerViolations, violationStatus } from '@/composables/useServerViolations';
import type { ValueInputProps, ValueInputEmits, ValueInputExposes } from './ValueInputContract';
import ImagePreview from './ImagePreview.vue';
import ImageSourceValues from './ImageSourceValues.vue';
import { ImageSubjectContext } from './ImageSubjectContext';
import { imageSourceOptions, projectName } from '@/presentation/imageSources';
const props = withDefaults( defineProps<ValueInputProps<ImageProperty>>(), { label: '', modelValue: undefined } );
const emit = defineEmits<ValueInputEmits>();
function preferredSource( value: Value | undefined ): ImageSource {
	const own = value?.type === ValueType.String ? parseImageReference( value.parts[ 0 ] || '' )?.repository : undefined;
	return own ?? props.property.externalProperty?.source ?? props.property.imageSource ?? 'local';
}
const repository = ref<ImageSource>( preferredSource( props.modelValue ) );
watch( () => [ props.property.imageSource, props.property.externalProperty?.source, props.property.externalProperty?.id ], () => {
	repository.value = preferredSource( getCurrentValue() );
} );
const direct = computed( () => repository.value === 'local' || repository.value === 'commons' );
const subject = inject( ImageSubjectContext, ref( undefined ) );
const repositories = computed( () => imageSourceOptions().map( ( option ) => ( { ...option, disabled: references.value.length > 0 && ( option.value === 'wikidata' || option.value === 'wikibase-local' ) } ) ) );
const references = ref<string[]>( [] );
watch( () => props.modelValue, ( value ) => {
	references.value = value?.type === ValueType.String ? [ ...value.parts ] : [];
	repository.value = preferredSource( value );
}, { immediate: true } );
const query = ref<string | number>( '' );
const results = ref<ImageInfo[]>( [] );
const busy = ref( false );
const failed = ref( false );
const pending = computed( () => direct.value && String( query.value ).trim() !== '' );
const menuItems = computed( () => results.value.map( ( item ) => ( { value: item.reference, label: item.filename, description: item.repository === 'commons' ? 'Commons' : projectName(), thumbnail: item.thumbnailUrl ? { url: item.thumbnailUrl } : undefined } ) ) );
const { firstMessages: messages, emitClears } = useServerViolations( toRef( props, 'property' ), toRef( props, 'serverViolations' ), emit );
let sequence = 0;
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | null = null;
function cancel(): number {
	clearTimeout( timer );
	controller?.abort();
	busy.value = false;
	return ++sequence;
}
onBeforeUnmount( cancel );
watch( [ query, repository ], () => {
	const ticket = cancel();
	results.value = [];
	failed.value = false;
	const text = String( query.value ).trim();
	if ( !text || !direct.value ) {
		return;
	}
	busy.value = true;
	timer = setTimeout( async () => {
		controller = new AbortController();
		try {
			const selectedRepository = repository.value;
			if ( selectedRepository !== 'local' && selectedRepository !== 'commons' ) {
				return;
			}
			const found = await lookupImages( selectedRepository, text, 'search', controller.signal );
			if ( ticket === sequence ) {
				results.value = found;
			}
		} catch {
			if ( ticket === sequence ) {
				failed.value = true;
			}
		} finally {
			if ( ticket === sequence ) {
				busy.value = false;
			}
		}
	}, 300 );
} );
function getCurrentValue(): Value | undefined {
	return references.value.length ? newStringValue( references.value ) : undefined;
}
function commit(): void {
	emitClears( 'all' );
	emit( 'update:modelValue', getCurrentValue() );
}
function choose( value: string | number | null ): void {
	const selected = results.value.find( ( item ) => item.reference === value );
	if ( !selected ) {
		return;
	}
	cancel();
	references.value = props.property.multiple ? [ ...references.value, selected.reference ] : [ selected.reference ];
	query.value = '';
	results.value = [];
	commit();
}
function remove( index: number ): void {
	references.value = references.value.filter( ( _, i ) => i !== index );
	if ( !references.value.length && props.property.externalProperty ) {
		repository.value = props.property.externalProperty.source;
	}
	commit();
}
function changeRepository( value: string | number | null ): void {
	if ( references.value.length && ( value === 'wikidata' || value === 'wikibase-local' ) ) {
		return;
	}
	if ( value === 'wikidata' || value === 'wikibase-local' || value === 'local' || value === 'commons' ) {
		repository.value = value;
	}
}
defineExpose<ValueInputExposes>( { getCurrentValue, unparseableInputMessage: () => pending.value ? mw.message( 'sws-image-confirm' ).text() : null } );
</script>
