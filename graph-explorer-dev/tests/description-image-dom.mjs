import os from 'node:os';
import ts from 'typescript';
import {JSDOM} from 'jsdom';
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
const root=path.resolve(path.dirname(new URL(import.meta.url).pathname),'..');
fs.mkdirSync(path.join(root,'Pruefergebnisse'),{recursive:true});
const manifest=JSON.parse(fs.readFileSync(path.join(root,'BUILD-MANIFEST.json'),'utf8'));
const commonPath=path.join(root,'Graph-Explorer/assets',manifest.generated.commonName);
const original=fs.readFileSync(commonPath,'utf8');
const ast=ts.createSourceFile('common.js',original,ts.ScriptTarget.Latest,true,ts.ScriptKind.JS);
let className;
function visit(n){
 if(ts.isMethodDeclaration(n)&&n.name.getText(ast)==='renderTemplate'&&n.getText(ast).includes('__body-content')){
  assert.equal(className,undefined);className=n.parent.parent.name.getText(ast);
 }
 ts.forEachChild(n,visit);
}
visit(ast);assert.ok(className);
const testModule=path.join(os.tmpdir(), 'sws-description-image-common-'+(process.argv[2]||'own')+'-test.mjs');
const modified=original.replace('./'+manifest.generated.helperName,path.join(root,'Graph-Explorer/assets',manifest.generated.helperName));
fs.writeFileSync(testModule,modified+`\nexport {${className} as TestStandardTemplate};\n`);
const dom=new JSDOM('<!doctype html><html><body><div id="root"></div></body></html>',{url:'https://aufeinwort.info/graph-explorer/envendpoint.html',pretendToBeVisual:true});
for(const key of ['window','document','navigator','HTMLElement','HTMLImageElement','SVGElement','Element','Image','Event','location','XMLSerializer','MutationObserver']){
 Object.defineProperty(globalThis,key,{value:dom.window[key],configurable:true});
}
globalThis.requestAnimationFrame=dom.window.requestAnimationFrame.bind(dom.window);
globalThis.cancelAnimationFrame=dom.window.cancelAnimationFrame.bind(dom.window);
const identityFetch=async(url)=>({ok:true,json:async()=>String(url).includes('/sparql?')?{results:{bindings:[{page:{type:'uri',value:'https://aufeinwort.info/page/1152'}}]}}:{swsbaseidentity:{version:2,pageId:1152,scope:'current',mainSubjectId:'sF16iHXBMTQdHDY',identities:{sF16iHXBMTQdHDY:{wikidata:{status:'resolved',id:'Q154751',url:'https://www.wikidata.org/wiki/Q154751'},'wikibase-local':{status:'resolved',id:'Q56',url:'https://aufeinwort.info/wiki/Qwasi:Q56'}}}}}});
globalThis.fetch=async(url)=> {
 if(new URL(url).searchParams.get('action')!=='swsbasevalues')return identityFetch(url);
 return {ok:true,json:async()=>({swsbasevalues:{version:1,scope:'current',pageId:1152,mainSubjectId:'sF16iHXBMTQdHDY',presentation:{version:1,pageUrlTemplate:'https://aufeinwort.info/wiki/SWSGRAPHTITLETOKEN',definitions:[
  {name:'Klassifizierung',type:'text',predicateLocalName:'Klassifizierung',wikiLink:{labelTarget:'Konzept:Klassifizierung',externalTarget:'local'},externalProperty:{source:'wikidata',id:'P31'}},
  {name:'Forschungsnotiz',type:'text',predicateLocalName:'Forschungsnotiz',wikiLink:{label:false,values:false},externalProperty:null}
 ]},fields:[{name:'Klassifizierung',source:'wikidata',propertyId:'P31',status:'resolved',values:[{kind:'entity',id:'Q5',label:'Mensch',url:'https://www.wikidata.org/wiki/Q5'}]}]}})};
};
const mode=process.argv[2]||'own';
const ownMode=mode==='own';
const activeRef=ownMode?'local:Own.jpg':'commons:Remote.jpg';
const activeName=ownMode?'Own.jpg':'Remote.jpg';
const activeUrl=ownMode?'https://aufeinwort.info/images/own.jpg':'https://thumb.wikimedia.org/remote.jpg';
const activeDescription=ownMode?'https://aufeinwort.info/wiki/Datei:Own.jpg':'https://commons.wikimedia.org/wiki/File:Remote.jpg';
const calls=[];const fieldFetch=globalThis.fetch;
globalThis.fetch=async(url)=>{
 calls.push(String(url));const params=new URL(url).searchParams;
 if(params.get('action')==='swsimages')return {ok:true,json:async()=>({swsimages:{version:1,repository:ownMode?'local':'commons',mode:'resolve',query:activeName,items:[{reference:activeRef,repository:ownMode?'local':'commons',filename:activeName,descriptionUrl:activeDescription,thumbnailUrl:activeUrl,width:122,height:150,mime:'image/jpeg'}]}})};
 const response=await fieldFetch(url);
 if(params.get('action')!=='swsbasevalues')return response;
 const data=await response.json();data.swsbasevalues.presentation.definitions.unshift({name:'Bild',type:'image',predicateLocalName:'Bild',wikiLink:null,externalProperty:{source:'wikidata',id:'P18'},ownImages:ownMode?['local:Own.jpg']:[]});
 data.swsbasevalues.fields.unshift({name:'Bild',source:'wikidata',propertyId:'P18',status:'resolved',values:[{kind:'image',reference:'commons:Remote.jpg',filename:'Remote.jpg',repository:'commons'}]});
 return {ok:true,json:async()=>data};
};
const imageFetchBase=globalThis.fetch;
globalThis.fetch=async(url)=>{
 const response=await imageFetchBase(url);const body=await response.json();
 if(new URL(url).searchParams.get('action')==='swsbaseidentity')body.swsbaseidentity.descriptions=[{text:'Kardinal und Universalgelehrter',language:'de',sources:[{source:'wikidata',id:'Q154751',url:'https://www.wikidata.org/wiki/Q154751'}]}];
 return {ok:true,json:async()=>body};
};
const module=await import(testModule);
const React=module.x(module.y());const ReactDOM=module.v();
const iri='https://aufeinwort.info/entity/sF16iHXBMTQdHDY';
const props={color:'#73bd97',types:'Person',isExpanded:true,iri,label:'Nikolaus von Kues',props:{},elementId:'test-person',
 propsAsList:[{id:'https://aufeinwort.info/prop/Bild',name:'Bild',property:{type:'string',values:[{value:'commons:Stale.jpg',language:'de'}]}},{id:'https://aufeinwort.info/prop/Forschungsnotiz',name:'Forschungsnotiz',property:{type:'string',values:[{value:'Unverändert',language:'de'}]}}]};
const template=new module.TestStandardTemplate(props);
const context={editor:{authoringState:{elements:new Map()},inAuthoringMode:false},view:{model:{getElement:()=>({elementState:undefined,redraw:()=>resized++})}}};
const container=document.getElementById('root');const reactRoot=ReactDOM.createRoot(container);
let bubbled=0, resized=0;
reactRoot.render(React.createElement('div',{onClick:()=>bubbled++,onLoad:()=>resized++},template.renderTemplate(context))); 
const start=Date.now();
while((!container.querySelector('.ge-sws-qids a') || !container.textContent.includes('Mensch') || !container.querySelector('.graph-explorer-standard-template__thumbnail-image'))&&Date.now()-start<30000)await new Promise(r=>setTimeout(r,50));
const row=container.querySelector('.ge-sws-qids');assert.ok(row);
assert.ok(row.textContent.includes('Wikidata: Q154751'),row.textContent);
assert.ok(row.textContent.includes('Wikibase: Q56'),row.textContent);
assert.equal(row.previousElementSibling.className,'graph-explorer-standard-template__label');
assert.equal(row.style.fontSize,'10px');
assert.ok(resized>=1,'Native graph redraw requested after async data');
const links=[...row.querySelectorAll('a')];
assert.deepEqual(links.map(a=>a.href),['https://www.wikidata.org/wiki/Q154751','https://aufeinwort.info/wiki/Qwasi:Q56']);

for(const a of links){const e=new dom.window.MouseEvent('click',{bubbles:true,cancelable:true});a.dispatchEvent(e);}
assert.equal(bubbled,0,'Q links do not trigger the graph node click handler');
const propertyRows=[...container.querySelectorAll('.graph-explorer-standard-template__properties-row')];
assert.deepEqual(propertyRows.map(r=>r.querySelector('.graph-explorer-standard-template__properties-key').textContent),['Bild','Klassifizierung','Forschungsnotiz']);
assert.equal(propertyRows[1].querySelector('.graph-explorer-standard-template__properties-key a').href,'https://aufeinwort.info/wiki/Konzept%3AKlassifizierung');
assert.equal(propertyRows[1].querySelector('.graph-explorer-standard-template__properties-value a').href,'https://aufeinwort.info/wiki/Mensch');
assert.ok(propertyRows[2].textContent.includes('Unverändert'));
assert.equal(propertyRows[2].querySelectorAll('a').length,0,'Disabled links preserved');
assert.equal(container.querySelectorAll('a[href*="Property%3AP31"],a[href*="Property:P31"]').length,0,'No redundant P source link');
for(const a of propertyRows[1].querySelectorAll('a'))a.dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true,cancelable:true}));
assert.equal(bubbled,0,'Field links do not trigger graph selection');
const description=container.querySelector('.ge-sws-descriptions');assert.ok(description);
assert.equal(description.previousElementSibling.className,'graph-explorer-standard-template__body-horizontal');
assert.ok(description.previousElementSibling.querySelector('img'));
assert.ok(description.textContent.includes('Kardinal und Universalgelehrter'));
const thumbnail=container.querySelector('.graph-explorer-standard-template__thumbnail');
const imageNode=thumbnail.querySelector('img');assert.ok(imageNode);assert.equal(imageNode.getAttribute('src'),activeUrl);
assert.equal(imageNode.getAttribute('width'),'41');assert.equal(imageNode.getAttribute('height'),'50');
assert.equal(thumbnail.querySelector('a').href,activeDescription);
assert.equal(thumbnail.nextElementSibling.className,'graph-explorer-standard-template__body-content');
assert.equal(thumbnail.querySelectorAll('figcaption').length,0);assert.equal(thumbnail.textContent,'');
assert.equal(container.querySelectorAll('img').length,1,'One image in the header, no duplicate in the property list');
assert.ok(container.querySelector('.graph-explorer-standard-template__properties-row').textContent.includes(activeName));
assert.ok(!container.textContent.includes('Stale.jpg'));
if(ownMode)assert.ok(!container.textContent.includes('Remote.jpg'));
assert.equal(calls.filter(url=>new URL(url).searchParams.get('action')==='swsimages').length,1,'Title and file link share one metadata read');
thumbnail.querySelector('a').dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true,cancelable:true}));assert.equal(bubbled,0);
imageNode.dispatchEvent(new dom.window.Event('load'));assert.ok(resized>=2);
fs.writeFileSync(path.join(root,'Pruefergebnisse/Bild-Knoten-'+mode+'-DOM.html'),container.innerHTML);
console.log(JSON.stringify({mode,activeRef,headerImageOnly:true,caption:false,width:41,height:50,currentSnapshotOverridesProjection:true,fileDescriptionLink:activeDescription},null,2));
console.log(JSON.stringify({actualServedTemplate:true,fixtureBridgeResult:row.textContent,position:'directly below title',fontSize:row.style.fontSize,
 links:links.map(a=>a.href),nodeClickSuppressed:true,originalPropertyPreserved:true},null,2));
// Collapsed nodes retain the same title and identity row.
template.props={...props,isExpanded:false};
context.view={model:{getElement:()=>({elementState:undefined})}};
reactRoot.render(React.createElement('div',{onClick:()=>bubbled++,onLoad:()=>resized++},template.renderTemplate(context))); 
await new Promise(r=>setTimeout(r,50));
assert.ok(container.querySelector('.ge-sws-qids').textContent.includes('Q154751'));
assert.equal(container.querySelector('.graph-explorer-standard-template__thumbnail-image').getAttribute('src'),activeUrl);console.log('Auf-/Zuklappen: Bild und Q-Zeile bleiben erhalten.');
reactRoot.unmount();dom.window.close();
