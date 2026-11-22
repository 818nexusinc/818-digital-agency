-- Serverless support: database sessions and database file storage
CREATE TABLE sessions (
  sid VARCHAR(128) NOT NULL PRIMARY KEY, data {{TEXT}} NOT NULL, updated_at INTEGER NOT NULL
){{ENGINE}};
CREATE INDEX ix_sessions_updated ON sessions(updated_at);
CREATE TABLE document_blobs (
  document_id {{FK}} NOT NULL PRIMARY KEY REFERENCES documents(id) ON DELETE CASCADE, content {{TEXT}} NOT NULL
){{ENGINE}};
