import { describe, expect, it } from 'vitest';
import { subjectCreatorButtonProps } from '@/components/SubjectCreator/subjectCreatorButtonProps.ts';

function propsOf( dataset: Record<string, string> ): ReturnType<typeof subjectCreatorButtonProps> {
	return subjectCreatorButtonProps( dataset as DOMStringMap );
}

describe( 'subjectCreatorButtonProps', () => {
	it( 'passes on the schema and the label', () => {
		const props = propsOf( { mwSwsSchema: 'Person', mwSwsText: 'Add a person' } );

		expect( props.schemaName ).toBe( 'Person' );
		expect( props.text ).toBe( 'Add a person' );
	} );

	it( 'leaves the schema and the text unset when the placeholder has neither', () => {
		const props = propsOf( { mwSwsPage: 'new' } );

		expect( props.schemaName ).toBeUndefined();
		expect( props.text ).toBeUndefined();
	} );

	it( 'defaults to an unfixed new page when no page is named', () => {
		expect( propsOf( {} ).initialPage ).toEqual( { choice: 'newPage', fixed: false } );
	} );

	it( 'fixes a new page for page=new', () => {
		expect( propsOf( { mwSwsPage: 'new' } ).initialPage ).toEqual( { choice: 'newPage', fixed: true } );
	} );

	it( 'fixes this page for page=this', () => {
		const props = propsOf( { mwSwsPage: 'this', mwSwsPageHasMainSubject: 'true' } );

		expect( props.initialPage ).toEqual( { choice: 'thisPage', fixed: true } );
	} );

	it( 'maps an existing named page to anotherPage', () => {
		const props = propsOf( {
			mwSwsPageTitle: 'The target page',
			mwSwsPageId: '42',
		} );

		expect( props.initialPage ).toEqual( {
			choice: 'anotherPage',
			page: { pageId: 42, title: 'The target page' },
			fixed: true,
		} );
	} );

	it( 'maps a missing named page to a titled new page', () => {
		const props = propsOf( {
			mwSwsPageTitle: 'Not a page yet',
			mwSwsPageId: '0',
		} );

		expect( props.initialPage ).toEqual( {
			choice: 'newPage',
			page: { pageId: null, title: 'Not a page yet' },
			fixed: true,
		} );
	} );

	it( 'reads hostPage from the main-subject attribute', () => {
		expect( propsOf( { mwSwsPageHasMainSubject: 'true' } ).hostPage ).toEqual( { hasMainSubject: true } );
		expect( propsOf( { mwSwsPageHasMainSubject: 'false' } ).hostPage ).toEqual( { hasMainSubject: false } );
	} );

	it( 'leaves hostPage null without the main-subject attribute', () => {
		expect( propsOf( { mwSwsPage: 'new' } ).hostPage ).toBeNull();
	} );
} );
