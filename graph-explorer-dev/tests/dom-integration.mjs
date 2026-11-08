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
const testModule=path.join(os.tmpdir(), 'sws-qids-common-test.mjs');
const modified=original.replace('./'+manifest.generated.helperName,path.join(root,'Graph-Explorer/assets',manifest.generated.helperName));
fs.writeFileSync(testModule,modified+`\nexport {${className} as TestStandardTemplate};\n`);
const dom=new JSDOM('<!doctype html><html><body><div id="root"></div></body></html>',{url:'https://aufeinwort.info/graph-explorer/envendpoint.html',pretendToBeVisual:true});
for(const key of ['window','document','navigator','HTMLElement','HTMLImageElement','SVGElement','Element','Image','Event','location','XMLSerializer','MutationObserver']){
 Object.defineProperty(globalThis,key,{value:dom.window[key],configurable:true});
}
globalThis.requestAnimationFrame=dom.window.requestAnimationFrame.bind(dom.window);
globalThis.cancelAnimationFrame=dom.window.cancelAnimationFrame.bind(dom.window);
globalThis.fetch=async(url)=>({ok:true,json:async()=>String(url).includes('/sparql?')?{results:{bindings:[{page:{type:'uri',value:'https://aufeinwort.info/page/1152'}}]}}:{swsbaseidentity:{version:2,pageId:1152,scope:'current',mainSubjectId:'sF16iHXBMTQdHDY',identities:{sF16iHXBMTQdHDY:{wikidata:{status:'resolved',id:'Q154751',url:'https://www.wikidata.org/wiki/Q154751'},'wikibase-local':{status:'resolved',id:'Q56',url:'https://aufeinwort.info/wiki/Qwasi:Q56'}}}}}});
const module=await import(testModule);
const React=module.x(module.y());const ReactDOM=module.v();
const iri='https://aufeinwort.info/entity/sF16iHXBMTQdHDY';
const props={color:'#73bd97',types:'Person',isExpanded:true,iri,label:'Nikolaus von Kues',props:{},elementId:'test-person',
 propsAsList:[{id:'https://aufeinwort.info/prop/Forschungsnotiz',name:'Forschungsnotiz',property:{type:'string',values:[{value:'Unverändert',language:'de'}]}}]};
const template=new module.TestStandardTemplate(props);
const context={editor:{authoringState:{elements:new Map()},inAuthoringMode:false},view:{model:{getElement:()=>({elementState:undefined,redraw:()=>resized++})}}};
const container=document.getElementById('root');const reactRoot=ReactDOM.createRoot(container);
let bubbled=0, resized=0;
reactRoot.render(React.createElement('div',{onClick:()=>bubbled++,onLoad:()=>resized++},template.renderTemplate(context))); 
const start=Date.now();
while(!container.querySelector('.ge-sws-qids a')&&Date.now()-start<30000)await new Promise(r=>setTimeout(r,50));
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
assert.ok(container.querySelector('.graph-explorer-standard-template__properties-values').textContent.includes('Unverändert'));
fs.writeFileSync(path.join(root,'Pruefergebnisse/Knoten-DOM.html'),container.innerHTML);
console.log(JSON.stringify({actualServedTemplate:true,fixtureBridgeResult:row.textContent,position:'directly below title',fontSize:row.style.fontSize,
 links:links.map(a=>a.href),nodeClickSuppressed:true,originalPropertyPreserved:true},null,2));
// Collapsed nodes retain the same title and identity row.
template.props={...props,isExpanded:false};
context.view={model:{getElement:()=>({elementState:undefined})}};
reactRoot.render(React.createElement('div',{onClick:()=>bubbled++,onLoad:()=>resized++},template.renderTemplate(context))); 
await new Promise(r=>setTimeout(r,50));
assert.ok(container.querySelector('.ge-sws-qids').textContent.includes('Q154751'));
console.log('Auf-/Zuklappen: Q-Zeile bleibt erhalten.');
reactRoot.unmount();dom.window.close();
