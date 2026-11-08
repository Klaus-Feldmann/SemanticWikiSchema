-- Schema-gesteuerte SMW-Übernahme; keine gepflegte Liste aller Feldnamen.
local api = require('Modul:SWS/API')
local config = mw.loadData('Modul:SWS/config')
local p = {}

local function trim(value)
    if value == nil then return nil end
    value = mw.text.trim(tostring(value))
    return value ~= '' and value or nil
end

local function options(frame)
    local a = frame.args
    local origin = trim(a.herkunft) or 'alle'
    local origins = { alle = 'all', eigene = 'own', abgerufen = 'external' }
    if not origins[origin] then error('SWS: Unbekannte Herkunft.', 0) end
    return { field = trim(a[1]) or trim(a.feld), page = trim(a.seite),
        subject = trim(a.subjekt), language = trim(a.sprache), origin = origins[origin] }
end

local function literal(value)
    return mw.text.nowiki(tostring(value))
end

function p.werte(frame)
    local o = options(frame)
    if not o.field then error('SWS: Feldname fehlt.', 0) end
    local result, values, seen = api.collect(o), {}, {}
    for _, field in ipairs(result.fields) do
        for _, value in ipairs(field.values) do
            if not seen[value.value] then
                seen[value.value] = true
                values[#values + 1] = literal(value.value)
            end
        end
    end
    return table.concat(values, frame.args.trenner or ', ')
end

function p.wert(frame)
    local o = options(frame)
    if not o.field then error('SWS: Feldname fehlt.', 0) end
    local result = api.collect(o)
    local index = tonumber(frame.args.index or '1')
    if not index or index < 1 or index ~= math.floor(index) then
        error('SWS: Index muss eine positive ganze Zahl sein.', 0)
    end
    local unique, seen = {}, {}
    for _, field in ipairs(result.fields) do
        for _, value in ipairs(field.values) do
            if not seen[value.value] then
                seen[value.value] = true
                unique[#unique + 1] = value.value
            end
        end
    end
    return unique[index] and literal(unique[index]) or ''
end

function p.label(frame)
    local o = options(frame)
    if not o.field then error('SWS: Feldname fehlt.', 0) end
    -- Label-Abfrage benötigt keinen externen Wertabruf.
    o.origin = 'own'
    local result = api.collect(o)
    return result.fields[1] and literal(result.fields[1].name) or ''
end

local function smwTime(value)
    local raw = value.metadata and value.metadata.sourceTime
    if not raw then error('SWS: Zeitmetadaten fehlen.', 0) end
    local y, m, d = raw.time:match('^([+-]%d+)%-(%d%d)%-(%d%d)T')
    local year, month, day = tonumber(y), tonumber(m), tonumber(d)
    local precision = raw.precision
    if not year or year == 0 or (precision ~= 9 and precision ~= 10 and precision ~= 11) then
        error('SWS: Nicht übernehmbarer Zeitwert.', 0)
    end
    local calendar
    if raw.calendarmodel == 'http://www.wikidata.org/entity/Q1985786' or raw.calendarmodel == 'https://www.wikidata.org/entity/Q1985786' then calendar = 'Jl'
    elseif raw.calendarmodel == 'http://www.wikidata.org/entity/Q1985727' or raw.calendarmodel == 'https://www.wikidata.org/entity/Q1985727' then calendar = 'Gr'
    else error('SWS: Unbekannter Kalender.', 0) end
    local months = {'January','February','March','April','May','June','July','August','September','October','November','December'}
    local text = ''
    if precision == 11 then text = day .. ' ' end
    if precision >= 10 then
        if not months[month] then error('SWS: Ungültiger Monat.', 0) end
        text = text .. months[month] .. ' '
    end
    return text .. math.abs(year) .. (year < 0 and ' BC' or '') .. ' ' .. calendar
end

local months = {'January','February','March','April','May','June','July','August','September','October','November','December'}
local supported = { page = true, text = true, select = true, url = true, number = true, boolean = true,
    historicalDate = true, date = true, dateTime = true }

local function validDate(year, month, day)
    if year < 1 or year > 9999 or month < 1 or month > 12 then return false end
    local leap = year % 4 == 0 and (year % 100 ~= 0 or year % 400 == 0)
    local days = {31, leap and 29 or 28, 31,30,31,30,31,31,30,31,30,31}
    return day >= 1 and day <= days[month]
end

local function gregorianDate(raw)
    local y, m, d = raw:match('^(%d%d%d%d)%-(%d%d)%-(%d%d)$')
    if not y or not validDate(tonumber(y), tonumber(m), tonumber(d)) then
        error('Ungültiges gregorianisches Datum.', 0)
    end
    return tonumber(d) .. ' ' .. months[tonumber(m)] .. ' ' .. tonumber(y) .. ' Gr'
end

local function daysInMonth(year, month)
    local leap = year % 4 == 0 and (year % 100 ~= 0 or year % 400 == 0)
    return ({31, leap and 29 or 28, 31,30,31,30,31,31,30,31,30,31})[month]
end

local function gregorianDateTime(raw)
    local date, h, m, seconds, tail = raw:match('^(%d%d%d%d%-%d%d%-%d%d)T(%d%d):(%d%d):(%d%d)(.*)$')
    if not date then error('Ungültiger dateTime-Wert.', 0) end
    gregorianDate(date)
    local hour, minute, second = tonumber(h), tonumber(m), tonumber(seconds)
    if hour > 23 or minute > 59 or second > 59 then error('Ungültige Uhrzeit.', 0) end
    local fraction = tail:match('^%.(%d+)')
    if fraction then
        if #fraction > 9 then error('Nicht unterstützte Sekundenpräzision.', 0) end
        tail = tail:sub(#fraction + 2)
    end
    local offset = 0
    if tail ~= 'Z' then
        local sign, oh, om = tail:match('^([+-])(%d%d):(%d%d)$')
        if not sign or tonumber(oh) > 23 or tonumber(om) > 59 then
            error('Explizite gültige Zeitzone fehlt.', 0)
        end
        offset = (tonumber(oh) * 60 + tonumber(om)) * (sign == '-' and -1 or 1)
    end
    local y, mo, d = date:match('^(%d+)%-(%d+)%-(%d+)$')
    y, mo, d = tonumber(y), tonumber(mo), tonumber(d)
    local minutes = hour * 60 + minute - offset
    -- Normalize minutes ourselves: SMW 7.3.1 only accepts :00/:30 numeric offsets.
    -- Integer day arithmetic avoids host timezone, DST and loss of fractional seconds.
    if minutes < 0 then
        minutes = minutes + 1440; d = d - 1
        if d == 0 then mo = mo - 1; if mo == 0 then mo = 12; y = y - 1 end; d = daysInMonth(y, mo) end
    elseif minutes >= 1440 then
        minutes = minutes - 1440; d = d + 1
        if d > daysInMonth(y, mo) then d = 1; mo = mo + 1; if mo == 13 then mo = 1; y = y + 1 end end
    end
    if y < 1 or y > 9999 then error('UTC-Datum außerhalb des unterstützten Bereichs.', 0) end
    local clock = string.format('%02d:%02d:%02d+00:00', math.floor(minutes / 60), minutes % 60, second)
    -- Fraction follows the timezone as its own token, as required by SMW's parser.
    return d .. ' ' .. months[mo] .. ' ' .. y .. ' ' .. clock
        .. (fraction and (' .' .. fraction) or '') .. ' Gr'
end

local function rawScalar(value)
    if value.metadata and value.metadata.raw ~= nil then return value.metadata.raw end
    return value.value
end

local function storedValue(field, value)
    if field.type == 'historicalDate' then return smwTime(value) end
    local raw = rawScalar(value)
    if field.type == 'date' then return gregorianDate(raw) end
    if field.type == 'dateTime' then return gregorianDateTime(raw) end
    if field.type == 'number' then
        if type(raw) ~= 'number' or raw ~= raw or raw == math.huge or raw == -math.huge then
            error('Keine endliche Zahl.', 0)
        end
        return tostring(raw)
    end
    if field.type == 'boolean' then
        if type(raw) ~= 'boolean' then error('Kein Wahrheitswert.', 0) end
        return raw and '1' or '0'
    end
    if field.type == 'url' and (type(raw) ~= 'string' or not raw:match('^[%a][%w+.-]*:')) then
        error('Keine absolute URL; Protokoll bitte ausdrücklich angeben.', 0)
    end
    return value.value
end

-- Small presentation helper for an SMW table row. It does not query or annotate.
function p.urlZeile(frame)
    local url = trim(frame.args.url or frame.args[1]) or ''
    local label = trim(frame.args.titel or frame.args[2]) or url
    local caption = trim(frame.args.bezeichnung or frame.args[3])
    local target = trim(frame.args.ziel or frame.args[4])
    local prefix = ''
    if caption or target then
        local title = target and mw.title.new(target)
        local text = literal(caption or target)
        if title and not title.isExternal and title.namespace >= 0 then
            prefix = '[[:' .. title.prefixedText .. '|' .. text .. ']]: '
        else prefix = text .. ': ' end
    end
    if url:lower():match('^https?://') and not url:find('[%c%s<>"{}|\\]') then
        local address = url:gsub('%[', '%%5B'):gsub('%]', '%%5D')
        return prefix .. '[' .. address .. ' ' .. literal(label) .. ']'
    end
    return prefix .. literal(label)
end

function p.annotieren(frame)
    -- Resolve everything before the first #set. A later conversion error must not
    -- result in a newly submitted partial import from this module.
    local result = api.collect({ origin = 'all' })
    if result.missing then return '' end
    local schemaName = result.schema.name
    if type(config.properties) ~= 'table' then error('SWS/config: properties muss eine Tabelle sein.', 0) end
    local names = config.properties[schemaName] or {}
    if type(names) ~= 'table' then error('SWS/config: Schemazuordnung muss eine Tabelle sein.', 0) end
    local rows, seen, objects = {}, {}, {}
    for _, field in ipairs(result.fields) do
        local occupied = #field.values > 0 or field.hasStoredValues
        if occupied and not supported[field.type] then
            error('SWS: Belegtes Feld „' .. field.name .. '“ hat den noch nicht unterstützten Typ ' .. field.type .. '.', 0)
        end
        if #field.values > 0 then
            local property = names[field.name]
            if property == nil then property = field.name end
            if type(property) ~= 'string' then error('SWS/config: Ungültige Zuordnung für „' .. field.name .. '“.', 0) end
            property = trim(property)
            if not property or property == 'template' or property == '@json' or property:sub(1, 1) == '+' or property:find('::', 1, true) or property:find('[=|%[%]{}<>\r\n]') then
                error('SWS: Unzulässiger SMW-Eigenschaftsname für „' .. field.name .. '“.', 0)
            end
            seen[property] = seen[property] or {}
            for _, value in ipairs(field.values) do
                local ok, stored = pcall(storedValue, field, value)
                if not ok then error('SWS: Feld „' .. field.name .. '“: ' .. tostring(stored), 0) end
                local entry = value.metadata and value.metadata.urlEntry
                local targetProperty = entry and trim(entry.caption) or property
                if not targetProperty or targetProperty == 'template' or targetProperty == '@json' or targetProperty:sub(1,1) == '+' or targetProperty:find('[%c#]') or targetProperty:find('::',1,true) or targetProperty:find('[=|%[%]{}<>\r\n]') then
                    error('SWS: Unzulässige URL-Bezeichnung als SMW-Eigenschaft für „' .. field.name .. '“.', 0)
                end
                seen[targetProperty] = seen[targetProperty] or {}
                if entry then
                    local mapping = config.urlEntries
                    if type(mapping) ~= 'table' then error('SWS/config: Zuordnung für URL-Einträge fehlt.', 0) end
                    local args = {'sws-url-' .. mw.hash.hashValue('sha1', result.subject.id .. '\0' .. field.name .. '\0' .. tostring(#objects + 1))}
                    local data = {url = stored, label = entry.label, caption = entry.caption, captionPage = entry.captionPage, field = field.name, subject = result.subject.id}
                    for _, key in ipairs({'url','label','caption','captionPage','field','subject'}) do
                        local item = data[key]
                        if item ~= nil and item ~= '' then
                            local target = mapping[key]
                            if type(target) ~= 'string' or target == '' or target == 'template' or target == '@json' or target:sub(1,1) == '+' or target:find('[=|%[%]{}<>\r\n]') or target:find('::',1,true) then
                                error('SWS/config: Ungültige URL-Unterobjektzuordnung.', 0)
                            end
                            args[#args + 1] = target .. '=' .. item
                        end
                    end
                    objects[#objects + 1] = args
                end
                if not seen[targetProperty][stored] then
                    seen[targetProperty][stored] = true
                    -- Structured parser arguments need no Wikitext re-expansion.
                    -- Preserve raw value syntax here; the SMW parser owns datatype parsing.
                    rows[#rows + 1] = targetProperty .. '=' .. stored
                end
            end
        end
    end
    local output = {}
    for _, row in ipairs(rows) do
        output[#output + 1] = frame:callParserFunction{ name = '#set', args = { row } } or ''
    end
    for _, args in ipairs(objects) do
        output[#output + 1] = frame:callParserFunction{ name = '#subobject', args = args } or ''
    end
    return table.concat(output)
end

return p
