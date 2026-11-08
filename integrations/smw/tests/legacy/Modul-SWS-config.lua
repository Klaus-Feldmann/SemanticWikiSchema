-- Nur Ausnahmen pflegen. Alle anderen Schemafeldnamen gelten unmittelbar als SMW-Namen.
-- Quell-IDs und Anzeige-/Linkpräfixe gehören nicht in diese Zuordnung.
return {
    urlEntries = { url = 'Quellen-URL', label = 'Quellentitel', caption = 'Quellenbezeichnung', captionPage = 'Quellenbezeichnungsseite', field = 'SWS-Feld', subject = 'SWS-Subjekt' },
    properties = {
        Werk = {
            Leserschaft = 'hat Leserschaft',
            Leserschaftziel = 'hat Leserschaftziel',
            Werkausgabe = 'hat Werkausgabe',
        },
    },
}
