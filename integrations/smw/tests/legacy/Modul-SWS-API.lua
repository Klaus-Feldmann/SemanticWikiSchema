-- Schema-gesteuerter Abruf; eigene Angaben haben bei Herkunft alle Vorrang.
local p = {}

local function backend()
    return require('mw.sws')
end

local function scalar(value)
    if type(value) == 'boolean' then return value and 'ja' or 'nein' end
    if type(value) == 'number' then return tostring(value) end
    if type(value) == 'string' then return value end
    error('SWS/API: Nicht unterstützter Werttyp.', 0)
end

-- Own historical dates now follow the agreed Wikipedia calendar convention.
local function ownTime(raw)
    if type(raw) ~= 'string' then error('SWS/API: Ungültige eigene Datierung.', 0) end
    local y, m, d = raw:match('^(-?%d%d%d%d)%-(%d%d)%-(%d%d)$')
    local precision = 11
    if not y then y, m = raw:match('^(-?%d%d%d%d)%-(%d%d)$'); precision = 10 end
    if not y then y = raw:match('^(-?%d%d%d%d)$'); precision = 9 end
    if not y or tonumber(y) == 0 then error('SWS/API: Ungültige eigene Datierung.', 0) end
    local year, month, day = tonumber(y), tonumber(m) or 1, tonumber(d) or 1
    local gregorian = year > 1582 or year == 1582 and (month > 10 or month == 10 and day >= 15)
    local astronomical = year < 0 and year + 1 or year
    local leap = astronomical % 4 == 0 and (not gregorian or astronomical % 100 ~= 0 or astronomical % 400 == 0)
    local days = {31, leap and 29 or 28, 31,30,31,30,31,31,30,31,30,31}
    if month < 1 or month > 12 or day < 1 or day > days[month]
        or year == 1582 and month == 10 and day >= 5 and day <= 14 then
        error('SWS/API: Ungültige eigene Datierung.', 0)
    end
    return { kind = 'time', own = true, raw = raw, year = year, month = m and month or nil, day = d and day or nil,
        sourceTime = { time = (year < 0 and '-' or '+') .. string.format('%04d', math.abs(year))
            .. '-' .. (m or '00') .. '-' .. (d or '00') .. 'T00:00:00Z',
            precision = precision, calendarmodel = 'http://www.wikidata.org/entity/' .. (gregorian and 'Q1985727' or 'Q1985786') } }
end

local function ownTimeLabel(value, definition, language)
    local en = language:match('^en') ~= nil
    local months = en and {'January','February','March','April','May','June','July','August','September','October','November','December'}
        or {'Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'}
    local link = definition.wikiLink or {}
    local suffix = en and (link.dateBceEn or 'BC') or (link.dateBceDe or 'v. Chr.')
    local prefix = value.day and (value.day .. (en and ' ' or '. ')) or ''
    if value.month then prefix = prefix .. months[value.month] .. ' ' end
    return prefix .. math.abs(value.year) .. (value.year < 0 and (' ' .. suffix) or '')
end

local function nonEmpty(values)
    local result = {}
    for _, value in ipairs(values or {}) do
        if value ~= nil and (type(value) ~= 'string' or mw.text.trim(value) ~= '') then
            result[#result + 1] = value
        end
    end
    return result
end

local function selectLabel(value, definition)
    for _, option in ipairs(definition.options or {}) do
        if value == option.label then return value end
    end
    for _, option in ipairs(definition.options or {}) do
        if value == option.id and type(option.label) == 'string' and option.label ~= '' then
            return option.label
        end
    end
    error('SWS/API: Auswahlwert für „' .. definition.name .. '“ nicht auflösbar.', 0)
end

function p.collect(options)
    options = options or {}
    local origin = options.origin or 'all'
    if origin ~= 'all' and origin ~= 'own' and origin ~= 'external' then
        error('SWS/API: Unbekannte Herkunft.', 0)
    end
    if options.page and options.subject then
        error('SWS/API: Seite oder Subjekt angeben, nicht beides.', 0)
    end
    local nw = backend()
    local subject = options.subject and nw.getSubject(options.subject)
        or (not options.subject and nw.getMainSubject(options.page))
    if not subject then return { fields = {}, missing = true } end
    local schema = nw.getSchema(subject.schema)
    if not schema then error('SWS/API: Schema ist nicht verfügbar.', 0) end
    local selected, needsExternal, found = {}, false, false
    for _, definition in ipairs(schema.properties or {}) do
        if (not options.field or options.field == definition.name)
            and definition.type ~= 'wikitext'
            and (options.includeImages or definition.type ~= 'image') then
            found = true
            local own = origin == 'external' and {} or nonEmpty(nw.getAll(definition.name, { subject = subject.id }))
            local statement = subject.statements and subject.statements[definition.name]
            if origin ~= 'external' and definition.type == 'url' and statement and statement.values then
                own = nonEmpty(statement.values)
            end
            selected[#selected + 1] = { definition = definition, own = own }
            if definition.externalProperty and origin ~= 'own' and #own == 0 then
                needsExternal = true
            end
        end
    end
    if options.field and not found then
        error('SWS/API: Feld nicht im Schema gefunden: ' .. options.field, 0)
    end
    local external = {}
    if needsExternal then
        local bridge = require('mw.swsbase')
        if type(bridge.getFields) ~= 'function' then
            error('SWS/API: Lua-Anbindung für Quellenwerte fehlt.', 0)
        end
        local result = bridge.getFields({ page = options.page,
            subject = subject.id, language = options.language })
        if type(result) ~= 'table' or result.version ~= 1
            or result.scope ~= 'current' or result.mainSubjectId ~= subject.id
            or type(result.fields) ~= 'table' then
            error('SWS/API: Ungültige Antwort der Quellen-Schnittstelle.', 0)
        end
        for _, field in ipairs(result.fields) do
            if external[field.name] then error('SWS/API: Doppeltes Quellenfeld.', 0) end
            external[field.name] = field
        end
    end
    local fields = {}
    for _, selection in ipairs(selected) do
        local definition, own = selection.definition, selection.own
        local field = { name = definition.name, type = definition.type,
            externalProperty = definition.externalProperty, values = {},
            rawStatement = subject.statements and subject.statements[definition.name],
            hasStoredValues = subject.statements and subject.statements[definition.name]
                and #(subject.statements[definition.name].values or {}) > 0 or false }
        local seen = {}
        local function add(value, source, metadata)
            local text = scalar(value)
            local key = text
            if metadata and metadata.urlEntry then
                local e = metadata.urlEntry
                key = text .. '\0' .. (e.label or '') .. '\0' .. (e.caption or '') .. '\0' .. (e.captionPage or '')
            end
            if text ~= '' and not seen[key] then
                seen[key] = true
                field.values[#field.values + 1] = { value = text, origin = source, metadata = metadata }
            end
        end
        for _, value in ipairs(own) do
            if definition.type == 'url' and type(value) == 'table' then
                if type(value.url) ~= 'string' then error('SWS/API: Ungültiger URL-Eintrag für „' .. definition.name .. '“.', 0) end
                for _, key in ipairs({'label','caption','captionPage'}) do
                    if value[key] ~= nil and type(value[key]) ~= 'string' then error('SWS/API: Ungültige URL-Zusatzangabe.', 0) end
                end
                add(value.url, 'own', {raw = value.url, urlEntry = value})
            elseif definition.type == 'historicalDate' then
                local time = ownTime(value)
                add(ownTimeLabel(time, definition, options.language or mw.language.getContentLanguage().code), 'own', time)
            elseif definition.type == 'select' then
                add(selectLabel(value, definition), 'own', { raw = value })
            else
                if type(value) ~= 'string' and type(value) ~= 'number' and type(value) ~= 'boolean' then
                    error('SWS/API: Belegtes Feld „' .. definition.name .. '“ hat einen noch nicht unterstützten Werttyp.', 0)
                end
                if type(value) == 'number' and (value ~= value or value == math.huge or value == -math.huge) then
                    error('SWS/API: Ungültige Zahl in „' .. definition.name .. '“.', 0)
                end
                add(value, 'own', { raw = value })
            end
        end
        if #own > 0 then
            field.status = 'own'
        elseif definition.externalProperty and origin ~= 'own' then
            local remote = external[definition.name]
            local manual = definition.type == 'historicalDate' and remote and remote.status == 'manual'
            if not remote or (remote.status ~= 'resolved' and remote.status ~= 'missing' and not manual) then
                error('SWS/API: Quellenwerte für „' .. definition.name .. '“ nicht verfügbar.', 0)
            end
            if remote.source ~= definition.externalProperty.source
                or remote.propertyId ~= definition.externalProperty.id or type(remote.values) ~= 'table' then
                error('SWS/API: Quellenzuordnung für „' .. definition.name .. '“ stimmt nicht überein.', 0)
            end
            field.status, field.reason = remote.status, remote.reason
            for _, value in ipairs(remote.values) do
                if value.kind == 'time' and definition.type == 'historicalDate' then
                    if type(value.sourceTime) ~= 'table' or type(value.label) ~= 'string' then
                        error('SWS/API: Ungültiger Zeitwert für „' .. definition.name .. '“.', 0)
                    end
                    add(value.label, remote.source, value)
                elseif value.kind == 'entity' then
                    if type(value.label) ~= 'string' or value.label == '' or value.label == value.id then
                        error('SWS/API: Wertbezeichnung für „' .. definition.name .. '“ fehlt.', 0)
                    end
                    add(value.label, remote.source, value)
                elseif value.kind ~= 'somevalue' and value.kind ~= 'novalue' then
                    error('SWS/API: Nicht unterstützter Quellenwert für „' .. definition.name .. '“.', 0)
                end
            end
        else
            field.status = 'missing'
        end
        fields[#fields + 1] = field
    end
    return { subject = subject, schema = schema, fields = fields }
end

return p
