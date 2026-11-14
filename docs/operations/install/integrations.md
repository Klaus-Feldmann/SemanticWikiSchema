# Optional sources, SMW and MCP

For the operator adding external data or semantic querying after local data entry works.

## Wikidata and local Wikibase

SchemaBaseBridge is included. A source-bound field needs the source entity association and a compatible P-ID. Own SWS values and labels have precedence over source values and labels.

The optional helper in `integrations/registration/` registers Wikidata Q-IDs as page metadata. Load it only if that workflow is wanted:

```php
$wgAewiWikidataAutoNamespaces = [ NS_MAIN ];
require_once "$IP/extensions/SemanticWikiSchema/integrations/registration/AewiWikidataRegistrierung.php";
```

This helper is an AufEinWort integration, not a prerequisite for local SWS data entry. Its automatic association searches German Wikipedia. Inspect [its documentation](../../../integrations/registration/README.md) before enabling it. Imported Wikipedia Lua modules using UnlinkedWikibase have their own fetch/cache/jobs path; SWS's own source store does not replace that path.

## Semantic MediaWiki

Install and configure SMW separately. SWS can generate annotations from configured fields and field notes; the default property name is schema-scoped, for example `S1:F12`. An explicit mapping can choose a shared semantic property; changing display labels does not rename the SMW property. Existing SMW properties still need compatible datatypes. Enable semantic links for any additional namespaces that should receive annotations. Do not change a working SMW database backend simply to install SWS.

SMW is optional. Local SWS storage, the Data tab and direct SWS queries do not require it. `integrations/smw/` supplies the optional wiki-side query material.

## Scribunto and MCP

Scribunto enables the `mw.sws` / `mw.swsbase` Lua interfaces. The optional MCP adapter in `integrations/mcp/` is a separately versioned integration; use the [SWS 0.5.0 patch for MediaWiki MCP 0.21.0](https://github.com/Klaus-Feldmann/SemanticWikiSchema/releases/tag/mcp-sws-0.5.0) and its compatibility checks. It is not installed or authenticated by the SWS installer.

[Graph Explorer setup](graph-explorer.md) · [Installation index](README.md)
