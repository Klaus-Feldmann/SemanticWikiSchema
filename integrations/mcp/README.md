# MCP integration for SemanticWikiSchema

[Complete MediaWiki MCP integration package](https://github.com/Klaus-Feldmann/SemanticWikiSchema/releases/tag/mcp-sws-0.5.0) · [Installation instructions](0.5.0/README.md) · [Verification records](0.5.0/TEST-RESULTS.json)

The adapter is a separately versioned package for MediaWiki MCP Server 0.21.0. It supplies built runtime files, source changes and a checked installation procedure. It is not installed or authenticated by the SWS extension installer. Existing wiki configuration and OAuth credentials remain in their own locations.

It exposes SWS schema/entity operations, validation, processing diagnostics and the SchemaBaseBridge Data view according to the server's tool permissions. Cypher tools additionally require a configured Neo4j endpoint.

For the recommended AI-assisted workflow see the [complete SWS reference](../../docs/reference/SWS-0.5.02.en.md#4-ai-assisted-installation-support-and-schema-design).

The adapter's MIT license and existing author notices are retained; see [LICENSE-UPSTREAM](0.5.0/LICENSE-UPSTREAM).
