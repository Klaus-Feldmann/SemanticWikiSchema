import { z } from 'zod';
import { HELP_TOPICS, HELP_VERSION, helpTopic } from '../agent/help.js';
const inputSchema = {
    topic: z
        .enum(HELP_TOPICS)
        .describe('Bundled skill topic; overview describes the account profile and source priorities.'),
};
export const getSkillTopic = {
    name: 'get-skill-topic',
    wikiScoped: false,
    description: 'Returns versioned local help about SWS, the assistant role, querying, editing or authentication. Available without a wiki login; no Internet lookup or wiki write is performed.',
    inputSchema,
    annotations: {
        title: 'Get skill topic',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: false,
    },
    async handle({ topic }, ctx) {
        return ctx.format.ok({ topic, helpVersion: HELP_VERSION, text: helpTopic(topic) });
    },
};
