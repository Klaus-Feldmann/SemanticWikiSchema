# LocalSettings.php ergänzen

Für den Betreiber, der die erfolgreich installierte Erweiterung aktiviert. Folgendes in die vorhandene Datei einfügen; die Datei nicht vollständig ersetzen:

```php
$wgUseParsoidParser = false;
$wgUseParsoidLinksUpdate = false;

wfLoadExtension( 'SemanticWikiSchema' );
wfLoadExtension(
    'SchemaBaseBridge',
    "$IP/extensions/SemanticWikiSchema/SchemaBaseBridge/extension.json"
);

// Datenpflege unter dem Reiter Daten, ohne automatische Infobox im Artikel.
$wgSWSAutoRenderMainSubject = false;
```

SchemaBaseBridge wird mitgeliefert. Beide Komponenten jeweils nur einmal laden. Weitere gewünschte Erweiterungen nach deren eigener Anleitung laden.

Ohne zusätzliche Einstellungen gelten `store` als Quellenbetriebsart, aktiviertes PrefixSearch mit allen Namensräumen und die seitenbezogene Arbeitsweise (`SWSSubjectFirst = false`). Für die automatische Infobox kann `SWSAutoRenderMainSubject` stattdessen aktiviert werden.

Optionale Begrenzungen und zusätzliche Datennamensräume:

```php
$wgSWSPrefixSearchNamespaces = [ NS_MAIN, NS_CATEGORY, NS_TEMPLATE ];
$wgSWSAdditionalSubjectNamespaces = [ 828, NS_TEMPLATE, NS_MEDIAWIKI ];
// Alternative Quellenbetriebsart:
// $wgSWSSourceDataMode = 'cache';
```

Die zusätzlichen Namensräume ergänzen die Inhaltsnamensräume. Gewünschte SMW-Annotationen dort erfordern zusätzlich die Freigabe dieser Namensräume in SMW. Die Benennung von Namensraum 0 folgt der Nachricht `MediaWiki:Article`.

PrefixSearch verwendet das installierte Suchsystem. Die Namensraumauswahl allein garantiert keine beliebige Teilwortsuche; dafür sind die entsprechenden CirrusSearch-Funktionen und Einstellungen nötig.

[Weiter: Datenbank, Jobs und Prüfung](pruefung.md)
