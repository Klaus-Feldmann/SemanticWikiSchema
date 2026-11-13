# MCP patch for the SWS technical rename

Targets the checked MediaWiki-MCP-Server 0.20.0/0.20.1 runtime and its previous supplied Data-view patch. It registers sws-* tools and swsbase-get-data-view for SemanticWikiSchema/SchemaBaseBridge and uses /sws/v0 and swsbasedataview. Authentication configuration and OAuth files are unchanged. The old neowiki tool directories may remain unused in the external MCP installation; this installer does not delete unrelated files.

First run `python3 anwenden.py /actual/path/to/@professional-wiki/mediawiki-mcp-server` for checks only. Add `--anwenden` to back up and install. The patch rejects unknown target versions/hashes. Apply while the client is stopped, coordinate the wiki endpoint switch, and restart the client. Use a fresh connection so the tool catalog is rediscovered.

The source patch applies to the previously supplied 0.20.1 source tree, not an unmodified upstream source checkout. Quellcode/ contains the changed/new source files. The generated runtime includes the full renamed tool group, not just its index.

Verification: TypeScript emission completed; generated JavaScript syntax and relative import resolution were checked against the supported runtime. Isolated requester checks covered the new REST base, OAuth, CSRF, payloads and error handling. The full MCP typecheck/test run could not be repeated because required npm dependencies were unavailable locally and registry access failed. This is a local candidate, not a verified live MCP deployment. No secrets are included.
