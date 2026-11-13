<template>
	<CdxDialog
		:open="true"
		class="ext-sws-ui ext-sws-schema-import-dialog"
		:title="$i18n( 'sws-schemas-import-title' ).text()"
		:use-close-button="!importing"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@primary="importCheckedSchemas"
		@default="emit( 'close' )"
		@update:open="onUpdateOpen"
	>
		<template v-if="outcome === null">
			<div
				v-for="section in selectableSections"
				:key="section.heading"
				class="ext-sws-schema-import-dialog__section"
			>
				<CdxCheckbox
					class="ext-sws-schema-import-dialog__heading"
					:model-value="section.names.every( isChecked )"
					:indeterminate="isPartlyChecked( section.names )"
					:disabled="importing"
					@update:model-value="setChecked( section.names, $event )"
				>
					<strong>{{ $i18n( section.heading, section.names.length ).text() }}</strong>
					<template
						v-if="section.description !== null"
						#description
					>
						{{ $i18n( section.description, section.names.length ).text() }}
					</template>
				</CdxCheckbox>
				<div class="ext-sws-schema-import-dialog__items">
					<CdxCheckbox
						v-for="name in section.names"
						:key="name"
						v-model="checkedNames"
						:input-value="name"
						:disabled="importing"
					>
						{{ displayImportName( name ) }}
					</CdxCheckbox>
				</div>
			</div>

			<div
				v-if="unchangedNames.length > 0"
				class="ext-sws-schema-import-dialog__section"
			>
				<p class="ext-sws-schema-import-dialog__heading">
					<strong>{{ $i18n( 'sws-schemas-import-unchanged', unchangedNames.length ).text() }}</strong>
				</p>
				<p>
					{{ $i18n( 'sws-schemas-import-unchanged-names', nameList( unchangedNames ) ).text() }}
				</p>
			</div>
		</template>

		<div
			v-else
			ref="outcomeElement"
			class="ext-sws-schema-import-dialog__outcome"
			tabindex="-1"
		>
			<CdxMessage
				v-if="outcome.created.length > 0"
				type="success"
			>
				{{ $i18n( 'sws-schemas-import-created', outcome.created.length, nameList( outcome.created ) ).text() }}
			</CdxMessage>
			<CdxMessage
				v-if="outcome.replaced.length > 0"
				type="success"
			>
				{{ $i18n( 'sws-schemas-import-replaced', outcome.replaced.length, nameList( outcome.replaced ) ).text() }}
			</CdxMessage>
			<CdxMessage
				v-for="failure in outcome.failed"
				:key="failure.name"
				type="error"
			>
				{{ $i18n( 'sws-schemas-import-failed', failure.name, failure.reason ).text() }}
			</CdxMessage>
		</div>
	</CdxDialog>
</template>

<script setup lang="ts">
import { computed, inject, nextTick, ref, shallowRef } from 'vue';
import { CdxCheckbox, CdxDialog, CdxMessage } from '@wikimedia/codex';
import type { DialogAction, PrimaryDialogAction } from '@wikimedia/codex';
import {
	importSchemas,
	type SchemaImportItem,
	type SchemaImportOutcome,
	type SchemaImportStatus
} from '@/components/SchemasPage/schemaImport.ts';
import { Service } from '@/SWSServices';
import type { SchemaRepository } from '@/application/SchemaRepository';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

const props = defineProps<{
	items: SchemaImportItem[];
	sourceDocument?: string;
}>();

const emit = defineEmits<{
	close: [];
	imported: [];
}>();

const schemaStore = useSchemaStore();
const schemaRepository = inject<SchemaRepository | undefined>( Service.SchemaRepository, undefined );

const listedItems = [ ...props.items ].sort(
	( a, b ) => a.schema.getName().localeCompare( b.schema.getName() )
);

function displayImportName( name: string ): string {
	const schema = props.items.find( ( item ) => item.schema.getName() === name )?.schema;
	const label = schema?.getLabel() ?? name;
	return label === name ? name : `${ label } (${ name })`;
}

function namesWith( status: SchemaImportStatus ): string[] {
	return listedItems.filter( ( item ) => item.status === status ).map( ( item ) => item.schema.getName() );
}

const selectableSections = [
	{ heading: 'sws-schemas-import-new', description: null, names: namesWith( 'new' ) },
	{ heading: 'sws-schemas-import-changed', description: 'sws-schemas-import-replaces', names: namesWith( 'changed' ) }
].filter( ( section ) => section.names.length > 0 );

const unchangedNames = namesWith( 'unchanged' );

const checkedNames = ref<string[]>( namesWith( 'new' ) );
const importing = ref( false );
const outcome = shallowRef<SchemaImportOutcome | null>( null );
const outcomeElement = ref<HTMLElement | null>( null );

const primaryAction = computed( (): PrimaryDialogAction | undefined => {
	if ( outcome.value !== null ) {
		return undefined;
	}

	return {
		label: mw.msg( 'sws-schemas-import-confirm', checkedNames.value.length ),
		actionType: 'progressive',
		disabled: importing.value || checkedNames.value.length === 0
	};
} );

const defaultAction = computed( (): DialogAction => ( {
	label: mw.msg( outcome.value === null ? 'cancel' : 'cdx-dialog-close-button-label' ),
	disabled: importing.value
} ) );

// Closing mid-run would hide which saves went through.
function onUpdateOpen( open: boolean ): void {
	if ( !open && !importing.value ) {
		emit( 'close' );
	}
}

async function importCheckedSchemas(): Promise<void> {
	importing.value = true;

	if ( props.sourceDocument !== undefined ) {
		try {
			if ( schemaRepository?.importSchemaDocument === undefined ) {
				throw new Error( 'Stable schema import is unavailable' );
			}
			const result = await schemaRepository.importSchemaDocument( props.sourceDocument, checkedNames.value );
			outcome.value = { created: result.created.map( ( id, index ) => `${ displayImportName( checkedNames.value[ index ] ) } (${ id })` ), replaced: [], failed: [] };
		} catch ( error ) {
			outcome.value = { created: [], replaced: [], failed: [ { name: '', reason: error instanceof Error ? error.message : String( error ) } ] };
		}
	} else {
		outcome.value = await importSchemas(
			listedItems.filter( ( item ) => checkedNames.value.includes( item.schema.getName() ) ),
			( schema ) => schemaStore.saveSchema( schema, mw.msg( 'sws-schemas-import-summary' ) )
		);

	}

	importing.value = false;
	emit( 'imported' );

	// Focus left with the import action. The outcome takes it, so the keyboard can close the dialog.
	await nextTick();
	outcomeElement.value?.focus();
}

function isChecked( name: string ): boolean {
	return checkedNames.value.includes( name );
}

function isPartlyChecked( names: string[] ): boolean {
	return names.some( isChecked ) && !names.every( isChecked );
}

function setChecked( names: string[], checked: boolean ): void {
	const others = checkedNames.value.filter( ( name ) => !names.includes( name ) );
	checkedNames.value = checked ? [ ...others, ...names ] : others;
}

function nameList( names: string[] ): string {
	return names.join( mw.msg( 'comma-separator' ) );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-sws-schema-import-dialog {
	&__section + &__section {
		margin-top: @spacing-100;
	}

	&__items {
		padding-inline-start: calc( @min-size-input-binary + @spacing-50 );
	}

	&__outcome {
		display: flex;
		flex-direction: column;
		gap: @spacing-75;
	}
}
</style>
