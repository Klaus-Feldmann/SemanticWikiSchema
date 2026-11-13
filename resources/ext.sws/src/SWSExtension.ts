import { ImageType } from '@/domain/propertyTypes/Image';
import ImageDisplay from '@/components/Value/ImageDisplay.vue';
import ImageInput from '@/components/Value/ImageInput.vue';
import ImageAttributesEditor from '@/components/SchemaEditor/Property/ImageAttributesEditor.vue';
import { RightsBasedSubjectPermissionHints } from '@/persistence/RightsBasedSubjectPermissionHints.ts';
import { CurrentPageSubjectPermissionHints } from '@/persistence/CurrentPageSubjectPermissionHints.ts';
import { SubjectPermissionHints } from '@/application/SubjectPermissionHints.ts';
import { RightsFetcher, UserObjectBasedRightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher.ts';
import { WikitextValueType } from '@/domain/propertyTypes/WikitextValue';
import { WikitextType } from '@/domain/propertyTypes/Wikitext';
import { PageType } from '@/domain/propertyTypes/Page';
import WikitextInput from '@/components/Value/WikitextInput.vue';
import WikitextDisplay from '@/components/Value/WikitextDisplay.vue';
import WikitextAttributesEditor from '@/components/SchemaEditor/Property/WikitextAttributesEditor.vue';
import PageInput from '@/components/Value/PageInput.vue';
import PageDisplay from '@/components/Value/PageDisplay.vue';
import PageAttributesEditor from '@/components/SchemaEditor/Property/PageAttributesEditor.vue';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import TextDisplay from '@/components/Value/TextDisplay.vue';
import { UrlType } from '@/domain/propertyTypes/Url.ts';
import UrlDisplay from '@/components/Value/UrlDisplay.vue';
import { NumberType } from '@/domain/propertyTypes/Number.ts';
import NumberDisplay from '@/components/Value/NumberDisplay.vue';
import { SelectType } from '@/domain/propertyTypes/Select.ts';
import SelectDisplay from '@/components/Value/SelectDisplay.vue';
import { RelationType } from '@/domain/propertyTypes/Relation.ts';
import { DateTimeType } from '@/domain/propertyTypes/DateTime.ts';
import HistoricalDateInput from '@/components/Value/HistoricalDateInput.vue';
import HistoricalDateDisplay from '@/components/Value/HistoricalDateDisplay.vue';
import HistoricalDateAttributesEditor from '@/components/SchemaEditor/Property/HistoricalDateAttributesEditor.vue';
import { HistoricalDateType } from '@/domain/propertyTypes/HistoricalDateType';
import { DateType } from '@/domain/propertyTypes/Date.ts';
import { BooleanType } from '@/domain/propertyTypes/Boolean.ts';
import BooleanDisplay from '@/components/Value/BooleanDisplay.vue';
import { MonolingualTextType } from '@/domain/propertyTypes/MonolingualText.ts';
import MonolingualTextDisplay from '@/components/Value/MonolingualTextDisplay.vue';
import MonolingualTextInput from '@/components/Value/MonolingualTextInput.vue';
import { TypeSpecificComponentRegistry } from '@/TypeSpecificComponentRegistry.ts';
import { ViewTypeRegistry } from '@/ViewTypeRegistry.ts';
import Infobox from '@/components/Views/Infobox.vue';
import RelationDisplay from '@/components/Value/RelationDisplay.vue';
import DateTimeDisplay from '@/components/Value/DateTimeDisplay.vue';
import DateTimeInput from '@/components/Value/DateTimeInput.vue';
import DateDisplay from '@/components/Value/DateDisplay.vue';
import DateInput from '@/components/Value/DateInput.vue';
import BooleanInput from '@/components/Value/BooleanInput.vue';
import { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import { ProductionHttpClient } from '@/infrastructure/HttpClient/ProductionHttpClient';
import { RestSchemaRepository } from '@/persistence/RestSchemaRepository.ts';
import { SchemaRepository } from '@/application/SchemaRepository.ts';
import { RestEditNoticeRepository } from '@/persistence/RestEditNoticeRepository.ts';
import { EditNoticeRepository } from '@/application/EditNoticeRepository.ts';
import { LayoutRepository } from '@/application/LayoutRepository.ts';
import { RestLayoutRepository } from '@/persistence/RestLayoutRepository.ts';
import { LayoutSerializer } from '@/persistence/LayoutSerializer.ts';
import { LayoutDeserializer } from '@/persistence/LayoutDeserializer.ts';
import { LayoutPermissionHints } from '@/application/LayoutPermissionHints.ts';
import { RightsBasedLayoutPermissionHints } from '@/persistence/RightsBasedLayoutPermissionHints.ts';
import { MappingPermissionHints } from '@/application/MappingPermissionHints.ts';
import { RightsBasedMappingPermissionHints } from '@/persistence/RightsBasedMappingPermissionHints.ts';
import { CsrfSendingHttpClient } from '@/infrastructure/HttpClient/CsrfSendingHttpClient.ts';
import { SchemaSerializer } from '@/persistence/SchemaSerializer.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';
import { RightsBasedSchemaPermissionHints } from '@/persistence/RightsBasedSchemaPermissionHints.ts';
import { SchemaPermissionHints } from '@/application/SchemaPermissionHints.ts';
import { SubjectRepository } from '@/domain/SubjectRepository.ts';
import { RestSubjectRepository } from '@/persistence/RestSubjectRepository.ts';
import { SubjectLabelSearch } from '@/domain/SubjectLabelSearch.ts';
import type { PageTitleSearch } from '@/domain/PageTitleSearch.ts';
import { RestSubjectLabelSearch } from '@/persistence/RestSubjectLabelSearch.ts';
import type { SubjectSummaryLookup } from '@/application/SubjectSummaryLookup.ts';
import { RestSubjectSummaryLookup } from '@/persistence/RestSubjectSummaryLookup.ts';
import type { SubjectCountLookup } from '@/application/SubjectCountLookup.ts';
import { RestSubjectCountLookup } from '@/persistence/RestSubjectCountLookup.ts';
import { RestPageTitleSearch } from '@/persistence/RestPageTitleSearch.ts';
import TextInput from '@/components/Value/TextInput.vue';
import UrlInput from '@/components/Value/UrlInput.vue';
import NumberInput from '@/components/Value/NumberInput.vue';
import SelectInput from '@/components/Value/SelectInput.vue';
import RelationInput from '@/components/Value/RelationInput.vue';
import { MediaWikiPageSaver } from '@/persistence/MediaWikiPageSaver.ts';
import { SubjectDeserializer } from '@/persistence/SubjectDeserializer.ts';
import { Neo } from '@/Neo.ts';
// import { cdxIconStringInteger } from '@/assets/CustomIcons.ts';
import { cdxIconImage, cdxIconLink, cdxIconSearchCaseSensitive, cdxIconArticles, cdxIconListBullet, cdxIconMathematics, cdxIconClock, cdxIconCalendar, cdxIconCheck, cdxIconAlert, cdxIconLanguage } from '@wikimedia/codex-icons';
import UnregisteredTypeValueDisplay from '@/components/Value/UnregisteredTypeValueDisplay.vue';
import UnregisteredTypeValueInput from '@/components/Value/UnregisteredTypeValueInput.vue';
import UnregisteredTypeAttributesEditor from '@/components/SchemaEditor/Property/UnregisteredTypeAttributesEditor.vue';
import TextAttributesEditor from '@/components/SchemaEditor/Property/TextAttributesEditor.vue';
import MonolingualTextAttributesEditor from '@/components/SchemaEditor/Property/MonolingualTextAttributesEditor.vue';
import NumberAttributesEditor from '@/components/SchemaEditor/Property/NumberAttributesEditor.vue';
import SelectAttributesEditor from '@/components/SchemaEditor/Property/SelectAttributesEditor.vue';
import UrlAttributesEditor from '@/components/SchemaEditor/Property/UrlAttributesEditor.vue';
import RelationAttributesEditor from '@/components/SchemaEditor/Property/RelationAttributesEditor.vue';
import DateTimeAttributesEditor from '@/components/SchemaEditor/Property/DateTimeAttributesEditor.vue';
import DateAttributesEditor from '@/components/SchemaEditor/Property/DateAttributesEditor.vue';
import BooleanAttributesEditor from '@/components/SchemaEditor/Property/BooleanAttributesEditor.vue';
import { PropertyTypeRegistry } from '@/domain/PropertyType.ts';
import { StoreStateLoader } from '@/persistence/StoreStateLoader.ts';
import { createPinia } from 'pinia';
import type { Pinia } from 'pinia';

export class SWSExtension {
	private static instance: SWSExtension;

	public static getInstance(): SWSExtension {
		SWSExtension.instance ??= new SWSExtension();
		return SWSExtension.instance;
	}

	private rightsFetcher: RightsFetcher|undefined;

	private typeSpecificComponentRegistry: TypeSpecificComponentRegistry | undefined;

	public getTypeSpecificComponentRegistry(): TypeSpecificComponentRegistry {
		this.typeSpecificComponentRegistry ??= this.newTypeSpecificComponentRegistry();
		return this.typeSpecificComponentRegistry;
	}

	private newTypeSpecificComponentRegistry(): TypeSpecificComponentRegistry {
		const registry = new TypeSpecificComponentRegistry();

		registry.registerType( WikitextType.typeName, {
			valueDisplayComponent: WikitextDisplay, valueEditor: WikitextInput, attributesEditor: WikitextAttributesEditor,
			label: 'sws-property-type-template', icon: cdxIconSearchCaseSensitive,
		} );
		registry.registerType( WikitextValueType.typeName, {
			valueDisplayComponent: WikitextDisplay, valueEditor: WikitextInput, attributesEditor: TextAttributesEditor,
			label: 'sws-property-type-wikitext', icon: cdxIconSearchCaseSensitive,
		} );
		registry.registerType( PageType.typeName, {
			valueDisplayComponent: PageDisplay, valueEditor: PageInput,
			attributesEditor: PageAttributesEditor, label: 'sws-property-type-page', icon: cdxIconLink,
		} );

		registry.registerType( TextType.typeName, {
			valueDisplayComponent: TextDisplay,
			valueEditor: TextInput,
			attributesEditor: TextAttributesEditor,
			label: 'sws-property-type-text',
			icon: cdxIconSearchCaseSensitive,
		} );

		registry.registerType( ImageType.typeName, {
			valueDisplayComponent: ImageDisplay, valueEditor: ImageInput, attributesEditor: ImageAttributesEditor,
			label: 'sws-property-type-image', icon: cdxIconImage,
		} );

		registry.registerType( UrlType.typeName, {
			valueDisplayComponent: UrlDisplay,
			valueEditor: UrlInput,
			attributesEditor: UrlAttributesEditor,
			label: 'sws-property-type-url',
			icon: cdxIconLink,
		} );

		registry.registerType( NumberType.typeName, {
			valueDisplayComponent: NumberDisplay,
			valueEditor: NumberInput,
			attributesEditor: NumberAttributesEditor,
			label: 'sws-property-type-number',
			icon: cdxIconMathematics,
		} );

		registry.registerType( SelectType.typeName, {
			valueDisplayComponent: SelectDisplay,
			valueEditor: SelectInput,
			attributesEditor: SelectAttributesEditor,
			label: 'sws-property-type-select',
			icon: cdxIconListBullet,
		} );

		registry.registerType( RelationType.typeName, {
			valueDisplayComponent: RelationDisplay,
			valueEditor: RelationInput,
			attributesEditor: RelationAttributesEditor,
			label: 'sws-property-type-relation',
			icon: cdxIconArticles,
		} );

		registry.registerType( DateTimeType.typeName, {
			valueDisplayComponent: DateTimeDisplay,
			valueEditor: DateTimeInput,
			attributesEditor: DateTimeAttributesEditor,
			label: 'sws-property-type-datetime',
			icon: cdxIconClock,
		} );

		registry.registerType( HistoricalDateType.typeName, {
			valueDisplayComponent: HistoricalDateDisplay,
			valueEditor: HistoricalDateInput,
			attributesEditor: HistoricalDateAttributesEditor,
			label: 'sws-property-type-historicaldate',
			icon: cdxIconCalendar,
		} );

		registry.registerType( DateType.typeName, {
			valueDisplayComponent: DateDisplay,
			valueEditor: DateInput,
			attributesEditor: DateAttributesEditor,
			label: 'sws-property-type-date',
			icon: cdxIconCalendar,
		} );

		registry.registerType( MonolingualTextType.typeName, {
			valueDisplayComponent: MonolingualTextDisplay,
			valueEditor: MonolingualTextInput,
			attributesEditor: MonolingualTextAttributesEditor,
			label: 'sws-property-type-monolingualtext',
			icon: cdxIconLanguage,
		} );

		registry.registerType( BooleanType.typeName, {
			valueDisplayComponent: BooleanDisplay,
			valueEditor: BooleanInput,
			attributesEditor: BooleanAttributesEditor,
			label: 'sws-property-type-boolean',
			icon: cdxIconCheck,
		} );

		// Render any property type that is not registered (e.g. owned by a disabled
		// or failed extension) with read-only placeholders instead of throwing.
		registry.setUnregisteredTypeFallback( {
			valueDisplayComponent: UnregisteredTypeValueDisplay,
			valueEditor: UnregisteredTypeValueInput,
			attributesEditor: UnregisteredTypeAttributesEditor,
			label: 'sws-property-type-unregistered',
			icon: cdxIconAlert,
		} );

		return registry;
	}

	private viewTypeRegistry: ViewTypeRegistry | undefined;

	public getViewTypeRegistry(): ViewTypeRegistry {
		this.viewTypeRegistry ??= this.newViewTypeRegistry();
		return this.viewTypeRegistry;
	}

	private newViewTypeRegistry(): ViewTypeRegistry {
		const registry = new ViewTypeRegistry();
		registry.registerType( 'infobox', Infobox );
		return registry;
	}

	private pinia: Pinia | undefined;

	public getPinia(): Pinia {
		this.pinia ??= createPinia();
		return this.pinia;
	}

	public getMediaWiki(): typeof mw {
		return window.mw;
	}

	public newSubjectPermissionHints(): SubjectPermissionHints {
		const globalRightsHints = new RightsBasedSubjectPermissionHints(
			this.getUserObjectBasedRightsFetcher(),
		);

		const config = this.getMediaWiki()?.config;
		const canEditPageSubjects = config?.get( 'wgSWSCanEditPageSubjects' );
		const currentPageId = Number( config?.get( 'wgArticleId' ) );

		// A decision arrives only from a page that holds Subjects, so Schema pages, Layout pages and
		// special pages have none and keep the wiki-global rights. So does a title with no page
		// behind it, whose id is 0 and whose decision therefore describes no page at all.
		if ( typeof canEditPageSubjects === 'boolean' && currentPageId > 0 ) {
			return new CurrentPageSubjectPermissionHints(
				currentPageId,
				canEditPageSubjects,
				globalRightsHints,
			);
		}

		return globalRightsHints;
	}

	public getUserObjectBasedRightsFetcher(): RightsFetcher {
		if ( this.rightsFetcher === undefined ) {
			this.rightsFetcher = new UserObjectBasedRightsFetcher();
		}
		return this.rightsFetcher;
	}

	public getSchemaRepository(): SchemaRepository {
		return new RestSchemaRepository(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
			new SchemaSerializer(),
			new SchemaDeserializer(),
			new MediaWikiPageSaver( this.getMediaWiki() ),
		);
	}

	public getEditNoticeRepository(): EditNoticeRepository {
		return new RestEditNoticeRepository(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
		);
	}

	public getLayoutRepository(): LayoutRepository {
		return new RestLayoutRepository(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
			new LayoutSerializer(),
			new LayoutDeserializer(),
			new MediaWikiPageSaver( this.getMediaWiki() ),
		);
	}

	public newLayoutPermissionHints(): LayoutPermissionHints {
		return new RightsBasedLayoutPermissionHints(
			this.getUserObjectBasedRightsFetcher(),
		);
	}

	public newMappingPermissionHints(): MappingPermissionHints {
		return new RightsBasedMappingPermissionHints(
			this.getUserObjectBasedRightsFetcher(),
		);
	}

	public newHttpClient(): HttpClient {
		return new CsrfSendingHttpClient(
			new ProductionHttpClient(),
		);
	}

	public newSchemaPermissionHints(): SchemaPermissionHints {
		return new RightsBasedSchemaPermissionHints(
			this.getUserObjectBasedRightsFetcher(),
		);
	}

	public getSubjectLabelSearch(): SubjectLabelSearch {
		return new RestSubjectLabelSearch(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
		);
	}

	public getSubjectSummaryLookup(): SubjectSummaryLookup {
		return new RestSubjectSummaryLookup(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
		);
	}

	public getSubjectCountLookup(): SubjectCountLookup {
		return new RestSubjectCountLookup(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
		);
	}

	public getPageTitleSearch(): PageTitleSearch {
		return new RestPageTitleSearch(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
		);
	}

	public getSubjectRepository(): SubjectRepository {
		return new RestSubjectRepository(
			this.getMediaWiki().util.wikiScript( 'rest' ),
			this.newHttpClient(),
			this.getSubjectDeserializer(),
			new SchemaDeserializer(),
			this.getRevisionId(),
		);
	}

	private getRevisionId(): number | undefined {
		const current = mw.config.get( 'wgRevisionId' );
		const latest = mw.config.get( 'wgCurRevisionId' );
		// wgRevisionId is 0 on action pages and other non-revision contexts; treat as "latest".
		if ( !current || current === latest ) {
			return undefined;
		}
		return current;
	}

	public getSubjectDeserializer(): SubjectDeserializer {
		return this.getNeo().getSubjectDeserializer();
	}

	private getNeo(): Neo {
		return Neo.getInstance();
	}

	public getValidationDebounceMs(): number {
		const value = mw.config.get( 'wgSWSValidationDebounceMs' );
		return typeof value === 'number' ? value : 300;
	}

	/** Whether the wiki rejects writes that introduce new error-severity violations (`$wgSWSEnforceValidation`). */
	public isValidationEnforced(): boolean {
		return mw.config.get( 'wgSWSEnforceValidation' ) === true;
	}

	public getPropertyTypeRegistry(): PropertyTypeRegistry {
		return this.getNeo().getPropertyTypeRegistry();
	}

	public getStoreStateLoader(): StoreStateLoader {
		return new StoreStateLoader(
			this.getSubjectRepository(),
			this.getSchemaRepository(),
			this.getLayoutRepository(),
		);
	}

}
