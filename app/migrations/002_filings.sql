-- Corporate registry filings (Government Filing Agent)
CREATE TABLE filings (
  id {{PK}}, number VARCHAR(30) NOT NULL UNIQUE, project_id {{FK}} NULL REFERENCES projects(id) ON DELETE SET NULL,
  customer_id {{FK}} NOT NULL REFERENCES customers(id), jurisdiction CHAR(3) NOT NULL, filing_type VARCHAR(40) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'awaiting_details', data {{TEXT}}, validation {{TEXT}}, data_hash CHAR(64) NULL,
  connector VARCHAR(40) NULL, mode VARCHAR(20) NULL, submission_ref VARCHAR(120) NULL, registry_number VARCHAR(80) NULL,
  rejected_reason {{TEXT}}, attempts INTEGER NOT NULL DEFAULT 0, next_check_at DATETIME NULL,
  auth_signer VARCHAR(190) NULL, auth_capacity VARCHAR(60) NULL, auth_ip VARCHAR(45) NULL, auth_ua VARCHAR(255) NULL, auth_hash CHAR(64) NULL, authorized_at DATETIME NULL,
  submitted_at DATETIME NULL, completed_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
){{ENGINE}};
CREATE INDEX ix_filings_status ON filings(status);

CREATE TABLE filing_events (
  id {{PK}}, filing_id {{FK}} NOT NULL REFERENCES filings(id) ON DELETE CASCADE, kind VARCHAR(40) NOT NULL,
  detail {{TEXT}}, actor VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL
){{ENGINE}};
