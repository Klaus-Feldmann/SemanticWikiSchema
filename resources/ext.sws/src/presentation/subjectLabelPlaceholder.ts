import type { Subject } from '@/domain/Subject';

export function subjectLabelPlaceholder( subject: Subject ): string {
	return subject.getLabel() === null && !subject.hasGeneratedDisplayName() ?
		subject.getDisplayName() :
		mw.msg( 'sws-subject-no-label' );
}
