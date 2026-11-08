<template>
	<div>
		<CdxCheckbox :model-value="property.wikiLink?.label !== false" @update:model-value="update( 'label', $event )">
			{{ $i18n( 'sws-link-label' ).text() }}
		</CdxCheckbox>
		<CdxCheckbox
			v-if="property.type !== 'image' && property.type !== 'page'"
			:model-value="property.wikiLink?.values !== false"
			@update:model-value="update( 'values', $event )">
			{{ $i18n( 'sws-link-values' ).text() }}
		</CdxCheckbox>
		<CdxField v-if="property.type !== 'image' && property.type !== 'page'">
			<template #label>
				{{ $i18n( 'sws-link-namespace' ).text() }}
			</template>
			<CdxTextInput :model-value="property.wikiLink?.namespace || ''" @update:model-value="update( 'namespace', $event )" />
		</CdxField>
		<template v-if="property.type === 'historicalDate'">
			<CdxField>
				<template #label>
					{{ $i18n( 'sws-date-bce-de' ).text() }}
				</template>
				<CdxTextInput :model-value="property.wikiLink?.dateBceDe || 'v. Chr.'" @update:model-value="update( 'dateBceDe', $event || 'v. Chr.' )" />
			</CdxField>
			<CdxField>
				<template #label>
					{{ $i18n( 'sws-date-bce-en' ).text() }}
				</template>
				<CdxTextInput :model-value="property.wikiLink?.dateBceEn || 'BC'" @update:model-value="update( 'dateBceEn', $event || 'BC' )" />
			</CdxField>
		</template>
		<CdxField>
			<template #label>
				{{ $i18n( 'sws-link-label-target' ).text() }}
			</template>
			<CdxTextInput :model-value="property.wikiLink?.labelTarget || ''" @update:model-value="update( 'labelTarget', $event )" />
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { CdxCheckbox, CdxField, CdxTextInput } from '@wikimedia/codex';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
const props = defineProps<{ property: PropertyDefinition }>();
const emit = defineEmits<{ 'update:property': [ Partial<PropertyDefinition> ] }>();
function update( key: string, value: string | boolean ): void {
	emit( 'update:property', { wikiLink: { ...props.property.wikiLink, [ key ]: value } } );
}
</script>
