# MCP integration for SemanticWikiSchema

## SWS 0.5.0 / MediaWiki MCP 0.21.0

**[Download the patch](https://github.com/Klaus-Feldmann/SemanticWikiSchema/releases/tag/mcp-sws-0.5.0)** · **[Installation and rollback](0.5.0/README.md)** · **[Test results](0.5.0/TEST-RESULTS.json)**

The complete patch in [`0.5.0/`](0.5.0/) applies to an unmodified official MediaWiki MCP Server 0.21.0. It includes the source patch, built runtime files, a checked installer with backup and rollback, pinned npm installation files and verification records. Earlier SWS patches are not required. Existing wiki configuration and OAuth credentials remain in their own locations.

It adds twelve SWS tools and the SchemaBaseBridge data view, including field annotations, own multilingual identity and persistent Subject IDs. Private OAuth wiki discovery and write access controls are included. Original NeoWiki tools remain available on NeoWiki wikis.

This is an **MCP patch for the SWS 0.5.0 interface**, not a MediaWiki extension installer or a SWS upgrade. It was live-tested against SWS internally labelled 0.5.00-alpha.8. The SWS version in this repository is not changed by this publication. Cypher requires an independently configured Neo4j endpoint; it was not live-qualified on the test wiki.

## Earlier incremental patch

The files directly in this directory, outside `0.5.0/`, are the earlier technical-rename patch for MCP 0.20.0/0.20.1. They are retained for older installations. Their runtime installer does not include subsequent field-annotation additions, and their source delta requires a previously patched source tree.

For SWS 0.5.0 use the complete versioned package above. Do not combine the new patch with the earlier incremental installer.

## License

The MCP code retains its upstream MIT license and authorship. The SWS-specific additions in `0.5.0/` are also provided under that license; see [LICENSE-UPSTREAM](0.5.0/LICENSE-UPSTREAM).
