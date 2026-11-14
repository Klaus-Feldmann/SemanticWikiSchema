export function flattenSubject(subject, fallbackId) {
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
export function writeResult(data, subjectId) {
    return {
        violations: Array.isArray(data.violations) ? data.violations : [],
        ...(data.subject !== undefined ? { subject: flattenSubject(data.subject, subjectId) } : {}),
    };
}
