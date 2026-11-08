<template>
	<div>
		<CdxLookup
			v-model:input-value="inputText"
			:selected="unmatched ? null : selectedItem?.id ?? null"
			:menu-items="menuItems"
			:placeholder="$i18n( 'sws-id-placeholder' ).text()"
			:aria-label="$i18n( 'sws-id-property' ).text()"
			@update:selected="selectItem">
			<template #no-results>
				{{ $i18n( busy ? 'sws-id-loading' : failed ? 'sws-id-unavailable' : searched ? 'sws-id-no-results' : 'sws-id-placeholder' ).text() }}
			</template>
		</CdxLookup>
		<p v-if="selectedItem?.url && !unmatched">
			<a
				:href="selectedItem.url"
				:title="selectedItem.id"
				target="_blank"
				rel="noopener noreferrer">{{ selectedItem.label }}</a>
		</p>
        <p v-if="selectedItem?.datatype">{{ $i18n( 'sws-property-datatype' ).text() }} {{ selectedItem.datatype }}</p>
        <CdxMessage v-if="datatypeError" type="error" inline>{{ datatypeError }}</CdxMessage>
        <p v-if="modelValue" class="ext-sws-id-saved">
			{{ $i18n( 'sws-id-saved' ).text() }} <code>{{ modelValue }}</code>
		</p>
		<CdxMessage
			v-if="failed"
			type="warning"
			inline>
			{{ $i18n( 'sws-id-unavailable' ).text() }}
		</CdxMessage>
		<CdxMessage
			v-else-if="unmatched && !busy"
			type="notice"
			inline>
			{{ $i18n( 'sws-id-confirm' ).text() }}
		</CdxMessage>
	</div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { CdxLookup, CdxMessage } from '@wikimedia/codex';
import { lookupPropertyIds, type IdSource, type IdSuggestion } from '@/persistence/PropertyIdLookup';

const props = defineProps<{ source: IdSource; modelValue: string | null; fieldType?: string }>();
const emit = defineEmits<{ 'update:modelValue': [ string | null ] }>();
const inputText = ref<string | number>( '' );
const selectedItem = ref<IdSuggestion | null>( null );
const selectedSource = ref<IdSource | null>( null );
const checkedFieldType = ref<string | undefined>();
const originalBinding = { source: props.source, id: props.modelValue, fieldType: props.fieldType };
function lookup( query: string, mode: 'search' | 'resolve', signal: AbortSignal ): Promise<IdSuggestion[]> {
    return props.fieldType ? lookupPropertyIds( props.source, query, mode, signal, props.fieldType ) : lookupPropertyIds( props.source, query, mode, signal );
}
const datatypeError = computed( () => props.fieldType && selectedItem.value?.datatype && selectedItem.value.compatible === false ?
    mw.message( 'sws-property-datatype-invalid', selectedItem.value.id + ': ' + selectedItem.value.datatype + ' → ' + props.fieldType ).text() : null );
const results = ref<IdSuggestion[]>( [] );
const busy = ref( false );
const failed = ref( false );
const searched = ref( false );
const unmatched = computed( () => String( inputText.value ).trim() !== '' &&
	String( inputText.value ) !== selectedItem.value?.label && String( inputText.value ) !== selectedItem.value?.id );
const menuItems = computed( () => ( selectedItem.value && !unmatched.value && !results.value.some( ( item ) => item.id === selectedItem.value?.id ) ?
	[ selectedItem.value, ...results.value ] : results.value ).map( ( item ) => ( {
	value: item.id, label: item.label, supportingText: item.id, description: item.description
} ) ) );
let sequence = 0;
let searching = false;
let resolving = false;
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | null = null;
function cancel(): number {
	clearTimeout( timer );
	searching = false;
	resolving = false;
	searched.value = false;
	controller?.abort();
	busy.value = false;
	return ++sequence;
}
onBeforeUnmount( cancel );

watch( () => [ props.source, props.modelValue, props.fieldType ], async () => {
	const ticket = cancel();
	failed.value = false;
	results.value = [];
	if ( selectedSource.value === props.source && selectedItem.value?.id === props.modelValue && checkedFieldType.value === props.fieldType ) {
		return;
	}
	// A persisted ID is already a confirmed selection. Label resolution enriches it;
	// it must not temporarily turn it into an empty Codex selection.
	selectedItem.value = props.modelValue ? { id: props.modelValue, label: props.modelValue, description: '', url: '' } : null;
	selectedSource.value = props.modelValue ? props.source : null;
	resolving = props.modelValue !== null;
	inputText.value = props.modelValue || '';
	if ( !props.modelValue ) {
		return;
	}
	controller = new AbortController();
	busy.value = true;
	try {
		const found = await lookup( props.modelValue, 'resolve', controller.signal );
		if ( ticket === sequence ) {
			resolving = false;
			selectedItem.value = found[ 0 ] ?? selectedItem.value;
            checkedFieldType.value = props.fieldType;
			selectedSource.value = props.source;
			inputText.value = found[ 0 ]?.label ?? props.modelValue;
			failed.value = found.length === 0;
		}
	} catch {
		if ( ticket === sequence ) {
			resolving = false;
			failed.value = true;
		}
	} finally {
		if ( ticket === sequence ) {
			busy.value = false;
		}
	}
}, { immediate: true } );

watch( inputText, ( value ) => {
	const text = String( value ).trim();
	if ( text === selectedItem.value?.label || text === selectedItem.value?.id || ( resolving && text === props.modelValue ) ) {
		if ( searching ) {
			cancel();
			results.value = [];
		}
		return;
	}
	const ticket = cancel();
	results.value = [];
	failed.value = false;
	if ( text === '' ) {
		selectedItem.value = null;
		// Empty search text does not erase a stored reference. Disable the assignment explicitly to remove it.
		return;
	}
	busy.value = true;
	searching = true;
	timer = setTimeout( async () => {
		controller = new AbortController();
		try {
			const found = await lookup( text, 'search', controller.signal );
			if ( ticket === sequence ) {
				results.value = found;
				searched.value = true;
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

async function selectItem( id: string | number | null ): Promise<void> {
	if ( id === null ) {
		return; // Codex clears its selection while typing; typing is not a confirmed replacement.
	}
	const item = results.value.find( ( result ) => result.id === id );
	if ( !item ) {
		return;
	}
	cancel();
	selectedItem.value = item;
	selectedSource.value = props.source;
	inputText.value = item.label;
	failed.value = false;
    if ( props.fieldType ) {
        const ticket = sequence;
        controller = new AbortController();
        busy.value = true;
        try {
            const found = await lookup( item.id, 'resolve', controller.signal );
            if ( ticket !== sequence ) { return; }
            selectedItem.value = found[ 0 ] ?? item;
            checkedFieldType.value = props.fieldType;
            failed.value = found.length === 0;
        } catch {
            if ( ticket === sequence ) { failed.value = true; }
        } finally {
            if ( ticket === sequence ) { busy.value = false; }
        }
        if ( failed.value || datatypeError.value || selectedItem.value?.compatible !== true ) { return; }
    }
    emit( 'update:modelValue', item.id );
}
function unparseableInputMessage(): string | null {
    if ( props.fieldType && ( props.modelValue || selectedItem.value ) ) {
        if ( datatypeError.value ) { return datatypeError.value; }
        if ( originalBinding.id && originalBinding.id === props.modelValue && originalBinding.source === props.source && originalBinding.fieldType === props.fieldType && !unmatched.value ) { return null; }
        if ( busy.value || failed.value || !selectedItem.value?.datatype || selectedItem.value.compatible !== true ) {
            return mw.message( 'sws-id-unavailable' ).text();
        }
    }
	// An unchanged stored reference survives a temporary outage or a deleted remote property.
	const unchanged = props.modelValue !== null &&
		( String( inputText.value ).trim() === '' || String( inputText.value ) === props.modelValue || ( selectedItem.value?.id === props.modelValue && !unmatched.value ) );
	return unchanged ? null : mw.message( 'sws-id-confirm' ).text();
}
defineExpose( { unparseableInputMessage } );
</script>
