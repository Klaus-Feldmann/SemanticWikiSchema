<template>
	<div class="ext-sws-schema-display-header">
		<div class="ext-sws-schema-display-header__content">
			<div class="ext-sws-schema-display-header__title">
				{{ schema.getLabel() }}{{ schema.getId() ? ` (${ schema.getId() })` : '' }}
			</div>
			<div
				v-if="schema.getDescription()"
				class="ext-sws-schema-display-header__description"
			>
				{{ schema.getDescription() }}
			</div>
			<p
				v-if="schema.subjectsAreDependent()"
				class="ext-sws-schema-display-header__dependence"
			>
				{{ $i18n( 'sws-schema-subjects-are-dependent' ).text() }}
			</p>
			<CdxButton
				v-if="canCreateSubject"
				class="ext-sws-schema-display-header__create-subject"
				action="progressive"
				weight="primary"
				@click="emit( 'create-subject' )"
			>
				<CdxIcon :icon="cdxIconAdd" />
				{{ $i18n( 'sws-schema-create-subject', schema.getLabel() ).text() }}
			</CdxButton>
		</div>
		<div class="ext-sws-schema-display-header__actions">
			<CdxButton
				v-if="canEditSchema"
				weight="quiet"
				:aria-label="$i18n( 'sws-edit-schema' ).text()"
				@click="emit( 'edit' )"
			>
				<CdxIcon :icon="cdxIconEdit" />
			</CdxButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import { Schema } from '@/domain/Schema.ts';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconEdit } from '@wikimedia/codex-icons';

defineProps( {
	schema: {
		type: Schema,
		required: true
	},
	canEditSchema: {
		type: Boolean,
		required: true
	},
	canCreateSubject: {
		type: Boolean,
		required: true
	}
} );

const emit = defineEmits<{
	edit: [];
	'create-subject': [];
}>();
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-sws-schema-display-header {
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

	&__dependence {
		margin: @spacing-25 0 0;
		color: @color-subtle;
		font-size: @font-size-small;
	}

	&__create-subject {
		margin-top: @spacing-75;
	}
}
</style>
