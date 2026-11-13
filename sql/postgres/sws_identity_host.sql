CREATE TABLE /*_*/sws_identity_host (swsih_id TEXT NOT NULL, swsih_page INT NOT NULL, PRIMARY KEY (swsih_id));
CREATE INDEX swsih_page ON /*_*/sws_identity_host (swsih_page);
