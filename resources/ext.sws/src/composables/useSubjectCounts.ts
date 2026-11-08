import { computed, type ComputedRef, ref, shallowRef } from 'vue';
import { SWSServices } from '@/SWSServices.ts';
import { areSubjectCountsAvailable } from '@/subjectCountAvailability.ts';

export interface SubjectCounts {
	/** True while the counts are on their way. */
	subjectCountsPending: ComputedRef<boolean>;
	/** Null while the counts are not known. */
	subjectCountOf: ( schemaName: string ) => number | null;
	loadSubjectCounts: () => Promise<void>;
}

export function useSubjectCounts(): SubjectCounts {
	const lookup = SWSServices.getSubjectCountLookup();
	const shown = ref( areSubjectCountsAvailable() );
	const counts = shallowRef<Map<string, number> | null>( null );

	async function loadSubjectCounts(): Promise<void> {
		if ( !shown.value ) {
			return;
		}

		try {
			counts.value = await lookup.getSubjectCounts();
		} catch ( error ) {
			console.error( 'Failed to load subject counts:', error );
			shown.value = false;
		}
	}

	function subjectCountOf( schemaName: string ): number | null {
		return counts.value === null ? null : counts.value.get( schemaName ) ?? 0;
	}

	return {
		subjectCountsPending: computed( () => shown.value && counts.value === null ),
		subjectCountOf,
		loadSubjectCounts,
	};
}

/**
 * The text of a link to the Subjects of a Schema: how many there are where the count is known, and otherwise
 * "View all subjects".
 */
export function subjectListLinkText( subjectCount: number | null ): string {
	return subjectCount === null ?
		mw.msg( 'sws-subjects-view-all' ) :
		mw.msg( 'sws-schema-subject-count', mw.language.convertNumber( subjectCount ) );
}
