-- SWS 0.4.00 D1; source: sql/sws_identity_alias.json; identity administration only.
CREATE TABLE /*_*/sws_identity_alias (
  swsia_hash TEXT NOT NULL,
  swsia_kind TEXT NOT NULL,
  swsia_origin TEXT NOT NULL,
  swsia_legacy TEXT NOT NULL,
  swsia_target TEXT NOT NULL,
  PRIMARY KEY (swsia_hash)
);
