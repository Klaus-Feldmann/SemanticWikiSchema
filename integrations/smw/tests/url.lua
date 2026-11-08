local n=0
local function check(ok,msg)n=n+1;if not ok then error(msg)end end
local records,other,allCalls,bridgeCalls,fail={{url='https://de.wikipedia.org/wiki/Regiomontanus?oldid=123&x=2',label='Regiomontanus',caption='Wikipedia (de)',captionPage='Vermerk:Wikipedia'}},{},{},0,false
mw={loadData=function(name)return require(name)end,text={trim=function(s)return s:match('^%s*(.-)%s*$')end,nowiki=function(s)return s end},language={getContentLanguage=function()return {code='de'}end},hash={hashValue=function(_,s)return 'id'..s:gsub('%z','_')end}}
package.preload['mw.sws']=function()return {
getMainSubject=function()return {id='sTest',schema='Person',statements={Quellenangabe={values=records}}}end,
getSchema=function()return {name='Person',properties={{name='Quellenangabe',type='url'},{name='Zahl',type='number'}}}end,
getAll=function(name)if name=='Zahl'then return other end;local t={};for _,v in ipairs(records)do t[#t+1]=v.url end;return t end
}end
package.preload['mw.swsbase']=function()bridgeCalls=bridgeCalls+1;error('Not needed')end
local api=require('Modul:SWS/API');local m=require('Modul:SWS');local config=require('Modul:SWS/config')
local frame={callParserFunction=function(self,o)allCalls[#allCalls+1]=o;return ''end}
local values=api.collect().fields[1].values
check(values[1].value==records[1].url,'Scalar URL preserved')
check(values[1].metadata.urlEntry.captionPage=='Vermerk:Wikipedia','All URL fields available to Lua')
m.annotieren(frame)
check(#allCalls==2 and allCalls[1].name=='#set' and allCalls[2].name=='#subobject','Flat URL plus grouped record')
local args=table.concat(allCalls[2].args,'|')
check(args:find('Quellenbezeichnung=Wikipedia (de)',1,true),'Caption stored')
check(args:find('Quellentitel=Regiomontanus',1,true),'Display title stored')
check(args:find('Quellenbezeichnungsseite=Vermerk:Wikipedia',1,true),'Internal page target preserved')
check(allCalls[1].args[1]=='Wikipedia (de)='..records[1].url,'URL query string unchanged')
check(bridgeCalls==0,'No external identity lookup required')
-- Two different descriptions of one URL retain their pairing, flat URLs remain unique.
records[2]={url=records[1].url,label='Alternative',caption='Another source'};allCalls={};m.annotieren(frame)
check(#allCalls==4,'Two caption properties plus two distinct records')
check(table.concat(allCalls[4].args,'|'):find('Quellentitel=Alternative',1,true),'Second caption not cross-paired')
check(allCalls[2].args[1]=='Another source='..records[1].url,'Different captions use separate SMW properties')
records[2].caption='Wikipedia (de)';allCalls={};m.annotieren(frame)
check(#allCalls==3,'Same caption and URL are deduplicated per target property, records stay paired')
-- Source changes are reflected in newly submitted records without an independent cache.
records[1].label='Changed';records[2]=nil;allCalls={};m.annotieren(frame)
check(#allCalls==2 and table.concat(allCalls[2].args,'|'):find('Quellentitel=Changed',1,true),'Changed record follows')
records[1].caption='Wikipedia (en)';allCalls={};m.annotieren(frame)
check(allCalls[1].args[1]=='Wikipedia (en)='..records[1].url,'Changed caption selects the new property')
records={};allCalls={};m.annotieren(frame);check(#allCalls==0,'Removed record is no longer submitted')
records={{url='https://example.org',label='Title'}};other={'not a number'};allCalls={}
check(not pcall(m.annotieren,frame)and #allCalls==0,'Preflight prevents partial flat or grouped output')
other={};config.urlEntries.url='bad|name';allCalls={}
check(not pcall(m.annotieren,frame)and #allCalls==0,'Invalid child property names rejected before writes')
config.urlEntries.url='Quellen-URL'
records={{url='https://example.org'}};allCalls={};m.annotieren(frame)
check(allCalls[1].args[1]=='Quellenangabe=https://example.org','Missing caption uses original schema property')
records={{url='https://example.org',caption='template'}};allCalls={}
check(not pcall(m.annotieren,frame)and #allCalls==0,'Reserved dynamic property rejected before any writes')
records={{url='https://example.org',caption='bad|name'}};allCalls={}
check(not pcall(m.annotieren,frame)and #allCalls==0,'Malformed dynamic property rejected before any writes')
records={{url='https://example.org',caption='Source#fragment'}};allCalls={}
check(not pcall(m.annotieren,frame)and #allCalls==0,'Fragment cannot silently become another SMW property')
mw.title={new=function(t) if t=='Vermerk:Wikipedia' then return {prefixedText=t,isExternal=false,namespace=102} end end}
check(m.urlZeile({args={url='https://de.wikipedia.org/wiki/Regiomontanus?oldid=123&x=2',titel='Regiomontanus',bezeichnung='Wikipedia (de)',ziel='Vermerk:Wikipedia'}})=='[[:Vermerk:Wikipedia|Wikipedia (de)]]: [https://de.wikipedia.org/wiki/Regiomontanus?oldid=123&x=2 Regiomontanus]','Paired SMW display links')
check(m.urlZeile({args={url='javascript:alert(1)',titel='Title'}})=='Title','Unsafe address is never emitted as a link')
check(m.urlZeile({args={url='https://example.org/a[1]',titel='Title'}})=='[https://example.org/a%5B1%5D Title]','URL brackets cannot break link syntax')
print(n..' structured URL Lua checks passed.')
