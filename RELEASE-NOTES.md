# Semantic WikiSchema 0.7.0.3

This beta release fixes repeated page refresh jobs. If a running SWS refresh encounters an incomplete or stale update, it no longer queues another refresh for the same page. MediaWiki can apply its normal job retry rules. If a page or dependency changes during rendering, SWS retries once before updating derived data.

Upgrading from 0.7.0.2 only requires replacing the extension code and restarting web and job processes.

[Installation](docs/operations/install/README.md) · [Upgrade from 0.6.0 or 0.7.0.2](docs/operations/upgrade-0.7.0.3.md) · [Deutsch](RELEASE-NOTES.de.md)
