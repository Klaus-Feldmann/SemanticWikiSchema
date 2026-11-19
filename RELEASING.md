# Publish SWS

1. Preserve the supplied live-confirmed source outside the repository. Update release metadata, installation/upgrade instructions and attribution without changing accepted runtime behavior.
2. Run installer, package and source checks. Generate the complete distribution SHA256 manifest from the staged Git archive. Commit the release source.
3. Build the source, first-installation, upgrade and MCP patch archives with `python3 scripts/build_release.py --output /path/outside/repository`. Test the extracted packages and rollback.
4. Publish a branch/PR, wait for required checks, then merge without rewriting history.
5. Tag the merged commit `vVERSION`, rebuild the archives from that commit and publish the GitHub release with all archives and SHA256SUMS. Verify the uploaded files. A pre-1.0 release qualified on a MediaWiki development build is marked as a prerelease.

The first-installation helper does not upgrade existing installations. The folder upgrader refuses Composer-managed installations; those use the separate Composer path in the upgrade guide.
