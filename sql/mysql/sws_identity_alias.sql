-- SWS 0.4.00 D1; source: sql/sws_identity_alias.json; identity administration only.
CREATE TABLE /*_*/sws_identity_alias (
  swsia_hash VARBINARY(64) NOT NULL,
  swsia_kind VARBINARY(16) NOT NULL,
  swsia_origin VARBINARY(64) NOT NULL,
  swsia_legacy TEXT NOT NULL,
  swsia_target VARBINARY(32) NOT NULL,
  PRIMARY KEY (swsia_hash)
) /*$wgDBTableOptions*/;
