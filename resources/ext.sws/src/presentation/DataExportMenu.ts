import type { MenuItemData } from '@wikimedia/codex';

/**
 * What the Subject UI's export controls offer, for a single Subject or for all Subjects on a page: the
 * JSON URL, the complete read-view export when available, and named stored-data projections.
 */

const NATIVE_PROJECTION = 'native';
const COMPLETE_PROJECTION = '__complete_data_view__';

function completeEnabled(): boolean {
	return mw.config?.get( 'wgSWSCompleteExport' ) === true;
}
function completeUrl( endpoint: string, format: string ): string {
	const language = String( mw.config.get( 'wgUserLanguage' ) || mw.config.get( 'wgContentLanguage' ) || 'de' );
	return `${ endpoint }?format=${ format }&language=${ encodeURIComponent( language ) }`;
}

type RdfFormat = 'turtle' | 'trig';

const RDF_FORMATS: readonly { format: RdfFormat; messageKey: string }[] = [
	{ format: 'turtle', messageKey: 'sws-managesubjects-export-format-turtle' },
	{ format: 'trig', messageKey: 'sws-managesubjects-export-format-trig' },
];

/**
 * A Mapping as the export menus see it: its name, which names its projection, and the Schemas it maps.
 */
interface MappingSummary {
	readonly name: string;
	readonly schemas: readonly string[];
}

function restApiBase(): string {
	return mw.util.wikiScript( 'rest' );
}

function projectionLabel( projection: string ): string {
	if ( projection === COMPLETE_PROJECTION ) {
		return mw.msg( 'sws-managesubjects-export-complete' );
	}
	if ( completeEnabled() && projection !== NATIVE_PROJECTION ) {
		return mw.msg( 'sws-managesubjects-export-stored-projection', projection );
	}
	return projection === NATIVE_PROJECTION ?
		mw.msg( 'sws-managesubjects-export-native' ) :
		projection;
}

export interface ExportUrls {
	jsonUrl: string;
	rdfUrl( projection: string, format: RdfFormat ): string;
}

function exportUrls( jsonUrl: string, rdfEndpoint: string, completeEndpoint: string ): ExportUrls {
	return {
		jsonUrl: completeEnabled() ? completeUrl( completeEndpoint, 'json' ) : jsonUrl,
		rdfUrl: ( projection, format ) =>
			projection === COMPLETE_PROJECTION && completeEnabled() ? completeUrl( completeEndpoint, format ) :
				`${ rdfEndpoint }?projection=${ encodeURIComponent( projection ) }&format=${ format }`,
	};
}

export function subjectExportUrls( subjectId: string ): ExportUrls {
	const base = `${ restApiBase() }/sws/v0/subject/${ encodeURIComponent( subjectId ) }`;
	return exportUrls( base, `${ base }/rdf`, `${ restApiBase() }/swsbase/v1/subject/${ encodeURIComponent( subjectId ) }/export` );
}

export function pageExportUrls( pageId: number ): ExportUrls {
	const base = `${ restApiBase() }/sws/v0/page/${ pageId }`;
	return exportUrls( `${ base }/subjects`, `${ base }/rdf`, `${ restApiBase() }/swsbase/v1/page/${ pageId }/export` );
}

/**
 * The projections that hold data for Subjects of the given Schemas: native always, and each Mapping the
 * viewing user may read that maps one of the Schemas. A Mapping projects only the Subjects whose Schema it
 * maps, so any other Mapping's export of them would be empty.
 */
export function rdfProjectionsFor( schemaNames: readonly string[] ): string[] {
	// Set, filtered to what the viewer may read, by every surface that shows the export buttons.
	const mappings = ( mw.config.get( 'wgSWSMappings' ) as MappingSummary[] | null ) ?? [];

	return [
		completeEnabled() ? COMPLETE_PROJECTION : NATIVE_PROJECTION,
		...mappings
			.filter( ( mapping ) => mapping.schemas.some( ( schema ) => schemaNames.includes( schema ) ) )
			.map( ( mapping ) => mapping.name ),
	];
}

/**
 * One item per projection and format, its value the URL it opens. Items name their projection only when
 * there is more than one to choose from.
 */
export function rdfMenuItems(
	projections: readonly string[],
	rdfUrl: ExportUrls['rdfUrl'],
): MenuItemData[] {
	return projections.flatMap( ( projection ) => RDF_FORMATS.map( ( { format, messageKey } ): MenuItemData => ( {
		value: rdfUrl( projection, format ),
		label: projections.length === 1 ?
			mw.msg( messageKey ) :
			mw.msg( 'sws-managesubjects-export-projection-format', projectionLabel( projection ), mw.msg( messageKey ) ),
	} ) ) );
}
