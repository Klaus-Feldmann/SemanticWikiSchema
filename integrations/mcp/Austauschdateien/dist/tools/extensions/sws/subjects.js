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
        statements,
    };
}
export function writeResult(data, subjectId) {
    return {
        violations: Array.isArray(data.violations) ? data.violations : [],
        ...(data.subject !== undefined ? { subject: flattenSubject(data.subject, subjectId) } : {}),
    };
}
