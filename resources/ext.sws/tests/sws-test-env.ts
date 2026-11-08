/**
 * Import this file ONLY in specs that import from @/sws directly.
 * It suppresses the module-level side-effect initializers that run on page load.
 * Must be imported before any import of @/sws.
 */
( window as unknown as { swsTestMode: boolean } ).swsTestMode = true;
