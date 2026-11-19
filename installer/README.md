# Semantic WikiSchema 0.6.0 — first installation

For an existing configured MediaWiki receiving its first SWS installation. Requires PHP 8.3+, Composer 2 and Python 3.9+. Qualified on MediaWiki 1.47.0-wmf.24 with the legacy parser.

```sh
python3 install.py --mediawiki-root /path/to/mediawiki --dry-run
python3 install.py --mediawiki-root /path/to/mediawiki --apply
```

Use the MediaWiki installation account. Optional executable paths: `--php /path/to/php --composer /path/to/composer`. The helper backs up affected Composer files outside the web root and installs the pinned package. It does not activate SWS, modify LocalSettings or run database updates.

Continue with the [English installation guide](https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/v0.6.0/docs/operations/install/README.md) or [deutschen Installationsanleitung](https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/v0.6.0/docs/operations/install/de/README.md).

**Already using SWS 0.5.x?** Use the separate upgrade package and [upgrade guide](https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/v0.6.0/docs/operations/upgrade-0.6.0.md). This first-installation helper refuses existing SWS/NeoWiki installations.

Support: [Klaus Feldmann / AufEinWort](mailto:info@aufeinwort.info). SWS is an independent fork of [NeoWiki](https://neowiki.ai), not maintained or supported by Professional Wiki.

Softwaredesign: Klaus Feldmann / AufEinWort, Programmierung: ChatGPT/Sophos. GPL-2.0-or-later; upstream attribution is retained in the extension package.
