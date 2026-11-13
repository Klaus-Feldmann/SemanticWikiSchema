'use strict';
/** Framework injection keeps the same component testable outside ResourceLoader. */
module.exports = function createPrefixSearch( Vue, Codex, search ) {
	return Vue.defineComponent( {
		name: 'SWSPrefixSearch',
		props: { scopes: { type: Array, required: true }, initialInput: { type: String, default: '' } },
		setup( props ) {
			const input = Vue.ref( props.initialInput );
			const selected = Vue.ref( props.scopes[ 0 ].id );
			const results = Vue.ref( [] );
			let timer, sequence = 0;
			try {
				const saved = sessionStorage.getItem( 'sws-prefixsearch-namespace' );
				if ( props.scopes.some( ( scope ) => String( scope.id ) === saved ) ) {
					selected.value = Number( saved );
				}
			} catch ( e ) { /* Storage may be unavailable. */ }
			function update( value ) {
				input.value = value;
				clearTimeout( timer );
				const current = ++sequence;
				results.value = [];
				if ( !value.trim() ) { return; }
				timer = setTimeout( async () => {
					try {
						const pages = await search( value, 10, selected.value );
						if ( current !== sequence ) { return; }
						results.value = pages.map( ( page ) => ( {
							value: page.title, label: page.displayTitle || page.title,
							url: mw.util.getUrl( page.title ),
							description: page.description, thumbnail: page.thumbnail,
							match: page.redirectFrom ? mw.msg( 'sws-prefixsearch-redirect', page.redirectFrom ) : undefined
						} ) );
					} catch ( e ) {
						if ( current === sequence ) {
							mw.notify( mw.msg( 'sws-prefixsearch-error' ), { type: 'warn', tag: 'sws-prefixsearch' } );
						}
					}
				}, 200 );
			}
			function changeScope( value ) {
				selected.value = value;
				try { sessionStorage.setItem( 'sws-prefixsearch-namespace', String( value ) ); } catch ( e ) {}
				update( input.value );
			}
			Vue.onBeforeUnmount( () => { sequence++; clearTimeout( timer ); } );
			Vue.onMounted( () => { if ( input.value ) { update( input.value ); } } );
			return () => {
				const params = new URLSearchParams( { title: 'Special:Search', search: input.value,
					fulltext: '1', profile: 'advanced', [ 'ns' + selected.value ]: '1' } );
				const hidden = ( name, value ) => Vue.h( 'input', { type: 'hidden', name, value } );
				return Vue.h( 'div', { class: 'sws-prefixsearch' }, [
					Vue.h( 'span', { class: 'sws-prefixsearch-scope ext-sws-content-width-select' }, [
						Vue.h( 'span', { class: 'ext-sws-content-width-select__measure', 'aria-hidden': 'true' },
							props.scopes.map( ( scope ) => Vue.h( 'span', {}, scope.label ) ) ),
						Vue.h( Codex.CdxSelect, {
						selected: selected.value,
						menuItems: props.scopes.map( ( scope ) => ( { value: scope.id, label: scope.label } ) ),
						'aria-label': mw.msg( 'sws-prefixsearch-namespace' ),
						'onUpdate:selected': changeScope
					} ) ] ),
					Vue.h( Codex.CdxTypeaheadSearch, {
						id: 'searchform', class: 'sws-prefixsearch-input', useButton: true,
						buttonLabel: mw.msg( 'searchbutton' ), initialInputValue: props.initialInput,
						placeholder: mw.msg( 'searchsuggest-search' ),
						formAction: mw.config.get( 'wgScript' ), searchResults: results.value,
						searchFooterUrl: mw.config.get( 'wgScript' ) + '?' + params.toString(),
						highlightQuery: true, showThumbnail: true, autoExpandWidth: false,
						visibleItemLimit: 10, onInput: update
					}, {
						default: () => [ hidden( 'title', 'Special:Search' ), hidden( 'fulltext', '1' ),
							hidden( 'profile', 'advanced' ), hidden( 'ns' + selected.value, '1' ) ],
						'search-footer-text': () => mw.msg( 'sws-prefixsearch-fullsearch', input.value )
					} )
				] );
			};
		}
	} );
};
