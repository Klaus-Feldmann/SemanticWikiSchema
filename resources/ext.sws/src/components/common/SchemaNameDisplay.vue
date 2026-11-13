<template>
	<!-- A span where the surface owns the click: a link inside a clickable row competes with it. -->
	<component
		:is="props.link === 'none' ? 'span' : 'a'"
		class="ext-sws-schema-name"
		:href="props.link === 'none' ? undefined : schemaUrl"
		:target="props.link === 'new-tab' ? '_blank' : undefined"
		:rel="props.link === 'new-tab' ? 'noopener' : undefined"
	>
		<span class="ext-sws-schema-name__noun">{{ accessibleName }}</span>
		<span
			class="ext-sws-schema-name__text"
			aria-hidden="true"
		>{{ shownLabel }}</span>
	</component>
</template>

<script lang="ts">
/** Where following the link leaves the reader. */
export type SchemaNameLink = 'same-tab' | 'new-tab' | 'none';
</script>

<script setup lang="ts">
import { getActivePinia } from 'pinia';
import { useSchemaStore } from '@/stores/SchemaStore';
import { computed, watch } from 'vue';
import { schemaSummaryLabel } from '@/presentation/schemaSummaryLabel';

const props = withDefaults( defineProps<{
	schemaName: string;
	link?: SchemaNameLink;
}>(), {
	link: 'same-tab'
} );

const schemaStore = getActivePinia() ? useSchemaStore() : null;
const shownLabel = computed( () => {
	const schema = schemaStore?.schemas.get( props.schemaName );
	const summary = schemaStore?.schemaSummaries.get( props.schemaName );
	return schema?.getLabel() ?? ( summary ? schemaSummaryLabel( summary ) : props.schemaName );
} );
// Summary-only tables may not have loaded the full schema definitions. Share the catalog
// request across badges and the picker; do not fetch every definition separately.
watch( () => props.schemaName, async ( name ) => {
	if ( !schemaStore || !/^S[1-9][0-9]*$/.test( name ) || schemaStore.schemas.has( name ) || schemaStore.schemaSummaries.has( name ) ) {
		return;
	}
	try {
		await schemaStore.fetchAllSchemaSummaries();
	} catch { /* A failed lookup leaves the stable ID visible. */ }
}, { immediate: true } );
const schemaUrl = computed( (): string => mw.util.getUrl( `Schema:${ props.schemaName }` ) );

// The badge shows the bare name, so the noun reaches a screen reader through this instead;
// the visible name is aria-hidden, or it would be announced twice.
const accessibleName = computed( (): string =>
	mw.message( 'sws-schema-label', shownLabel.value ).text()
);
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

/* Not a Codex InfoChip: that is a pill at `@font-size-small`, the size of a navigator row's
	own text, and a wall of them down the navigator. Badge and chip stay distinct shapes for
	distinct ideas — the chip means Property Type on the Schema page. */
.ext-sws-schema-name {
	display: inline-flex;
	align-items: center;
	box-sizing: @box-sizing-base;
	max-width: @size-full;
	// As a flex item it shrinks to fit, so its text ellipsizes rather than overflowing the line.
	min-width: 0;
	min-height: @size-125;
	border: @border-width-base @border-style-base @background-color-neutral;
	border-radius: @border-radius-base;
	padding: 0 @spacing-35;
	background-color: @background-color-neutral-subtle;
	color: @color-subtle;
	font-size: @font-size-x-small;
	line-height: @line-height-xx-small;

	&__text {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__noun {
		.ext-sws-visually-hidden();
	}
}

/* Codex 2.6.2's progressive-action colours. The Codex MediaWiki serves has older values and
	lacks the two subtle hover/active fills, and it defines the properties it does ship, so a
	`var()` fallback would never reach ours — hence redefining them here. `@supports` because a
	custom property holding an unsupported `light-dark()` is invalid at substitution, which would
	leave the badge with no background rather than MediaWiki's older one.

	Remove once MediaWiki 1.47 ships a Codex carrying these values. */
@supports ( color: light-dark( #000, #fff ) ) {
	.ext-sws-schema-name {
		--background-color-progressive-subtle: light-dark( #e8eeff, #1b223d );
		--background-color-progressive-subtle--hover: light-dark( #d9e2ff, #233566 );
		--background-color-progressive-subtle--active: light-dark( #b6d4fb, #3056a9 );
		--border-color-progressive--hover: light-dark( #3056a9, #88a3e8 );
		--border-color-progressive--active: light-dark( #233566, #a6bbf5 );
		--color-progressive: light-dark( #36c, #88a3e8 );
		--color-progressive--hover: light-dark( #3056a9, #a6bbf5 );
		--color-progressive--active: light-dark( #233566, #b6d4fb );
	}
}

/* Where it links, the badge is Codex's normal-weight progressive-action button; the resting
	border takes the fill so it reads flat until hovered, without leaving the box model.

	Every state is spelled out because core styles `a:visited` and underlines `a:hover, a:focus`
	at specificity (0,1,1) — a pseudo-class counts as a class — tying with a bare `a.<class>`
	and winning on load order. Only `a.<class>:<state>` at (0,1,2) beats them, and the states
	tie with one another, so source order decides among them too.

	The subtle fills are `var()` calls because Codex 1.14 has no LESS variable for them. */
a.ext-sws-schema-name {
	&,
	&:visited,
	&:hover,
	&:focus,
	&:active {
		text-decoration: none;
	}

	&,
	&:visited {
		border-color: @background-color-progressive-subtle;
		background-color: @background-color-progressive-subtle;
		color: @color-progressive;
	}

	&:hover {
		border-color: @border-color-progressive--hover;
		background-color: var( --background-color-progressive-subtle--hover, @background-color-progressive-subtle );
		color: @color-progressive--hover;
	}

	&:active {
		border-color: @border-color-progressive--active;
		background-color: var( --background-color-progressive-subtle--active, @background-color-progressive-subtle );
		color: @color-progressive--active;
	}

	&:focus-visible {
		outline: @border-width-thick solid @outline-color-progressive--focus;
	}
}

</style>
