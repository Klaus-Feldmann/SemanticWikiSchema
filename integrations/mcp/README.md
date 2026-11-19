# MediaWiki MCP patch for SWS 0.6.0

Patch for MediaWiki MCP Server **0.21.0**, with the checked SWS 0.5.0 patch or supplied r2–r4 test patches. This is a patch, not a separate MCP server. Upstream MIT attribution is retained.

Stop the MCP connection. Run the preview, then apply:

```sh
python3 anwenden.py --ziel /path/to/node_modules/@professional-wiki/mediawiki-mcp-server --pruefen
python3 anwenden.py --ziel /path/to/node_modules/@professional-wiki/mediawiki-mcp-server --sicherungen /private/backups/mcp --anwenden
```

Only a fully recognized starting state is accepted. A backup is created before changes; authentication and wiki configuration remain unchanged. Restart the app/connection after installation.

Rollback: `python3 anwenden.py --ziel /path/to/package --wiederherstellen /path/to/backup`. Add `--pruefen` to preview rollback. Stop/restart the connection around a rollback too.

The package contains the runtime delta, matching source delta and exact-base file hashes. No rebuild of wiki data is performed. It is not an installer for MediaWiki MCP Server itself.
