import { describe, expect, it } from 'vitest';
import { defineStore } from 'pinia';
import { SWSExtension } from '@/SWSExtension';
import MonolingualTextDisplay from '@/components/Value/MonolingualTextDisplay.vue';
import MonolingualTextInput from '@/components/Value/MonolingualTextInput.vue';
import MonolingualTextAttributesEditor from '@/components/SchemaEditor/Property/MonolingualTextAttributesEditor.vue';
import { MonolingualTextType } from '@/domain/propertyTypes/MonolingualText';
import { setupMwMock } from './VueTestHelpers.ts';

describe( 'SWSExtension registry caching', () => {
	it( 'returns the same TypeSpecificComponentRegistry instance on repeated calls', () => {
		const ext = SWSExtension.getInstance();
		expect( ext.getTypeSpecificComponentRegistry() )
			.toBe( ext.getTypeSpecificComponentRegistry() );
	} );

	it( 'offers the monolingual text type with its own display, editor, attributes editor and label', () => {
		const registry = SWSExtension.getInstance().getTypeSpecificComponentRegistry();

		expect( registry.getPropertyTypes() ).toContain( MonolingualTextType.typeName );
		expect( registry.getValueDisplayComponent( MonolingualTextType.typeName ) ).toBe( MonolingualTextDisplay );
		expect( registry.getValueEditingComponent( MonolingualTextType.typeName ) ).toBe( MonolingualTextInput );
		expect( registry.getAttributesEditor( MonolingualTextType.typeName ) ).toBe( MonolingualTextAttributesEditor );
		expect( registry.getLabel( MonolingualTextType.typeName ) ).toBe( 'sws-property-type-monolingualtext' );
	} );

	it( 'returns the same ViewTypeRegistry instance on repeated calls so extension registrations persist', () => {
		const ext = SWSExtension.getInstance();
		expect( ext.getViewTypeRegistry() )
			.toBe( ext.getViewTypeRegistry() );
	} );
} );

describe( 'SWSExtension.getPinia', () => {
	it( 'returns the same Pinia instance on every call', () => {
		const ext = SWSExtension.getInstance();
		const a = ext.getPinia();
		const b = ext.getPinia();
		expect( a ).toBe( b );
	} );

	it( 'state mutations through one store consumer are visible to another using the same Pinia', () => {
		const pinia = SWSExtension.getInstance().getPinia();
		const useTestStore = defineStore( 'test-shared-state', {
			state: () => ( { count: 0 } ),
		} );

		useTestStore( pinia ).count = 7;

		expect( useTestStore( pinia ).count ).toBe( 7 );
	} );
} );

describe( 'SWSExtension.newSubjectPermissionHints', () => {
	it( 'answers for the page being viewed with the decision the wiki sent', async () => {
		setupMwMock( { config: { wgArticleId: 42, wgSWSCanEditPageSubjects: true } } );

		const hints = SWSExtension.getInstance().newSubjectPermissionHints();

		expect( await hints.canEditSubject( 42 ) ).toBe( true );
	} );

	it( 'does not read the absence of a page as a page, as on a special page', async () => {
		setupMwMock( { config: { wgArticleId: 0, wgSWSCanEditPageSubjects: true } } );

		const hints = SWSExtension.getInstance().newSubjectPermissionHints();

		expect( await hints.canEditSubject( 0 ) ).toBe( false );
	} );
} );

describe( 'SWSExtension.isValidationEnforced', () => {
	it( 'is true only when the wiki says so', () => {
		setupMwMock( { config: { wgSWSEnforceValidation: true } } );
		expect( SWSExtension.getInstance().isValidationEnforced() ).toBe( true );
	} );

	it( 'is false when the wiki does not enforce validation', () => {
		setupMwMock( { config: { wgSWSEnforceValidation: false } } );
		expect( SWSExtension.getInstance().isValidationEnforced() ).toBe( false );
	} );

	it( 'is false when the wiki did not say, since enforcement is opt-in', () => {
		setupMwMock();
		expect( SWSExtension.getInstance().isValidationEnforced() ).toBe( false );
	} );
} );
