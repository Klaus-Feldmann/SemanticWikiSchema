# Local verification of 0.1.21

- Input: user's supplied installed 0.1.20 directory, preserved unchanged. Runtime files matched the previous prepared 0.1.20 copy; two documentation files differed.
- PHP standalone suite: 542 tests, 917 assertions passed.
- Existing PHP contracts for page storage, Data-view lookup/export, API identities and Wikitext/refresh passed with isolated MediaWiki stand-ins.
- New technical-rename contract passed for renamed slot, content models, SQL schema definitions, retained numeric namespaces, REST paths, registered hook class files and built frontend.
- PHP runtime/maintenance syntax checks passed for 621 files before the added contract; final syntax checks were repeated after documentation URL adjustments.
- Current/legacy Lua harness passed: 30 module checks, 202 schema-field checks, 24 URL checks and 10 current reader checks.
- Frontend: all 230 test files and 3747 tests passed after absolute test API URLs were updated. TypeScript and production Vite build passed. The existing CSS :has selector warning remains non-fatal.
- Graph: 69 tests passed. Build, bundle-export comparison and both DOM integration checks passed, including configured field links.
- MCP: generated JavaScript syntax and all relative imports checked on three isolated supported runtime copies; baseline check, patch application and repeat application passed. The source patch passed git apply --check on its documented prior patched 0.20.1 baseline.
- Isolated MCP requester checks passed for new REST URLs, OAuth header forwarding, CSRF, write bodies, denied requests and timeout handling.
- MCP TypeScript emission completed. Full MCP semantic typechecking and its full test suite were not repeatable: the cached dependencies were incomplete and npm registry DNS access was unavailable. No live MCP switch was performed.

No current/historical wiki revisions, SQL rows, user accounts or graph-store contents were changed. Fresh MediaWiki installation and real save/job/SMW/graph/MCP behavior must be verified during the separately prepared deployment.
