import type { ExternalField } from '@/composables/useExternalStatements';

export function sourceNoteForField( field?: ExternalField ): ExternalField | undefined {
	if ( !field || field.storageMode === 'store' && field.recorded === false ) {
		return undefined;
	}
	const status = field.sourceStatus || field.status;
	return [ 'unavailable', 'missing', 'unsupported', 'manual' ].includes( status ) ? { ...field, status } : undefined;
}
