# Frontend Extension Mechanism

Date: 2026-04-21

Status: Accepted

## Context

SWS's frontend is built with TypeScript and Vue. The PHP side has a clean extension mechanism. The
`SWSRegistration` hook hands extensions a registrar, with which they add Property Types, Neo4j value builders and
Page Property Providers. The frontend has no equivalent. Property Types and their Vue components are hardcoded in core.
An extension cannot add a frontend Property Type, or reuse SWS's UI, without editing SWS source and rebuilding.

There are two related needs:

1. Extensions register frontend behaviour into SWS, such as a Property Type's display, input and attributes-editor
   components.
2. Extensions consume SWS's frontend as building blocks: Vue components, the domain model, frontend stores,
   repositories and services.

Both publish a stable JavaScript surface from `ext.sws`. They are the same work in opposite directions, so we treat
them as one effort.

We want several things, in rough priority order:

* Extensions reuse SWS's frontend as building blocks.
* SWS core stays in TypeScript.
* Easy development for extension authors.
* Easy deployment for sysadmins.
* TypeScript types for extension authors, optional, so extensions can also be written in plain JavaScript.
* Consistency with standard MediaWiki patterns.
* Consistency with industry patterns.
* Simplicity.

Some of these conflict.

MediaWiki's ResourceLoader compiles `.vue` Single File Components server-side. A JS-only author can therefore ship real
Vue components with no build step. The dialect is restricted; see the
[MediaWiki Vue.js docs](https://www.mediawiki.org/wiki/Vue.js). So the choice of option is not about whether JS authors
can write extensions. They always can. It is about what the TypeScript author's experience looks like.

Every option needs two pieces of shared groundwork. First, we expose a frontend API surface for extensions to build on.
The bundle previously only mounted apps and exported nothing. Second, we add a frontend registration mechanism. Given
those, three options sit on the Pareto frontier:

* **Option A: MediaWiki-native.** A public API barrel exported from the `ext.sws` ResourceLoader module, plus a
  frontend registration hook. Plain JS and SFC authoring needs no build step. TypeScript authors point their `tsconfig`
  `paths` at SWS's source for types, which are erased at compile time. The barrel's exports are an explicit public
  API boundary.
* **Option B: source-level merge.** Extensions ship `.ts` and `.vue` source. SWS's build discovers and bundles it
  into one output. Extensions import SWS internals directly.
* **Option C: npm package.** SWS is published to npm as a library with source and types. The runtime stays the
  shared `ext.sws` ResourceLoader module.

## Decision

We adopt Option A.

### What SWS ships

SWS ships a public API barrel at `resources/ext.sws/src/public-api.ts`. Any ResourceLoader module that declares
a dependency on `ext.sws` reaches it through `require( 'ext.sws' )`. The barrel re-exports components, domain
objects, stores, repositories and services for reuse as building blocks.

SWS also ships a frontend registration hook, `mw.hook( 'sws.registration' )`. It hands subscribers a
`FrontendRegistrar`. A call to `registrar.registerPropertyType()` takes a plain object of type
`PropertyTypeRegistration`. The object holds the display, input and attributes components, plus the type's behaviour.
An internal `PropertyTypeAdapter` extends SWS's `BasePropertyType` and forwards each method to the plain object. A
change to the Property Type contract breaks the adapter's compilation until handled, so the JavaScript registration
shape and the TypeScript contract stay aligned.

Extensions register Property Types and consume SWS's frontend in plain JavaScript, with no build step.

### TypeScript types

TypeScript authoring is documentation, not a deliverable. A TypeScript author points their `tsconfig` `paths` at
SWS's source for types. SWS ships nothing extra for this. It only needs documenting.

### Rejected and deferred options

We reject Option B, the unified build. It defeats the plugin model and breaks standard MediaWiki extension isolation.

We defer Option C, the published npm package. We do not need it until a consumer cannot check out SWS next to its
own code.

### A broad public API for now

The barrel re-exports almost everything for now. An explicit 0.x alpha contract states that anything in it can change
without notice. SWS is pre-production, so a breaking change costs a rebuild rather than a data migration. Real usage
is better evidence for what to keep public than guessing up front. The cost is that internal refactors become visible
once a consumer depends on a symbol. We will narrow the surface before either SWS or its extensions reach
production.

## Consequences

* Extension authors need no build step. They write plain JS and server-compiled SFCs. Sysadmins need no extra build at
  install time, so deployment stays standard.
* Extensions reuse SWS's frontend through the `ext.sws` barrel. The services and the frontend stores
  ([ADR 16](016-frontend-state-management.md)) are shared singletons. An extension that mutates a store re-renders
  core's views reactively, without a reload.
* JS authors get no compile-time type safety at the registration boundary. Documentation and runtime tests enforce the
  contract instead. The RedHerb example extension registers through the hook and is exercised end-to-end, so it acts as
  a living contract test. TypeScript authors opt into type safety; there is none unless they do.
* Standard MediaWiki extension isolation holds. Extensions are ordinary ResourceLoader modules that depend on
  `ext.sws`. They build and release on their own cadence.
* Registering a Property Type spans both layers. The PHP `SWSRegistration` hook adds the backend type and the JS
  `sws.registration` hook adds the frontend. Authors do both.
* Two things remain before production. We narrow the barrel to an intentional surface. We do a layering review, because
  it currently re-exports Persistence and Infrastructure that extensions should not reach.
* The same `FrontendRegistrar` and `sws.registration` mechanism is the wiring point for registering other pluggable
  things. View Type registration ([ADR 18](018-views.md)) rides it next. Today `registerPropertyType` is the only
  registrar method, and `infobox` is the only built-in View Type.

## Alternatives Considered

### Option B: source-level merge

Pros:

* Best TypeScript ergonomics. SWS's source is in the tree, with no path setup.
* Unrestricted reuse of internals.

Cons:

* No public API boundary. Either internal refactors break extensions, or everything becomes public in practice.
* Every extension is forced onto SWS's TypeScript and bundler toolchain.
* SWS must be rebuilt to add any extension. This breaks independent release cadence and MediaWiki extension
  isolation.
* JS-only, gadget-style extensions cannot participate.

### Option C: published npm package

Pros:

* Industry-standard TypeScript ergonomics. An author runs `npm install`, gets IntelliSense, and runs tests without
  MediaWiki.
* A clean, versioned public API.
* Consumers can use SWS's TypeScript library outside MediaWiki. Validation and import tooling are examples.

Cons:

* A package must be published, and its version kept aligned with the installed `ext.sws` module. Much of that can be
  automated.
* Added release process and infrastructure.

We defer Option C rather than reject it. It runs against the same `ext.sws` module as Option A, so we can add it
later without reworking extensions, once a consumer needs the library without a SWS checkout.

### Curate a narrow public API up front

Instead of exposing the barrel broadly and narrowing later, we could define a minimal intentional surface now.

We rejected this for the pre-production phase. We do not yet know what extensions will use. Predicting wrong wastes
effort on both sides. Breaking changes are cheap while there is no production data. The alpha contract sets
expectations, and we will curate before production.

## Related

* [ADR 16: Frontend State Management](016-frontend-state-management.md). The frontend stores reused across apps through
  the barrel.
* [ADR 18: Views](018-views.md). View Types, whose registration will ride the same mechanism.
* [extending.md](../extending/extending.md). The user-facing reference for these extension points.
* Issue [#686](https://github.com/ProfessionalWiki/NeoWiki/issues/686) weighed the options. PR
  [#754](https://github.com/ProfessionalWiki/NeoWiki/pull/754) implemented the mechanism.
* Follow-ups: a public PHP API surface for extensions consuming SWS services in issue
  [#789](https://github.com/ProfessionalWiki/NeoWiki/issues/789), and exposing View Type registration through the same
  hook in issue [#919](https://github.com/ProfessionalWiki/NeoWiki/issues/919).
