<template>
	<div>
		<CdxCheckbox v-if="property.type !== 'image'" v-model="enabled">
			{{ $i18n( 'sws-id-enable' ).text() }}
		</CdxCheckbox>
		<CdxField v-if="property.type === 'image'">
			<template #label>
				{{ $i18n( 'sws-image-source' ).text() }}
			</template>
			<CdxSelect
				:selected="imageSource"
				:menu-items="imageSources"
				@update:selected="changeImageSource" />
		</CdxField>
		<template v-if="enabled">
			<CdxField v-if="![ 'image', 'historicalDate' ].includes( property.type )">
				<template #label>
					{{ $i18n( 'sws-id-source' ).text() }}
				</template>
				<CdxSelect
					:selected="source"
					:menu-items="sources"
					@update:selected="changeSource" />
			</CdxField>
			<CdxField>
				<template #label>
					{{ $i18n( 'sws-id-property' ).text() }}
				</template>
				<IdLookup
					ref="input"
					:source="source"
                    :field-type="property.type"
					:model-value="property.externalProperty?.id ?? null"
					@update:model-value="choose" />
				<template #help-text>
					{{ $i18n( 'sws-id-help' ).text() }}
				</template>
			</CdxField>
			<CdxField v-if="![ 'image', 'historicalDate' ].includes( property.type )">
				<template #label>
					{{ $i18n( 'sws-external-link-target' ).text() }}
				</template>
				<CdxSelect
					:selected="property.wikiLink?.externalTarget ?? 'source'"
					:menu-items="linkTargets"
					@update:selected="changeLinkTarget" />
				<template #help-text>
					{{ $i18n( 'sws-external-link-help' ).text() }}
				</template>
			</CdxField>
		</template>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CdxCheckbox, CdxField, CdxSelect } from '@wikimedia/codex';
import type { ImageProperty, ImageSource } from '@/domain/propertyTypes/Image';
import { imageSourceOptions } from '@/presentation/imageSources';
import IdLookup from '@/components/Id/IdLookup.vue';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { IdSource } from '@/persistence/PropertyIdLookup';
const props = defineProps<{ property: PropertyDefinition }>();
const emit = defineEmits<{ 'update:property': [ Partial<PropertyDefinition> ] }>();
const initialImageSource = ( props.property as ImageProperty ).imageSource;
const enabled = ref( props.property.externalProperty !== undefined ||
	( props.property.type === 'image' && ( initialImageSource === 'wikidata' || initialImageSource === 'wikibase-local' ) ) );
const source = ref<IdSource>( props.property.externalProperty?.source ?? ( initialImageSource === 'wikibase-local' ? 'wikibase-local' : 'wikidata' ) );
const input = ref<InstanceType<typeof IdLookup> | null>( null );
const sources = [ { value: 'wikidata', label: 'Wikidata' }, { value: 'wikibase-local', label: mw.message( 'sws-id-local' ).text() } ];
const imageSources = imageSourceOptions();
const imageSource = computed( () => ( props.property as ImageProperty ).imageSource ?? props.property.externalProperty?.source ?? 'local' );
function changeImageSource( value: string | number | null ): void {
	if ( !imageSources.some( ( item ) => item.value === value ) ) {
		return;
	}
	const chosen = value as ImageSource;
	enabled.value = chosen === 'wikidata' || chosen === 'wikibase-local';
	if ( chosen === 'wikidata' || chosen === 'wikibase-local' ) {
		source.value = chosen;
	}
	emit( 'update:property', { imageSource: chosen,
		externalProperty: props.property.externalProperty?.source === chosen ? props.property.externalProperty : undefined } as Partial<ImageProperty> );
}
const linkTargets = [
	{ value: 'source', label: mw.message( 'sws-external-link-source' ).text() },
	{ value: 'local', label: mw.message( 'sws-external-link-local' ).text() }
];
function changeLinkTarget( value: string | number | null ): void {
	if ( value === 'source' || value === 'local' ) {
		emit( 'update:property', { wikiLink: { ...props.property.wikiLink, externalTarget: value } } );
	}
}
watch( enabled, ( value ) => {
	if ( !value ) {
		emit( 'update:property', { externalProperty: undefined } );
	}
} );
function choose( id: string | null ): void {
	emit( 'update:property', { externalProperty: id === null ? undefined : { source: source.value, id } } );
}
function changeSource( value: string | number | null ): void {
	if ( ( value === 'wikidata' || value === 'wikibase-local' ) && value !== source.value ) {
		source.value = value;
		emit( 'update:property', { externalProperty: undefined } );
	}
}
function unparseableInputMessage(): string | null {
	if ( !enabled.value ) {
		return null;
	}
	// null from a mounted picker means valid; it is not a missing picker.
	return input.value ? input.value.unparseableInputMessage() : mw.message( 'sws-id-confirm' ).text();
}
defineExpose( { unparseableInputMessage } );
</script>
