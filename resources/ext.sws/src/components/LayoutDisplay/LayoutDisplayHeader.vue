<template>
	<div class="ext-sws-layout-display-header">
		<div class="ext-sws-layout-display-header__content">
			<div class="ext-sws-layout-display-header__title">
				{{ layout.getName() }}
			</div>
			<div
				v-if="layout.getDescription()"
				class="ext-sws-layout-display-header__description"
			>
				{{ layout.getDescription() }}
			</div>
			<div class="ext-sws-layout-display-header__metadata">
				<span class="ext-sws-layout-display-header__metadata-item">
					<span class="ext-sws-layout-display-header__metadata-label">
						{{ $i18n( 'sws-layout-display-schema' ).text() }}
					</span>
					<a :href="schemaPageUrl">{{ layout.getSchema() }}</a>
				</span>
				<span class="ext-sws-layout-display-header__metadata-item">
					<span class="ext-sws-layout-display-header__metadata-label">
						{{ $i18n( 'sws-layout-display-view-type' ).text() }}
					</span>
					<span>{{ layout.getType() }}</span>
				</span>
			</div>
		</div>
		<div class="ext-sws-layout-display-header__actions">
			<CdxButton
				v-if="canEditLayout"
				weight="quiet"
				:aria-label="$i18n( 'sws-edit-layout' ).text()"
				@click="emit( 'edit' )"
			>
				<CdxIcon :icon="cdxIconEdit" />
			</CdxButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Layout } from '@/domain/Layout.ts';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconEdit } from '@wikimedia/codex-icons';

const props = defineProps( {
	layout: {
		type: Layout,
		required: true
	},
	canEditLayout: {
		type: Boolean,
		required: true
	}
} );

const emit = defineEmits<{
	edit: [];
}>();

const schemaPageUrl = computed( () => mw.util.getUrl( `Schema:${ props.layout.getSchema() }` ) );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-sws-layout-display-header {
	display: flex;
	justify-content: space-between;
	gap: @spacing-100;

	&__content {
		line-height: @line-height-xx-small;
	}

	&__title {
		font-size: @font-size-large;
		font-weight: @font-weight-bold;
	}

	&__description {
		color: @color-subtle;
	}

	&__metadata {
		display: flex;
		gap: @spacing-200;
		margin-block-start: @spacing-50;
	}

	&__metadata-item {
		display: flex;
		gap: @spacing-50;
	}

	&__metadata-label {
		color: @color-subtle;
	}
}
</style>
