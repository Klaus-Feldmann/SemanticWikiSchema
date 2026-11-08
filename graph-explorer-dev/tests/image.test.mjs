import test from 'node:test';
import assert from 'node:assert/strict';
import {readImageResponse,createImageLoader,selectedImageReference,createThumbnail} from '../source/swsGraphImage.js';
import {orderedRows,readPresentation} from '../source/swsFields.js';
const origin='https://wiki.example';
const info={reference:'local:Own.jpg',repository:'local',filename:'Own.jpg',descriptionUrl:origin+'/wiki/Datei:Own.jpg',thumbnailUrl:origin+'/images/thumb.jpg',width:122,height:150,mime:'image/jpeg'};
const data=(overrides={})=>({version:1,repository:'local',mode:'resolve',query:'Own.jpg',items:[info],...overrides});
function presentation(own=['local:Own.jpg']){return {version:1,pageUrlTemplate:origin+'/wiki/SWSGRAPHTITLETOKEN',definitions:[{name:'Bild',type:'image',predicateLocalName:'Bild',wikiLink:null,externalProperty:{source:'wikidata',id:'P18'},ownImages:own}],fields:new Map([['Bild',{name:'Bild',source:'wikidata',propertyId:'P18',status:'resolved',values:[{kind:'image',reference:'commons:Remote.jpg',filename:'Remote.jpg',repository:'commons'}]}]])};}
const native=[{id:origin+'/prop/Bild',name:'Bild',property:{values:[{value:'commons:Stale.jpg'}]}}];
test('current own image wins over remote claim and stale native projection',()=>{
 const p=presentation();assert.equal(selectedImageReference(p),'local:Own.jpg');const rows=orderedRows(p,native,origin);assert.equal(rows.length,1);assert.equal(rows[0].own[0].reference,'local:Own.jpg');assert.equal(rows[0].values.length,0);
});
test('authoritatively empty own snapshot clears stale projection and restores remote image',()=>{
 const p=presentation([]);assert.equal(selectedImageReference(p),'commons:Remote.jpg');const r=orderedRows(p,native,origin)[0];assert.equal(r.own.length,0);assert.equal(r.values[0].reference,'commons:Remote.jpg');
});
test('missing metadata never guesses a remote title picture over an unknown own value',()=>{const p=presentation();delete p.definitions[0].ownImages;assert.equal(selectedImageReference(p),null);});
test('invalid explicit own value does not silently substitute a remote image',()=>{assert.equal(selectedImageReference(presentation(['invalid'])),null);});
test('schema and multiple image order selects the first explicit reference',()=>{const p=presentation(['local:First.jpg','local:Second.jpg']);assert.equal(selectedImageReference(p),'local:First.jpg');});
test('safe source-qualified image response preserves actual thumbnail and description links',()=>{assert.deepEqual(readImageResponse(data(),'local:Own.jpg',origin),info);assert.equal(readImageResponse(data({items:[]}), 'local:Own.jpg',origin),null);});
test('rejects unsafe URLs, oversized previews and incorrect repository identities',()=>{
 for(const patch of [{thumbnailUrl:'javascript:alert(1)'},{width:151},{height:151},{reference:'commons:Own.jpg'},{mime:'application/pdf'}])assert.throws(()=>readImageResponse(data({items:[{...info,...patch}]}),'local:Own.jpg',origin));
 const remote={...info,reference:'commons:Own.jpg',repository:'commons',descriptionUrl:'https://commons.wikimedia.org/wiki/File:Own.jpg',thumbnailUrl:'https://thumb.wikimedia.org/own.jpg'};
 assert.equal(readImageResponse(data({repository:'commons',items:[remote]}),'commons:Own.jpg',origin).thumbnailUrl,remote.thumbnailUrl);
 assert.throws(()=>readImageResponse(data({repository:'commons',items:[{...remote,thumbnailUrl:'https://evil.example/X'}]}),'commons:Own.jpg',origin));
});
test('thumbnail API requests are same-origin, source-qualified, batched and retried after errors',async()=>{
 const calls=[];const load=createImageLoader({origin,fetchImpl:async(url,opts)=>{calls.push([url,opts]);return {ok:true,json:async()=>({swsimages:data()})};}});
 const [a,b]=await Promise.all([load('local:Own.jpg'),load('local:Own.jpg')]);assert.equal(a,b);assert.equal(calls.length,1);assert.equal(new URL(calls[0][0]).searchParams.get('mode'),'resolve');assert.equal(calls[0][1].credentials,'same-origin');
 let count=0;const failing=createImageLoader({origin,fetchImpl:async()=>{count++;throw new Error('offline');}});await assert.rejects(failing('local:Own.jpg'));await assert.rejects(failing('local:Own.jpg'));assert.equal(count,2);assert.equal(await load('javascript:alert(1)'),null);
});
const React={Component:class{constructor(props){this.props=props;}setState(state){this.state={...this.state,...state};}},createElement:(tag,props,...children)=>({tag,props,children})};
test('header uses exactly the native left thumbnail class, at most 50px and no caption',async()=>{
 const Thumbnail=createThumbnail(React,{origin,loadPresentation:async()=>presentation(),loadImage:async()=>info});const t=new Thumbnail({iri:origin+'/entity/sTest',fallback:'P'});t.componentDidMount();await new Promise(r=>setImmediate(r));
 const view=t.render(),link=view.children[0],image=link.children[0];assert.equal(view.props.className,'graph-explorer-standard-template__thumbnail');assert.equal(link.props.href,info.descriptionUrl);assert.equal(image.tag,'img');assert.equal(image.props.width,41);assert.equal(image.props.height,50);assert.equal(link.children.length,1);assert.equal(image.props.alt,'Own.jpg');assert.equal(JSON.stringify(view).includes('figcaption'),false);
 let stopped=false;link.props.onClick({stopPropagation(){stopped=true;}});assert.equal(stopped,true);image.props.onError();assert.equal(t.render(),'P');
});
test('small images are not enlarged; loading, no image, unavailable file and stale responses keep fallback',async()=>{
 const Thumbnail=createThumbnail(React,{origin,loadPresentation:async()=>presentation(),loadImage:async()=>({...info,width:20,height:30})});const t=new Thumbnail({iri:'x',fallback:'P'});assert.equal(t.render(),'P');t.componentDidMount();await new Promise(r=>setImmediate(r));assert.equal(t.render().children[0].children[0].props.width,20);
 let finish;const Slow=createThumbnail(React,{origin,loadPresentation:()=>new Promise(resolve=>finish=resolve),loadImage:async()=>info});const s=new Slow({iri:'x',fallback:'P'});s.componentDidMount();s.componentWillUnmount();finish(presentation());await new Promise(r=>setImmediate(r));assert.equal(s.render(),'P');
 const Missing=createThumbnail(React,{origin,loadPresentation:async()=>presentation(),loadImage:async()=>null});const m=new Missing({iri:'x',fallback:'P'});m.componentDidMount();await new Promise(r=>setImmediate(r));assert.equal(m.render(),'P');
});
