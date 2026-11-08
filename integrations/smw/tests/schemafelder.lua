local n = 0
local function check(ok, message) n = n + 1; if not ok then error(message) end end
local own, definitions, remote, writes, calls, schemaName, exists, storedStatements
local function reset(name, defs, values, fields)
    schemaName, definitions, own, remote = name or 'Person', defs or {}, values or {}, fields or {}
    writes, calls, exists, storedStatements = {}, 0, true, {}
end
mw = {
    loadData = function(name) return require(name) end,
    language = { getContentLanguage = function() return {code='de'} end },
    text = {trim=function(s)return s:match('^%s*(.-)%s*$')end,
        nowiki=function(s)return s:gsub('&','&amp;'):gsub('<','&lt;'):gsub('>','&gt;'):gsub('%[','&#91;'):gsub('%]','&#93;'):gsub('|','&#124;')end}
}
package.preload['mw.sws'] = function() return {
    getMainSubject=function(page) return exists and {id='sTest',schema=schemaName,statements=storedStatements} or nil end,
    getSubject=function(id) return exists and {id='sTest',schema=schemaName,statements=storedStatements} or nil end,
    getSchema=function() return {name=schemaName,properties=definitions} end,
    getAll=function(name)
        local values = own[name] or {}
        -- Native Lua interface resolves known select IDs to their schema labels.
        local result={}
        for _, value in ipairs(values) do
            local resolved=value
            for _, definition in ipairs(definitions) do
                if definition.name==name and definition.type=='select' then
                    for _, option in ipairs(definition.options or {}) do
                        if value==option.id then resolved=option.label end
                    end
                end
            end
            result[#result+1]=resolved
        end
        return result
    end
} end
package.preload['mw.swsbase'] = function() return {getFields=function()
    calls=calls+1
    return {version=1,scope='current',mainSubjectId='sTest',fields=remote}
end} end
local api, module = require('Modul:SWS/API'), require('Modul:SWS')
local config = require('Modul:SWS/config')
local frame={args={},callParserFunction=function(self, options)
    check(options.name=='#set' and #options.args==1,'One structured #set argument per value')
    writes[#writes+1]=options.args[1]
    return ''
end}
local function has(value)
    local count=0;for _, row in ipairs(writes)do if row==value then count=count+1 end end;return count
end
local function rejects(fragment)
    local ok, err=pcall(module.annotieren,frame)
    check(not ok and tostring(err):find(fragment,1,true)~=nil,'Expected error: '..fragment)
    check(#writes==0,'No partial #set submissions after preflight failure')
end
local function entity(name,prop,label)
    return {name=name,source='wikidata',propertyId=prop,status='resolved',values={{kind='entity',id='Q999',label=label}}}
end

reset('Person',{{name='Geburtsort',type='text',externalProperty={source='wikidata',id='P19'},wikiLink={namespace='Vermerk'}}},{},{entity('Geburtsort','P19','Athen')})
module.annotieren(frame);check(has('Geburtsort=Athen')==1,'Previously filtered field now annotated without namespace prefix')
check(calls==1,'All needed sources batched once')

reset('Person',{{name='Sterbeort',type='text',externalProperty={source='wikidata',id='P20'}}},{Sterbeort={'Eigener Ort'}},{entity('Sterbeort','P20','Quellenort')})
module.annotieren(frame);check(has('Sterbeort=Eigener Ort')==1 and has('Sterbeort=Quellenort')==0,'Own value wins for text too');check(calls==0,'Own value does not require source request')
check(api.collect({field='Sterbeort',origin='external'}).fields[1].values[1].value=='Quellenort','Explicit source-only read remains available')

reset('Person',{{name='Namensvarianten',type='text'}},{Namensvarianten={'Alpha','Alpha','Beta'}})
module.annotieren(frame);check(has('Namensvarianten=Alpha')==1 and has('Namensvarianten=Beta')==1 and #writes==2,'Several own values retained and deduplicated')

reset('Werk',{
    {name='Leserschaft',type='text'},{name='Leserschaftziel',type='text'},{name='Werkausgabe',type='text'},
    {name='Urheber',type='text'},{name='Werkform',type='select',options={{id='o123',label='Abhandlung'}}}
},{Leserschaft={'Benutzer:Leser'},Leserschaftziel={'Gruppe'},Werkausgabe={'Sammlung'},Urheber={'Autor'},Werkform={'o123'}})
module.annotieren(frame)
check(has('hat Leserschaft=Benutzer:Leser')==1,'Reader alias')
check(has('hat Leserschaftziel=Gruppe')==1,'Intended-reader alias')
check(has('hat Werkausgabe=Sammlung')==1,'Edition alias')
check(has('Urheber=Autor')==1,'Unmapped field keeps its name')
check(has('Werkform=Abhandlung')==1 and has('Werkform=o123')==0,'Resolved select label, no option ID')

reset('Person',{{name='Werkform',type='select',options={{id='o123',label='Abhandlung'}}}},{Werkform={'oUnknown'}});rejects('Werkform')

local url='https://example.org/path?q=A%20B&x=1&x=2#part'
reset('Person',{{name='Quellenangabe',type='url'}},{Quellenangabe={url}})
module.annotieren(frame);check(has('Quellenangabe='..url)==1,'URL query/fragment preserved without entity double-encoding')

reset('Person',{{name='Anzahl',type='number'},{name='Aktiv',type='boolean'},{name='Erledigt',type='boolean'}},{Anzahl={0},Aktiv={false},Erledigt={true}})
module.annotieren(frame);check(has('Anzahl=0')==1 and has('Aktiv=0')==1 and has('Erledigt=1')==1,'Zero and both booleans preserved for typed SMW values')

reset('Person',{{name='Bild',type='image'},{name='Leer',type='text'},{name='Nichts',type='text'}},{Bild={'commons:X.jpg'},Leer={'  '}})
module.annotieren(frame);check(#writes==0 and calls==0,'Images and empty values excluded')

reset('Person',{{name='Sammlung',type='relation'}},{Sammlung={'Andere Sammlung'}});rejects('Sammlung')
reset('Person',{{name='Text',type='monolingualText'}},{Text={'Wort'}});rejects('monolingualText')
reset('Person',{{name='Unbekannt',type='newType'}})
storedStatements.Unbekannt={propertyType='newType',values={{raw='structured'}}};rejects('Unbekannt')
reset('Person',{{name='Sammlung',type='relation'},{name='Text',type='monolingualText'}},{})
module.annotieren(frame);check(#writes==0,'Empty unsupported types do not block imports')

reset('Person',{{name='Datum',type='date'},{name='Uhrzeit',type='dateTime'}},{Datum={'1458-10-01'},Uhrzeit={'2026-02-12T08:49:00.000Z'}})
module.annotieren(frame);check(has('Datum=1 October 1458 Gr')==1,'Core date remains Gregorian before 1582')
check(has('Uhrzeit=12 February 2026 08:49:00+00:00 .000 Gr')==1,'UTC datetime and fractional zeros passed without dropping time')

local clocks={
 {'2026-02-12T09:49:00+01:00','12 February 2026 08:49:00+00:00 Gr'},
 {'2026-02-12T00:15:59.123456789+05:45','11 February 2026 18:30:59+00:00 .123456789 Gr'},
 {'2026-02-12T23:30:00-03:30','13 February 2026 03:00:00+00:00 Gr'},
 {'2026-02-12T09:30:00-00:30','12 February 2026 10:00:00+00:00 Gr'},
 {'2026-01-01T00:00:00+01:00','31 December 2025 23:00:00+00:00 Gr'},
 {'2025-12-31T23:30:00-01:00','1 January 2026 00:30:00+00:00 Gr'},
 {'2024-03-01T00:00:00+01:00','29 February 2024 23:00:00+00:00 Gr'},
 {'1900-03-01T00:00:00+01:00','28 February 1900 23:00:00+00:00 Gr'}
}
for _, c in ipairs(clocks)do
 reset('Person',{{name='Uhrzeit',type='dateTime'}},{Uhrzeit={c[1]}});module.annotieren(frame)
 check(has('Uhrzeit='..c[2])==1,'UTC normalization: '..c[1])
end
for _, raw in ipairs({'2025-02-29','2026-04-31','0000-01-01','2026-01','2026-01-01Z'})do
 reset('Person',{{name='Datum',type='date'}},{Datum={raw}});rejects('Datum')
end
for _, raw in ipairs({'2026-01-01T00:00:00','2026-01-01T24:00:00Z','2026-01-01T00:00:60Z','2026-01-01T00:00:00.1234567890Z','2026-01-01T00:00:00+00:61','0001-01-01T00:00:00+01:00'})do
 reset('Person',{{name='Uhrzeit',type='dateTime'}},{Uhrzeit={raw}});rejects('Uhrzeit')
end
for _, raw in ipairs({math.huge,0/0})do
 reset('Person',{{name='Anzahl',type='number'}},{Anzahl={raw}});rejects('Anzahl')
end
reset('Person',{{name='Quellenangabe',type='url'}},{Quellenangabe={'example.org'}});rejects('Quellenangabe')

reset('Person',{{name='Ort',type='text',externalProperty={source='wikidata',id='P19'}}},{},{{name='Ort',source='wikidata',propertyId='P19',status='unavailable',values={}}});rejects('Ort')
reset('Person',{{name='Ort',type='text',externalProperty={source='wikidata',id='P19'}}},{},{entity('Ort','P19','Q999')});rejects('Wertbezeichnung')
reset('Person',{{name='Ort',type='text',externalProperty={source='wikidata',id='P19'}}},{},{{name='Ort',source='wikibase-local',propertyId='P19',status='resolved',values={{kind='entity',id='Q999',label='Ort'}}}});rejects('Quellenzuordnung')
reset('Person',{{name='Ort',type='text',externalProperty={source='wikidata',id='P19'}}},{},{{name='Ort',source='wikidata',propertyId='P19',status='missing',values={}}});module.annotieren(frame);check(#writes==0,'Confirmed missing source value is empty')

for _, key in ipairs({'Bad::Property','bad=property','bad|property','template','@json','+sep'})do
 reset('Person',{{name=key,type='text'}},{[key]={'value'}});rejects('SMW-Eigenschaftsname')
end
reset('Werk',{{name='Urheber',type='text'}},{Urheber={'X'}})
config.properties.Werk.Urheber={};rejects('Ungültige Zuordnung');config.properties.Werk.Urheber=nil
config.properties.Werk.A='Gleich';config.properties.Werk.B='Gleich'
reset('Werk',{{name='A',type='text'},{name='B',type='text'}},{A={'Wert'},B={'Wert'}});module.annotieren(frame);check(#writes==1,'Deduplication uses mapped SMW property across fields')
config.properties.Werk.A=nil;config.properties.Werk.B=nil

reset('Person',{{name='Forschungsnotiz',type='text'}},{Forschungsnotiz={']]{{NotATemplate}}|+sep=;&<text>'}})
module.annotieren(frame);check(has('Forschungsnotiz=]]{{NotATemplate}}|+sep=;&<text>')==1,'Value stays one literal parser argument')

-- Reprocessing simply submits the current values; it does not maintain an append-only history.
reset('Person',{{name='Ort',type='text'}},{Ort={'Alt'}});module.annotieren(frame);check(has('Ort=Alt')==1,'Initial current value')
writes={};own.Ort={'Neu'};module.annotieren(frame);check(has('Ort=Neu')==1 and has('Ort=Alt')==0,'Changed value no longer resubmitted')
writes={};own.Ort={};module.annotieren(frame);check(#writes==0,'Removed value no longer submitted')
reset();exists=false;module.annotieren(frame);check(#writes==0,'Missing main subject submits nothing')

-- Use the actual current schemas, own subjects, and separately captured bridge responses.
for title, page in pairs(liveFixtures.pages)do
 local schema=liveFixtures.schemas[page.schema]
 local bridge=liveFixtures.sources[title] or {fields={}}
 reset(page.schema,schema.properties,page.own,bridge.fields)
 -- Fixture bridge identity is replaced by the controlled mock subject ID, not changed in source files.
 module.annotieren(frame)
 if title=='Platon' then
  check(has('Geburtsort=Athen')==1 and has('Sterbeort=Athen')==1,'Plato locations from captured source')
  check(has('Quellenangabe=https://de.wikipedia.org/wiki/Platon')==1,'Plato own URL')
  check(has('Geburtsdatum=427 BC Jl')==1 and has('Sterbedatum=347 BC Jl')==1,'Plato own BCE years survive generalization')
  check(has('Tätigkeit=Philosoph')==1,'Newly configured P106 imported without code changes')
 elseif title=='Johann Wolfgang von Goethe' then
  check(has('Sterbeort=Weimar')==1,'Goethe location appears once despite own/source duplicate')
  check(has('Geburtsname=Johann Wolfgang Goethe')==1,'Goethe own name')
  check(has('Geburtsdatum=28 August 1749 Gr')==1 and has('Sterbedatum=22 March 1832 Gr')==1,'Goethe captured precise source dates')
 else
  check(has('Werkform=Abhandlung')==1 and has('Werkart=Abschrift')==1,'Actual work selection labels')
  check(has('Urheberdatum=1 October 1458 Jl')==1 and has('Urwerkdatum=1 October 1458 Gr')==1,'Different native date types keep their distinct calendar semantics')
  check(has('Erstelldatum=12 February 2026 08:49:00+00:00 .000 Gr')==1,'Actual work timestamp')
  check(has('hat Werkausgabe=Sammlung')==1,'Actual work alias')
  check(calls==0,'Own-only work schema needs no Wikidata request')
 end
end
reset('Person',{{name='Geburtsort',type='page'},{name='Bezug',type='page'}},{Geburtsort={'Athen','Athen'},Bezug={'Werk:De mathematica perfectione'}})
module.annotieren(frame)
check(has('Geburtsort=Athen')==1,'Page values are annotated once')
check(has('Bezug=Werk:De mathematica perfectione')==1,'Page namespace survives SMW import')
check(calls==0,'Local page fields do not need an external request')
reset('Person',{{name='Geburtsort',type='page'}},{Geburtsort={}})
module.annotieren(frame);check(#writes==0,'Cleared page field emits no annotation')
reset('Person',{{name='Verarbeitung',type='wikitext',execute=true,wikitext='{{SWS}}'},{name='Wikitextnotiz',type='wikitext'},{name='Ort',type='page'}},{Verarbeitung={'{{NichtAusfuehren}}'},Wikitextnotiz={'{{AuchNicht}}'},Ort={'Athen'}})
module.annotieren(frame)
check(#writes==1 and has('Ort=Athen')==1,'Wikitext source and schema code are not SMW metadata')
print(n..' schema-field checks passed.')
