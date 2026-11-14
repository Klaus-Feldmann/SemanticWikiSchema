import {parseImageReference,createThumbnail,createImageFileLink} from './swsGraphImage.js';
import {localSubjectId, readPageId} from './swsQIds.js';

function validDateParts(parts){return Array.isArray(parts)&&parts.length>0&&parts.length<=3&&parts.every(p=>typeof p.text==='string'&&p.text.length<=128&&(p.yearTitle===undefined||/^[1-9][0-9]{0,3}(?: BC| v\. Chr\.)?$/.test(p.yearTitle)));}

const CLASS = 'graph-explorer-standard-template__properties';
const SOURCE_NAMES = {'wikidata': 'Wikidata', 'wikibase-local': 'Wikibase'};

function safeUrl(value, origin, local = false) {
  if (typeof value !== 'string') return null;
  try {
    const url = new URL(value, origin);
    return ['https:', 'http:'].includes(url.protocol) && !url.username && !url.password && (!local || url.origin === origin) ? url.href : null;
  } catch { return null; }
}

export function localPageUrl(title, namespace, template, origin, fullLocalTitle = false) {
  if (typeof title !== 'string' || typeof template !== 'string') return null;
  const target = (namespace ? namespace + ':' : '') + title.trim();
  if (!target || /[[\]{}|<>\r\n]/.test(target) || target.startsWith(':') || (!fullLocalTitle && /^(?:https?|javascript|data):/i.test(target))) return null;
  if (template.split('SWSGRAPHTITLETOKEN').length !== 2) return null;
  return safeUrl(template.replace('SWSGRAPHTITLETOKEN', encodeURIComponent(target.replaceAll(' ', '_'))), origin, true);
}

export function readPresentation(data, pageId, subjectId, origin) {
  if (data?.version !== 1 || data.scope !== 'current' || data.pageId !== pageId) throw new Error('Invalid graph data response');
  if (data.mainSubjectId !== subjectId) return null;
  const meta = data.presentation;
  if (!meta) return null;
  if (meta.version !== 1 || !Array.isArray(meta.definitions) || !Array.isArray(data.fields)) throw new Error('Invalid schema presentation');
  if (!localPageUrl('Test', '', meta.pageUrlTemplate, origin)) throw new Error('Invalid local page URL template');
  const names = new Set();
  for (const def of meta.definitions) {
    if (typeof def.name !== 'string' || !def.name || names.has(def.name) || typeof def.type !== 'string' || typeof def.predicateLocalName !== 'string') throw new Error('Invalid field definition');
    if (def.predicatePath !== undefined && (typeof def.predicatePath !== 'string' || !/^\/(?:prop\/[A-Za-z0-9%._~-]+|schema(?:-id|-source|-source-id)?\/[A-Za-z0-9%._~-]+(?:\/[A-Za-z0-9%._~-]+)?\/field\/[A-Za-z0-9%._~-]+)$/.test(def.predicatePath))) throw new Error('Invalid field predicate path');
    if (def.displayLabel !== undefined && typeof def.displayLabel !== 'string') throw new Error('Invalid field display label');
    if (def.labels !== undefined && (!def.labels || typeof def.labels !== 'object' || Array.isArray(def.labels) || Object.entries(def.labels).some(([tag,label])=>! /^[a-z]{1,8}(-[a-z0-9]{1,8})*$/.test(tag) || typeof label!=='string'))) throw new Error('Invalid field labels');
    names.add(def.name);
    if (def.ownPages !== undefined && (def.type !== 'page' || !Array.isArray(def.ownPages) || def.ownPages.some(v=>typeof v!=='string'))) throw new Error('Invalid own page snapshot');
    if (def.execute !== undefined && (def.type !== 'wikitext' || typeof def.execute !== 'boolean')) throw new Error('Invalid execution marker');
    const link = def.wikiLink;
    if (link !== null && link !== undefined && (typeof link !== 'object' || Array.isArray(link))) throw new Error('Invalid field link configuration');
    if (def.ownUrls !== undefined && (def.type !== 'url' || !Array.isArray(def.ownUrls) || def.ownUrls.some(v=>!v || typeof v.url!=='string' || ['label','caption','captionPage'].some(k=>v[k]!==undefined&&typeof v[k]!=='string')))) throw new Error('Invalid own URL snapshot');
    if (def.ownImages !== undefined && (!Array.isArray(def.ownImages) || def.ownImages.some(v=>typeof v!=='string'))) throw new Error('Invalid own image snapshot');
    if (def.ownDates !== undefined && (def.type!=='historicalDate' || !Array.isArray(def.ownDates) || def.ownDates.some(v=>typeof v!=='string'))) throw new Error('Invalid own date snapshot');
    if (def.ownDateParts !== undefined && (!Array.isArray(def.ownDateParts) || def.ownDateParts.some(v=>!validDateParts(v)))) throw new Error('Invalid own date presentation');
    if (link?.externalTarget !== undefined && !['source','local'].includes(link.externalTarget)) throw new Error('Invalid link target');
  }
  if (meta.ownDescription !== undefined && meta.ownDescription !== null && typeof meta.ownDescription !== 'string') throw new Error('Invalid own description');
  const fields = new Map();
  for (const field of data.fields) {
    const def = meta.definitions.find(d => d.name === field.name);
    if (!def?.externalProperty || field.source !== def.externalProperty.source || field.propertyId !== def.externalProperty.id || !SOURCE_NAMES[field.source] || fields.has(field.name)) throw new Error('Mismatched property source');
    if (!['resolved','missing','unavailable','unsupported','manual'].includes(field.status) || !Array.isArray(field.values)) throw new Error('Invalid derived field');
    for (const value of field.values) {
      if (value.kind === 'entity') {
        if (!/^Q[1-9][0-9]*$/.test(value.id) || typeof value.label !== 'string') throw new Error('Invalid value entity');
        const url = safeUrl(value.url, origin, field.source === 'wikibase-local');
        if (!url || (field.source === 'wikidata' && url !== 'https://www.wikidata.org/wiki/' + value.id)) throw new Error('Invalid value source URL');
      } else if (value.kind === 'text') {
        if(typeof value.value!=='string'||!value.value.trim()||(value.language!==undefined&&!/^[a-z][a-z0-9-]{0,39}$/.test(value.language)))throw new Error('Invalid text value');
      } else if (value.kind === 'time') {
        if(def.type!=='historicalDate' || !validDateParts(value.parts) || ![9,10,11].includes(value.precision) || !['julian','gregorian'].includes(value.calendar)) throw new Error('Invalid time value');
      } else if (value.kind === 'image') {
        if(def.type !== 'image' || typeof value.filename !== 'string' || value.repository !== 'commons' || value.reference !== 'commons:' + value.filename || /[[\]{}<>|#\r\n]/.test(value.filename)) throw new Error('Invalid image reference');
      } else if (!['somevalue','novalue'].includes(value.kind)) throw new Error('Unsupported value kind');
    }
    fields.set(field.name, field);
  }
  return {...meta, fields};
}

export function createPresentationLoader({origin, fetchImpl = globalThis.fetch, now = Date.now}) {
  const cache = new Map();
  async function json(url) {
    if (new URL(url).origin !== origin) throw new Error('Graph request must be local');
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 25000);
    try {
      const response = await fetchImpl(url, {credentials:'same-origin', cache:'no-store', signal:controller.signal, headers:{Accept:'application/json'}});
      if (!response.ok) throw new Error('Graph data request failed');
      const data = await response.json();
      if (data.error) throw new Error('Graph data unavailable');
      return data;
    } finally { clearTimeout(timer); }
  }
  async function load(iri, sid, language) {
    const base = globalThis.SWSGraphConfig?.baseIri || origin + '/';
    const sparql = new URL(globalThis.SWSGraphConfig?.sparqlUrl || '/graph-explorer/sparql', origin);
    if (!sparql.searchParams.has('default-graph-uri')) sparql.searchParams.set('default-graph-uri','urn:x-arq:UnionGraph');
    sparql.searchParams.set('query', `SELECT DISTINCT ?page WHERE { ?page <${base}ontology/mainSubject> <${iri}> } LIMIT 2`);
    const graph = await json(sparql.href);
    if (!Array.isArray(graph.results?.bindings)) throw new Error('Invalid graph mapping');
    const pageId = readPageId(graph.results.bindings, origin);
    if (pageId === null) return null;
    const api = new URL(globalThis.SWSGraphConfig?.apiUrl || '/w/api.php', origin);
    api.search = new URLSearchParams({action:'swsbasevalues', pageid:String(pageId), subject:sid, language,
      presentation:'1', format:'json', formatversion:'2'}).toString();
    return readPresentation((await json(api.href)).swsbasevalues, pageId, sid, origin);
  }
  return (iri, requestedLanguage) => {
    const language = requestedLanguage || new URLSearchParams(globalThis.location?.search || '').get('uselang') || globalThis.SWSGraphConfig?.language || globalThis.document?.documentElement?.lang || 'de';
    const key = iri + '\u0000' + language;
    const sid = localSubjectId(iri, origin);
    if (!sid) return Promise.resolve(null);
    const previous = cache.get(key);
    if (previous && previous.expires > now()) return previous.promise;
    const entry = {expires:now()+60000, promise:null};
    entry.promise = load(iri,sid,language).catch(error => {cache.delete(key);throw error;});
    cache.set(key,entry);
    return entry.promise;
  };
}

const presentationLoaders=new Map();
function sharedPresentationLoader(origin){if(!presentationLoaders.has(origin))presentationLoaders.set(origin,createPresentationLoader({origin}));return presentationLoaders.get(origin);}
export function createSWSThumbnail(React,options={}){const origin=options.origin||globalThis.location.origin;return createThumbnail(React,{origin,loadPresentation:options.loadPresentation||sharedPresentationLoader(origin),...options});}

/** Merge view data, never change graph properties, source identities or schema definitions. */
export function orderedRows(presentation, nativeProps, origin) {
  const used = new Set();
  const base = globalThis.SWSGraphConfig?.baseIri || origin + '/';
  const rows = [];
  for (const def of presentation.definitions) {
    const predicate = base.replace(/\/$/, '') + (def.predicatePath ?? '/prop/' + def.predicateLocalName);
    const native = nativeProps.filter(p => p.id === predicate);
    native.forEach(p => used.add(p));
    if (def.type === 'wikitext' && def.execute === true) continue;
    const projectedOwn = native.flatMap(p => (p.property?.values || []).map(v => String(v.value)));
    let own = def.type==='page' && Array.isArray(def.ownPages) ? def.ownPages : def.type==='url' && Array.isArray(def.ownUrls) ? def.ownUrls : def.type==='historicalDate' && Array.isArray(def.ownDates) ? def.ownDates : def.type==='image' && Array.isArray(def.ownImages) ? def.ownImages : projectedOwn;
    const external = ['image','historicalDate'].includes(def.type) && own.length ? null : presentation.fields.get(def.name);
    const values = external?.status === 'resolved' ? external.values.map(value => ({
      parts:value.kind==='time'?value.parts:null,
      reference: value.kind === 'image' ? value.reference : null,
      language:value.kind==='text'?value.language:null,
      rank:value.rank||null,
      statementId:value.statementId||null,
      text: value.kind === 'text' ? value.value : value.kind === 'time' ? value.label : value.kind === 'entity' ? value.label || value.id : value.kind === 'image' ? value.filename : value.kind === 'somevalue' ? 'Unbekannter Wert' : 'Kein Wert',
      href: value.kind === 'text' ? (def.wikiLink?.externalTarget==='local' && def.wikiLink?.values!==false ? localPageUrl(value.value,def.wikiLink?.namespace||'',presentation.pageUrlTemplate,origin) : null) : value.kind === 'entity' ? (def.wikiLink?.externalTarget === 'local' && value.label?.trim() && value.label.trim() !== value.id ?
        localPageUrl(value.label, def.wikiLink?.namespace || '', presentation.pageUrlTemplate, origin) || value.url : value.url) : null,
    })) : own.length ? [] : external ? [{text:({missing:['Not specified in the source.','In der Quelle nicht angegeben.'],unavailable:['Source retrieval unavailable.','Quellenabruf nicht verfügbar.'],unsupported:['Unsupported source datatype.','Quelldatentyp nicht unterstützt.'],manual:['Please enter your own date.','Bitte eigene Datierung eingeben.']}[external.status]||['',''])[presentation.language?.startsWith('en')?0:1],href:null}] : [];
    if (def.type==='url' && Array.isArray(def.ownUrls) && own.length) {
      for (const entry of def.ownUrls) {
        const caption = entry.caption?.trim();
        rows.push({name:caption || def.displayLabel || def.name,
          labelHref:caption ? (entry.captionPage ? localPageUrl(entry.captionPage,'',presentation.pageUrlTemplate,origin) : null) : (def.wikiLink?.label === false ? null : localPageUrl(def.wikiLink?.labelTarget || def.name,'',presentation.pageUrlTemplate,origin)),
          own:[{text:entry.label || entry.url,href:/^https?:\/\//i.test(entry.url)?safeUrl(entry.url,origin):null}],values:[]});
      }
      if (!values.length) continue;

    }
    if (!own.length && !values.length) continue;
    rows.push({namespace:def.wikiLink?.namespace || '', yearLinks:def.wikiLink?.values!==false,image:def.type==='image',name:def.displayLabel || def.name, fieldKey:def.name, labelHref:def.wikiLink?.label === false ? null : localPageUrl(def.wikiLink?.labelTarget || def.name, '', presentation.pageUrlTemplate, origin),
      own:own.map((text,ownIndex) => def.type==='url' && typeof text==='object' ? {text:text.label || text.url,href:/^https?:\/\//i.test(text.url)?safeUrl(text.url,origin):null,caption:text.caption || text.captionPage || '',captionHref:text.captionPage?localPageUrl(text.captionPage,'',presentation.pageUrlTemplate,origin):null} : ({parts:def.type==='historicalDate' && validDateParts(def.ownDateParts?.[ownIndex]) ? def.ownDateParts[ownIndex] : null,reference:def.type==='image'?text:null,text:def.type==='image'?(parseImageReference(text)?.filename||text):text, href:(def.type!=='page' && def.wikiLink?.values === false) || (def.externalProperty && text === def.externalProperty.id) ? null :
        (def.type==='page' ? localPageUrl(text,'',presentation.pageUrlTemplate,origin,true) : ['text','select'].includes(def.type) ? localPageUrl(text,def.wikiLink?.namespace || '',presentation.pageUrlTemplate,origin) : null)})),
      values, source:external?.source, status:external?.status});
  }
  // Preserve properties outside the current schema, including native graph metadata.
  for (const prop of nativeProps.filter(p => !used.has(p) && !String(p.id).startsWith('urn:sws:aewi:'))) rows.push({name:prop.name, labelHref:null,
    own:(prop.property?.values || []).map(v=>({text:String(v.value),href:null})),values:[]});
  return rows;
}

export function createSWSFields(React, options = {}) {
  const origin = options.origin || globalThis.location.origin;
  const load = options.load || sharedPresentationLoader(origin);
  const ImageFileLink=createImageFileLink(React,{origin});
  return class SWSFields extends React.Component {
    constructor(props) { super(props); this.state={presentation:null}; this.generation=0; }
    componentDidMount() { this.load(); }
    componentDidUpdate(previous, oldState) {
      if (previous.iri !== this.props.iri || previous.language !== this.props.language) this.load();
      else if (oldState.presentation !== this.state.presentation) this.props.onChange?.();
    }
    componentWillUnmount() { this.generation++; }
    load() {
      const generation=++this.generation;
      this.setState({presentation:null});
      load(this.props.iri,this.props.language).then(presentation=>{if(generation===this.generation)this.setState({presentation});})
        .catch(()=>{if(generation===this.generation)this.setState({presentation:null});});
    }
    render() {
      if (!this.state.presentation) return this.props.fallback;
      const h=React.createElement;
      const stop=e=>e.stopPropagation();
      const link=(text,href)=>href?h('a',{href,target:'_blank',rel:'noopener noreferrer',onClick:stop,onPointerDown:stop,onMouseDown:stop,onDoubleClick:stop},text):text;
      const rows=orderedRows(this.state.presentation,this.props.nativeProps,origin);
      return h('div',{className:CLASS},this.state.presentation.ownDescription ? h('div',{className:CLASS+'-own-description'},this.state.presentation.ownDescription) : null,...rows.map((row,index)=>h('div',{key:index,className:CLASS+'-row'},
        h('div',{className:CLASS+'-key',title:row.name},link(row.name,row.labelHref)),
        h('div',{className:CLASS+'-values'},
          ...row.own.map((v,i)=>h('div',{className:CLASS+'-value',key:'own'+i},
            v.caption?h('span',null,link(v.caption,v.captionHref),': '):null, row.values.length?h('span',{style:{fontSize:'10px'}},'Eigene Angabe: '):null,v.parts?h('span',null,...v.parts.map((part,i)=>h('span',{key:i},link(part.text,part.yearTitle&&row.yearLinks?localPageUrl(part.yearTitle,row.namespace,this.state.presentation.pageUrlTemplate,origin):null)))):row.image&&v.reference?h(ImageFileLink,{reference:v.reference,onChange:this.props.onChange}):link(v.text,v.href))),
          ...row.values.map((v,i)=>h('div',{className:CLASS+'-value',key:'external'+i,title:SOURCE_NAMES[row.source],lang:v.language},v.parts?h('span',null,...v.parts.map((part,i)=>h('span',{key:i},link(part.text,part.yearTitle&&row.yearLinks?localPageUrl(part.yearTitle,row.namespace,this.state.presentation.pageUrlTemplate,origin):null)))):row.image&&v.reference?h(ImageFileLink,{reference:v.reference,onChange:this.props.onChange}):link(v.text,v.href)))
        ))));
    }
  };
}
