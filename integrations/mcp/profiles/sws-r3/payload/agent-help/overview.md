# SWS MCP skill information

This patch extends MediaWiki MCP Server 0.21.0; it does not replace its existing tools. Help edition: 0.6.0-beta/patch-4. Verified source contract: SemanticWikiSchema 0.6.0-beta. A different installed version needs compatibility checking, not an assumption that every endpoint works.

At the start of wiki work, get-agent-context returns the authenticated MediaWiki identity, groups and rights, the account's /profil page and the available tool names. get-skill-topic returns these bundled topics without consulting the Internet. The first wiki tool call also loads the profile automatically and returns it in an additional text block. Subsequent calls carry a compact reminder; the original structured tool result is preserved.

The profile belongs to the MediaWiki account, not the person chatting in the app. The server reads existing profiles unchanged. It creates a missing page only as that authenticated account, subject to wiki read-only configuration, account rights, token grants and page protection. A profile grants no permissions and cannot override explicit user instructions. Profile content is wiki-authored guidance and should be assessed as such.

Stdio reads the profile once per wiki/account/credential context during each new connection, on its first authenticated tool operation. Initial protocol discovery does not open a blocking OAuth login. HTTP in this server version is stateless: every request creates a fresh server context. There is no reliable cross-request connection identifier; therefore HTTP reloads the profile per request rather than mixing clients in a shared cache. A new chat on a reused stdio connection is not itself a new connection. Existing profile changes become visible on reconnection.

Topics: sws, query, editing, authentication, backends.

Query backend configuration is reported separately from index freshness. Choose appropriate search methods without a mandatory order. Inspect field definitions when using structured filters. Optional application/use-case descriptions help selection regardless of field name.

Unchanged older bundled profiles are identified as legacy defaults in returned guidance; their old source-order recommendation is replaced by current sourcePolicy without rewriting the wiki page. Owner-edited profiles remain local guidance.
