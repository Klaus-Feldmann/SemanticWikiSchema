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
const testModule=path.join(os.tmpdir(), 'sws-wikitext-common-test.mjs');
const modified=original.replace('./'+manifest.generated.helperName,path.join(root,'Graph-Explorer/assets',manifest.generated.helperName));
fs.writeFileSync(testModule,modified+`\nexport {${className} as TestStandardTemplate};\n`);
const dom=new JSDOM('<!doctype html><html><body><div id="root"></div></body></html>',{url:'https://aufeinwort.info/graph-explorer/envendpoint.html',pretendToBeVisual:true});
for(const key of ['window','document','navigator','HTMLElement','HTMLImageElement','SVGElement','Element','Image','Event','location','XMLSerializer','MutationObserver']){
 Object.defineProperty(globalThis,key,{value:dom.window[key],configurable:true});
}
globalThis.requestAnimationFrame=dom.window.requestAnimationFrame.bind(dom.window);
globalThis.cancelAnimationFrame=dom.window.cancelAnimationFrame.bind(dom.window);

globalThis.fetch=async(url)=>({ok:true,json:async()=>{
 if(String(url).includes('/sparql?'))return {results:{bindings:[{page:{type:'uri',value:'https://aufeinwort.info/page/1152'}}]}};
 if(String(url).includes('swsbasevalues'))return {swsbasevalues:{version:1,pageId:1152,scope:'current',mainSubjectId:'sF16iHXBMTQdHDY',fields:[],presentation:{version:1,language:'de',pageUrlTemplate:'https://aufeinwort.info/wiki/SWSGRAPHTITLETOKEN',definitions:[{name:'Bezug',type:'page',predicateLocalName:'Bezug',wikiLink:{labelTarget:'Vermerk:Bezug'},externalProperty:null,ownPages:['Werk:Abhandlung','Neue Seite']},{name:'Verarbeitung',type:'wikitext',predicateLocalName:'Verarbeitung',execute:true,wikiLink:null,externalProperty:null}]}}};
 return {swsbaseidentity:{version:2,pageId:1152,scope:'current',mainSubjectId:'sF16iHXBMTQdHDY',identities:{}}};
}});
const module=await import(testModule);
const React=module.x(module.y());const ReactDOM=module.v();
const iri='https://aufeinwort.info/entity/sF16iHXBMTQdHDY';
const props={color:'#73bd97',types:'Person',isExpanded:true,iri,label:'Nikolaus von Kues',props:{},elementId:'test-person',
 propsAsList:[{id:'https://aufeinwort.info/prop/Verarbeitung',name:'Verarbeitung',property:{type:'string',values:[{value:'{{NichtAusfuehren}}',language:'de'}]}},{id:'https://aufeinwort.info/prop/Forschungsnotiz',name:'Forschungsnotiz',property:{type:'string',values:[{value:'Unverändert',language:'de'}]}}]};
const template=new module.TestStandardTemplate(props);
const context={editor:{authoringState:{elements:new Map()},inAuthoringMode:false},view:{model:{getElement:()=>({elementState:undefined,redraw:()=>resized++})}}};
const container=document.getElementById('root');const reactRoot=ReactDOM.createRoot(container);
let bubbled=0, resized=0;
reactRoot.render(React.createElement('div',{onClick:()=>bubbled++,onLoad:()=>resized++},template.renderTemplate(context))); 
const start=Date.now();
while(!container.textContent.includes('Werk:Abhandlung')&&Date.now()-start<15000)await new Promise(r=>setTimeout(r,25));
const values=container.querySelector('.graph-explorer-standard-template__properties-values');
assert.ok(values,'Actual built graph renders page field');
const row=values.parentElement;
const links=[...row.querySelectorAll('a')];
assert.equal(row.querySelector('.graph-explorer-standard-template__properties-key').textContent,'Bezug');
assert.ok(values.textContent.includes('Werk:Abhandlung'));
assert.ok(values.textContent.includes('Neue Seite'));
assert.deepEqual(links.map(a=>a.textContent),['Bezug','Werk:Abhandlung','Neue Seite']);
assert.equal(links[0].href,'https://aufeinwort.info/wiki/Vermerk%3ABezug');
assert.equal(links[1].href,'https://aufeinwort.info/wiki/Werk%3AAbhandlung');
assert.equal(links[2].href,'https://aufeinwort.info/wiki/Neue_Seite');
for(const a of links)a.dispatchEvent(new dom.window.MouseEvent('click',{bubbles:true,cancelable:true}));
assert.equal(bubbled,0);
assert.ok(!container.textContent.includes('NichtAusfuehren'));
assert.ok(!container.textContent.includes('Verarbeitung'));
fs.mkdirSync(path.join(root,'Pruefergebnisse'),{recursive:true});
fs.writeFileSync(path.join(root,'Pruefergebnisse/Wikitext-Knoten-DOM.html'),container.innerHTML);
console.log('Actual built graph hides executable schema fields and retains ordinary page links; passed.');
reactRoot.unmount();dom.window.close();
