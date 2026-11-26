# SWS 0.6.0 → 0.7.0.2

Der direkte Upgradepfad gilt für die veröffentlichte Version 0.6.0. Das Austauschpaket unterstützt auch den bekannten 0.6.0-Stand mit der damaligen Kompatibilitätsanpassung. Für ältere Stände siehe [Upgrade auf 0.6.0](upgrade-0.6.0.md).

## Manuelle Installation

1. Wiki-Datenbank und lokale Konfiguration sichern. Das Upgrade-Paket außerhalb des Webverzeichnisses entpacken und im Terminal in den entpackten Ordner wechseln.
2. Ausgangsstand und Paket prüfen. Den Beispielpfad durch den installierten Erweiterungsordner ersetzen.

   ```sh
   python3 upgrade.py --extension "/pfad/zu/mediawiki/extensions/SemanticWikiSchema"
   ```

3. Schreibzugriffe sperren und PHP-Webprozesse sowie dauerhafte Job-Prozesse anhalten. Den Code austauschen. Das Sicherungsverzeichnis liegt außerhalb des Webverzeichnisses.

   ```sh
   python3 upgrade.py --extension "/pfad/zu/mediawiki/extensions/SemanticWikiSchema" \
     --backup-dir "/pfad/zu/privaten-sicherungen" --processes-stopped --apply
   ```

## Composer-Installation

Wiki-Datenbank und lokale Konfiguration sichern. Schreibzugriffe sperren und PHP-Webprozesse sowie dauerhafte Job-Prozesse anhalten. In `composer.local.json` das Paket `aufeinwort/semantic-wikischema` auf `0.7.0.2` setzen und im MediaWiki-Stammverzeichnis ausführen.

```sh
composer update aufeinwort/semantic-wikischema --with-dependencies
```

## Wartung nach dem Codewechsel

Im MediaWiki-Stammverzeichnis mit dem Installationskonto in dieser Reihenfolge ausführen. Jeden Befehl erfolgreich abschließen, bevor der nächste beginnt.

```sh
php maintenance/run.php update
php maintenance/run.php rebuildLocalisationCache --force
php maintenance/run.php SemanticWikiSchema:RebuildData
```

`RebuildData` aktualisiert abgeleitete SWS-Daten, darunter Seitenkategorien und Suchdaten. Der Lauf kann bei vielen Vorlagen Folgejobs für einbindende Seiten auslösen. Bei einer Unterbrechung kann der Lauf mit der zuletzt gemeldeten Seiten-ID und `--after SEITEN_ID` fortgesetzt werden. Anschließend die Web- und Job-Prozesse wieder starten und die Warteschlange abarbeiten lassen.

In **Spezial:Version** die Version prüfen und eine vorhandene Entität im Reiter **Daten** öffnen. Bei Bedarf Schema- und Entitätssuche sowie die Wartungskategorie an einer Testseite prüfen. Die optionale KI-Anbindung richtet [User AI](https://www.mediawiki.org/wiki/Extension:User_AI) unabhängig von SWS ein.

## Rücknahme

Vor den Wartungsbefehlen lässt sich der Ordneraustausch bei gestoppten Prozessen mit `upgrade.py --rollback "/pfad/zur/ausgegebenen/sicherung" --processes-stopped --apply` rückgängig machen. Nach Änderungen an der Datenbank den Code und die passende Datenbanksicherung gemeinsam wiederherstellen.
