-- SWS 0.4.00 D1; source: sql/sws_id_counter.json; identity administration only.
CREATE TABLE /*_*/sws_id_counter (
  swsic_kind VARBINARY(16) NOT NULL,
  swsic_value BIGINT NOT NULL,
  PRIMARY KEY (swsic_kind)
) /*$wgDBTableOptions*/;
