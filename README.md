# Semantic WikiSchema (SWS) 0.6.0

Schema-based metadata and research data for MediaWiki. **SWS is an independent fork of [NeoWiki](https://neowiki.ai), not maintained or supported by Professional Wiki.**

[Installation](publication/INSTALLATION.md) · [Upgrade from 0.5.x](docs/operations/upgrade-0.6.0.md) · [Release notes](RELEASE-NOTES.md)

## What this version provides

- Stable **S-IDs for schemas** and **E-IDs for entities**, with editable multilingual labels and descriptions. Changing a schema label keeps its identity and references intact.
- Schema-local field keys of any valid spelling, automatic stored-key transfer and multilingual display labels; moving fields or changing labels keeps query keys stable.
- Schema-driven input forms, field notes, category assignment, and namespace-filtered page suggestions through PrefixSearch.
- Text, user-entered Wikitext, centrally executed schema templates, historical dates, images, page links, relations and structured URLs.
- A local entity store for configured Wikidata/Wikibase values. `store` is the default; the previous cached resolution is available as `cache`. Own SWS labels and entered values have precedence over imported labels and values.
- Optional automatic Semantic MediaWiki annotations and direct SWS queries from Wikitext and Lua.
- A bundled Graph Explorer with the current schema IDs and labels, linked entity cards, images and source identities. It requires a separately configured SPARQL service.
- JSON/RDF exports and [optional User AI (UAI) integration](docs/operations/install/integrations.md#user-ai-uai).

A source binding makes data available only when the associated entity, property and usable source response exist. SWS does not infer missing research facts.

## Installation and upgrade

Use the [first-installation guide](docs/operations/install/README.md) for a new SWS installation or the [0.5.x upgrade guide](docs/operations/upgrade-0.6.0.md) for an existing one. Built browser assets are included. Stop persistent PHP/job processes before replacing the extension and restart them afterward.

## Documentation and support

[Configurable outward names](docs/operations/naming.md) · [Documentation index](docs/README.md) · [Release notes](RELEASE-NOTES.md) · [Source and third-party notices](publication/THIRD-PARTY.md) · [Contact](mailto:info@aufeinwort.info)

This fork is maintained by AufEinWort, not by Professional Wiki or the original NeoWiki maintainers. Send SWS-specific reports to the contact above.

## Credits and license

**Softwaredesign: Klaus Feldmann / AufEinWort, Programmierung: ChatGPT/Sophos.**

Based on NeoWiki by Professional Wiki, Jeroen De Dauw, Morne Alberts, Alistair3149 and the upstream contributors. Their copyright notices and the **GPL-2.0-or-later** license are retained; see [COPYING](COPYING) and [the original project description](README-UPSTREAM.md).

The bundled Zazuko Graph Explorer retains its **LGPL** license, corresponding sources and dependency notices. The legacy MediaWiki MCP integration retains its original license. This publication does not imply endorsement by the upstream projects or OpenAI.
