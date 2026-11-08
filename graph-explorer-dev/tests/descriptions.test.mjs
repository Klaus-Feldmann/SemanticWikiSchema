import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readDescriptionGroups,createSWSDescriptionRow} from '../source/swsQIds.js';
const source={source:'wikidata',id:'Q5',url:'https://www.wikidata.org/wiki/Q5'};
const group={text:'Mensch',language:'de',sources:[source]};
const identities={wikidata:{status:'resolved',id:'Q5',url:source.url},descriptionGroups:[group]};
test('valid description sources remain attached; malicious targets rejected',()=>{
 assert.deepEqual(readDescriptionGroups([group],identities),[group]);
 assert.deepEqual(readDescriptionGroups([{...group,sources:[{...source,url:'javascript:bad()'}]}],identities),[]);
 assert.deepEqual(readDescriptionGroups(null,identities),[]);
});
test('description renders plain text with source labels and does not replace IDs',()=>{
 class Component{constructor(props){this.props=props;this.state={};}setState(v){this.state={...this.state,...v};}}
 const React={Component,createElement:(type,props,...children)=>({type,props,children})};
 const Row=createSWSDescriptionRow(React,{origin:'https://aufeinwort.info',load:async()=>identities});
 const row=new Row({iri:'https://aufeinwort.info/entity/sF16iHXBMTQdHDY'});assert.equal(row.render(),null);
 row.state.identities=identities;const rendered=row.render();assert.equal(rendered.props.className,'ge-sws-descriptions');
 assert.match(JSON.stringify(rendered),/Mensch/);assert.match(JSON.stringify(rendered),/Wikidata/);
 row.state.identities={...identities,descriptionGroups:[]};assert.equal(row.render(),null);
});

test('page descriptions are limited to the same-origin hosting page',()=>{
 const page={id:42,url:'https://aufeinwort.info/wiki/Person',origin:'https://aufeinwort.info'};
 const own={text:'Eigene Seite',language:'de',sources:[{source:'page',id:'42',url:page.url}]};
 assert.deepEqual(readDescriptionGroups([own],identities,page),[own]);
 assert.deepEqual(readDescriptionGroups([own],identities,{...page,id:43}),[]);
});
test('identity envelope carries only the selected display description into the graph',async()=>{
 const {readIdentities}=await import('../source/swsQIds.js');
 const sid='sF16iHXBMTQdHDY';
 const local={source:'wikibase-local',id:'Q56',url:'https://aufeinwort.info/wiki/Qwasi:Q56'};
 const selected={text:'Eigene lokale Beschreibung',language:'de',sources:[local]};
 const data={version:2,pageId:42,scope:'current',mainSubjectId:sid,identities:{[sid]:{
  wikidata:{status:'resolved',id:'Q5',url:'https://www.wikidata.org/wiki/Q5'},'wikibase-local':{status:'resolved',id:local.id,url:local.url}
 }},descriptions:[group,selected],displayDescription:selected};
 assert.deepEqual(readIdentities(data,42,sid,'https://aufeinwort.info').descriptionGroups,[selected]);
 assert.equal(data.descriptions.length,2,'full source descriptions not mutated');
});
