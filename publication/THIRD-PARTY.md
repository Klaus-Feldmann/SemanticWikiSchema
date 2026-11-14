# Upstream software and licenses

SWS is based on ProfessionalWiki/NeoWiki release 0.1.0, commit `92cf0e347d40899647cdbb1312c4f0dc09768ae3`. Its GPL-2.0-or-later license and author credits are retained. Downstream implementation assistance by ChatGPT/Codex is disclosed in README.md; this does not replace upstream authorship.

The bundled Graph Explorer is Zazuko Graph Explorer, derived from Ontodia, under LGPL-2.1-or-later according to its LICENSE notice. Copyright holders named there include Netage B.V., Zazuko GmbH and metaphacts GmbH. The original notice, full LGPL text and upstream thirdparty.txt are supplied in graph-explorer/ and graph-explorer-dev/.

`graph-explorer-dev/Ausgangsstand` retains the exact captured starting bundles/maps. `original-sources/` contains the application source embedded in those maps. The 78 embedded application source files match the current reference checkout at `a748252d29b40e9f4fd41f18a88c0485d236e991`; map-transformed image imports are recorded separately in UPSTREAM-SOURCE.json. A complete reference source archive, including build files, image sources and dependency lockfile, is included in `third-party/graph-explorer-upstream-a748252d.tar.gz`. The exact captured dependency implementation is also present in the original source maps. Downstream modifications are dated 2026-11-03 and supplied as source/build scripts; rebuilding this fork transforms those captured bundles, rather than claiming a fresh upstream Graph Explorer build.

Source: https://github.com/zazuko/graph-explorer/tree/a748252d29b40e9f4fd41f18a88c0485d236e991

The optional MCP patch targets ProfessionalWiki/MediaWiki-MCP-Server 0.20.1, commit `5b979b5a64f8dbb3596b95efc0c1d3f570505646`. Its license is preserved as integrations/mcp/LICENSE-UPSTREAM. Patch source and tests are provided separately from SWS. No account credentials, OAuth token store or private-wiki discovery adapter is included.

Historical-person names and date fixtures in regression tests are public test material, not a research-corpus export. Live authentication/debug logs are not shipped. Existing upstream demo passwords are public disposable-demo examples, not credentials for AufEinWort.

Additional license/notice texts for the nine bundled JavaScript dependency packages are in third-party/graph-notices/. REFERENCE-PACKAGES.json records their versions from the matching upstream reference lockfile; the captured maps remain the source of the exact bundled implementation.

The optional HDTO example is adapted from ProfessionalWiki/NeoWiki commit `b6548939bc6742e6a00b34fa86c93e5f82584fe2` (#1861), contributed by Alistair3149 with the co-author credit stated in that commit. Its GPL-2.0-or-later attribution is retained. SWS supplies a separate local generator for explicit S-ID bindings.

Standalone D4 tests include the exact `wfEscapeWikiText()` function excerpt from MediaWiki `wmf/1.47.0-wmf.24`, `includes/GlobalFunctions.php` (MediaWiki contributors, GPL-2.0-or-later). Source: https://github.com/wikimedia/mediawiki/blob/wmf/1.47.0-wmf.24/includes/GlobalFunctions.php . Production calls the installed core function; the excerpt is a test fixture only.

Multilingual field-label input and label-editing helpers are selectively adapted from ProfessionalWiki/NeoWiki commit `5eabca74c5861ed28522db69280da0decfe86289` by Alistair3149, co-authored by Claude Opus 5.5 (GPL-2.0-or-later). Original authorship and licensing remain credited; SWS adds its schema-key scope, bridge/Graph/RDF integration and regression checks. Source: https://github.com/ProfessionalWiki/NeoWiki/commit/5eabca74 .
