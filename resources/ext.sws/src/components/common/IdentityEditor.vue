<template>
	<details class="ext-sws-identity-editor">
		<summary>{{ $i18n( 'sws-identity-terms' ).text() }}</summary>
		<p v-if="id" class="ext-sws-identity-editor__id">
			{{ id }}
		</p>
		<div
			v-for="language in languages"
			:key="language"
			class="ext-sws-identity-editor__row">
			<strong :lang="language">{{ language }}</strong>
			<label>{{ $i18n( 'sws-identity-label' ).text() }}
				<input
					:value="modelValue.labels[ language ] ?? ''"
					:lang="language"
					maxlength="1024"
					@input="setTerm( 'labels', language, ( $event.target as HTMLInputElement ).value )">
			</label>
			<label>{{ $i18n( 'sws-identity-description' ).text() }}
				<input
					:value="modelValue.descriptions[ language ] ?? ''"
					:lang="language"
					maxlength="1024"
					@input="setTerm( 'descriptions', language, ( $event.target as HTMLInputElement ).value )">
			</label>
		</div>
		<div class="ext-sws-identity-editor__add">
			<label>{{ $i18n( 'sws-identity-language' ).text() }}
				<input
					v-model="newLanguage"
					placeholder="de / en"
					autocapitalize="none"
					@keydown.enter.prevent="addLanguage">
			</label>
			<button
				type="button"
				:disabled="!validLanguage"
				@click="addLanguage">
				{{ $i18n( 'sws-identity-add-language' ).text() }}
			</button>
		</div>
	</details>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import type { LocalizedIdentity } from '@/domain/LocalizedIdentity';
const props = defineProps<{ modelValue: LocalizedIdentity; id?: string | null }>();
const emit = defineEmits<{ 'update:modelValue': [ value: LocalizedIdentity ] }>();
const added = ref<string[]>( [] );
const newLanguage = ref( '' );
const languages = computed( () => [ ...new Set( [ props.modelValue.identityLanguage, ...Object.keys( props.modelValue.labels ), ...Object.keys( props.modelValue.descriptions ), ...added.value ] ) ] );
const validLanguage = computed( () => {
	const parts = newLanguage.value.trim().split( '-' );
	return parts.length <= 6 && /^[a-z]{1,8}$/.test( parts[ 0 ] ) && parts.slice( 1 ).every( ( part ) => /^[a-z0-9]{1,8}$/.test( part ) );
} );
function addLanguage(): void {
	if ( validLanguage.value ) {
		added.value.push( newLanguage.value.trim() );
		newLanguage.value = '';
	}
}
function setTerm( kind: 'labels' | 'descriptions', language: string, text: string ): void {
	const terms = { ...props.modelValue[ kind ] };
	if ( text === '' ) {
		delete terms[ language ];
	} else {
		terms[ language ] = text;
	}
	emit( 'update:modelValue', { ...props.modelValue, [ kind ]: terms } );
}
</script>

<style lang="less">
.ext-sws-identity-editor {
	margin-block: 0.75em;

	summary {
		cursor: pointer;
	}

	&__row {
		display: grid;
		grid-template-columns: 4em minmax( 8em, 1fr ) minmax( 12em, 2fr );
		gap: 0.75em;
		margin-block: 0.5em;
		align-items: end;
	}

	label {
		display: flex;
		flex-direction: column;
		font-size: 0.875em;
	}

	input {
		box-sizing: border-box;
		width: 100%;
		min-width: 0;
		color: inherit;
		background: var( --background-color-base, transparent );
		border: 1px solid var( --border-color-base, #72777d );
		padding: 0.3em;
	}

	&__add {
		display: flex;
		gap: 0.75em;
		align-items: end;
	}

	&__id {
		font-size: 0.875em;
	}
}
</style>
