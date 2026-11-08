# Technical rename 0.1.21

Local candidate derived from the supplied installed 0.1.20 copy. No GitHub publication and no live database or wiki change has been performed.

## Names

| Surface | Old | New |
|---|---|---|
| Product/install directory | SemanticWikiSchema | SemanticWikiSchema |
| Public companion extension | SchemaBaseBridge | SchemaBaseBridge |
| Main PHP namespace | ProfessionalWiki\NeoWiki | ProfessionalWiki\SWS |
| Companion PHP namespace | NeoWikiBaseBridge | SWSBaseBridge |
| Lua | mw.neowiki / mw.neowikibase | mw.sws / mw.swsbase |
| REST | /neowiki/v0 /neowikibase/v1 | /sws/v0 /swsbase/v1 |
| Subject slot | neo | sws |
| Content models | NeoWikiSubject/Schema/Layout/Mapping | SWSSubject/Schema/Layout/Mapping |
| Settings | $wgNeoWiki… | $wgSWS… |
| Own SQL tables | neowiki_* | sws_* |
| Wiki configuration page | MediaWiki:NeoWiki | MediaWiki:SWS |

Source identifiers, messages, permission names, job types, filenames and frontend bundles use the corresponding case-specific replacement. Camel-case variants are included consistently. General namespace names and numeric IDs 7474–7481 are unchanged. Existing subject IDs, schema field names, values, relation IDs and the underlying JSON shape are unchanged. The SMW setting remains $wgWikiSchemaSmwMapping.

Upstream repository/commit attribution, original copyright and license texts, actual Neo4j and other external dependencies retain their correct names. RDF base IRIs and existing external service URLs are not renamed blindly. The RDF abbreviation neo remains independent of the renamed MediaWiki slot.

## Existing installations

Do not replace 0.1.20 on a populated wiki without preparing the stored-data transition. The source package creates/addresses sws_* tables, SWS* content-model identifiers and the sws slot. It does not migrate existing neowiki_* tables or historical MediaWiki slot/model records. A simple folder replacement alone cannot read the old data through the new identifiers.

The intended fresh-start or a controlled stored-data rename must be carried out separately with a full backup and the job runner paused. Ordinary articles and data from other extensions must remain untouched. Do not run a global replacement over article text or historical conversation archives. The job queue and stored rights/OAuth grants must be checked before switching.

## Configuration and wiki modules

The wfLoadExtension lines remain SemanticWikiSchema and SchemaBaseBridge. Rename the affected wgNeoWiki settings to wgSWS; keep their actual deployment values. Keep the current graph endpoint URLs until the external Fuseki dataset is deliberately renamed. Its name/path is an external deployment setting, not a PHP identifier.

Move MediaWiki:NeoWiki to MediaWiki:SWS if the page is used. Replace the installed Modul:WikiSchema content with integrations/smw/Wikiseiten/Modul-WikiSchema.lua; the module now requires mw.sws. Update any other active direct mw.neowiki or mw.neowikibase callers. Wikidata/Wikibase modules and their config remain outside this change.

The browser configuration global becomes SWSGraphConfig. Built frontend and graph assets are included; a new localization cache and Composer autoload refresh are needed when activating the coordinated installation. Run normal MediaWiki installation/update procedures only after deciding how the existing slot/model/table state is handled.

## MCP

integrations/mcp contains an updated checked runtime patch. It renames the tool groups to sws and swsbase and includes all required tool files. Coordinate its installation and client restart with the wiki API switch. It does not modify the external MCP server's authentication configuration. See its README for supported baselines and test limitations.

## Installation status

This is a source/distribution candidate, not an already performed migration. No automated destructive cleanup or SQL migration command is included. Full live MediaWiki save/job/SMW/graph/MCP validation remains pending.
