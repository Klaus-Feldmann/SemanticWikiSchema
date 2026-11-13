'use strict';
const Vue = require( 'vue' );
const Codex = require( '../../codex.js' );
const createComponent = require( './component.js' );
const search = require( 'ext.sws.prefixSearch.api' ).search;
$( () => {
	const input = document.getElementById( 'searchInput' );
	const scopes = mw.config.get( 'wgSWSPrefixSearchNamespaces' ) || [];
	if ( !input || !input.form || !scopes.length ) { return; }
	const form = input.form;
	const mount = document.createElement( 'div' );
	mount.className = 'sws-prefixsearch-mount';
	const focused = document.activeElement === input;
	form.before( mount );
	try {
		Vue.createMwApp( createComponent( Vue, Codex, search ), { scopes, initialInput: input.value } ).mount( mount );
		form.remove();
		if ( focused ) { mount.querySelector( 'input[type="search"]' )?.focus(); }
	} catch ( error ) {
		mount.remove();
		mw.log.error( 'SWS PrefixSearch could not initialize', error );
	}
} );
