# Editing safely

Before changes, read the latest editable entity/revision. Preserve fields not covered by the user's request. Use actual schema field keys and select option IDs. Distinguish source values from missing/unavailable/unsupported states. Never copy a data-view response into a replacement write.

Verify the saved entity/revision directly. Categories, SMW annotations, lists and counters can lag behind saves while jobs run. A stale list is not proof that a save failed; do not repeat a successful write merely to refresh a category. Existing processing-status diagnostics remain available.

Template source, CSS, JSON, Lua modules, redirects and page protection require their respective content-model-aware operations. Do not append wikitext categories to CSS/JSON/Lua. Do not change schemas or launch rebuilds simply to make a display update sooner.

The account profile is the only automatic wiki write introduced by this patch. It uses create-only semantics, does not overwrite existing pages, does not follow redirects and performs a readback after creation. A failed profile bootstrap does not disable otherwise available tools.
