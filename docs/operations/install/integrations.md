# Optional sources, SMW and User AI

For the operator adding external data or semantic querying after local data entry works.

## Wikidata and local Wikibase

SchemaBaseBridge is included. A source-bound field needs the source entity association and a compatible P-ID. Own SWS values and labels have precedence over source values and labels.

## Optional Wikidata registration helper

SWS needs a page-to-source association before it can resolve configured Wikidata fields. If the wiki already provides that association, no additional registration helper is required. The bundled `AewiWikidataRegistrierung.php` is an optional way to register a Wikidata Q-ID as the page property `unlinkedwikibase_id`, which SchemaBaseBridge reads.

Install and load UnlinkedWikibase for this workflow. Add to LocalSettings.php:

```php
$wgAewiWikidataAutoNamespaces = [ NS_MAIN ];
// Without Semantic MediaWiki, disable the helper's optional SMW output:
// $wgAewiWikidataSmwProperty = '';
require_once "$IP/extensions/SemanticWikiSchema/integrations/registration/AewiWikidataRegistrierung.php";
```

Loading the file registers `#aewiwikidata`; a call on a page or through its template/schema is still required. For an explicitly known association, on the corresponding page:

```wikitext
{{#aewiwikidata:Q2063917}}
```

An empty-ID call can resolve the current page title through German Wikipedia's sitelink. This is not language-independent or guaranteed identity matching. Inspect the [helper reference](../../../integrations/registration/README.md) for all options and the supplied minimal template. Keep the job runner active and verify the resulting association. The helper does not edit Wikidata or local Wikibase.

Load one copy only. If another path already loads this helper, do not add a second include of the bundled copy. The companion magic-word file is loaded automatically. These helper options are separate from SWS's 22 registered settings. Site-specific store-name or Deepcat/Fuseki scripts are not general SWS prerequisites.

## Semantic MediaWiki

Install and configure SMW separately. SWS can generate annotations from configured fields and field notes; the default property name follows the field label in the wiki content language, falling back to the technical key, without an automatic schema-ID prefix. Field notes follow the same naming. Changing the relevant label changes the generated SMW name after reprocessing; an explicit mapping retains priority. Existing SMW properties still need compatible datatypes. Enable semantic links for any additional namespaces that should receive annotations. Do not change a working SMW database backend simply to install SWS.

SMW is optional. Local SWS storage, the Data tab and direct SWS queries do not require it. `integrations/smw/` supplies the optional wiki-side query material.

## Scribunto

Scribunto enables the `mw.sws` and `mw.swsbase` Lua interfaces.

## User AI (UAI)

[User AI (UAI)](https://www.mediawiki.org/wiki/Extension:User_AI) is the separately installed integration for AI-assisted access to MediaWiki and SWS. Connect through UAI and follow its approval flow in MediaWiki for changes. For category-based searches, use MediaWiki's category memberships alongside SWS entity fields. This also covers categories added by transcluded templates.

The former standalone MediaWiki MCP adapter belongs to earlier SWS releases. UAI supplies its own MCP connector. SWS can also be used independently in the MediaWiki interface.
