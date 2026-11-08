import { SWSServices, Service } from '@/SWSServices.ts';
import { SWSExtension } from '@/SWSExtension.ts';
import { InMemorySchemaRepository } from '@/application/SchemaRepository.ts';
import { InMemoryLayoutLookup } from '@/application/LayoutLookup.ts';
import { StubSubjectRepository } from '@/domain/SubjectRepository.ts';
import type { SubjectLabelSearch } from '@/domain/SubjectLabelSearch.ts';
import type { SubjectSummaryLookup } from '@/application/SubjectSummaryLookup.ts';
import type { SubjectCountLookup } from '@/application/SubjectCountLookup.ts';
import type { PageTitleSearch } from '@/domain/PageTitleSearch.ts';

export class SWSTestServices extends SWSServices {

	public static getServices(): Record<string, unknown> {
		const sws = SWSExtension.getInstance();

		return {
			[ Service.ComponentRegistry ]: sws.getTypeSpecificComponentRegistry(),
			[ Service.SchemaPermissionHints ]: sws.newSchemaPermissionHints(),
			[ Service.SubjectPermissionHints ]: sws.newSubjectPermissionHints(),
			[ Service.PropertyTypeRegistry ]: sws.getPropertyTypeRegistry(),
			[ Service.SchemaRepository ]: new InMemorySchemaRepository( [] ),
			[ Service.SubjectRepository ]: new StubSubjectRepository( [] ),
			[ Service.SubjectLabelSearch ]: { searchSubjectLabels: () => Promise.resolve( [] ) } as SubjectLabelSearch,
			[ Service.SubjectSummaryLookup ]: {
				getSubjectSummaries: () => Promise.resolve( { subjects: [], nextCursor: null } ),
				getRecentlyEditedSubjects: () => Promise.resolve( [] ),
			} as SubjectSummaryLookup,
			[ Service.SubjectCountLookup ]: {
				getSubjectCounts: () => Promise.resolve( new Map() ),
			} as SubjectCountLookup,
			[ Service.PageTitleSearch ]: { searchPageTitles: () => Promise.resolve( [] ) } as PageTitleSearch,
			[ Service.ViewTypeRegistry ]: sws.getViewTypeRegistry(),
			[ Service.LayoutPermissionHints ]: sws.newLayoutPermissionHints(),
			[ Service.LayoutRepository ]: new InMemoryLayoutLookup( [] ),
			[ Service.MappingPermissionHints ]: sws.newMappingPermissionHints(),
		};
	}

}
