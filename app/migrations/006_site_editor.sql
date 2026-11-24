-- Site editor: text changes made in the back office (per page or on every page), the text each page shows (captured from the editor preview), and brand files such as the logo.
CREATE TABLE site_text (
  id {{PK}}, page VARCHAR(190) NOT NULL, skey CHAR(40) NOT NULL, lang VARCHAR(2) NOT NULL, src {{TEXT}}, value {{TEXT}}, updated_by VARCHAR(190) NULL, updated_at DATETIME NOT NULL
){{ENGINE}};
CREATE UNIQUE INDEX ux_site_text ON site_text(page, skey, lang);
CREATE TABLE site_pages (
  id {{PK}}, page VARCHAR(190) NOT NULL UNIQUE, strings {{TEXT}}, shared_from INTEGER NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL
){{ENGINE}};
CREATE TABLE site_assets (
  id {{PK}}, akey VARCHAR(60) NOT NULL UNIQUE, mime VARCHAR(60) NOT NULL, data {{TEXT}}, updated_at DATETIME NOT NULL
){{ENGINE}};
