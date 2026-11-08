# Codex Styling Policy

Date: 2026-02-18

Status: Accepted

## Context

SWS uses [Codex](https://doc.wikimedia.org/codex/latest/) as its design system. Codex provides accessibility,
consistency with the broader MediaWiki ecosystem, and skin-agnosticism. However, Codex makes conservative visual choices
because it must support many languages, scripts, reading directions, low-bandwidth environments, and multiple skins.
This can make SWS's UI look plain compared to more opinionated UI libraries.

When something in SWS's UI doesn't look good, the fix can fall into one of three categories:

1. **Layout and composition** — Improving spacing, grouping, and information hierarchy around Codex components
2. **Component selection** — Using a different or more appropriate Codex component
3. **Component styling** — Overriding the visual appearance of Codex components themselves

Categories 1 and 2 are unambiguously SWS's responsibility. Category 3 is where a decision is needed.

## Decision

SWS uses Codex components without overriding their styles. We invest in layout, composition, and component
selection to make SWS's UI as good as possible within Codex's constraints.

Visual customization beyond what Codex provides happens at the skin layer, not the extension layer. Skins exist to
control visual appearance, and Codex is designed to be themed by skins. If a wiki has a skin with customized Codex
styles, SWS inherits those styles automatically — but only if SWS itself does not override Codex components.

## Consequences

* SWS's UI stays consistent with the rest of the MediaWiki ecosystem on any given wiki
* SWS works correctly with any skin that themes Codex, without per-skin adaptation in the extension
* Visual improvements made at the skin layer benefit all Codex-based extensions, not just SWS
* Some UI polish that could be achieved via component overrides is deferred to the skin layer
* SWS developers focus styling effort on layout and component selection rather than CSS overrides

## Alternatives Considered

### Allow scoped Codex overrides in SWS

Override specific Codex component styles within SWS's own CSS. This would allow more visual polish without depending
on skins, but creates a maintenance burden (overrides can break on Codex updates), conflicts with skin theming, and
couples SWS to Codex implementation details.
