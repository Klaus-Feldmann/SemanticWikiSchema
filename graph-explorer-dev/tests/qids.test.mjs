import test from 'node:test';
import assert from 'node:assert/strict';
import {localSubjectId, readPageId, readIdentities, createIdentityLoader, createSWSQIdRow} from '../source/swsQIds.js';
const origin = 'https://aufeinwort.info';
const sid = 'sF16iHXBMTQdHDY';
const iri = `${origin}/entity/${sid}`;
const sources = {
  wikidata: {status: 'resolved', id: 'Q154751', url: 'https://www.wikidata.org/wiki/Q154751'},
  'wikibase-local': {status: 'resolved', id: 'Q56', url: `${origin}/wiki/Qwasi:Q56`},
};
const identity = (overrides = {}) => ({version: 2, pageId: 1152, scope: 'current', mainSubjectId: sid, identities: {[sid]: sources}, ...overrides});
const bindings = [{page: {type: 'uri', value: `${origin}/page/1152`}}];
test('accepts a local subject IRI', () => assert.equal(localSubjectId(iri, origin), sid));
for (const invalid of ['https://example.org/entity/sTest', `${iri}?x=1`, `${iri}#a`, `${origin}/entity/sBad>`, 'javascript:alert(1)', `${origin}/page/1152`]) {
  test(`rejects foreign or malformed subject ${invalid}`, () => assert.equal(localSubjectId(invalid, origin), null));
}
test('maps the stored main-subject page', () => assert.equal(readPageId(bindings, origin), 1152));
test('no mapping yields no Q assignment', () => assert.equal(readPageId([], origin), null));
test('does not guess between two hosting pages', () => assert.throws(() => readPageId([...bindings, {page:{type:'uri', value:`${origin}/page/33059`}}], origin)));
test('rejects foreign page IDs', () => assert.throws(() => readPageId([{page:{type:'uri', value:'https://evil.example/page/1152'}}], origin)));
test('keeps sources and their distinct links', () => assert.deepEqual(readIdentities(identity(), 1152, sid, origin), sources));
test('a changed main subject is not assigned old page IDs', () => assert.equal(readIdentities(identity({mainSubjectId:'sOther'}), 1152, sid, origin), null));
test('rejects an incompatible bridge version', () => assert.throws(() => readIdentities(identity({version:1}), 1152, sid, origin)));
for (const source of ['wikidata', 'wikibase-local']) {
  test(`rejects unsafe ${source} links`, () => {
    const data = identity({identities:{[sid]:{...sources, [source]:{...sources[source], url:'javascript:alert(1)'}}}});
    assert.throws(() => readIdentities(data, 1152, sid, origin));
  });
}
test('rejects a Wikidata ID linked to another item', () => {
  const data=identity({identities:{[sid]:{...sources,wikidata:{...sources.wikidata,url:'https://www.wikidata.org/wiki/Q42'}}}});
  assert.throws(() => readIdentities(data,1152,sid,origin));
});
test('supports one missing source without losing the other', () => {
  const data=identity({identities:{[sid]:{...sources,wikidata:{status:'missing',id:null,url:null}}}});
  assert.equal(readIdentities(data,1152,sid,origin)['wikibase-local'].id,'Q56');
});
test('deduplicates concurrent requests and uses only GET reads with same-origin cookies', async () => {
  const calls=[];
  const loader=createIdentityLoader({origin,fetchImpl:async (url,opts) => {
    calls.push([url,opts]); return {ok:true,json:async()=>url.includes('/sparql?')?{results:{bindings}}:{swsbaseidentity:identity()}};
  }});
  const a=loader(iri), b=loader(iri); assert.equal(a,b); assert.deepEqual(await a,sources);
  assert.equal(calls.length,2);assert.equal(calls[0][1].credentials,'same-origin');assert.equal(calls[1][1].method,undefined);
  assert.equal(new URL(calls[1][0]).searchParams.get('pageid'),'1152');
});
test('does not query an API for a non-main node', async () => {
  let calls=0;
  const loader=createIdentityLoader({origin,fetchImpl:async()=>{calls++;return {ok:true,json:async()=>({results:{bindings:[]}})};}});
  assert.equal(await loader(iri),null);assert.equal(calls,1);
});
test('does not cache request failures as missing identities', async () => {
  let calls=0;
  const loader=createIdentityLoader({origin,fetchImpl:async()=>{calls++;return {ok:false};}});
  await assert.rejects(loader(iri));await assert.rejects(loader(iri));assert.equal(calls,2);
});
test('expires successful cache entries', async () => {
  let clock=0,calls=0;
  const loader=createIdentityLoader({origin,now:()=>clock,fetchImpl:async(url)=>{calls++;return {ok:true,json:async()=>url.includes('/sparql?')?{results:{bindings}}:{swsbaseidentity:identity()}};}});
  await loader(iri);await loader(iri);assert.equal(calls,2);clock=60001;await loader(iri);assert.equal(calls,4);
});
const React={Component:class{constructor(props){this.props=props;}setState(values){this.state={...this.state,...values};}},createElement:(type,props,...children)=>({type,props,children})};
test('renders a small row with source labels and separate safe anchors', () => {
  const Row=createSWSQIdRow(React,{origin,load:async()=>sources});const row=new Row({iri});row.state.identities=sources;
  const view=row.render();assert.equal(view.props.style.fontSize,'10px');
  const nodes=view.children.flat().filter(x=>x&&typeof x==='object');
  assert.equal(nodes[0].children[0],'Wikidata: ');assert.equal(nodes[1].children[0],'Wikibase: ');
  const anchor=nodes[0].children[1];assert.equal(anchor.props.target,'_blank');assert.equal(anchor.props.rel,'noopener noreferrer');
  let stopped=false;anchor.props.onClick({stopPropagation(){stopped=true;}});assert.ok(stopped);
});
test('a response arriving after unmount does not update a removed node', async () => {
  let finish;const Row=createSWSQIdRow(React,{origin,load:()=>new Promise(resolve=>{finish=resolve;})});
  const row=new Row({iri});row.componentDidMount();row.componentWillUnmount();finish(sources);await new Promise(resolve=>setImmediate(resolve));
  assert.equal(row.state.identities,null);
});

for (const source of SOURCES_FOR_TEST()) {
  test(`renders only resolved label and ID: ${source}`, () => {
    const data={wikidata:{status:'missing',id:null,url:null},'wikibase-local':{status:'missing',id:null,url:null},[source]:sources[source]};
    const Row=createSWSQIdRow(React,{origin,load:async()=>data});const row=new Row({iri});row.state.identities=data;
    const children=row.render().children.flat().filter(x=>x&&typeof x==='object');
    assert.equal(children.length,1);assert.equal(children[0].children[0],source==='wikidata'?'Wikidata: ':'Wikibase: ');
    assert.ok(!row.render().children.includes(' · '));
  });
}
function SOURCES_FOR_TEST(){return ['wikidata','wikibase-local'];}
test('no row while loading, when both sources are missing, or on failure',()=>{
 const Row=createSWSQIdRow(React,{origin,load:async()=>null});const row=new Row({iri});
 assert.equal(row.render(),null);
 row.state.identities={wikidata:{status:'missing'},'wikibase-local':{status:'unavailable'}};
 assert.equal(row.render(),null);row.state={identities:null,failed:true};assert.equal(row.render(),null);
});
test('configuration supports a wiki and RDF base under a subdirectory',async()=>{
 globalThis.SWSGraphConfig={baseIri:origin+'/research/',apiUrl:'/research/api.php',sparqlUrl:'/research/sparql'};
 try{
  const customIri=origin+'/research/entity/'+sid;
  assert.equal(localSubjectId(customIri,origin),sid);
  assert.equal(readPageId([{page:{type:'uri',value:origin+'/research/page/1152'}}],origin),1152);
  const urls=[];
  const loader=createIdentityLoader({origin,fetchImpl:async(url)=>{urls.push(url);return {ok:true,json:async()=>url.includes('/sparql?')?{results:{bindings:[{page:{type:'uri',value:origin+'/research/page/1152'}}]}}:{swsbaseidentity:identity()}};}});
  assert.deepEqual(await loader(customIri),sources);
  assert.equal(new URL(urls[1]).pathname,'/research/api.php');
  assert.ok(new URL(urls[0]).searchParams.get('query').includes(origin+'/research/ontology/mainSubject'));
 }finally{delete globalThis.SWSGraphConfig;}
});
