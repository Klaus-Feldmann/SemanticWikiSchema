-- SWS 0.4.00 D5; source: sql/sws_search_term.json; derived published terms only.
CREATE TABLE /*_*/sws_search_term (
  swst_page_id BIGINT NOT NULL,
  swst_kind BYTEA NOT NULL,
  swst_reference BYTEA NOT NULL,
  swst_language BYTEA NOT NULL,
  swst_role BYTEA NOT NULL,
  swst_key BYTEA NOT NULL,
  PRIMARY KEY (swst_page_id, swst_kind, swst_reference, swst_language, swst_role)
);
CREATE INDEX /*i*/swst_lookup ON /*_*/sws_search_term (swst_kind, swst_key, swst_page_id);
