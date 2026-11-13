-- SWS 0.4.00 D1; source: sql/sws_identity_alias.json; identity administration only.
CREATE TABLE /*_*/sws_identity_alias (
  swsia_hash BLOB NOT NULL,
  swsia_kind BLOB NOT NULL,
  swsia_origin BLOB NOT NULL,
  swsia_legacy TEXT NOT NULL,
  swsia_target BLOB NOT NULL,
  PRIMARY KEY (swsia_hash)
);
