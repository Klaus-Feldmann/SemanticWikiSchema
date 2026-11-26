# Semantic WikiSchema 0.7.0.2

Changes since the published 0.6.0 release.

- **AI integration.** User AI (UAI) is the optional assistant integration. This release removes the patch for the former standalone MediaWiki MCP server. Category-based searches can combine entity data with wiki page memberships, including categories assigned by transcluded templates.
- **Entity editing.** Direct authenticated SWS REST access uses a MediaWiki browser session. Authorized UAI requests use the designated endpoints. An entity replacement can include a base revision. SWS rejects a stale base with HTTP 409.
- **Search.** Title search finds schemas by label. Entity search uses labels and descriptions.
- **Wikitext.** Free multiline entity wikitext renders on its own wiki page and stays on that page when the template is transcluded.
- **Field validation.** SWS processing adds a configurable tracking category to pages with invalid field values or missing required values.
- **Category fields.** Field labels survive category-default normalization.
- **Special:Version.** SWS appears under semantic extensions, and SchemaBaseBridge appears under Wikibase extensions.

[Installation](docs/operations/install/README.md) · [Direct upgrade from 0.6.0](docs/operations/upgrade-0.7.0.2.md) · [User AI](https://www.mediawiki.org/wiki/Extension:User_AI)
