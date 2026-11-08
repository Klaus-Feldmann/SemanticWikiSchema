# Releasing a SemanticWikiSchema version

1. Verify the source baseline in SEMANTICSWS-VERSION.json, current Wikiseiten revision manifest and the separately versioned MCP patch.
2. Run the checks in publication/VERIFICATION.md. Keep local and live evidence distinct.
3. Review the full Git diff, licenses, dependency/configuration exclusions and generated assets. The extension and Composer installer folder are SemanticWikiSchema. Composer is the supported install path.
4. Publish the reviewed candidate as a new commit on master and a named version branch, preserving the previous history. Never force-push; reconcile a changed remote head before retrying.
5. Create a GitHub release appropriate to the verified support scope and attach the source archive/patch plus SHA256 checksums; installation uses the Composer VCS branch. No release has been created by the preparation scripts.

The upstream Docker/website publishing workflows are archived rather than active. Upstream Docker images/Packagist packages do not contain this fork. Original release instructions are preserved in RELEASING-UPSTREAM.md for reference, not as this fork's release procedure.
