# Semantic WikiSchema 0.4.03 — first-installation helper

For a configured MediaWiki that has not used SWS or NeoWiki. PHP 8.3+, Composer 2 and Python 3.9+ are required. This release is qualified on MediaWiki 1.47.0-wmf.24 with the legacy parser.

From the extracted installer folder:

```sh
python3 install.py --mediawiki-root /path/to/mediawiki --dry-run
python3 install.py --mediawiki-root /path/to/mediawiki --apply
```

Use the MediaWiki installation account. Optional executable paths: `--php /path/to/php --composer /path/to/composer`. The helper backs up the affected Composer files outside the web root and installs the pinned package. It does not activate the extension, change LocalSettings or run database updates.

Continue with the [English installation guide](https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/v0.4.03/docs/operations/install/README.md) or [der deutschen Installationsanleitung](https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/v0.4.03/docs/operations/install/de/README.md). Do not skip the database update, first checks or job runner.

**Deutsch:** Im bereits eingerichteten MediaWiki den tatsächlichen Pfad angeben. Zunächst die Vorschau ausführen. Erst bei erfolgreicher Vorprüfung `--apply` verwenden und danach die Ladezeilen, Wartungsaufrufe und Jobs gemäß Anleitung einrichten. Bestehende SWS-/NeoWiki-Installationen werden vom Erstinstallations-Installer abgelehnt.

Existing installation / vorhandene Installation: contact [Klaus Feldmann / AufEinWort](mailto:info@aufeinwort.info).

Softwaredesign: Klaus Feldmann / AufEinWort, Programmierung: ChatGPT/Sophos. GPL-2.0-or-later; upstream attribution is retained in the extension package.
