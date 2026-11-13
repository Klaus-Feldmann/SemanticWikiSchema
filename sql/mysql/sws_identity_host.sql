CREATE TABLE /*_*/sws_identity_host (swsih_id VARBINARY(32) NOT NULL, swsih_page INT UNSIGNED NOT NULL, PRIMARY KEY (swsih_id)) /*$wgDBTableOptions*/;
CREATE INDEX swsih_page ON /*_*/sws_identity_host (swsih_page);
