# SWS 0.5.02 – Funktionen und Konfiguration

Referenz für Wiki-Verantwortliche, Schema-Verwalter und Autoren. Diese Seite beschreibt den Funktionsumfang der Version 0.5.02, ihre Einstellungen und optionalen Integrationen. Die Grundbedienung beginnt mit einem Schema und dem Reiter **Daten**; Daten und Artikeltext werden getrennt gespeichert.

## 1. Funktionen und Verwendung

### Datenmodell und Bearbeitung

| Bereich | Möglichkeiten |
|---|---|
| Schemata | Felder, Datentypen, Beschreibungen, Pflichtangaben, Vorgaben und typabhängige Einschränkungen festlegen; anschließend durch Formulare erfassen. |
| Entitäten | Eine Hauptentität und weitere Entitäten pro Seite; eigene Seiten für frei angelegte Entitäten sind als Betriebsart verfügbar. |
| Beziehungen | Entitäten verknüpfen und abhängige Entitäten innerhalb eines Datensatzes bearbeiten. Qualifikationen und Quellen können durch eigene passende Schemata modelliert werden; es gibt keinen automatischen Import jeder Quellenstruktur. |
| Identitäten | S-ID für Schemata, E-ID für Entitäten; mehrsprachige Labels und Beschreibungen unabhängig von der ID bearbeiten. Schema-Labeländerungen erfordern kein Verschieben der ID-Seite. |
| Felder | Schemaeigene technische Kennung und getrennte mehrsprachige Benennungen; gleiche Kennungen verschiedener Schemata sind unabhängig. Keine globale F-ID-Verwaltung oder automatische Nummerierung. |
| Feldkennung ändern | Der Schemaeditor hält Schlüsseländerungen fest; Hintergrundjobs übertragen Werte, Anmerkungen und Quellenstände. Kollisionen werden abgelehnt. Verweise in eigenem Wikitext und Vorlagen müssen vom Autor angepasst werden. |
| Oberfläche | Zweispaltige, höhenbegrenzte Eingabemaske mit scrollbarem Inhalt; Auswahlfelder orientieren sich an ihren Beschriftungen. Geprüfte Oberfläche: Vector 2022, Tablet/Desktop. |
| Anmerkungen | Zusätzliche Text- oder Wikitext-Anmerkung direkt am Feld, bei Bedarf erst einblenden; optionale SMW-Ausgabe, auch wenn das eigentliche Feld keinen Wert enthält. |
| Validierung | Typ- und Schema-Prüfung, Warnungen/Fehler, Editorprüfung vor dem Speichern und serverseitige Prüfung. Blockieren neuer Fehler ist konfigurierbar. |
| Bearbeitungshinweise | Seiten-/Schema-bezogene Hinweise vor der Bearbeitung; die Anzeige ersetzt keine Berechtigungsprüfung. |
| Namensräume | Daten in Inhaltsnamensräumen sowie ausdrücklich freigegebenen weiteren Namensräumen, einschließlich Vorlagen, Modulen und Seiten ohne Wikitext-Hauptinhalt. |
| Anzeige und Sprache | Nur belegte Daten werden in der Datenansicht dargestellt; Feldlabels folgen Sprache und Rückfallsprachen. MediaWiki-Nachrichten sind über die zugehörigen MediaWiki:-Seiten anpassbar. |

### Enthaltene Feldtypen

| Technischer Typ | Verwendung |
|---|---|
| `text` | Freier Text, einzelne oder mehrere Werte; kein ausführbarer Wikitext. |
| `wikitextValue` | Frei eingegebener Wikitext als Entitätswert, im Ergebnis gerendert. |
| `wikitext` | **Vorlage/Template**: zentral im Schema vorgegebener ausführbarer Wikitext, nicht als normales Eingabefeld der Entität. |
| `page` | Wikiseite mit optional vorgegebenem Namensraum; Kategorie wahlweise als Seitenverweis oder tatsächliche Kategoriezuweisung. |
| `url` | Externer Link mit strukturierten Angaben wie Label, Quellenbezeichnung und Bezeichnungsseite. |
| `image` | Bildverweis, beispielsweise lokal, Commons oder aus eingerichteter Quelle. |
| `number` | Zahlen und typabhängige Grenzen. |
| `boolean` | Wahr/Falsch. |
| `select` | Aus vorgegebenen Optionen auswählen. |
| `relation` | Beziehung zu einer Entität eines erlaubten Schemas. |
| `date` | Kalenderdatum. |
| `dateTime` | Datum mit Uhrzeit und ausdrücklichem Zeitzonenbezug. |
| `historicalDate` | Historische Lebensdaten, auch vor Christus und mit Jahres-/Monatsgenauigkeit; Kalender und Quellenpräzision werden berücksichtigt. |
| `monolingualText` | Text mit Sprachkennzeichnung. |

Typabhängige Mehrfachwerte, Vorgaben, Einschränkungen und Linkdarstellung werden am Feld konfiguriert. Nicht jede Option gilt für jeden Typ. Kategorien werden bei `page` mit gewähltem Kategorienamensraum über „Kategorie setzen“ zugewiesen; ohne dieses Verhalten bleibt es ein normaler Seitenverweis. Die Schema-Vorgabe kann eine Kategorie setzen, wenn kein eigener Feldwert vorliegt.

### Quellen, lokale Speicherung und Annotationen

- Wikidata und eine eingerichtete lokale Wikibase können Feldwerte über Quellenbindung und P-ID liefern. Eine auflösbare Entitätsverknüpfung und ein verwendbarer Quellenwert sind erforderlich; SWS ergänzt keine unbekannten Forschungsdaten durch Vermutung.
- `store` hält verarbeitete Quellenwerte lokal in der Entität; `cache` verwendet den Cache-/Quellenweg. Eigene Eingaben haben Vorrang. Fehlgeschlagene Abrufe werden feldbezogen verarbeitet; unabhängige Eingaben können gespeichert werden. Letzte erfolgreiche Quellenstände werden nach den Speicherregeln erhalten.
- Quellen-/Feldwerte und Abrufstatus bleiben getrennt. Anmerkungen können Quellenhinweise enthalten und optional als eigene SMW-Attribute erscheinen.
- Die automatische SMW-Ausgabe verwendet die Feldbenennung in der Wiki-Inhaltssprache, bei fehlendem passenden Label die technische Kennung. Kein automatischer Schema-ID-Präfix. Ausdrückliche Zuordnungen haben Vorrang. Zwei Schemata können denselben SMW-Attributnamen bedienen.
- SHORTDESC aus Seite oder Schema hat Vorrang vor lokaler Wikibase, diese vor Wikidata. Die erste nicht leere Beschreibung wird als Suchuntertitel und zusätzlich als SMW-Attribut `Kurzbeschreibung` ausgegeben. Ein eigenes gleichnamiges Schemafeld kann einen weiteren SMW-Wert erzeugen, ohne den aktiven Suchrang zu ändern.
- Die Kurzbeschreibung wird bei der vollständigen Seitenverarbeitung gespeichert. Die Suche liest diesen Stand, ohne bei jeder Eingabe externe Quellen abzurufen. Ohne SMW bleibt der Suchuntertitel verwendbar.

Ein eigenes Feld `Kurzbeschreibung` vom Typ Text kann über ein zweites Feld vom Typ Vorlage SHORTDESC setzen:

```wikitext
{{#if:{{#sws_value: Kurzbeschreibung}}|{{SHORTDESC:{{#sws_value: Kurzbeschreibung}}}}}}
```

Das Textfeld allein erzeugt seine normale Annotation; erst die SHORTDESC-Anweisung verleiht ihm den höchsten Suchrang. Vorhandene SHORTDESC-Anweisungen und deren Reihenfolge bei mehrfacher Verwendung sind beim Schemaentwurf zu berücksichtigen.

### Suche, Ausgaben und Schnittstellen

| Funktion | Verwendung / Voraussetzung |
|---|---|
| PrefixSearch | Hauptsuche mit Namensraumauswahl, Vorschlägen, Bildern, DISPLAYTITLE-Anzeige und Kurzbeschreibung; gemeinsamer Suchweg für entsprechende Seitenfelder im Schema. |
| Titelabgleich | Verwendet die eingerichtete MediaWiki-Suche; zusätzliche Titelteilwortsuche benötigt CirrusSearch-Regexunterstützung. DISPLAYTITLE-Anzeige allein garantiert keine Indizierung beliebiger Anzeigetitel. |
| Direkte Werte | `{{#sws_value: E3 \| F12 }}` liest das schemaeigene Feld der Entität; `{{#sws_value: F12 }}` das Feld der aktuellen Hauptentität. Benannte Felder funktionieren ebenso. `subject=` und `entity=` sind für diese Parserfunktion nicht verfügbar. |
| Eigene Ausgabe | Werte in eigenen Links und Vorlagenargumenten verwenden; die Ausgabe führt nicht selbst beliebigen Wertinhalt als Wikitext aus. |
| Ansichten/Layouts | Entitäten explizit über `#view`, Hauptentität über `#main_subject` ausgeben; Layouts und erweiterbare Ansichtstypen bestimmen die Darstellung. Automatische Hauptansicht ist schaltbar. |
| Erstellung | `#create_subject` bietet den Einstieg zum Anlegen einer Entität im erlaubten Kontext. |
| Lua | `require('mw.sws')`: Werte, Entitäten, Schemata und Sammlungen lesen; `query`/`sparqlQuery` stehen mit dem jeweiligen Backend bereit. Quellen-/Datenansichten ergänzt die mitgelieferte Brücke. |
| SPARQL/Cypher | Lesende Abfragen über REST, Lua und `#sparql_raw`/`#cypher_raw`; jeweils separat konfiguriertes Backend erforderlich. Direkte Feldabfragen benötigen diese Dienste nicht. |
| JSON-Schema-Export | Schema als JSON-Schema-Dokument für externe Datenprüfung bereitstellen. |
| JSON/REST | Entitäten und Schemata lesen/verwalten, validieren und strukturierte Daten exportieren; vorhandene Berechtigungs- und Revisionsregeln gelten. |
| RDF | Einzel-/Seitenexport und RDF-Gesamtexport; native Identitäts-IRIs sowie ausdrückliche Ontologie-Mappings. Feldprädikate bleiben schemaeigen. |
| Graph Explorer | Verknüpfte Entitäten, Schemata, Beschreibungen und Bilder darstellen; benötigt eingerichteten SPARQL-Abfrageanschluss und passende Projektion. |
| Graphspeicher | Neo4j und SPARQL-Projektionen, Speicherstatus und kontrollierte Neuberechnung über die vorhandenen Verwaltungsoberflächen. |
| Berechtigungen | MediaWiki-Leserechte, Bearbeitungsrechte und veröffentlichte Revisionen berücksichtigen; zusätzliche Namensraumfreigabe ist keine Rechtevergabe. |
| Wartung | Jobs, `RebuildData`, Graph-Neuaufbau, Index-Neuaufbau und RDF-Dump. Änderungen sind erst nach abgeschlossener zugehöriger Verarbeitung überall sichtbar. |
| Erweiterbarkeit | Eigene Feld-/Ansichtstypen, Seitenmetadatenanbieter, Revisionsrichtlinien, Bearbeitungshinweise, Quellen und Graphbackends über Erweiterungsschnittstellen. |
| MCP | Optionaler externer MediaWiki-MCP-Server für freigegebene Lese-/Schreibwerkzeuge. Wird nicht allein durch Installation von SWS bereitgestellt. |


### Verwaltungsseiten und Namensräume

| Seite / Namensraum | Aufgabe |
|---|---|
| `Spezial:Schemata` / `Special:Schemas` | Schemata auflisten und bearbeiten. |
| `Spezial:Entitäten` / `Special:Entities` | Entitäten finden und aufrufen. |
| `Spezial:Entität` / `Special:Entity` | Eine Entität anhand ihrer Kennung anzeigen. |
| `Spezial:Entität_erstellen` / `Special:CreateEntity` | Eine Entität anlegen. |
| `Special:Layouts` / `Special:Mappings` | Layouts und Ontologie-Mappings verwalten. |
| `Special:GraphStores` | Graphspeicher und Neuaufbauten verwalten. |
| `Special:SWS` | SWS-Übersicht; auf `MediaWiki:SWS` liegen die freigegebenen Wiki-Einstellungen. |
| `Special:NeoJson` | Technische JSON-Entwicklungsoberfläche; über Entwicklungsoption steuerbar. |
| `Schema` 7474, `Layout` 7476, `Mapping` 7478 | Eigene Verwaltungsnamensräume, jeweils mit Diskussionsnamensraum. |
| `Entity` / `Entität` 7480 | Eigene Entitätsseiten bei entsprechender Betriebsart, Diskussion 7481. |

## 2. LocalSettings.php – vollständige Einstellungsreferenz

Die Tabelle umfasst die **21 in SWS und eine in SchemaBaseBridge registrierten Einstellungen**. Standardwerte gelten ohne lokale Überschreibung. Beispiele sind Alternativen, kein gemeinsam einzufügender Pflichtblock. Nach Konfigurationsänderungen betroffene Seiten beziehungsweise Projektionen passend neu verarbeiten; nicht jede Einstellung benötigt einen vollständigen Neuaufbau.

| Variable | Standard | Zulässige Form | Wirkung |
|---|---|---|---|
| `$wgSWSSourceDataMode` | 'store' | `'store'`, `'cache'` | Quellenwerte dauerhaft in der Entität speichern oder über den bestehenden Cache auflösen. |
| `$wgWikiSchemaSmwMapping` | [] | Array; siehe Unteroptionen | Optionale abweichende SMW-Attributnamen und Namen strukturierter URL-Teilattribute. Ohne Zuordnung gilt die Feldbenennung in der Wiki-Inhaltssprache. |
| `$wgSWSEnableDevelopmentUI` | false | Boolean | Entwicklungsoberflächen verfügbar machen; für normale Autorenarbeit nicht erforderlich. |
| `$wgSWSEnableInWikiConfig` | true | Boolean | Erlaubt vier Einstellungen auf MediaWiki:SWS; dort gesetzte Werte haben Vorrang. false deaktiviert diesen Konfigurationsweg. |
| `$wgSWSEnableLua` | true | Boolean | mw.sws bei geladenem Scribunto registrieren. Parserfunktionen bleiben unabhängig davon verfügbar. |
| `$wgSWSEnforceValidation` | false | Boolean | Neue Validierungsfehler der Stufe error können Schreibvorgänge ablehnen. Warnungen blockieren nicht. |
| `$wgSWSAutoRenderMainSubject` | true | Boolean | Hauptentität automatisch in der Inhaltsansicht darstellen. false lässt den Daten-Reiter und ausdrückliche Ausgaben nutzbar. |
| `$wgSWSValidationDebounceMs` | 300 | Integer ≥ 0 | Wartezeit in Millisekunden vor automatischer Editorvalidierung; 0 schaltet den Timer aus, nicht Blur-/Speicherprüfung. |
| `$wgSWSQueryLimits` | `default`: 30 s / 5000; `expensive`: 300 s / 50000 | Array; siehe Unteroptionen | Abfragelimits nach Berechtigungsstufe. Cypher nutzt Zeit und Zeilenzahl; SPARQL nutzt nur die Zeitgrenze. |
| `$wgSWSNeo4jInternalWriteUrl` | null | `null` oder Bolt-URL | Schreibanschluss für die optionale Neo4j-Projektion; kein Erfordernis für lokale SWS-Werte. |
| `$wgSWSNeo4jInternalReadUrl` | null | `null` oder Bolt-URL | Lese-/Abfrageanschluss für Neo4j, vorzugsweise mit eigenen eingeschränkten Zugangsdaten. |
| `$wgSWSRdfBaseUri` | null | `null` oder URI-String | Gemeinsame Basis aller erzeugten RDF-IRIs; null verwendet CanonicalServer. Änderung ändert Identitäts-URIs und erfordert geplante Neuprojektion. |
| `$wgSWSSubjectFirst` | false | Boolean | false: Entitäten auf ihrer Ausgangsseite; true: neue Entitäten auf eigenen Seiten, Navigation über Entitätsansicht. |
| `$wgSWSDataLanguages` | [] | Array von BCP-47-Sprachtags | Daten-/Eingabesprachen in gewünschter Reihenfolge; [] verwendet die Wiki-Inhaltssprache. Keine automatische Übersetzung. |
| `$wgSWSSubjectPageNamespace` | 7480 | Integer: Inhaltsnamensraum | Namensraum eigener Entitätsseiten bei SubjectFirst=true; bei false ohne Wirkung. Standard 7480: Entity/Entität. |
| `$wgSWSAutoRebuildOnMappingChange` | false | Boolean | Nach Änderung/Löschung/Wiederherstellung eines Mappings betroffene Graphspeicher automatisch vollständig neu projizieren. |
| `$wgSWSSparqlStores` | [] | Array von Speichereinträgen | Optionale SPARQL-Speicher. Der erste gültige Speicher bedient die allgemeinen SPARQL-Abfrageoberflächen. |
| `$wgSWSPrefixSearchEnabled` | true | Boolean | Hauptsuchfeld durch namengesraumgeführte Vorschläge ersetzen. |
| `$wgSWSPrefixSearchNamespaces` | null | `null` oder Array<Integer> | null: alle registrierten nichtnegativen Namensräume; Array: Auswahl und Reihenfolge; []: keine globale Ersetzung. Keine automatische Ausnahme für Administratoren. |
| `$wgSWSPrefixSearchSubstringFallback` | true | Boolean | Freie Plätze durch Teilworttreffer im Titel ergänzen: expliziter Namensraum, mindestens drei Zeichen, funktionierende CirrusSearch-Regexsuche erforderlich. |
| `$wgSWSAdditionalSubjectNamespaces` | [] | Array<Integer> | Zusätzliche Namensräume neben Inhaltsnamensräumen für Daten freigeben, einschließlich Unterseiten und anderer Inhaltsmodelle. SWS-Verwaltungsseiten und virtuelle Namensräume bleiben ausgenommen. |
| `$wgSWSBaseBridgeLocalSiteId` | null | `null` oder String | Explizite Site-ID dieser Wiki-Installation im lokalen Wikibase-Repository. Nicht die Q-ID und nicht der Fuseki-Speichername. Gehört zur mitgelieferten SchemaBaseBridge. |

### Beispielwerte je Einstellung

```php
$wgSWSSourceDataMode = 'store';
$wgWikiSchemaSmwMapping = ['properties' => ['S1' => ['F12' => 'Geburtsort']]];
$wgSWSEnableDevelopmentUI = false;
$wgSWSEnableInWikiConfig = true;
$wgSWSEnableLua = true;
$wgSWSEnforceValidation = true;
$wgSWSAutoRenderMainSubject = false;
$wgSWSValidationDebounceMs = 300;
$wgSWSQueryLimits = ['default' => ['timeoutSeconds' => 30, 'maxRows' => 5000], 'expensive' => ['timeoutSeconds' => 300, 'maxRows' => 50000]];
$wgSWSNeo4jInternalWriteUrl = 'bolt://USER:PASSWORD@127.0.0.1:7687';
$wgSWSNeo4jInternalReadUrl = 'bolt://READER:PASSWORD@127.0.0.1:7687';
$wgSWSRdfBaseUri = 'https://wiki.example.org';
$wgSWSSubjectFirst = false;
$wgSWSDataLanguages = ['de', 'en'];
$wgSWSSubjectPageNamespace = 7480;
$wgSWSAutoRebuildOnMappingChange = false;
$wgSWSSparqlStores = [['name' => 'sws', 'updateUrl' => 'http://127.0.0.1:3331/sws/update', 'queryUrl' => 'http://127.0.0.1:3331/sws/query', 'projection' => 'native']];
$wgSWSPrefixSearchEnabled = true;
$wgSWSPrefixSearchNamespaces = [NS_MAIN, NS_CATEGORY, NS_TEMPLATE];
$wgSWSPrefixSearchSubstringFallback = true;
$wgSWSAdditionalSubjectNamespaces = [828, NS_TEMPLATE, NS_MEDIAWIKI];
$wgSWSBaseBridgeLocalSiteId = 'mywiki';
```

### Unteroptionen: `WikiSchemaSmwMapping`

`properties` ist eine Tabelle Schema → Feldkennung → gewünschter SMW-Attributname, beispielsweise `['S1' => ['F12' => 'Geburtsort']]`. Die Schema-ID bleibt bei einer Labeländerung stabil. Es sind keine Einträge nötig, um gewöhnliche Feldbenennungen als Attribute auszugeben. Nach einer Änderung die betroffenen Seiten neu verarbeiten. Die Zuordnung gilt auch für Anmerkungsattribute; der konfigurierte Nachrichtensuffix wird angefügt. Im Schlüsseländerungsverlauf können bisherige Zuordnungen weiter aufgelöst werden; eine Zuordnung am aktuellen Schlüssel hat Vorrang.

`urlEntries` ändert nur die Namen der Unterattribute strukturierter URL-Einträge:

| Schlüssel | Standardname |
|---|---|
| `url` | `Quellen-URL` |
| `label` | `Quellentitel` |
| `caption` | `Quellenbezeichnung` |
| `captionPage` | `Quellenbezeichnungsseite` |
| `field` | `WikiSchema-Feld` |
| `subject` | `WikiSchema-Subjekt` |

Nicht angegebene URL-Namen behalten ihren Standard. Ein gespeichertes URL-Feld mit ausdrücklichem Quellen-Caption kann den vorgesehenen fachlichen Zielnamen bestimmen. Attributnamen dürfen nicht leer sein oder SMW-Parametertrenner wie `=` oder `|` enthalten. Der Feldanmerkungssuffix ist eine MediaWiki-Nachricht (`sws-notes-property-suffix`), keine zusätzliche LocalSettings-Option. Die automatische ranggemäße Seitenbeschreibung wird unter `Kurzbeschreibung` erzeugt; das Feldmapping betrifft die ausdrücklich definierten Schemafelder.

### Unteroptionen: `SWSSparqlStores`

| Schlüssel pro Eintrag | Standard / Pflicht | Verwendung |
|---|---|---|
| `updateUrl` | Pflicht | SPARQL-1.1-Update-Endpunkt. |
| `queryUrl` | `updateUrl` | SPARQL-Abfrage-Endpunkt; für Fuseki gewöhnlich ausdrücklich `/query` angeben. |
| `accessToken` | nicht gesetzt | Bearer-Token für Abfragen und Updates. |
| `projection` | `'native'` | Native Projektion oder konfiguriertes Mapping-Ziel. |
| `name` | Wert von `projection` | Eindeutiger Speichername für Status und Neuaufbau; maximal 255 Bytes. `neo4j` ist unabhängig von Groß-/Kleinschreibung reserviert. |

Ungültige oder doppelte Speichereinträge werden mit Protokollhinweis ausgelassen. Für die allgemeinen SPARQL-Abfragen ist der erste gültige Eintrag maßgeblich. Zugangsdaten bleiben auf dem Server und gehören nicht in die Wiki-Konfigurationsseite oder öffentlich kopierte Beispiele.

### Unteroptionen: `SWSQueryLimits`

| Stufe | Auswahl | `timeoutSeconds` | `maxRows` |
|---|---|---|---|
| `default` | Ohne `apihighlimits`; außerdem Parserausgaben unabhängig vom auslösenden Benutzer | 30 | 5000 |
| `expensive` | API-/Lua-Abfragekontexte mit `apihighlimits`, soweit die jeweilige Oberfläche dies verwendet | 300 | 50000 |

Beide Stufen mit ihren erforderlichen Schlüsseln beibehalten; positive ganzzahlige Werte verwenden. Cypher nutzt beide Grenzen. SPARQL nutzt `timeoutSeconds`, schneidet das SPARQL-Ergebnis aber nicht anhand von `maxRows` ab; eine benötigte Ergebnisgrenze in der SPARQL-Abfrage selbst mit `LIMIT` setzen.

### Einstellungen auf `MediaWiki:SWS`

Wenn `$wgSWSEnableInWikiConfig = true`, können genau diese Schlüssel zusätzlich auf der JSON-Konfigurationsseite gesetzt werden:

| Wiki-Schlüssel | Entsprechung in LocalSettings.php |
|---|---|
| `subjectFirst` | `$wgSWSSubjectFirst` |
| `dataLanguages` | `$wgSWSDataLanguages` |
| `enforceValidation` | `$wgSWSEnforceValidation` |
| `autoRenderMainSubject` | `$wgSWSAutoRenderMainSubject` |

Dort ausdrücklich gesetzte Werte überschreiben LocalSettings.php. Nicht gesetzte Schlüssel behalten den Serverwert. Die Bearbeitung erfordert die MediaWiki-Rechte `editinterface` und `editsitejson`; JSON und Einstellungen werden beim Speichern geprüft. Infrastruktur, Zugangsdaten und alle übrigen SWS-Einstellungen bleiben serverseitig.

## 3. Voraussetzungen, Installation und Integrationen

| Bestandteil | Aufgabe / Voraussetzung |
|---|---|
| MediaWiki/PHP | Manifest-Mindeststand MediaWiki `1.47.0-wmf.24`, PHP 8.3; live bestätigt auf PHP 8.3.36. Spätere Core-Versionen benötigen eigene Prüfung. |
| Parser | Legacy-Parser für diesen Release; `$wgUseParsoidParser = false` und `$wgUseParsoidLinksUpdate = false` sind **MediaWiki-**, keine SWS-Einstellungen. |
| Datenbank | SQL-Schemata für MySQL/MariaDB, PostgreSQL und SQLite vorhanden; kein daraus abgeleitetes Versprechen gleicher Live-Testabdeckung auf allen Servern. |
| Installation | Composer 2 und vorhandene MediaWiki-Abhängigkeiten; der angebotene Helfer benötigt Python 3.9+. Gebaute Browserdateien liegen bei; Node/npm ist auf dem Wiki-Server nicht erforderlich. |
| Skin | Vector 2022 ist die geprüfte Oberfläche; Eingabe für Tablet/Desktop, Entwurfsminimum 12 Zoll. |
| SchemaBaseBridge | Mitgelieferte, separat zu ladende Integration für Quellen und Datenansichten. |
| Scribunto | Für Lua-Abfragen und Schema-Wikitext mit Lua-Aufrufen erforderlich. |
| Wikidata/lokale Wikibase | Einrichtung der Quellen und Seiten-/Entitätsverknüpfung vorausgesetzt. Quellenfunktionen einer anderen Erweiterung müssen separat vorhanden sein. |
| SMW | Für semantische Annotationen und SMW-Abfragen; unabhängig vom lokalen SWS-Speicher. Attributdatentypen müssen passen. Zusätzliche Namensräume in SMW separat freigeben. |
| SHORTDESC | Eine funktionierende SHORTDESC-Verarbeitung ist für diesen höchsten Rang erforderlich; die Bereitstellung erfolgt durch die entsprechende Wiki-Erweiterung, nicht allein durch ein beliebiges Textfeld. |
| CirrusSearch | Optional für erweiterte Titelvorschläge; die Teilwortergänzung benötigt funktionierende Regexunterstützung. Ein aktivierter SWS-Schalter installiert keine Suchplugins oder Indizes. |
| SPARQL/Fuseki/QLever | Optionaler Graphspeicher und Abfrageanschluss; notwendig für den beschriebenen Graph Explorer, nicht für lokale Dateneingabe oder direkte Feldabfragen. |
| Neo4j | Optionales Backend für Neo4j-Projektion und Cypher. |
| MCP | Separater Dienst mit eigener Authentifizierung und freigegebenen Werkzeugen; kein LocalSettings-Schalter installiert diesen Dienst. |

Grundaktivierung in der vorhandenen LocalSettings.php:

```php
$wgUseParsoidParser = false;
$wgUseParsoidLinksUpdate = false;
wfLoadExtension( 'SemanticWikiSchema' );
wfLoadExtension(
    'SchemaBaseBridge',
    "$IP/extensions/SemanticWikiSchema/SchemaBaseBridge/extension.json"
);
// Optional: Metadaten unter Daten statt automatischer Artikel-Infobox.
$wgSWSAutoRenderMainSubject = false;
```

Externe Einstellungen sind keine SWS-Optionen:

- `$wgLanguageCode`, `$wgContentNamespaces`, `$wgCanonicalServer`, `$wgUseParsoidParser`, `$wgUseParsoidLinksUpdate`: MediaWiki.
- `$smwgNamespacesWithSemanticLinks`: Semantic MediaWiki; zusätzliche Namensräume passend zu SWS separat freigeben.
- CirrusSearch-/OpenSearch-Einstellungen: Sucherweiterung beziehungsweise Suchdienst; insbesondere Regex-/Completion-Konfiguration nicht als SWS-Funktion voraussetzen.
- `MediaWiki:Article` benennt die Anzeige des Hauptnamensraums im SWS-Namensraumwähler. Nachrichten und Anmerkungssuffix werden über MediaWiki:-Seiten angepasst, nicht über ein neues Beschriftungsarray.
- Webserver-Regeln für öffentliche Identitäts-IRIs und Graph-Proxy sowie Graph-Explorer-Konfiguration gehören zur Dienstbereitstellung. Eine SWS-Installation richtet sie nicht automatisch ein.

Nach der Installation das MediaWiki-Updateverfahren ausführen und Jobs dauerhaft betreiben. `SemanticWikiSchema:RebuildData` verarbeitet Entitätsseiten neu; `--refresh-sources` fordert zusätzlich eine Aktualisierung der eingerichteten Quellen an. Den Umfang einer Neuberechnung auf die jeweilige Aufgabe abstimmen.

Für die optionale Q-ID-Registrierung siehe [Einrichtung mit require_once](../operations/install/de/erweiterungen.md#optionale-wikidata-registrierung) und [Helfer-Einstellungen](../../integrations/registration/README.de.md). Der Helfer benötigt UnlinkedWikibase und entfällt bei bereits vorhandener Quellenzuordnung.

## 4. KI-unterstützte Installation, Support und Schemaerstellung

**Empfehlung: Zunächst das vorhandene MediaWiki über einen passenden MediaWiki-MCP-Server mit einer KI verbinden und anschließend SWS mit deren Unterstützung installieren und einrichten.** Die Anbindung kann bereits vor SWS für allgemeine Wiki-Werkzeuge verwendet werden. Sie ist eine empfohlene Arbeitshilfe, keine Voraussetzung für die manuelle Installation von SWS.

MCP stellt der KI die jeweils freigegebenen Werkzeuge bereit. Welche Arbeiten sie selbst ausführen kann, hängt vom MCP-Server, dessen SWS-Unterstützung und den Berechtigungen des verbundenen Wiki-Kontos ab. SWS-spezifische Werkzeuge stehen nach Installation von SWS und Einrichtung ihrer Unterstützung im MCP-Server zur Verfügung. Ein Wiki-MCP-Zugang ersetzt keinen Zugriff auf den Server: Composer, LocalSettings.php und Wartungsskripte benötigen passende Serverwerkzeuge oder die Ausführung durch den Betreiber.

### Empfohlener Ablauf

1. **Bestehendes Wiki anbinden:** MCP-Server installieren, die KI verbinden und einen lesenden Zugriff auf das eigene Wiki prüfen.
2. **Installation vorbereiten:** Die KI den vorhandenen MediaWiki-Stand, Erweiterungen und die gewünschten Funktionen ermitteln lassen. Daraus den konkreten Installations- und Einrichtungsbedarf ableiten; eine Sicherung vor den Änderungen erstellen.
3. **SWS installieren und einrichten:** Die KI kann passende Konfigurationen und Befehle vorbereiten und mit entsprechendem Serverzugriff ausführen. Andernfalls führt der Betreiber die Schritte aus und lässt die Ergebnisse durch die KI prüfen. Nur für die gewünschten Funktionen benötigte Integrationen einrichten.
4. **Funktion prüfen:** Gemeinsam eine geeignete Testseite und ein kleines Schema verwenden, Daten sichern und Ausgabe, Jobs sowie gewünschte Quellen-/SMW-/Suchfunktionen prüfen.
5. **Im Wiki weiterarbeiten:** SWS-Werkzeuge im MCP-Server freigeben und die KI bei Schemaentwurf, Datenpflege und Auswertungen einsetzen.

### Wobei die KI helfen kann

| Aufgabe | Mögliche Unterstützung |
|---|---|
| Installation und Konfiguration | Vorhandene Umgebung prüfen, passende Einstellungen auswählen, Arbeitsschritte vorbereiten und verfügbare Ergebnisse kontrollieren. |
| Schemaerstellung | Anforderungen in Felder, Typen, Benennungen, Beziehungen, Quellenbindungen, Vorgaben und Anmerkungen übersetzen; vorhandene Schemata prüfen und mit passenden Schreibwerkzeugen anlegen oder bearbeiten. |
| Datenpflege | Verfügbare Quellenwerte und eigene Angaben vergleichen, Entitäten bearbeiten und nachvollziehbare Übertragungsverfahren ausführen. |
| Abfragen und Darstellung | Direkte SWS-Abfragen, Vorlagen, Lua-Ausgaben und bei eingerichteten Backends semantische oder Graphabfragen erstellen. |
| Fehlersuche | Fehlermeldungen, Verarbeitungsstatus, Jobs und tatsächlich gespeicherte Werte untersuchen; Ursachen eingrenzen und passende Korrekturen vorbereiten. |
| Nutzung und Lernen | Die für die aktuelle Aufgabe benötigten Funktionen erklären und konkrete Beispiele für das eigene Wiki entwickeln. |

Ein möglicher erster Auftrag lautet:

> Prüfe mein angebundenes MediaWiki und unterstütze mich bei der Installation und Einrichtung von SWS. Ermittle zuerst den vorhandenen Stand und die Voraussetzungen für die Funktionen, die ich nutzen möchte. Erkläre, welche Schritte du mit deinen Werkzeugen ausführen kannst und welche ich am Server ausführen muss.

Die KI unterstützt die Arbeit; die fachliche Prüfung von Forschungsangaben und die Freigabe der gewünschten Änderungen verbleiben bei den verantwortlichen Anwendern. Für projektspezifische Unterstützung: **info@aufeinwort.info**. Konkrete Installations- und Datenfragen werden anhand des jeweiligen Systems behandelt.

Weiterführend: [Parserfunktionen](../authoring/parser-functions.md), [Lua](../authoring/lua-api.md), [Feldkennungen](../authoring/field-identifiers.md), [REST](../api/rest-api.md), [RDF](../api/rdf-export.md), [Wartung](../operations/maintenance.md), [Installation](../operations/install/README.md).
