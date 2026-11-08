---
title: Using SWS from JavaScript
order: 10
---
# Using SWS from JavaScript

`require( 'ext.sws' )` from your [frontend module](extending.md#frontend-module) returns SWS's public API
barrel; its exports are listed in [`public-api.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/public-api.ts). `nw.SWSServices` is the service
locator: the repositories (`getSubjectRepository()`, `getSchemaRepository()`, `getLayoutRepository()`), the
component registry, and `registerServices( app )` for a Vue app of your own. The value model and factories
(`newStringValue`, `newNumberValue`) live in [`domain/Value.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/domain/Value.ts).

## Displaying values

Render each value through its Property Type's display component; rendering by hand loses Display Attributes and
custom types. `resolveDisplayProperties()` picks the properties a View shows: those its Layout names, or without one
every non-empty property except relation properties that point at
[Dependent Subjects](../glossary.md#dependent-subject):

```javascript
const nw = require( 'ext.sws' );
const registry = nw.SWSServices.getComponentRegistry();

// [ { propertyDefinition, value }, ... ]
const resolvedProperties = nw.resolveDisplayProperties( schema, subject, layout, nw.useSubjectStore().findSubject );
```

```html
<component
	v-for="resolved in resolvedProperties"
	:key="resolved.propertyDefinition.name.toString()"
	:is="registry.getValueDisplayComponent( resolved.propertyDefinition.type )"
	:value="resolved.value"
	:property="resolved.propertyDefinition"
></component>
```

Inside a View the stores hold the Subject, Schema and Layout (`nw.useSubjectStore()`, `useSchemaStore()`,
`useLayoutStore()`); elsewhere, fetch them through the repositories. The props are typed in
[`ValueDisplayContract.ts`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/resources/ext.neowiki/src/components/Value/ValueDisplayContract.ts); `subjectDisplayName( subject )` names a
Subject the way SWS does. Example: [`RedHerbCard.vue`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/resources/RedHerbCard.vue).

## Mounting standalone Vue features

Give a Vue app of your own SWS's Pinia instance and its services. SWS's components inject the services and
read the shared stores, so both calls are required:

```javascript
const nw = require( 'ext.sws' );
const app = Vue.createMwApp( MyComponent );

app.use( nw.SWSExtension.getInstance().getPinia() );
nw.SWSServices.registerServices( app );
app.mount( '#my-mount-point' );
```

Examples: [`resources/createSubject/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/createSubject),
[`resources/editMainSubject/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/editMainSubject), and
[`resources/subjectFinder/`](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb/resources/subjectFinder).

If you mount `nw.SubjectEditor` yourself, call `saveBlocker()` before `getSubjectData()` and hold the save while
it is non-null. It gives the first reason not to save, as a property name and the message to show. Today that is
always a field holding text the widget cannot turn into a Value, which `getSubjectData()` returns as a statement
without a value, so the text would be lost on save. `nw.SubjectEditorDialog` does this for you.

## Authoring in TypeScript

SWS ships no types package ([ADR 24](../adr/024-frontend-extension-mechanism.md)); a published one is deferred
until a consumer needs types without a SWS checkout. Point your `tsconfig.json` `paths` at the barrel source,
which assumes SWS is installed beside your extension as `extensions/SWS`:

```json
"paths": {
	"ext.sws": [ "../SWS/resources/ext.sws/src/public-api" ]
}
```

The runtime specifier then carries types too: `import { ValueType, type PropertyTypeRegistration } from 'ext.sws';`.
Mark the modules SWS already provides as external in your bundler, so you do not ship a second copy and break
the shared store: `ext.sws`, `vue`, `@wikimedia/codex`, `@wikimedia/codex-icons` and `pinia`.
