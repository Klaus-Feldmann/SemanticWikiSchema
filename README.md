# Semantic WikiSchema (SWS) 0.4.03

Schema-based metadata and research data for MediaWiki. Define fields once, enter structured values through the **Data** tab, and keep those values outside the article body. SWS is an independent fork of [NeoWiki](https://github.com/ProfessionalWiki/NeoWiki).

**[Install SWS](docs/operations/install/README.md)** · **[Installation auf Deutsch](docs/operations/install/de/README.md)** · **[Downloads](https://github.com/Klaus-Feldmann/SemanticWikiSchema/releases/tag/v0.4.03)**

## What this version provides

- Stable **S-IDs for schemas** and **E-IDs for entities**, with editable multilingual labels and descriptions. Changing a schema label keeps its identity and references intact.
- Schema-driven input forms, field notes, category assignment, and namespace-filtered page suggestions through PrefixSearch.
- Text, user-entered Wikitext, centrally executed schema templates, historical dates, images, page links, relations and structured URLs.
- A local entity store for configured Wikidata/Wikibase values. `store` is the default; the previous cached resolution is available as `cache`. Own SWS labels and entered values have precedence over imported labels and values.
- Optional automatic Semantic MediaWiki annotations and direct SWS queries from Wikitext and Lua.
- A bundled Graph Explorer with the current schema IDs and labels, linked entity cards, images and source identities. It requires a separately configured SPARQL service.
- JSON/RDF exports and optional [MCP integration](integrations/mcp/README.md) for external tools.

A source binding makes data available only when the associated entity, property and usable source response exist. SWS does not infer missing research facts.

## Installation scope

This release is for **first installation into an existing MediaWiki**. The helper installs the pinned Composer package and preserves unrelated Composer settings. It does not modify LocalSettings.php, enable extensions, create a database or install Fuseki automatically. Built browser assets are included; Node.js is not needed on the wiki server.

The live-confirmed environment uses **MediaWiki 1.47.0-wmf.24, PHP 8.3.36 and the legacy parser**. MediaWiki 1.47 stable has not been qualified by this release. See [requirements](docs/operations/install/requirements.md) and [verification](publication/VERIFICATION.md).

Already using SWS or NeoWiki? Contact **[Klaus Feldmann / AufEinWort](mailto:info@aufeinwort.info)** before switching. The installer refuses existing SWS/NeoWiki package or directory installations. An automatic NeoWiki import is not included.

## Installation steps

1. Check [requirements](docs/operations/install/requirements.md).
2. Follow [first installation](docs/operations/install/first-installation.md).
3. Add the [LocalSettings configuration](docs/operations/install/configuration.md).
4. Run the database update, activate the job runner and complete the [functional checks](docs/operations/install/verification.md).
5. Add the optional [Graph Explorer and Fuseki service](docs/operations/install/graph-explorer.md) or [source/SMW integrations](docs/operations/install/integrations.md).

The installation pages are split by task so they can also be maintained as MediaWiki.org subpages.

## Documentation and support

[Documentation index](docs/README.md) · [Release notes](RELEASE-NOTES.md) · [Source and third-party notices](publication/THIRD-PARTY.md) · [Contact](mailto:info@aufeinwort.info)

This fork is maintained by AufEinWort, not by Professional Wiki or the original NeoWiki maintainers. Send SWS-specific reports to the contact above.

## Credits and license

**Softwaredesign: Klaus Feldmann / AufEinWort, Programmierung: ChatGPT/Sophos.**

Based on NeoWiki by Professional Wiki, Jeroen De Dauw, Morne Alberts, Alistair3149 and the upstream contributors. Their copyright notices and the **GPL-2.0-or-later** license are retained; see [COPYING](COPYING) and [the original project description](README-UPSTREAM.md).

The bundled Zazuko Graph Explorer retains its **LGPL** license, corresponding sources and dependency notices. The separate MCP integration retains the MCP project's license. This publication does not imply endorsement by the upstream projects or OpenAI.
