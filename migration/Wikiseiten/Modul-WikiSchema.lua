-- Schema-gesteuerte SMW-Übernahme; keine gepflegte Liste aller Feldnamen.
local api = {}
function api.collect(options)
    local nw = require('mw.sws')
    if type(nw.collectFields) ~= 'function' then
        error('WikiSchema: Die gemeinsame PHP-Werteschnittstelle fehlt.', 0)
    end
    return nw.collectFields(options or {})
end
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
    if not origins[origin] then error('WikiSchema: Unbekannte Herkunft.', 0) end
    return { field = trim(a[1]) or trim(a.feld), page = trim(a.seite),
        subject = trim(a.subjekt), language = trim(a.sprache), origin = origins[origin] }
end

local function literal(value)
    return mw.text.nowiki(tostring(value))
end

function p.werte(frame)
    local o = options(frame)
    if not o.field then error('WikiSchema: Feldname fehlt.', 0) end
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
    if not o.field then error('WikiSchema: Feldname fehlt.', 0) end
    local result = api.collect(o)
    local index = tonumber(frame.args.index or '1')
    if not index or index < 1 or index ~= math.floor(index) then
        error('WikiSchema: Index muss eine positive ganze Zahl sein.', 0)
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
    if not o.field then error('WikiSchema: Feldname fehlt.', 0) end
    -- Label-Abfrage benötigt keinen externen Wertabruf.
    o.origin = 'own'
    local result = api.collect(o)
    return result.fields[1] and literal(result.fields[1].name) or ''
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

-- Annotation is now performed by the extension. Keep old calls harmless.
function p.annotieren(frame)
    local nw = require('mw.sws')
    if type(nw.hasAutomaticAnnotations) ~= 'function' or not nw.hasAutomaticAnnotations() then
        error('WikiSchema: Fuer dieses Modul wird die Version mit automatischer SMW-Uebernahme benoetigt.', 0)
    end
    return ''
end

return p
