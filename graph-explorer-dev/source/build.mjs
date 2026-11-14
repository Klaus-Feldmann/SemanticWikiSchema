import ts from 'typescript';
import * as esbuild from 'esbuild';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {fileURLToPath} from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (name) => fs.readFileSync(path.join(root, 'Ausgangsstand', name), 'utf8');
const write = (name, content) => fs.writeFileSync(path.join(root, 'Graph-Explorer', name), content);
const hash = (s) => crypto.createHash('sha256').update(s).digest('hex');
for (const name of fs.readdirSync(path.join(root, 'Graph-Explorer/assets'))) {
  if (/^(sws-qids-|common-qids-|envendpoint-qids-)/.test(name)) fs.unlinkSync(path.join(root, 'Graph-Explorer/assets', name));
}
const original = read('common-CPcN3Iil.js');
const file = ts.createSourceFile('common.js', original, ts.ScriptTarget.Latest, true, ts.ScriptKind.JS);
if (file.parseDiagnostics.length) throw new Error('Baseline JavaScript did not parse');
const candidates = [];
function visit(node) {
  if (ts.isCallExpression(node) && node.arguments.length >= 3 && ts.isStringLiteralLike(node.arguments[0]) &&
      node.arguments[0].text === 'div' && ts.isObjectLiteralExpression(node.arguments[1])) {
    const cls = node.arguments[1].properties.find((p) => ts.isPropertyAssignment(p) && p.name.getText(file) === 'className');
    if (cls && cls.initializer.getText(file).includes('__body-content')) {
      let parent = node.parent;
      while (parent && !ts.isMethodDeclaration(parent)) parent = parent.parent;
      if (parent?.name?.getText(file) === 'renderTemplate') candidates.push(node);
    }
  }
  ts.forEachChild(node, visit);
}
visit(file);
if (candidates.length !== 1) throw new Error('Expected exactly one known standard node title container');
const node = candidates[0];
const expr = node.expression.getText(file);
const match = /([A-Za-z_$][\w$]*)\.createElement/.exec(expr);
if (!match || original.includes('__swsBaseQIdRow')) throw new Error('Unexpected baseline renderer');
const react = match[1];
const helperBuild = await esbuild.build({stdin:{contents: "export {createSchemaQueries} from './swsSchemaQueries.js'; export {createSWSQIdRow,createSWSDescriptionRow} from './swsQIds.js'; export {createSWSFields,createSWSThumbnail} from './swsFields.js';",resolveDir:path.join(root,'source'),sourcefile:'graphHelpers.js'},bundle:true,write:false,outfile:'helper.js',format:'esm',target:'es2022',minify:true,sourcemap:'external'});
const helper = {code:helperBuild.outputFiles.find(f=>f.path.endsWith('/helper.js')).text,map:helperBuild.outputFiles.find(f=>f.path.endsWith('.map')).text};
const helperName = `sws-qids-${hash(helper.code).slice(0,12)}.js`;
write('assets/' + helperName, helper.code + `\n//# sourceMappingURL=${helperName}.map\n`);
write('assets/' + helperName + '.map', helper.map);
let method = node.parent;
while (!ts.isMethodDeclaration(method)) method = method.parent;
const context = method.parameters[0].name.getText(file);
const insertion = `,${expr}((__swsBaseQIdRow??=createSWSQIdRow(${react})),{iri:this.props.iri,onChange:()=>${context}.view.model.getElement(this.props.elementId)?.redraw()})`;
const at = node.end - 1;
if (original[at] !== ')') throw new Error('Invalid insertion point');
const tableCalls=[];
function findTable(n){
 if(ts.isCallExpression(n)&&ts.isPropertyAccessExpression(n.expression)&&n.expression.name.text==='renderProperties'&&n.arguments.length===1){
  let m=n.parent;while(m&&!ts.isMethodDeclaration(m))m=m.parent;
  if(m?.name?.getText(file)==='renderTemplate'&&n.arguments[0].getText(file)==='propsAsList')tableCalls.push(n);
 }
 ts.forEachChild(n,findTable);
}
findTable(file);
// Minified baseline aliases the destructured propsAsList; identify it from the binding.
if(!tableCalls.length){
 function findAlias(n){if(ts.isCallExpression(n)&&n.expression.getText(file)==='this.renderProperties'){
  let m=n.parent;while(m&&!ts.isMethodDeclaration(m))m=m.parent;
  if(m===method&&ts.isIdentifier(n.arguments[0])&&n.arguments[0].getText(file)===/propsAsList:([A-Za-z_$][\w$]*)/.exec(method.getText(file))?.[1])tableCalls.push(n);
 }ts.forEachChild(n,findAlias);}findAlias(file);
}
if(tableCalls.length!==1)throw new Error('Expected one expanded property table');
const table=tableCalls[0];
const replacement=`${expr}((__swsFields??=createSWSFields(${react})),{iri:this.props.iri,language:${context}.view.getLanguage?.(),nativeProps:${table.arguments[0].getText(file)},fallback:${table.getText(file)},onChange:()=>${context}.view.model.getElement(this.props.elementId)?.redraw()})`;
const thumbCalls=[];
function findThumbnail(n){if(ts.isCallExpression(n)&&n.expression.getText(file)==='this.renderThumbnail')thumbCalls.push(n);ts.forEachChild(n,findThumbnail);}
findThumbnail(method);if(thumbCalls.length!==1)throw new Error('Expected exactly one thumbnail in the standard header');
const thumb=thumbCalls[0];
if(thumb.end>at)throw new Error('Unexpected thumbnail order');
const thumbReplacement=`${expr}((__swsThumbnail??=createSWSThumbnail(${react})),{iri:this.props.iri,fallback:${thumb.getText(file)},onChange:()=>${context}.view.model.getElement(this.props.elementId)?.redraw()})`;
let horizontal=node.parent;
while(horizontal && !(ts.isCallExpression(horizontal) && horizontal.arguments[1]?.getText(file).includes('__body-horizontal')))horizontal=horizontal.parent;
if(!horizontal)throw new Error('Missing image/title container');
const descriptionInsertion=`,${expr}((__swsDescription??=createSWSDescriptionRow(${react})),{iri:this.props.iri,onChange:()=>${context}.view.model.getElement(this.props.elementId)?.redraw()})`;
const edits=[{start:at,end:at,text:insertion},{start:horizontal.end,end:horizontal.end,text:descriptionInsertion},
 {start:table.getStart(file),end:table.end,text:replacement},{start:thumb.getStart(file),end:thumb.end,text:thumbReplacement}].sort((a,b)=>b.start-a.start);
let added=original;for(const edit of edits)added=added.slice(0,edit.start)+edit.text+added.slice(edit.end);
const input = `import {createSWSQIdRow,createSWSDescriptionRow,createSWSFields,createSWSThumbnail} from './${helperName}';\nvar __swsBaseQIdRow,__swsDescription,__swsFields,__swsThumbnail;\n` +
  added.replace(/\n\/\/# sourceMappingURL=.*$/, '');
const common = await esbuild.transform(input, {format: 'esm', target: 'es2022', minify: true,
  sourcemap: 'external', sourcefile: 'Ausgangsstand/common-CPcN3Iil.js'});
const commonName = `common-qids-${hash(common.code).slice(0,12)}.js`;
write('assets/' + commonName, common.code + `\n//# sourceMappingURL=${commonName}.map\n`);
write('assets/' + commonName + '.map', common.map);
const entryOriginal = read('envendpoint-Qu3prYG8.js');
if (entryOriginal.split('./common-CPcN3Iil.js').length !== 2) throw new Error('Unexpected entry imports');
const configuredEntry = entryOriginal.replace('f=`https://aufeinwort.info/`', 'f=globalThis.SWSGraphConfig.baseIri').replace('endpointUrl:`/graph-explorer/sparql?default-graph-uri=urn%3Ax-arq%3AUnionGraph`', 'endpointUrl:globalThis.SWSGraphConfig.sparqlUrl');
if (configuredEntry === entryOriginal || configuredEntry.includes('https://aufeinwort.info/')) throw new Error('Entry configuration replacement failed');
let schemaEntry = configuredEntry;
for (const key of ['classTreeQuery', 'classInfoQuery']) {
 const pattern = new RegExp(key + ':`[^`]*`', 'g');
 if ([...schemaEntry.matchAll(pattern)].length !== 1) throw new Error('Unexpected schema query: ' + key);
 schemaEntry = schemaEntry.replace(pattern, key + ':__swsSchemaQueries.' + key);
}
const entryText = `import {createSchemaQueries} from './${helperName}'; const __swsSchemaQueries=createSchemaQueries(globalThis.SWSGraphConfig.baseIri,document.documentElement.lang||navigator.language||'de');\n` + schemaEntry.replace('./common-CPcN3Iil.js', './' + commonName).replace(/\n\/\/# sourceMappingURL=.*$/, '');
const entry = await esbuild.transform(entryText, {format: 'esm', target: 'es2022', minify: true,
  sourcemap: 'external', sourcefile: 'Ausgangsstand/envendpoint-Qu3prYG8.js'});
const entryName = `envendpoint-qids-${hash(entry.code).slice(0,12)}.js`;
write('assets/' + entryName, entry.code + `\n//# sourceMappingURL=${entryName}.map\n`);
write('assets/' + entryName + '.map', entry.map);
const html = read('envendpoint.html').replace('envendpoint-Qu3prYG8.js', entryName).replace('common-CPcN3Iil.js', commonName);
const cleanedHtml = html.replace(/    <select class="demoSelector"[\s\S]*?(?=  <\/body>)/, '').replace('Graph Explorer on $SPARQL_ENDPOINT', 'SWS Graph Explorer');
write('envendpoint.html', cleanedHtml.replaceAll('/graph-explorer/assets/', './assets/').replace('<script type="module"', '<script src="./config.js"></script>\n    <script type="module"'));
// Supply the exact TypeScript source counterpart for the owner's normal source tree.
const map = JSON.parse(read('common-CPcN3Iil.js.map'));
const idx = map.sources.indexOf('../../../src/graph-explorer/customization/templates/standard.tsx');
const standard = map.sourcesContent[idx];
const needle = '                  {label}\n                </div>';
if (standard.split(needle).length !== 2) throw new Error('Unexpected original TypeScript template');
const source = standard.replace('const FOAF_NAME',
  'import { createSWSFields, createSWSThumbnail } from \"./swsFields\";\nconst SWSFields = createSWSFields(React);\nconst SWSThumbnail = createSWSThumbnail(React);\n\nimport { createSWSQIdRow, createSWSDescriptionRow } from "./swsQIds";\nconst SWSQIdRow = createSWSQIdRow(React);\nconst SWSDescriptionRow = createSWSDescriptionRow(React);\n\nconst FOAF_NAME')
  .replace('            {pinnedProperties ? (', '            <SWSDescriptionRow iri={iri} onChange={() => context.view.model.getElement(this.props.elementId)?.redraw()} />\n            {pinnedProperties ? (')
  .replace(needle, needle + '\n                <SWSQIdRow iri={iri} onChange={() => context.view.model.getElement(this.props.elementId)?.redraw()} />');
fs.writeFileSync(path.join(root, 'source/standard.tsx'), source.replace('{this.renderThumbnail()}', '{<SWSThumbnail iri={iri} fallback={this.renderThumbnail()} onChange={() => context.view.model.getElement(this.props.elementId)?.redraw()} />}').replace('{this.renderProperties(propsAsList)}', '{<SWSFields iri={iri} language={context.view.getLanguage()} nativeProps={propsAsList} fallback={this.renderProperties(propsAsList)} onChange={() => context.view.model.getElement(this.props.elementId)?.redraw()} />}'));
const manifest = {
  baseline: {'envendpoint.html': hash(read('envendpoint.html')), 'assets/envendpoint-Qu3prYG8.js': hash(entryOriginal),
    'assets/common-CPcN3Iil.js': hash(original), 'assets/common-BzxewltY.css': hash(read('common-BzxewltY.css'))},
  changes: 'Left 50px linked title thumbnail, own-image priority and current image snapshot; Schema-ordered derived values and configured links in expanded tables; Q-ID row below title and source-qualified descriptions below image/title; only resolved source-label/ID pairs; configurable endpoint/base; relative assets; unrelated demos omitted.',
  generated: {helperName, commonName, entryName},
  assets: Object.fromEntries(fs.readdirSync(path.join(root, 'Graph-Explorer/assets')).map((f) => [f, hash(fs.readFileSync(path.join(root, 'Graph-Explorer/assets',f)))]))
};
fs.writeFileSync(path.join(root, 'BUILD-MANIFEST.json'), JSON.stringify(manifest, null, 2) + '\n');
console.log(JSON.stringify({templateInsertions: candidates.length, generated: manifest.generated}, null, 2));
