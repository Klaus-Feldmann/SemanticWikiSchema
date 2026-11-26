# Semantic WikiSchema 0.7.0.3

Diese Beta behebt wiederholt neu eingereihte Seitenaktualisierungen. Wenn ein laufender SWS-Job eine unvollständige oder veraltete Aktualisierung feststellt, reiht er für dieselbe Seite keinen neuen Job ein. MediaWiki übernimmt die Wiederholung nach den üblichen Regeln. Ändert sich beim Rendern die Seite oder eine ihrer Abhängigkeiten, versucht SWS es vor der Aktualisierung der abgeleiteten Daten einmal erneut.

Beim Upgrade von 0.7.0.2 genügt es, den Erweiterungscode auszutauschen und die Web- und Job-Prozesse neu zu starten.

[Installation](docs/operations/install/de/README.md) · [Upgrade von 0.6.0 oder 0.7.0.2](docs/operations/upgrade-0.7.0.3.md) · [English](RELEASE-NOTES.md)
