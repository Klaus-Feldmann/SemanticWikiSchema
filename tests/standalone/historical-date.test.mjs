import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseHistoricalDate, historicalDateBounds, historicalYearTitle } from '../../resources/ext.sws/src/domain/propertyTypes/historicalDate.ts';
import { wikiPageUrl } from '../../resources/ext.sws/src/presentation/wikiLinks.ts';

for (const [wire, expected] of [
 ['1458',{year:1458}], ['1458-10',{year:1458,month:10}], ['1458-10-01',{year:1458,month:10,day:1}],
 ['0001',{year:1}], ['-0001',{year:-1}], ['-0044',{year:-44}], ['-0044-03-15',{year:-44,month:3,day:15}],
 ['2024-02-29',{year:2024,month:2,day:29}], ['-0001-02-29',{year:-1,month:2,day:29}], ['9999-12-31',{year:9999,month:12,day:31}],
 ['-9999',{year:-9999}]
]) test(`parses ${wire} without adding unknown components`,()=>assert.deepEqual(parseHistoricalDate(wire),expected));
for (const wire of ['', '0000','-0000','0000-01-01','-0000-01-01','44','-44','+0044',' 1458','1458 ',
 '1458-1','1458-01-1','1458-00','1458-13','1458-01-00','1458-01-32','1458-04-31','2025-02-29','1900-02-29',
 '-0044-02-29','1458-10-01T00:00:00Z','10000','-10000','1458-10-01junk','1e03','1458-01-01\n'])
 test(`rejects ${JSON.stringify(wire)}`,()=>assert.equal(parseHistoricalDate(wire),null));
test('whole year produces bounds without changing stored precision',()=>{
 const value=parseHistoricalDate('1458'); assert.deepEqual(historicalDateBounds(value),{start:2253593,end:2253957}); assert.deepEqual(value,{year:1458});
});
test('February range respects the declared precision',()=>assert.deepEqual(historicalDateBounds(parseHistoricalDate('2024-02')),{start:2460342,end:2460370}));
test('BCE -> CE comparison has no year-zero gap',()=>{
 const a=historicalDateBounds(parseHistoricalDate('-0001-12-31'));
 const b=historicalDateBounds(parseHistoricalDate('0001-01-01'));
 assert.ok(a.end < b.start);
 assert.ok(historicalDateBounds(parseHistoricalDate('-0044')).end < historicalDateBounds(parseHistoricalDate('-0043')).start);
});
test('partial-date overlap differs from exact-day equality',()=>{
 const year=historicalDateBounds(parseHistoricalDate('1458'));
 const day=historicalDateBounds(parseHistoricalDate('1458-10-01'));
 assert.ok(year.start <= day.end && year.end >= day.start); assert.notDeepEqual(year,day);
});
test('year links use historical era names',()=>{assert.equal(historicalYearTitle(-44),'44 v. Chr.');assert.equal(historicalYearTitle(1458),'1458');});
// Exercise the actual URL helper against a MediaWiki getUrl stub; no DOM/HTML injection.
globalThis.mw={util:{getUrl:title=>'/wiki/'+encodeURIComponent(title.replaceAll(' ','_'))}};
for(const [value,ns,result] of [['Buch','','/wiki/Buch'],['Federica de Felice','','/wiki/Federica_de_Felice'],['De mathematica perfectione','Werk','/wiki/Werk%3ADe_mathematica_perfectione'],['Neue Bezeichnung','','/wiki/Neue_Bezeichnung']])
 test(`generic title ${value}`,()=>assert.equal(wikiPageUrl(value,ns),result));
for(const value of ['',':Kategorie:Test','[[Buch]]','<img src=x>','A|B','javascript:alert(1)','https://example.org','A\nB'])
 test(`unsafe title ${JSON.stringify(value)}`,()=>assert.equal(wikiPageUrl(value),null));
