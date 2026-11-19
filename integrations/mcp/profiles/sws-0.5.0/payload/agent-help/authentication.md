# Authentication and connection context

The authenticated userinfo response determines the identity, not a configured label or a previous chat. Rights returned by MediaWiki do not prove that an OAuth token includes the required grant. Page protection and private-wiki policies still apply. Anonymous access never creates an account or profile.

OAuth authorization expiry, an unavailable local callback listener and a MediaWiki CSRF/session failure are distinct conditions. Do not attribute all of them to SWS. Return the existing error category and request reauthentication where required. Never place tokens, callback codes, cookies or configuration secrets in profiles, help files or returned context.

One stdio connection owns its own profile cache; wiki/account/credential changes cannot reuse another identity's profile. Concurrent initial calls share one profile read/create operation. HTTP is stateless and reloads the profile for each request. Failed authentication does not establish a usable identity or profile.

The profile is limited to 16000 characters in automatic context, with explicit truncation. The ordinary get-page tool can fetch additional profile content on demand. Bundled help is maintained with the patch/SWS contract; installation does not silently rewrite owner-edited profiles when help is updated.
