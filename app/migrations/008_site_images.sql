-- In-page editor: pictures the owner replaced on the website (keyed by the original picture address), plus a social media post log.
CREATE TABLE site_images (
  id {{PK}}, skey CHAR(40) NOT NULL UNIQUE, src VARCHAR(500) NOT NULL, mime VARCHAR(60) NOT NULL, data {{TEXT}}, alt VARCHAR(300) NULL,
  width INTEGER NULL, height INTEGER NULL, updated_by VARCHAR(190) NULL, updated_at DATETIME NOT NULL
){{ENGINE}};
CREATE TABLE social_posts (
  id {{PK}}, network VARCHAR(30) NOT NULL, body {{TEXT}}, link VARCHAR(500) NULL, image_url VARCHAR(500) NULL, status VARCHAR(20) NOT NULL,
  external_id VARCHAR(190) NULL, error VARCHAR(500) NULL, created_by VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL
){{ENGINE}};
CREATE TABLE form_findings (
  id {{PK}}, service_slug VARCHAR(60) NOT NULL, jurisdiction VARCHAR(10) NOT NULL DEFAULT '', field VARCHAR(190) NULL, issue VARCHAR(40) NOT NULL,
  detail {{TEXT}}, suggestion {{TEXT}}, source_url VARCHAR(500) NULL, severity VARCHAR(20) NOT NULL DEFAULT 'medium', status VARCHAR(20) NOT NULL DEFAULT 'open',
  found_by VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, decided_by VARCHAR(190) NULL, decided_at DATETIME NULL
){{ENGINE}};
CREATE INDEX ix_form_findings ON form_findings(status, service_slug);
