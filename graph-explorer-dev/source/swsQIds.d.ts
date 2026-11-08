import type * as React from 'react';
export function createSWSQIdRow(react: typeof React, options?: {origin?: string; load?: (iri: string) => Promise<unknown>}): React.ComponentType<{iri: string; onChange?: () => void}>;

export function createSWSDescriptionRow(react: typeof React, options?: {origin?: string; load?: (iri: string) => Promise<unknown>}): React.ComponentType<{iri: string; onChange?: () => void}>;
