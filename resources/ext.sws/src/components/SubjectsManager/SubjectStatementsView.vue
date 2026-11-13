<template>
	<div class="ext-sws-subject-statements">
		<ExternalIdentityDisplay :subject="subject" @descriptions="descriptionGroups = $event" />
		<EntityDescriptions v-if="!descriptionImage" :groups="descriptionGroups" />
		<dl
			v-if="displayProperties.length > 0"
			class="ext-sws-subject-statements__list"
		>
			<div
				v-for="( resolved, rowIndex ) in displayProperties"
				:key="`${ resolved.sourcePropertyName }-${ rowIndex }`"
				class="ext-sws-subject-statements__item"
			>
				<dt class="ext-sws-subject-statements__property">
					<PropertyLabel :property="resolved.propertyDefinition" />
				</dt>
				<dd
					class="ext-sws-subject-statements__value"
					:class="{ 'ext-sws-subject-statements__value--with-description': descriptionImage === resolved.propertyDefinition.name.toString() && descriptionGroups.length > 0 }">
					<EntityDescriptions v-if="descriptionImage === resolved.propertyDefinition.name.toString()" :groups="descriptionGroups" />
					<div class="ext-sws-subject-statements__value-content">
						<small v-if="resolved.defaultValue">{{ $i18n( 'sws-schema-default-value' ).text() }}</small>
						<small v-if="resolved.value !== undefined && resolved.propertyDefinition.externalProperty">{{ $i18n( 'sws-derived-own' ).text() }}</small>
						<StatementDisplay
							v-if="resolved.value !== undefined"
							:value="resolved.value"
							:property="manualProperty( resolved )"
						/>
						<ExternalStatementDisplay
							v-if="resolved.external?.status === 'resolved'"
							:field="resolved.external"
							:property="resolved.propertyDefinition" />
						<FieldNoteDisplay
							v-if="resolved.showNote"
							:property="noteProperty( resolved )"
							:text="subject.getStatements().fieldNotes[ resolved.sourcePropertyName || resolved.propertyDefinition.name.toString() ] || ''"
							:source="resolved.value === undefined ? resolved.external : undefined" />
					</div>
				</dd>
			</div>
		</dl>
		<div
			v-else-if="!loading"
			class="ext-sws-subject-statements__empty"
		>
			{{ $i18n( 'sws-managesubjects-no-statements' ).text() }}
		</div>
		<span v-if="loading">{{ $i18n( 'sws-derived-loading' ).text() }}</span>
		<span v-if="failed">{{ $i18n( 'sws-derived-unavailable' ).text() }}</span>
	</div>
</template>

<script setup lang="ts">
import FieldNoteDisplay from './FieldNoteDisplay.vue';
import EntityDescriptions from './EntityDescriptions.vue';
import type { EntityDescription } from '@/presentation/entityDescriptions';
import { isSchemaExecution } from '@/domain/propertyTypes/Wikitext';
import { expandUrlDisplayRows } from '@/presentation/urlDisplayRows';
import ExternalStatementDisplay from './ExternalStatementDisplay.vue';
import { useExternalStatements, type ExternalField } from '@/composables/useExternalStatements';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { ValueType, type Value } from '@/domain/Value';
import ExternalIdentityDisplay from './ExternalIdentityDisplay.vue';
import PropertyLabel from '@/components/common/PropertyLabel.vue';
import { computed, inject, provide, ref, watch } from 'vue';
import { Subject } from '@/domain/Subject';
import { Schema } from '@/domain/Schema';
import { useSchemaStore } from '@/stores/SchemaStore';
import { resolveAllNonEmptyProperties } from '@/domain/resolveDisplayProperties';
import StatementDisplay from '@/components/Value/StatementDisplay.vue';
import { EnclosingSubjectIdsKey, WikitextContextKey } from '@/components/Value/ValueDisplayContract.ts';

const emit = defineEmits<{ 'count-change': [ count: number | null ] }>();
const props = defineProps<{
	subject: Subject;
}>();

const schemaStore = useSchemaStore();
const descriptionGroups = ref<EntityDescription[]>( [] );

const enclosingSubjectIds = inject( EnclosingSubjectIdsKey, ref( [] ) );

provide( EnclosingSubjectIdsKey, computed( () => [ ...enclosingSubjectIds.value, props.subject.getId().text ] ) );
provide( WikitextContextKey, computed( () => ( { pageId: props.subject.getPageIdentifiers().getPageId(), subject: props.subject.getId().text } ) ) );

const schema = computed( () => schemaStore.schemas.get( props.subject.getSchemaName() ) as Schema | undefined );
const definitions = computed( () => schema.value ? [ ...schema.value.getPropertyDefinitions() ] : [] );
const { fields, loading, failed } = useExternalStatements( computed( () => props.subject as Subject ), definitions );
interface DisplayProperty { sourcePropertyName?: string; propertyDefinition: PropertyDefinition; value?: Value; external?: ExternalField; showNote?: boolean; defaultValue?: boolean }
const displayProperties = computed<DisplayProperty[]>( () => {
	if ( !schema.value ) {
		return [];
	}
	const own = new Map( resolveAllNonEmptyProperties( schema.value, props.subject as Subject )
		.filter( ( row ) => row.value.type !== ValueType.String || row.value.parts.some( ( part ) => part.trim() !== '' ) )
		.map( ( row ) => [ row.propertyDefinition.name.toString(), row.value ] ) );
	const result: DisplayProperty[] = [];
	for ( const propertyDefinition of definitions.value ) {
		if ( isSchemaExecution( propertyDefinition ) ) {
			continue;
		}
		let value = own.get( propertyDefinition.name.toString() );
		let defaultValue = false;
		if ( value === undefined && propertyDefinition.type === 'text' && !propertyDefinition.externalProperty &&
            propertyDefinition.default?.type === ValueType.String && propertyDefinition.default.parts.some( ( part ) => part.trim() !== '' ) ) {
			value = propertyDefinition.default;
			defaultValue = true;
		}
		// Own image references take precedence, including when their preview is temporarily unavailable.
		const external = [ 'image', 'historicalDate' ].includes( propertyDefinition.type ) && value?.type === ValueType.String && value.parts.length > 0 ?
			undefined : fields.value.get( propertyDefinition.name.toString() );
		if ( value !== undefined || external !== undefined || props.subject.getStatements().fieldNotes[ propertyDefinition.name.toString() ] ) {
			result.push( { propertyDefinition, value, external, defaultValue } );
		}
	}
	const seen = new Set<string>();
	return expandUrlDisplayRows( result ).map( ( row ) => {
		const showNote = !seen.has( row.sourcePropertyName );
		seen.add( row.sourcePropertyName );
		return { ...row, showNote };
	} );
} );
const descriptionImage = computed( () => displayProperties.value.find( ( row ) => row.propertyDefinition.type === 'image' &&
 ( row.value !== undefined || row.external?.status === 'resolved' && row.external.values.some( ( value ) => value.kind === 'image' ) ) )?.propertyDefinition.name.toString() );
watch( () => [ loading.value, failed.value, displayProperties.value ] as const, () => {
	emit( 'count-change', loading.value || failed.value ? null : new Set( displayProperties.value.filter( ( row ) =>
		row.value !== undefined || row.external?.status === 'resolved' && row.external.values.length > 0 || !!props.subject.getStatements().fieldNotes[ row.sourcePropertyName || row.propertyDefinition.name.toString() ] ).map( ( row ) => row.sourcePropertyName || row.propertyDefinition.name.toString() ) ).size );
}, { immediate: true } );

function noteProperty( row: DisplayProperty ): PropertyDefinition {
	return definitions.value.find( ( p ) => p.name.toString() === ( row.sourcePropertyName || row.propertyDefinition.name.toString() ) ) || row.propertyDefinition;
}
function manualProperty( row: DisplayProperty ): PropertyDefinition {
	// A manually entered property ID stays stored, but is not misrepresented as a local page link.
	if ( row.value?.type === ValueType.String && row.propertyDefinition.externalProperty &&
		row.value.parts.every( ( part ) => part === row.propertyDefinition.externalProperty?.id ) ) {
		return { ...row.propertyDefinition, wikiLink: { ...row.propertyDefinition.wikiLink, values: false } };
	}
	return row.propertyDefinition;
}

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-sws-subject-statements {
	line-height: @line-height-small;

	&__list {
		margin: 0;
		padding: 0;
	}

	&__item {
		display: flex;
		flex-direction: column;
		gap: @spacing-25;
		padding-block: @spacing-75;
		border-top: @border-subtle;
	}

	&__item:first-child {
		border-top: 0;
	}

	&__property {
		font-weight: @font-weight-bold;
		color: @color-emphasized;
	}

	&__value {
		margin: 0;
		overflow-wrap: anywhere;
		word-break: break-word;
	}

	&__value-content {
		min-width: 0;
	}

	&__value--with-description {
		display: grid;
		grid-template-columns: minmax( 0, 1fr ) minmax( 0, 150px );
		gap: 12px;
	}

	@media ( max-width: 500px ) {
		&__value--with-description {
			grid-template-columns: minmax( 0, 1fr );
		}
	}

	&__empty {
		color: @color-subtle;
		font-style: italic;
	}

	@media ( min-width: @min-width-breakpoint-tablet ) {
		&__item {
			flex-direction: row;
			align-items: flex-start;
			column-gap: @spacing-150;
		}

		&__property {
			flex: 0 0 40%;
		}

		&__value {
			flex: 0 1 60%;
		}

		&__item:has( .ext-sws-dependent-subject-display, .ext-sws-dependent-subject-table ) {
			flex-direction: column;
			align-items: stretch;
		}

		&__item:has( .ext-sws-dependent-subject-display, .ext-sws-dependent-subject-table ) > &__property,
		&__item:has( .ext-sws-dependent-subject-display, .ext-sws-dependent-subject-table ) > &__value {
			flex: none;
		}
	}
}
</style>
