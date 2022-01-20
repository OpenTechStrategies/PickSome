-- MySQL/SQLite schema for the Deliberation extension

CREATE TABLE /*_*/Deliberation (
  id int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  page_id int(11) NOT NULL default 0,
  user_id int(11) NOT NULL default 0
) /*$wgDBTableOptions*/;

