import { describe, it, expect, beforeEach } from 'vitest';
import {
	subjectExportUrls, pageExportUrls, rdfProjectionsFor, rdfMenuItems,
} from '@/presentation/DataExportMenu';
import { setupMwMock } from '../VueTestHelpers.ts';

const MAPPINGS = [
	{ name: 'EDM', schemas: [ 'Artwork', 'Person' ] },
	{ name: 'CIDOC-CRM', schemas: [ 'Museum' ] },
	{ name: 'Linked Art', schemas: [ 'Artwork', 'Place' ] },
];

describe( 'DataExportMenu', () => {
	beforeEach( () => {
		setupMwMock( {
			functions: [ 'config', 'msg', 'util' ],
			config: { wgSWSMappings: MAPPINGS },
			messages: {
				'sws-managesubjects-export-native': 'Native',
				'sws-managesubjects-export-format-turtle': 'Turtle',
				'sws-managesubjects-export-format-trig': 'TriG',
				'sws-managesubjects-export-projection-format': ( projection, format ) => `${ projection } · ${ format }`,
			},
		} );
	} );

	describe( 'subjectExportUrls', () => {
		it( 'builds the JSON URL at the subject base', () => {
			expect( subjectExportUrls( 's2picasso2aaaa2' ).jsonUrl )
				.toBe( '/rest.php/sws/v0/subject/s2picasso2aaaa2' );
		} );

		it( 'builds RDF URLs with projection and format, percent-encoding the projection', () => {
			const { rdfUrl } = subjectExportUrls( 's1aaaaaaaaaaaa1' );
			expect( rdfUrl( 'native', 'turtle' ) )
				.toBe( '/rest.php/sws/v0/subject/s1aaaaaaaaaaaa1/rdf?projection=native&format=turtle' );
			expect( rdfUrl( 'Wikidata items', 'trig' ) )
				.toBe( '/rest.php/sws/v0/subject/s1aaaaaaaaaaaa1/rdf?projection=Wikidata%20items&format=trig' );
		} );
	} );

	describe( 'pageExportUrls', () => {
		it( 'targets the page subjects JSON and the page RDF endpoint', () => {
			const { jsonUrl, rdfUrl } = pageExportUrls( 42 );
			expect( jsonUrl ).toBe( '/rest.php/sws/v0/page/42/subjects' );
			expect( rdfUrl( 'EDM', 'turtle' ) )
				.toBe( '/rest.php/sws/v0/page/42/rdf?projection=EDM&format=turtle' );
		} );
	} );

	describe( 'rdfProjectionsFor', () => {
		it( 'offers only native when no Mapping maps the Schemas', () => {
			expect( rdfProjectionsFor( [ 'Attendance' ] ) ).toEqual( [ 'native' ] );
		} );

		it( 'offers every Mapping that maps one of the Schemas, in the order the Mappings come in', () => {
			expect( rdfProjectionsFor( [ 'Place', 'Person' ] ) ).toEqual( [ 'native', 'EDM', 'Linked Art' ] );
		} );
	} );

	describe( 'rdfMenuItems', () => {
		const rdfUrl = ( projection: string, format: string ): string => `RDF:${ projection }:${ format }`;

		it( 'names the formats alone when native is the only projection', () => {
			expect( rdfMenuItems( [ 'native' ], rdfUrl ) ).toEqual( [
				{ value: 'RDF:native:turtle', label: 'Turtle' },
				{ value: 'RDF:native:trig', label: 'TriG' },
			] );
		} );

		it( 'names the projection of each format when there are several projections', () => {
			expect( rdfMenuItems( [ 'native', 'EDM' ], rdfUrl ) ).toEqual( [
				{ value: 'RDF:native:turtle', label: 'Native · Turtle' },
				{ value: 'RDF:native:trig', label: 'Native · TriG' },
				{ value: 'RDF:EDM:turtle', label: 'EDM · Turtle' },
				{ value: 'RDF:EDM:trig', label: 'EDM · TriG' },
			] );
		} );
	} );
} );

describe( 'complete data view exports with Bridge', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'config', 'msg', 'util' ], config: { wgSWSCompleteExport: true, wgUserLanguage: 'de', wgSWSMappings: MAPPINGS },
			messages: { 'sws-managesubjects-export-complete': 'Complete', 'sws-managesubjects-export-stored-projection': ( name ) => `${ name } (stored)` } } );
	} );
	it( 'uses complete JSON and RDF for a page', () => {
		const urls = pageExportUrls( 42 );
		expect( urls.jsonUrl ).toBe( '/rest.php/swsbase/v1/page/42/export?format=json&language=de' );
		const projections = rdfProjectionsFor( [ 'Person' ] );
		expect( projections ).toEqual( [ '__complete_data_view__', 'EDM' ] );
		expect( urls.rdfUrl( projections[ 0 ], 'turtle' ) ).toBe( '/rest.php/swsbase/v1/page/42/export?format=turtle&language=de' );
		expect( urls.rdfUrl( projections[ 0 ], 'trig' ) ).toBe( '/rest.php/swsbase/v1/page/42/export?format=trig&language=de' );
	} );
	it( 'keeps named stored-data projections separate and available', () => {
		const urls = pageExportUrls( 42 );
		expect( urls.rdfUrl( 'EDM', 'turtle' ) ).toBe( '/rest.php/sws/v0/page/42/rdf?projection=EDM&format=turtle' );
		const menu = rdfMenuItems( rdfProjectionsFor( [ 'Person' ] ), urls.rdfUrl );
		expect( String( menu[ 2 ].label ) ).toContain( 'EDM (stored)' );
	} );
	it( 'builds a subject-scoped complete download URL', () => {
		expect( subjectExportUrls( 's1aaaaaaaaaaaa1' ).jsonUrl ).toBe( '/rest.php/swsbase/v1/subject/s1aaaaaaaaaaaa1/export?format=json&language=de' );
	} );
} );
