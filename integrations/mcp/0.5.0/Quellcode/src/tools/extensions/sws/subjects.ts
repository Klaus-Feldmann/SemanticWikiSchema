interface SubjectStatement {
	propertyType?: string;
	value?: unknown;
}

export interface SubjectData {
	formatVersion?: number;
	identityLanguage?: string;
	labels?: Record<string, string>;
	descriptions?: Record<string, string>;
	fieldKeyVersion?: number;
	displayNameIsGenerated?: boolean;
	pageNamespaceId?: number;

	id?: string;
	label?: string | null;
	displayName?: string;
	schema?: string | { id: string };
	hostSubject?: unknown;
	pageId?: number;
	pageTitle?: string;
	statements?: Record<string, SubjectStatement>;
	fieldNotes?: Record<string, string>;
}

export function flattenSubject(
	subject: SubjectData,
	fallbackId: string,
): {
	formatVersion?: number;
	identityLanguage?: string;
	labels?: Record<string, string>;
	descriptions?: Record<string, string>;
	fieldKeyVersion?: number;
	displayNameIsGenerated?: boolean;
	pageNamespaceId?: number;
	id: string;
	label: string;
	displayName: string;
	schema: string | { id: string };
	hostSubject?: unknown;
	pageId?: number;
	pageTitle?: string;
	fieldNotes?: Record<string, string>;
	statements: Array<{ property: string; type: string; value: unknown }>;
} {
	const statements = Object.entries(subject.statements ?? {}).map(([property, statement]) => ({
		property,
		type: typeof statement.propertyType === 'string' ? statement.propertyType : '',
		value: statement.value,
	}));
	return {
		id: subject.id ?? fallbackId,
		label: subject.label ?? '',
		displayName: subject.displayName ?? '',
		schema: subject.schema ?? '',
		...(subject.hostSubject !== undefined ? { hostSubject: subject.hostSubject } : {}),
		...(subject.pageId !== undefined ? { pageId: subject.pageId } : {}),
		...(subject.pageTitle !== undefined ? { pageTitle: subject.pageTitle } : {}),
		...(subject.formatVersion !== undefined ? { formatVersion: subject.formatVersion } : {}),
		...(subject.identityLanguage !== undefined
			? { identityLanguage: subject.identityLanguage }
			: {}),
		...(subject.labels !== undefined ? { labels: subject.labels } : {}),
		...(subject.descriptions !== undefined ? { descriptions: subject.descriptions } : {}),
		...(subject.fieldKeyVersion !== undefined ? { fieldKeyVersion: subject.fieldKeyVersion } : {}),
		...(subject.displayNameIsGenerated !== undefined
			? { displayNameIsGenerated: subject.displayNameIsGenerated }
			: {}),
		...(subject.pageNamespaceId !== undefined ? { pageNamespaceId: subject.pageNamespaceId } : {}),
		statements,
		...(subject.fieldNotes !== undefined ? { fieldNotes: subject.fieldNotes } : {}),
	};
}

export interface SubjectWriteResponse {
	status?: string;
	subjectId?: string;
	violations?: unknown[];
	subject?: SubjectData;
}

export function writeResult(
	data: SubjectWriteResponse,
	subjectId: string,
): { violations: unknown[]; subject?: ReturnType<typeof flattenSubject> } {
	return {
		violations: Array.isArray(data.violations) ? data.violations : [],
		...(data.subject !== undefined ? { subject: flattenSubject(data.subject, subjectId) } : {}),
	};
}
