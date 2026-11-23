-- Unfinished order forms (saved as visitors move through the steps) and demo-data registry.
CREATE TABLE form_drafts (
  id {{PK}}, token CHAR(32) NOT NULL UNIQUE, form VARCHAR(60) NOT NULL, path VARCHAR(190) NOT NULL, step INTEGER NOT NULL DEFAULT 0, steps INTEGER NOT NULL DEFAULT 0,
  email VARCHAR(190) NULL, name VARCHAR(190) NULL, phone VARCHAR(40) NULL, data {{TEXT}}, status VARCHAR(20) NOT NULL DEFAULT 'open', notes {{TEXT}},
  lead_id {{FK}} NULL, ip VARCHAR(45) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_form_drafts_status ON form_drafts(status);
CREATE TABLE demo_records (
  id {{PK}}, tbl VARCHAR(60) NOT NULL, rid INTEGER NOT NULL, created_at DATETIME NOT NULL
){{ENGINE}};
