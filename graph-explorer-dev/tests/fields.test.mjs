import test from 'node:test';
import assert from 'node:assert/strict';
import {readPresentation,orderedRows,localPageUrl,createPresentationLoader,createSWSFields} from '../source/swsFields.js';
const origin='https://wiki.example',sid='E1',iri=origin+'/entity/'+sid;
const property=(source='wikidata',extra={})=>({name:'Klassifizierung',type:'text',predicateLocalName:'Klassifizierung',wikiLink:{labelTarget:'Konzept:Klassifizierung',externalTarget:'local',...extra},externalProperty:{source,id:'P31'}});
const native=(name,value)=>({name,id:origin+'/prop/'+name,property:{type:'string',values:[{value,language:'de'}]}});
function response(source='wikidata',valueLabel='Mensch') {return {version:1,scope:'current',pageId:12,mainSubjectId:sid,presentation:{version:1,pageUrlTemplate:origin+'/wiki/SWSGRAPHTITLETOKEN',definitions:[property(source),{name:'Geburtsort',type:'text',predicateLocalName:'Geburtsort',wikiLink:{values:false},externalProperty:null}]},fields:[{name:'Klassifizierung',source,propertyId:'P31',status:'resolved',values:[{kind:'entity',id:'Q5',label:valueLabel,url:source==='wikidata'?'https://www.wikidata.org/wiki/Q5':origin+'/wiki/Objekt:Q5'}]}]};}
function rows(data=response(),own=[native('Geburtsort','Frankfurt')]){return orderedRows(readPresentation(data,12,sid,origin),own,origin);}
test('schema order includes derived field before native values, with label prefix and local value links',()=>{
 const r=rows();assert.deepEqual(r.map(v=>v.name),['Klassifizierung','Geburtsort']);assert.equal(r[0].labelHref,origin+'/wiki/Konzept%3AKlassifizierung');assert.equal(r[0].values[0].href,origin+'/wiki/Mensch');assert.equal(r[1].own[0].href,null);
});
test('local target uses configured namespace, permits absent pages and needs no Q mapping',()=>{const d=response();d.presentation.definitions[0].wikiLink.namespace='Forschung';d.fields[0].values[0].label='Noch unbekannt';assert.equal(rows(d)[0].values[0].href,origin+'/wiki/Forschung%3ANoch_unbekannt');});
test('absent policy means source link; local repository and Wikidata remain distinct',()=>{
 for(const source of ['wikidata','wikibase-local']){const d=response(source);delete d.presentation.definitions[0].wikiLink.externalTarget;assert.equal(rows(d)[0].values[0].href,d.fields[0].values[0].url);}
});
test('ID-only, empty and unsafe labels retain canonical source link',()=>{for(const label of ['Q5','','<script>']){const d=response('wikidata',label);assert.equal(rows(d)[0].values[0].href,'https://www.wikidata.org/wiki/Q5');}});
test('own and derived values remain separate, neither DTO nor graph props mutated',()=>{const d=response(), own=[native('Klassifizierung','Eigene Klasse')],snapshot=JSON.stringify([d,own]);const r=rows(d,own);assert.equal(r[0].own[0].text,'Eigene Klasse');assert.equal(r[0].values[0].text,'Mensch');assert.equal(JSON.stringify([d,own]),snapshot);});
test('unknown native properties retained; missing derived field does not hide own values',()=>{const d=response();d.fields[0].status='missing';d.fields[0].values=[];const r=rows(d,[native('Klassifizierung','Eigen'),native('Alt','Unverändert')]);assert.equal(r[0].own[0].text,'Eigen');assert.equal(r[1].name,'Alt');});
test('disabled label is not linked; saved P-ID is not linked as a local value',()=>{const d=response();d.presentation.definitions[0].wikiLink.label=false;const r=rows(d,[native('Klassifizierung','P31')]);assert.equal(r[0].labelHref,null);assert.equal(r[0].own[0].href,null);});
test('multiple values, somevalue and novalue remain distinct',()=>{const d=response();d.fields[0].values.push({kind:'somevalue'},{kind:'novalue'});assert.deepEqual(rows(d)[0].values.map(v=>v.text),['Mensch','Unbekannter Wert','Kein Wert']);});
test('language-tagged source text keeps its value, language, rank, source ID, and local link policy',()=>{
 const d=response();d.presentation.definitions[0].externalProperty.id='P1477';d.fields[0].propertyId='P1477';
 d.fields[0].values=[{kind:'text',value:'Avram Noam Chomsky',language:'en',rank:'normal',statementId:'Q9049$source'}];
 const value=rows(d)[0].values[0];assert.equal(value.text,'Avram Noam Chomsky');assert.equal(value.language,'en');
 assert.equal(value.rank,'normal');assert.equal(value.statementId,'Q9049$source');assert.equal(value.href,origin+'/wiki/Avram_Noam_Chomsky');
});
test('mismatched subject, historical scope and wrong source bindings never used',()=>{
 const d=response();d.mainSubjectId='sOther';assert.equal(readPresentation(d,12,sid,origin),null);d.mainSubjectId=sid;d.scope='historical';assert.throws(()=>readPresentation(d,12,sid,origin));d.scope='current';d.fields[0].source='wikibase-local';assert.throws(()=>readPresentation(d,12,sid,origin));
});
test('unsafe or cross-source URLs and templates rejected',()=>{
 for(const url of ['javascript:alert(1)',origin+'/wiki/Q5','https://www.wikidata.org/wiki/Q6']){const d=response();d.fields[0].values[0].url=url;assert.throws(()=>readPresentation(d,12,sid,origin));}
 const d=response('wikibase-local');d.fields[0].values[0].url='https://evil.example/Q5';assert.throws(()=>readPresentation(d,12,sid,origin));
 assert.equal(localPageUrl('X','','https://evil.example/SWSGRAPHTITLETOKEN',origin),null);
});
test('local URL construction supports query article paths and rejects title injection',()=>{
 assert.equal(localPageUrl('Mensch','Attribut',origin+'/w/index.php?title=SWSGRAPHTITLETOKEN',origin),origin+'/w/index.php?title=Attribut%3AMensch');
 for(const title of ['javascript:alert(1)','[X]','X\nY',':X'])assert.equal(localPageUrl(title,'',origin+'/wiki/SWSGRAPHTITLETOKEN',origin),null);
});
test('loader batches concurrent callers, uses private current-main API and expires cache',async()=>{
 let clock=0;const calls=[];const loader=createPresentationLoader({origin,now:()=>clock,fetchImpl:async(url,opts)=>{calls.push([url,opts]);return{ok:true,json:async()=>url.includes('/sparql?')?{results:{bindings:[{page:{type:'uri',value:origin+'/page/12'}}]}}:{swsbasevalues:response()}};}});
 const [a,b]=await Promise.all([loader(iri),loader(iri)]);assert.equal(a,b);assert.equal(calls.length,2);assert.equal(new URL(calls[1][0]).searchParams.get('presentation'),'1');assert.equal(calls[1][1].credentials,'same-origin');assert.equal(calls[1][1].cache,'no-store');clock=61000;await loader(iri);assert.equal(calls.length,4);assert.equal(await loader('https://elsewhere/entity/sX'),null);
});
test('loader leaves non-main subjects and failures to native rendering and retries errors',async()=>{
 let calls=0;const loader=createPresentationLoader({origin,fetchImpl:async()=>{calls++;return{ok:false};}});await assert.rejects(loader(iri));await assert.rejects(loader(iri));assert.equal(calls,2);
 const secondary=createPresentationLoader({origin,fetchImpl:async()=>({ok:true,json:async()=>({results:{bindings:[]}})})});assert.equal(await secondary(iri),null);
});
test('stale async responses cannot replace another subject and failures keep original rendering',async()=>{
 const React={Component:class{constructor(props){this.props=props;}setState(s){this.state={...this.state,...s};}}};let finish;
 const Row=createSWSFields(React,{origin,load:()=>new Promise(resolve=>finish=resolve)});const row=new Row({iri,fallback:'native'});row.componentDidMount();assert.equal(row.render(),'native');row.componentWillUnmount();finish(readPresentation(response(),12,sid,origin));await new Promise(r=>setImmediate(r));assert.equal(row.render(),'native');
});

test('Unicode and reserved IRI characters match native predicates without URL normalization',()=>{const d=response();d.presentation.definitions[1].name='Tätigkeit';d.presentation.definitions[1].predicateLocalName='Tätigkeit';const r=rows(d,[native('Tätigkeit','Forscher')]);assert.deepEqual(r.map(v=>v.name),['Klassifizierung','Tätigkeit']);assert.equal(r[1].own[0].text,'Forscher');});

test('image claims remain readable filenames while graph thumbnails are a separate increment',()=>{
 const data=response();data.presentation.definitions[0].type='image';data.presentation.definitions[0].externalProperty.id='P18';data.fields[0].propertyId='P18';data.fields[0].values=[{kind:'image',reference:'commons:Goethe.jpg',repository:'commons',filename:'Goethe.jpg'}];
 const result=rows(data);assert.equal(result[0].values[0].text,'Goethe.jpg');assert.equal(result[0].values[0].href,null);assert.equal(result[1].name,'Geburtsort');
 data.fields[0].values[0].reference='local:Goethe.jpg';assert.throws(()=>readPresentation(data,12,sid,origin));
});

test('structured URLs keep their label and caption links paired in schema order',()=>{
 const data={version:1,scope:'current',pageId:12,mainSubjectId:sid,fields:[],presentation:{version:1,pageUrlTemplate:origin+'/wiki/SWSGRAPHTITLETOKEN',definitions:[{name:'Quellenangabe',type:'url',predicateLocalName:'Quellenangabe',wikiLink:null,externalProperty:null,ownUrls:[{url:'https://de.wikipedia.org/wiki/Regiomontanus?oldid=123&x=2',label:'Regiomontanus',caption:'Wikipedia (de)',captionPage:'Vermerk:Wikipedia'},{url:'https://other.example',label:'Another article',caption:'Other source'}]}]}};
 const before=JSON.stringify(data);const r=rows(data,[]);
 assert.equal(r[0].own[0].text,'Regiomontanus');assert.equal(r[0].own[0].href,data.presentation.definitions[0].ownUrls[0].url);
 assert.equal(r[0].name,'Wikipedia (de)');assert.equal(r[0].labelHref,origin+'/wiki/Vermerk%3AWikipedia');
 assert.equal(r[1].own[0].text,'Another article');assert.equal(r[1].name,'Other source');assert.equal(JSON.stringify(data),before);
});
test('URL metadata does not create unrelated extra graph rows',()=>{
 const data={version:1,scope:'current',pageId:12,mainSubjectId:sid,fields:[],presentation:{version:1,pageUrlTemplate:origin+'/wiki/SWSGRAPHTITLETOKEN',definitions:[]}};
 assert.equal(rows(data,[{name:'urlEntry',id:'urn:sws:aewi:urlEntry',property:{values:[{value:'entry-id'}]}}]).length,0);
});
test('malformed structured URL presentation fails rather than displaying mixed records',()=>{
 const d=response();d.presentation.definitions[0].ownUrls=[{url:'https://example.org',label:7}];assert.throws(()=>readPresentation(d,12,sid,origin));
});


test('page fields use stored full titles rather than projected RDF URLs',()=>{
 const data={version:1,scope:'current',pageId:12,mainSubjectId:sid,fields:[],presentation:{version:1,pageUrlTemplate:origin+'/wiki/SWSGRAPHTITLETOKEN',definitions:[{name:'Werk',type:'page',predicateLocalName:'Werk',wikiLink:{namespace:'Vermerk',values:false},externalProperty:null,ownPages:['Werk:De mathematica perfectione','Neue Seite']}]}};
 const result=orderedRows(readPresentation(data,12,sid,origin),[native('Werk',origin+'/wiki/Werk:De_mathematica_perfectione')],origin);
 assert.deepEqual(result[0].own.map(v=>v.text),['Werk:De mathematica perfectione','Neue Seite']);
 assert.equal(result[0].own[0].href,origin+'/wiki/Werk%3ADe_mathematica_perfectione');
 assert.equal(result[0].own[1].href,origin+'/wiki/Neue_Seite');
 data.presentation.definitions[0].ownPages=[42];assert.throws(()=>readPresentation(data,12,sid,origin));
});


test('executable schema fields hide even stale native values without executing them',()=>{
 const data={version:1,scope:'current',pageId:12,mainSubjectId:sid,fields:[],presentation:{version:1,pageUrlTemplate:origin+'/wiki/SWSGRAPHTITLETOKEN',definitions:[{name:'Verarbeitung',type:'wikitext',predicateLocalName:'Verarbeitung',execute:true,wikiLink:null,externalProperty:null}]}};
 assert.equal(rows(data,[native('Verarbeitung','{{NichtAusfuehren}}')]).length,0);
 data.presentation.definitions[0].execute=false;
 assert.equal(rows(data,[native('Verarbeitung','{{NichtAusfuehren}}')])[0].own[0].text,'{{NichtAusfuehren}}');
});
test('D5 graph uses schema-qualified predicates and never confuses another schema field',()=>{
 const p={definitions:[{name:'F4',type:'text',predicateLocalName:'F4',predicatePath:'/schema-id/S1/field/F4',externalProperty:null}],fields:new Map()};
 const own=[{id:origin+'/schema-id/S2/field/F4',property:{type:'string',values:[{value:'Other schema'}]}},{id:origin+'/schema-id/S1/field/F4',property:{type:'string',values:[{value:'Correct schema'}]}}];
 const result=orderedRows(p,own,origin);
 assert.equal(result[0].name,'F4');
 assert.match(JSON.stringify(result[0]),/Correct schema/);
 assert.doesNotMatch(JSON.stringify(result[0]),/Other schema/);
});
test('D5 rejects non-local predicate paths in presentation metadata',()=>{
 const d=response();d.presentation.definitions[0].predicatePath='//external.example/field/F4';
 assert.throws(()=>readPresentation(d,12,sid,origin),/predicate path/);
});

 test('D6 translated captions retain technical matching and link destinations',()=>{
 const d=response(); const def=d.presentation.definitions[0];
 def.labels={de:'Klasse',en:'Class'};def.displayLabel='Class';
 const out=rows(d);assert.equal(out[0].name,'Class');assert.equal(out[0].fieldKey,'Klassifizierung');
 assert.equal(out[0].labelHref,origin+'/wiki/Konzept%3AKlassifizierung');
 assert.equal(out[0].values[0].text,'Mensch');
 delete def.displayLabel;assert.equal(rows(d)[0].name,'Klassifizierung');
 });
 test('D6 rejects malformed label metadata',()=>{
 const d=response();d.presentation.definitions[0].labels={de:42};
 assert.throws(()=>readPresentation(d,12,sid,origin));
 });

test('D7 presentation cache isolates data languages and reuses only the same language',async()=>{
 let calls=0;const languages=[];
 const load=createPresentationLoader({origin,fetchImpl:async(url)=>{
  calls++;const u=new URL(url);
  if(u.searchParams.get('action')==='swsbasevalues'){
   const language=u.searchParams.get('language');languages.push(language);
   const d=response();d.presentation.language=language;d.presentation.definitions[0].displayLabel=language==='en'?'Class':'Klasse';
   return {ok:true,json:async()=>({swsbasevalues:d})};
  }
  return {ok:true,json:async()=>({results:{bindings:[{page:{type:'uri',value:origin+'/page/12'}}]}})};
 }});
 const de=await load(iri,'de'),en=await load(iri,'en');
 assert.equal(de.definitions[0].displayLabel,'Klasse');assert.equal(en.definitions[0].displayLabel,'Class');
 await load(iri,'de');assert.deepEqual(languages,['de','en']);assert.equal(calls,4);
});
test('D7 language changes reload field presentation and ignore a late previous-language response',async()=>{
 const React={Component:class{constructor(props){this.props=props;}setState(s){this.state={...this.state,...s};}}};
 const finish={};const requested=[];
 const Row=createSWSFields(React,{origin,load:(id,language)=>{requested.push([id,language]);return new Promise(resolve=>finish[language]=resolve);}});
 const row=new Row({iri,language:'de',fallback:'native'});row.componentDidMount();
 const before=row.props;row.props={...before,language:'en'};row.componentDidUpdate(before,row.state);
 finish.en({language:'en'});await new Promise(r=>setImmediate(r));
 finish.de({language:'de'});await new Promise(r=>setImmediate(r));
 assert.equal(row.state.presentation.language,'en');assert.deepEqual(requested,[[iri,'de'],[iri,'en']]);
 row.componentWillUnmount();
});

test('D8 every key spelling uses its own schema-qualified predicate',()=>{
 for(const key of ['F1','N1','Z1','Klassenzuordnung','Änderung','Ort:Name','Has author']){
  const d=response();d.fields=[];d.presentation.definitions=[{name:key,type:'text',predicateLocalName:key,predicatePath:'/schema-id/S1/field/'+encodeURIComponent(key),externalProperty:null}];
  const own=[{id:origin+'/schema-id/S2/field/'+encodeURIComponent(key),property:{values:[{value:'Other schema'}]}},{id:origin+d.presentation.definitions[0].predicatePath,property:{values:[{value:'Correct'}]}}];
  const parsed=readPresentation(d,12,sid,origin);const out=orderedRows(parsed,own,origin);
  assert.equal(out[0].own[0].text,'Correct');assert.equal(out[0].fieldKey,key);
 }
});
