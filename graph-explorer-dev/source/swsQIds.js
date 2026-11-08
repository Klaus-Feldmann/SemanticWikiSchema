/** Q-ID row for the existing Graph Explorer standard node template. No DOM scraping. */
export const SOURCES = ['wikidata', 'wikibase-local'];
const labels = { wikidata: 'Wikidata', 'wikibase-local': 'Wikibase' };


function baseIri(origin) { return globalThis.SWSGraphConfig?.baseIri || origin + '/'; }

export function localSubjectId( iri, origin ) {
  if ( typeof iri !== 'string' ) return null;
  try {
    const url = new URL( iri );
    if ( url.origin !== origin || url.search || url.hash ) return null;
    const prefix = new URL('entity/', baseIri(origin));
    if (url.origin !== prefix.origin || !url.pathname.startsWith(prefix.pathname)) return null;
    const m = /^(s[A-Za-z0-9_-]{1,79})$/.exec(url.pathname.slice(prefix.pathname.length));
    return m ? m[1] : null;
  } catch { return null; }
}

export function readPageId( bindings, origin ) {
  const pages = new Set();
  for ( const binding of bindings ) {
    const value = binding?.page?.value;
    if ( binding?.page?.type !== 'uri' || typeof value !== 'string' ) throw new Error('Invalid page mapping');
    const url = new URL(value);
    const prefix = new URL('page/', baseIri(origin));
    const match = url.pathname.startsWith(prefix.pathname) ? /^([1-9][0-9]*)$/.exec(url.pathname.slice(prefix.pathname.length)) : null;
    if (url.origin !== origin || url.search || url.hash || !match) throw new Error('Invalid page mapping');
    const id = Number(match[1]);
    if (!Number.isSafeInteger(id)) throw new Error('Invalid page identifier');
    pages.add(id);
  }
  if (pages.size > 1) throw new Error('Ambiguous main subject');
  return pages.size ? [...pages][0] : null;
}

export function readIdentities( data, pageId, subjectId, origin ) {
  if (data?.version !== 2 || data.pageId !== pageId || data.scope !== 'current') throw new Error('Unsupported identity response');
  // The stored graph can lag behind a main-subject change. Never copy another subject's IDs.
  if (data.mainSubjectId !== subjectId) return null;
  const item = data.identities?.[subjectId];
  if (!item) throw new Error('Missing identity response');
  const result = {};
  for (const source of SOURCES) {
    const value = item[source];
    if (!value || !['resolved', 'missing', 'unavailable', 'error'].includes(value.status)) throw new Error('Invalid source identity');
    if (value.status === 'resolved') {
      if (typeof value.id !== 'string' || !/^Q[1-9][0-9]*$/.test(value.id) || value.id !== value.id.trim()) throw new Error('Invalid Q ID');
      const url = new URL(value.url);
      if (!['https:', 'http:'].includes(url.protocol) || url.username || url.password) throw new Error('Unsafe identity link');
      if (source === 'wikidata' && url.href !== 'https://www.wikidata.org/wiki/' + value.id) throw new Error('Wrong Wikidata target');
      if (source === 'wikibase-local' && url.origin !== origin) throw new Error('Wrong local repository');
      result[source] = {status: 'resolved', id: value.id, url: url.href};
    } else {
      if (value.id !== null || value.url !== null) throw new Error('Invalid unresolved identity');
      result[source] = {status: value.status, id: null, url: null};
    }
  }
  if (data.displayDescription !== undefined) result.descriptionGroups = readDescriptionGroups(data.displayDescription === null ? [] : [data.displayDescription], result,
    {id:pageId,url:data.pageDescription?.url,origin});
  else if (data.descriptions !== undefined) {
    const groups=readDescriptionGroups(data.descriptions,result);
    const preferred=groups.find(group=>group.sources.some(item=>item.source==='wikibase-local'))||groups[0];
    result.descriptionGroups=preferred?[preferred]:[];
  }
  return result;
}

export function createIdentityLoader({origin, fetchImpl = globalThis.fetch, now = Date.now}) {
  const cache = new Map();
  async function json(url) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetchImpl(url, {credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
        headers: {Accept: 'application/json, application/sparql-results+json'}});
      if (!response.ok) throw new Error('Identity request failed');
      const data = await response.json();
      if (data.error) throw new Error('Identity request failed');
      return data;
    } finally { clearTimeout(timeout); }
  }
  async function load(iri, subjectId) {
    const sparql = new URL(globalThis.SWSGraphConfig?.sparqlUrl || '/graph-explorer/sparql', origin);
    if (!sparql.searchParams.has('default-graph-uri')) sparql.searchParams.set('default-graph-uri', 'urn:x-arq:UnionGraph');
    if (sparql.origin !== origin) throw new Error('SPARQL must be same-origin');
    sparql.searchParams.set('query', `SELECT DISTINCT ?page WHERE { ?page <${baseIri(origin)}ontology/mainSubject> <${iri}> } LIMIT 2`);
    const graph = await json(sparql.href);
    if (!Array.isArray(graph.results?.bindings)) throw new Error('Invalid graph response');
    const pageId = readPageId(graph.results.bindings, origin);
    if (pageId === null) return null;
    const api = new URL(globalThis.SWSGraphConfig?.apiUrl || '/w/api.php', origin);
    if (api.origin !== origin) throw new Error('API must be same-origin');
    api.search = new URLSearchParams({action: 'swsbaseidentity', descriptions: '1', language: displayLanguage(), pageid: String(pageId), format: 'json', formatversion: '2'}).toString();
    const data = await json(api.href);
    return readIdentities(data.swsbaseidentity, pageId, subjectId, origin);
  }
  return (iri) => {
    const subjectId = localSubjectId(iri, origin);
    if (subjectId === null) return Promise.resolve(null);
    const old = cache.get(iri);
    if (old && old.expires > now()) return old.promise;
    const entry = {expires: now() + 60000, promise: null};
    entry.promise = load(iri, subjectId).catch((error) => { cache.delete(iri); throw error; });
    cache.set(iri, entry);
    return entry.promise;
  };
}

/** React is supplied by the existing bundle, so no second React runtime is loaded. */
export function createSWSQIdRow(React, options = {}) {
  const origin = options.origin || globalThis.location.origin;
  const load = options.load || sharedIdentityLoader(origin);
  return class SWSQIdRow extends React.Component {
    constructor(props) { super(props); this.state = {identities: null, failed: false}; this.generation = 0; }
    componentDidMount() { this.load(); }
    componentDidUpdate(previous, previousState) {
      if (previous.iri !== this.props.iri) this.load();
      else if (previousState.identities !== this.state.identities || previousState.failed !== this.state.failed) {
        // Ask the graph to remeasure this node through its existing redraw path.
        this.props.onChange?.();
      }
    }
    componentWillUnmount() { this.generation++; }
    load() {
      const generation = ++this.generation;
      this.setState({identities: null, failed: false});
      load(this.props.iri).then((identities) => {
        if (generation === this.generation) this.setState({identities});
      }).catch(() => {
        if (generation === this.generation) this.setState({failed: true});
      });
    }
    render() {
      if (localSubjectId(this.props.iri, origin) === null) return null;
      const h = React.createElement;
      const stop = (event) => event.stopPropagation();
      const identities = this.state.identities;
      const visible = identities ? SOURCES.filter(source => identities[source].status === 'resolved') : [];
      if (!visible.length) return null;
      const children = visible.flatMap((source, index) => {
        const item = identities[source];
        return [index ? ' · ' : null, h('span', {key: source}, labels[source] + ': ',
          h('a', {href: item.url, target: '_blank', rel: 'noopener noreferrer', onClick: stop,
            onPointerDown: stop, onMouseDown: stop, onDoubleClick: stop}, item.id))];
      });
      return h('div', {className: 'ge-sws-qids', 'aria-live': 'polite',
        style: {fontSize: '10px', fontWeight: 400, lineHeight: '1.4', minHeight: '14px', marginTop: '3px', overflowWrap: 'anywhere'}}, ...children);
    }
  };
}

function displayLanguage() {
 const configured=globalThis.SWSGraphConfig?.language || globalThis.document?.documentElement?.lang;
 const language=String(configured || globalThis.navigator?.language?.split('-')[0] || 'de').toLowerCase();
 return /^[a-z]{2,12}(?:-[a-z0-9]{1,12})*$/.test(language) ? language : 'de';
}
const loaders = new Map();
function sharedIdentityLoader(origin) {
 const key=JSON.stringify([origin,baseIri(origin),globalThis.SWSGraphConfig?.apiUrl,globalThis.SWSGraphConfig?.sparqlUrl,displayLanguage()]);
 if(!loaders.has(key))loaders.set(key,createIdentityLoader({origin}));
 return loaders.get(key);
}
export function readDescriptionGroups(raw, identities, page) {
 if(!Array.isArray(raw))return [];
 return raw.filter(group=>group && typeof group.text==='string' && group.text.trim() &&
  typeof group.language==='string' && /^[a-z]{2,12}(?:-[a-z0-9]{1,12})*$/.test(group.language) &&
  Array.isArray(group.sources) && group.sources.length && group.sources.every(item=>{
   if(item?.source==='page') {
    if(!page||item.id!==String(page.id)||item.url!==page.url)return false;
    try {const url=new URL(page.url);return ['http:','https:'].includes(url.protocol)&&!url.username&&!url.password&&url.origin===page.origin;}catch{return false;}
   }
   return item && SOURCES.includes(item.source) && identities[item.source]?.status==='resolved' && item.id===identities[item.source].id && item.url===identities[item.source].url;
  }));
}
export function createSWSDescriptionRow(React,options={}) {
 const Base=createSWSQIdRow(React,options);
 return class SWSDescriptionRow extends Base {
  render() {
   if(localSubjectId(this.props.iri,options.origin||globalThis.location.origin)===null)return null;
   const groups=this.state.identities?.descriptionGroups || [];
   if(!groups.length)return null;
   const h=React.createElement;
   return h('div',{className:'ge-sws-descriptions','aria-live':'polite',
    style:{fontSize:'11px',fontWeight:400,lineHeight:'1.4',padding:'0 10px 6px',maxWidth:'300px',overflowWrap:'anywhere',whiteSpace:'normal'}},
    ...groups.map((group,index)=>h('div',{key:index,style:{marginTop:index?'4px':0}},
     h('span',{lang:group.language},group.text),
     h('small',{style:{display:'block',fontSize:'9px',opacity:0.75}},group.sources.map(item=>item.source==='page'?(displayLanguage().startsWith('de')?'Diese Seite':'This page'):labels[item.source]).join(' · ')))));
  }
 };
}
