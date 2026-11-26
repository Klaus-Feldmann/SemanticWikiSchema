interface RenderedField { source: string; html: string; modules?: string[]; styles?: string[] }
const pending = new Map<string, Promise<Record<string, RenderedField>>>();
/** Share only an in-flight page read; never retain results across edits or navigation. */
export async function loadSavedWikitext( pageid: number, subject: string, field: string, note = false ): Promise<RenderedField> {
 const key = pageid + ':' + note;
 let request = pending.get( key );
 if ( !request ) {
  request = Promise.resolve().then( async () => {
   const response = await new mw.Api().get( {
    action: 'swsrenderwikitext', pageid, all: true, ...( note ? { notesonly: true } : {} ), format: 'json'
   } );
   const entries = response.swsrenderwikitext?.entries;
   if ( !entries || typeof entries !== 'object' ) { throw new Error( 'Invalid wikitext page batch' ); }
   return entries;
  } );
  pending.set( key, request );
  const current = request;
  void request.then( () => { if ( pending.get( key ) === current ) { pending.delete( key ); } }, () => { if ( pending.get( key ) === current ) { pending.delete( key ); } } );
 }
 const entries = await request;
 const result = entries[ JSON.stringify( [ subject, field, note ? 'note' : 'value' ] ) ];
 if ( !result || typeof result.html !== 'string' ) { throw new Error( 'Saved wikitext field unavailable' ); }
 return result;
}
