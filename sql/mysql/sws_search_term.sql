-- SWS 0.4.00 D5; source: sql/sws_search_term.json; derived published terms only.
CREATE TABLE /*_*/sws_search_term (
  swst_page_id BIGINT NOT NULL,
  swst_kind VARBINARY(16) NOT NULL,
  swst_reference VARBINARY(255) NOT NULL,
  swst_language VARBINARY(64) NOT NULL,
  swst_role VARBINARY(16) NOT NULL,
  swst_key VARBINARY(255) NOT NULL,
  PRIMARY KEY (swst_page_id, swst_kind, swst_reference, swst_language, swst_role)
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/swst_lookup ON /*_*/sws_search_term (swst_kind, swst_key, swst_page_id);
