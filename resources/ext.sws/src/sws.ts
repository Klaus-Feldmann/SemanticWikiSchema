import { createMwApp } from 'vue';
import type { App } from 'vue';
import type { Pinia } from 'pinia';
import '@/codex/button-focus.less';
import '@/codex/menu-button-focus.less';
import '@/codex/accordion-focus.less';
import '@/codex/menu-footer.less';
import { keepMenuEscapesFromDialogs } from '@/codex/menuEscape.ts';
import SWSApp from '@/components/SWSApp.vue';
import { CdxTooltip } from '@wikimedia/codex';
import { SWSServices } from '@/SWSServices.ts';
import SchemaDisplay from '@/components/SchemaDisplay/SchemaDisplay.vue';
import LayoutDisplay from '@/components/LayoutDisplay/LayoutDisplay.vue';
import SchemasPage from '@/components/SchemasPage/SchemasPage.vue';
import SubjectsPage from '@/components/SubjectsPage/SubjectsPage.vue';
import LayoutsPage from '@/components/LayoutsPage/LayoutsPage.vue';
import MappingsPage from '@/components/MappingsPage/MappingsPage.vue';
import SubjectsManagerPage from '@/components/SubjectsManager/SubjectsManagerPage.vue';
import CreateSubjectPage from '@/components/CreateSubjectPage/CreateSubjectPage.vue';
import SubjectPage from '@/components/SubjectPage/SubjectPage.vue';
import OverviewPage from '@/components/OverviewPage/OverviewPage.vue';
import ConfigEditor from '@/components/ConfigEditor/ConfigEditor.vue';
import type { ConfigEditorJsConfig } from '@/components/ConfigEditor/configEditing.ts';
import { MediaWikiPageSaver } from '@/persistence/MediaWikiPageSaver.ts';
import { SWSExtension } from '@/SWSExtension.ts';
import { SchemaName } from '@/domain/Schema.ts';
import type { LayoutName } from '@/domain/Layout.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';
import { LayoutDeserializer } from '@/persistence/LayoutDeserializer.ts';
import { showPendingNotification } from '@/presentation/PendingNotification.ts';
import { FrontendRegistrar } from '@/presentation/FrontendRegistrar';
import { useSubjectStore } from '@/stores/SubjectStore';
import SubjectCreatorButton from '@/components/SubjectCreator/SubjectCreatorButton.vue';
import { subjectCreatorButtonProps } from '@/components/SubjectCreator/subjectCreatorButtonProps.ts';

const SUBJECT_CREATOR_TRIGGER_SELECTOR = '[data-mw-sws-action="open-subject-creator"]';
const SUBJECT_CREATOR_BUTTON_SELECTOR = '.ext-sws-create-subject-button';

export function registerSubjectCreatorClickHandler( pinia: Pinia, signal?: AbortSignal ): void {
	document.addEventListener( 'click', ( event ) => {
		const target = event.target;
		if ( !( target instanceof Element ) ) {
			return;
		}
		const trigger = target.closest( SUBJECT_CREATOR_TRIGGER_SELECTOR );
		if ( trigger === null ) {
			return;
		}
		event.preventDefault();
		useSubjectStore( pinia ).openSubjectCreator();
	}, { signal } );
}

/**
 * Mounts a SWS Vue app, tagging its root element with `ext-sws-ui`, the scope of the Codex
 * workarounds in codex/: they apply to the Codex controls SWS renders without touching those of
 * MediaWiki core or other extensions. Each CdxDialog SWS opens carries the class itself, since
 * Codex teleports dialogs out of this root, and so do SubjectEditor and LanguageInput, which other
 * extensions can render in dialogs of their own; `ext-sws-view` marks the on-page view placeholder.
 * Focusable Codex UI SWS renders must sit inside one of these.
 */
function mountSWSApp( app: App, element: Element ): void {
	element.classList.add( 'ext-sws-ui' );
	app.mount( element );
}

function fireRegistrationHook(): void {
	const ext = SWSExtension.getInstance();
	mw.hook( 'sws.registration' ).fire(
		new FrontendRegistrar(
			ext.getTypeSpecificComponentRegistry(),
			ext.getPropertyTypeRegistry(),
			ext.getViewTypeRegistry(),
		),
	);
}

function initializeSWSApp(): void {
	queueMicrotask( () => {
		const swsApp = document.querySelector( '#mw-content-text > #ext-sws-app' );

		if ( swsApp !== null ) {
			showPendingNotification( 'sws-subject-creator-success' );
			showPendingNotification( 'sws-managesubjects-delete-success' );

			const showSubjectCreator = ( swsApp as HTMLElement ).dataset.mwSwsCreateSubject === 'true';
			const pageHasMainSubject = ( swsApp as HTMLElement ).dataset.mwSwsPageHasMainSubject === 'true';

			const ext = SWSExtension.getInstance();

			const app = createMwApp( SWSApp, {
				showSubjectCreator,
				pageHasMainSubject,
			} ).directive( 'tooltip', CdxTooltip );
			const pinia = ext.getPinia();
			app.use( pinia );
			SWSServices.registerServices( app );
			mountSWSApp( app, swsApp );
			registerSubjectCreatorClickHandler( pinia );
		}
	} );
}

function initializeSchemaView(): void {
	queueMicrotask( async () => {
		const viewSchema = document.querySelector( '#ext-sws-view-schema' );

		if ( viewSchema !== null ) {
			const ext = SWSExtension.getInstance();

			const revisionId = mw.config.get( 'wgRevisionId' );
			const schemaName = mw.config.get( 'wgTitle' ) as SchemaName;

			const restApiUrl = ext.getMediaWiki().util.wikiScript( 'rest' );
			const response = await ext.newHttpClient().get( `${ restApiUrl }/v1/revision/${ revisionId }` );

			if ( !response.ok ) {
				throw new Error( 'Error fetching schema revision' );
			}

			const data = await response.json();
			const schemaJson = JSON.parse( data.source );

			if ( schemaJson.propertyDefinitions === undefined ) {
				throw new Error( 'Schema propertyDefinitions is undefined' );
			}

			const schema = new SchemaDeserializer().deserialize( schemaName, schemaJson );

			// The Subject creator the Schema page can open reaches value inputs that use v-tooltip.
			const app = createMwApp( SchemaDisplay, { schema } ).directive( 'tooltip', CdxTooltip );
			app.use( ext.getPinia() );
			SWSServices.registerServices( app );
			mountSWSApp( app, viewSchema );
		}
	} );
}

function initializeSchemasPage(): void {
	queueMicrotask( () => {
		const schemasPage = document.getElementById( 'ext-sws-schemas' );

		if ( schemasPage !== null ) {
			const ext = SWSExtension.getInstance();

			const app = createMwApp( SchemasPage ).directive( 'tooltip', CdxTooltip );
			app.use( ext.getPinia() );
			SWSServices.registerServices( app );
			mountSWSApp( app, schemasPage );
		}
	} );
}

function initializeSubjectsPage(): void {
	queueMicrotask( () => {
		const subjectsPage = document.getElementById( 'ext-sws-subjects' );

		if ( subjectsPage !== null ) {
			const ext = SWSExtension.getInstance();
			const initialSchema = subjectsPage.dataset.mwSwsSchema ?? null;

			// The Subject creator this page opens reaches value inputs that use v-tooltip.
			const app = createMwApp( SubjectsPage, { initialSchema } ).directive( 'tooltip', CdxTooltip );
			app.use( ext.getPinia() );
			SWSServices.registerServices( app );
			mountSWSApp( app, subjectsPage );
		}
	} );
}

function initializeLayoutView(): void {
	queueMicrotask( async () => {
		const viewLayout = document.querySelector( '#ext-sws-view-layout' );

		if ( viewLayout !== null ) {
			const ext = SWSExtension.getInstance();

			const revisionId = mw.config.get( 'wgRevisionId' );
			const layoutName = mw.config.get( 'wgTitle' ) as LayoutName;

			const restApiUrl = ext.getMediaWiki().util.wikiScript( 'rest' );
			const response = await ext.newHttpClient().get( `${ restApiUrl }/v1/revision/${ revisionId }` );

			if ( !response.ok ) {
				throw new Error( 'Error fetching layout revision' );
			}

			const data = await response.json();
			const layoutJson = JSON.parse( data.source );

			const layout = new LayoutDeserializer().deserialize( layoutName, layoutJson );

			const app = createMwApp( LayoutDisplay, { layout } );
			app.use( ext.getPinia() );
			SWSServices.registerServices( app );
			mountSWSApp( app, viewLayout );
		}
	} );
}

function initializeLayoutsPage(): void {
	queueMicrotask( () => {
		const layoutsPage = document.getElementById( 'ext-sws-layouts' );

		if ( layoutsPage !== null ) {
			const ext = SWSExtension.getInstance();

			const app = createMwApp( LayoutsPage );
			app.use( ext.getPinia() );
			SWSServices.registerServices( app );
			mountSWSApp( app, layoutsPage );
		}
	} );
}

function initializeMappingsPage(): void {
	queueMicrotask( () => {
		const mappingsPage = document.getElementById( 'ext-sws-mappings' );

		if ( mappingsPage !== null ) {
			const ext = SWSExtension.getInstance();

			const app = createMwApp( MappingsPage );
			app.use( ext.getPinia() );
			SWSServices.registerServices( app );
			mountSWSApp( app, mappingsPage );
		}
	} );
}

function initializeSubjectsManagerPage(): void {
	queueMicrotask( () => {
		const subjectsManager = document.getElementById( 'ext-sws-manage-subjects' );

		if ( subjectsManager !== null ) {
			const ext = SWSExtension.getInstance();

			const app = createMwApp( SubjectsManagerPage ).directive( 'tooltip', CdxTooltip );
			const pinia = ext.getPinia();
			app.use( pinia );
			SWSServices.registerServices( app );
			mountSWSApp( app, subjectsManager );
			registerSubjectCreatorClickHandler( pinia );
		}
	} );
}

function initializeCreateSubjectPage(): void {
	queueMicrotask( () => {
		const createSubjectPage = document.getElementById( 'ext-sws-create-subject' );

		if ( createSubjectPage !== null ) {
			const ext = SWSExtension.getInstance();
			const schemaName = createSubjectPage.dataset.mwSwsSchema;

			const app = createMwApp( CreateSubjectPage, { schemaName } ).directive( 'tooltip', CdxTooltip );
			app.use( ext.getPinia() );
			SWSServices.registerServices( app );
			mountSWSApp( app, createSubjectPage );
		}
	} );
}

function initializeSubjectPage(): void {
	queueMicrotask( () => {
		const subjectPage = document.getElementById( 'ext-sws-subject' );

		const subjectId = subjectPage?.dataset.mwSwsSubjectId;

		if ( subjectPage !== null && subjectId !== undefined ) {
			const ext = SWSExtension.getInstance();

			const app = createMwApp( SubjectPage, { subjectId } );

			app.directive( 'tooltip', CdxTooltip );
			app.use( ext.getPinia() );
			SWSServices.registerServices( app );
			mountSWSApp( app, subjectPage );
		}
	} );
}

/**
 * Mounts a button, with a creator dialog of its own, on every `{{#create_subject}}` placeholder.
 */
function initializeSubjectCreatorButtons(): void {
	queueMicrotask( () => {
		const ext = SWSExtension.getInstance();

		document.querySelectorAll( SUBJECT_CREATOR_BUTTON_SELECTOR ).forEach( ( element ) => {
			const app = createMwApp(
				SubjectCreatorButton,
				subjectCreatorButtonProps( ( element as HTMLElement ).dataset ),
			).directive( 'tooltip', CdxTooltip );
			app.use( ext.getPinia() );
			SWSServices.registerServices( app );
			mountSWSApp( app, element );
		} );
	} );
}

function initializeOverviewPage(): void {
	queueMicrotask( () => {
		const overviewPage = document.getElementById( 'ext-sws-overview' );

		if ( overviewPage !== null ) {
			const ext = SWSExtension.getInstance();
			const canManageGraphStores = overviewPage.dataset.mwSwsCanManageGraphStores === 'true';
			const canEditConfiguration = overviewPage.dataset.mwSwsCanEditConfiguration === 'true';
			const wikiHasSubjects = overviewPage.dataset.mwSwsWikiHasSubjects === 'true';

			// The Subject creator this page opens reaches value inputs that use v-tooltip.
			const app = createMwApp( OverviewPage, { canManageGraphStores, canEditConfiguration, wikiHasSubjects } )
				.directive( 'tooltip', CdxTooltip );
			app.use( ext.getPinia() );
			SWSServices.registerServices( app );
			mountSWSApp( app, overviewPage );
		}
	} );
}

function initializeConfigEditor(): void {
	queueMicrotask( () => {
		const configEditor = document.getElementById( 'ext-sws-config-editor' );

		if ( configEditor === null ) {
			return;
		}

		const config = mw.config.get( 'wgSWSConfigEditor' ) as ConfigEditorJsConfig;
		const app = createMwApp( ConfigEditor, {
			...config,
			canEdit: mw.config.get( 'wgIsProbablyEditable' ) === true,
			pageName: mw.config.get( 'wgPageName' ),
			pageSaver: new MediaWikiPageSaver( SWSExtension.getInstance().getMediaWiki() ),
		} );
		mountSWSApp( app, configEditor );
	} );
}

const isTestEnvironment = typeof window !== 'undefined' &&
	( window as unknown as { swsTestMode?: boolean } ).swsTestMode === true;

if ( !isTestEnvironment ) {
	keepMenuEscapesFromDialogs();
	fireRegistrationHook();
	initializeSWSApp();
	initializeSchemaView();
	initializeLayoutView();
	initializeSchemasPage();
	initializeSubjectsPage();
	initializeLayoutsPage();
	initializeMappingsPage();
	initializeSubjectsManagerPage();
	initializeCreateSubjectPage();
	initializeSubjectPage();
	initializeSubjectCreatorButtons();
	initializeOverviewPage();
	initializeConfigEditor();
}
