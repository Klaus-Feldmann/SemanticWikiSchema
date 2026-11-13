<template>
	<span class="ext-sws-content-width-select"
		:class="{ 'ext-sws-content-width-select--has-icon': menuItems.some( ( item ) => item.icon ) || $attrs[ 'default-icon' ] || $attrs.defaultIcon }">
		<span class="ext-sws-content-width-select__measure" aria-hidden="true">
			<span v-for="( label, index ) in labels" :key="index">{{ label }}</span>
		</span>
		<CdxSelect v-bind="$attrs" :menu-items="menuItems" :selected="selected ?? null">
			<template v-for="( _, name ) in $slots" #[name]="slotProps">
				<slot :name="name" v-bind="slotProps || {}" />
			</template>
		</CdxSelect>
	</span>
</template>

<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { CdxSelect, type MenuItemData } from '@wikimedia/codex';

defineOptions( { inheritAttrs: false } );
const props = defineProps<{ menuItems: MenuItemData[]; selected?: string | number | null }>();
const attrs = useAttrs();
// Intrinsic browser layout measures real glyph widths, including after a font change.
// Hidden labels share the select's grid cell; they never enter the tab or reading order.
const labels = computed( () => [
	String( attrs[ 'default-label' ] ?? attrs.defaultLabel ?? '' ),
	...props.menuItems.map( ( item ) => String( item.label ?? item.value ) ),
] );
</script>

<style src="../../../../styles/content-width-select.css"></style>
