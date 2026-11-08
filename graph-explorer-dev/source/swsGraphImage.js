/** Uses the existing MediaWiki file resolver; no source HTML and no arbitrary remote API. */
export function parseImageReference(value) {
  if (typeof value !== 'string') return null;
  const match=/^(local|commons):([^\r\n]+)$/.exec(value);
  if(!match || !match[2].trim() || match[2]!==match[2].trim() || match[2].startsWith(':') || /[\x00-\x1f\x7f[\]{}<>|#]/.test(match[2]))return null;
  return {repository:match[1],filename:match[2]};
}
function safeUrl(value,repository,origin){
 if(typeof value!=='string')return false;
 try{const u=new URL(value,origin);return ['https:','http:'].includes(u.protocol)&&!u.username&&!u.password&&
  (repository!=='commons'||['commons.wikimedia.org','upload.wikimedia.org','thumb.wikimedia.org'].includes(u.hostname));}catch{return false;}
}
export function readImageResponse(data,reference,origin){
 const parsed=parseImageReference(reference);
 if(!parsed||data?.version!==1||data.repository!==parsed.repository||data.mode!=='resolve'||data.query!==parsed.filename||!Array.isArray(data.items)||data.items.length>1)throw new Error('Invalid image response');
 if(!data.items.length)return null;
 const info=data.items[0];
 if(info.repository!==parsed.repository||typeof info.filename!=='string'||info.reference!==info.repository+':'+info.filename||!parseImageReference(info.reference)||!safeUrl(info.descriptionUrl,info.repository,origin)||typeof info.mime!=='string'||!info.mime.startsWith('image/'))throw new Error('Invalid image identity');
 if(info.thumbnailUrl!==null&&(!safeUrl(info.thumbnailUrl,info.repository,origin)||!Number.isInteger(info.width)||!Number.isInteger(info.height)||info.width<1||info.height<1||info.width>150||info.height>150))throw new Error('Invalid thumbnail');
 return info;
}
export function createImageLoader({origin,fetchImpl=globalThis.fetch,now=Date.now}){
 const cache=new Map();
 async function request(reference){
  const parsed=parseImageReference(reference);if(!parsed)return null;
  const api=new URL(globalThis.SWSGraphConfig?.apiUrl||'/w/api.php',origin);
  if(api.origin!==origin)throw new Error('Image API must be same-origin');
  api.search=new URLSearchParams({action:'swsimages',repository:parsed.repository,query:parsed.filename,mode:'resolve',format:'json',formatversion:'2'}).toString();
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
  try{const response=await fetchImpl(api.href,{credentials:'same-origin',cache:'no-store',signal:controller.signal});const body=await response.json();
   if(!response.ok||body.error)throw new Error('Image unavailable');return readImageResponse(body.swsimages,reference,origin);
  }finally{clearTimeout(timer);}
 }
 return reference=>{
  if(!parseImageReference(reference))return Promise.resolve(null);
  const old=cache.get(reference);if(old&&old.expires>now())return old.promise;
  const promise=request(reference).catch(error=>{cache.delete(reference);throw error;});cache.set(reference,{expires:now()+60000,promise});return promise;
 };
}
const loaders=new Map();
export function imageLoader(origin){if(!loaders.has(origin))loaders.set(origin,createImageLoader({origin}));return loaders.get(origin);}
/** First populated image field in schema order; a current own value suppresses the remote claim. */
export function selectedImageReference(presentation){
 if(!presentation)return null;
 for(const def of presentation.definitions){
  if(def.type!=='image'||!Array.isArray(def.ownImages))continue; // no authoritative snapshot -> no inferred title image
  const candidates=def.ownImages.length?def.ownImages:(presentation.fields.get(def.name)?.status==='resolved'?
    presentation.fields.get(def.name).values.filter(v=>v.kind==='image').map(v=>v.reference):[]);
  if(candidates.length)return parseImageReference(candidates[0])?candidates[0]:null;
 }
 return null;
}
const stop=event=>event.stopPropagation();
const linkProps=href=>({href,target:'_blank',rel:'noopener noreferrer',onClick:stop,onPointerDown:stop,onMouseDown:stop,onDoubleClick:stop});
export function createImageFileLink(React,options={}){
 const origin=options.origin||globalThis.location.origin,load=options.load||imageLoader(origin);
 return class ImageFileLink extends React.Component{
  constructor(props){super(props);this.state={info:null};this.sequence=0;}
  componentDidMount(){this.load();}
  componentDidUpdate(previous,oldState){if(previous.reference!==this.props.reference)this.load();else if(oldState.info!==this.state.info)this.props.onChange?.();}
  componentWillUnmount(){this.sequence++;}
  load(){const ticket=++this.sequence;this.setState({info:null});load(this.props.reference).then(info=>{if(ticket===this.sequence)this.setState({info});}).catch(()=>{});}
  render(){const info=this.state.info,text=info?.filename||parseImageReference(this.props.reference)?.filename||this.props.reference;
   return info?React.createElement('a',linkProps(info.descriptionUrl),text):React.createElement('span',null,text);}
 };
}
export function createThumbnail(React,options){
 const origin=options.origin||globalThis.location.origin,loadImage=options.loadImage||imageLoader(origin),loadPresentation=options.loadPresentation;
 return class SWSThumbnail extends React.Component{
  constructor(props){super(props);this.state={info:null,broken:false};this.sequence=0;}
  componentDidMount(){this.load();}
  componentDidUpdate(previous,oldState){if(previous.iri!==this.props.iri)this.load();else if(oldState.info!==this.state.info||oldState.broken!==this.state.broken)this.props.onChange?.();}
  componentWillUnmount(){this.sequence++;}
  load(){const ticket=++this.sequence;this.setState({info:null,broken:false});
   loadPresentation(this.props.iri).then(presentation=>{if(ticket!==this.sequence)return null;const ref=selectedImageReference(presentation);return ref?loadImage(ref):null;})
    .then(info=>{if(ticket===this.sequence)this.setState({info});}).catch(()=>{});
  }
  render(){
   const info=this.state.info;if(!info?.thumbnailUrl||this.state.broken)return this.props.fallback;
   const scale=Math.min(1,50/info.width,50/info.height),width=Math.max(1,Math.round(info.width*scale)),height=Math.max(1,Math.round(info.height*scale));
   const h=React.createElement;
   return h('div',{className:'graph-explorer-standard-template__thumbnail'},h('a',{...linkProps(info.descriptionUrl),'aria-label':info.filename},
    h('img',{className:'graph-explorer-standard-template__thumbnail-image',src:info.thumbnailUrl,alt:info.filename,width,height,
     style:{maxWidth:'50px',maxHeight:'50px',objectFit:'contain'},onLoad:()=>this.props.onChange?.(),onError:()=>this.setState({broken:true})})));
  }
 };
}
