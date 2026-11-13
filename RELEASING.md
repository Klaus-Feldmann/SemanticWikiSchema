# Publish SWS

1. Archive the supplied installed baseline unchanged and record its file hashes outside the public package.
2. Prepare current installation documentation, built assets, dependency metadata and source notices. Preserve runtime behavior; record any production-code changes separately.
3. Test the installer and the exact archives, run candidate checks and distinguish isolated checks from live confirmation.
4. Publish the reviewed candidate through a branch/PR, wait for checks, then merge without rewriting history.
5. Create the version tag and GitHub release, attach the installer/source ZIPs and SHA256SUMS, and verify the uploaded assets. A pre-1.0 release qualified on a MediaWiki development build is marked as a prerelease.

The first-installation installer does not upgrade SWS or import NeoWiki. Keep the support contact short; do not mix historical migration instructions into the installation path.
