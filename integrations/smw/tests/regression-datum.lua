local n = 0
local function check(ok, message) n = n + 1; if not ok then error(message) end end
local own, writes = {}, {}
local subject = {id='sTest',schema='Person'}
local definitions = {
 {name='Klassenzuordnung',type='text',externalProperty={source='wikidata',id='P31'}},
 {name='Geburtsdatum',type='historicalDate',wikiLink={dateBceDe='v. u. Z.',dateBceEn='BCE'},externalProperty={source='wikidata',id='P569'}}
}
local bridge
local function date(raw, precision, model)
 return {kind='time',label='28. August 1749',sourceTime={time=raw or '+1749-08-28T00:00:00Z',precision=precision or 11,calendarmodel=model or 'http://www.wikidata.org/entity/Q1985727'}}
end
local function reset(value, status)
 own={}; writes={}
 bridge={version=1,scope='current',mainSubjectId='sTest',fields={
  {name='Klassenzuordnung',source='wikidata',propertyId='P31',status='resolved',values={{kind='entity',id='Q5',label='Mensch'}}},
  {name='Geburtsdatum',source='wikidata',propertyId='P569',status=status or 'resolved',values=value and {value} or {}}
 }}
end
mw={language={getContentLanguage=function()return {code='de'}end},text={
 trim=function(s)return s:match('^%s*(.-)%s*$')end,
 nowiki=function(s)return s:gsub('&','&amp;'):gsub('<','&lt;'):gsub('>','&gt;'):gsub('%[','&#91;'):gsub('%]','&#93;'):gsub('|','&#124;')end
}}
package.preload['mw.sws']=function()return {
 getMainSubject=function()return subject end,getSubject=function()return subject end,
 getSchema=function()return {properties=definitions}end,
 getAll=function(name)return own[name] or {}end
}end
package.preload['mw.swsbase']=function()return {getFields=function()return bridge end}end
mw.loadData = function(name) return require(name) end
local api=require('Modul:SWS/API')
local module=require('Modul:SWS')
local frame={args={},callParserFunction=function(self,options)check(options.name=='#set','Uses #set');writes[#writes+1]=options.args[1];return ''end}
reset(date());module.annotieren(frame)
check(writes[1]=='Klassenzuordnung=Mensch','Existing classification retained')
check(writes[2]=='Geburtsdatum=28 August 1749 Gr','Explicit SMW Gregorian day')
reset(nil,'manual');own.Geburtsdatum={'-0347'};module.annotieren(frame)
check(writes[2]=='Geburtsdatum=347 BC Jl','Own research year overrides ambiguous source')
check(module.wert({args={[1]='Geburtsdatum'}})=='347 v. u. Z.','Own display uses configured suffix')
check(module.wert({args={[1]='Geburtsdatum',sprache='en'}})=='347 BCE','Own English label independent of SMW syntax')
reset(nil,'manual');module.annotieren(frame);check(#writes==1,'Ambiguous source does not invent a date')
reset(nil,'unavailable');local ok=pcall(module.annotieren,frame);check(not ok and #writes==0,'Unavailable date blocks partial new annotation')
reset(date());own.Geburtsdatum={'1750-07'};module.annotieren(frame);check(writes[2]=='Geburtsdatum=July 1750 Gr','Month precision is not a day')
reset(date());own.Geburtsdatum={'1582-10-04'};module.annotieren(frame);check(writes[2]=='Geburtsdatum=4 October 1582 Jl','Own last Julian day')
reset(date());own.Geburtsdatum={'1582-10-15'};module.annotieren(frame);check(writes[2]=='Geburtsdatum=15 October 1582 Gr','Own first Gregorian day')
reset(date('-0347-00-00T00:00:00Z',9,'http://www.wikidata.org/entity/Q1985786'));module.annotieren(frame)
check(writes[2]=='Geburtsdatum=347 BC Jl','External BCE year keeps calendar')
reset(date('+1700-12-25T00:00:00Z',11,'http://www.wikidata.org/entity/Q1985786'));bridge.fields[2].values[1].label='5. Januar 1701';module.annotieren(frame)
check(writes[2]=='Geburtsdatum=25 December 1700 Jl','SMW conversion uses original calendar, not converted display')
reset(date('+1749-08-28T00:00:00Z',11,'bad-calendar'));ok=pcall(module.annotieren,frame);check(not ok and #writes==0,'Unknown calendar does not write partial rows')
reset(date());bridge.mainSubjectId='wrong';ok=pcall(module.annotieren,frame);check(not ok and #writes==0,'Main-subject guard retained')
reset(date());subject=nil;check(module.annotieren(frame)=='' and #writes==0,'No main subject does not annotate')
print(n..' Lua module checks passed.')
