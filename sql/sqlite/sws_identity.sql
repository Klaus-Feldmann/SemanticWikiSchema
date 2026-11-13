-- SWS 0.4.00 D1; source: sql/sws_identity.json; identity administration only.
CREATE TABLE /*_*/sws_identity (
  swsi_id BLOB NOT NULL,
  swsi_kind BLOB NOT NULL,
  swsi_number BIGINT NOT NULL,
  swsi_request BLOB NOT NULL,
  swsi_owner BLOB DEFAULT '' NOT NULL,
  swsi_used SMALLINT DEFAULT 0 NOT NULL,
  PRIMARY KEY (swsi_id)
);
CREATE UNIQUE INDEX swsi_kind_number ON /*_*/sws_identity (swsi_kind,swsi_number);
CREATE UNIQUE INDEX swsi_request ON /*_*/sws_identity (swsi_request);
