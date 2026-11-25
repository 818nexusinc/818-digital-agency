-- Passkey sign-in (WebAuthn) and one-time email sign-in links.
CREATE TABLE passkeys (
  id {{PK}}, user_id {{FK}} NOT NULL REFERENCES users(id) ON DELETE CASCADE, credential_id VARCHAR(512) NOT NULL UNIQUE, public_key {{TEXT}} NOT NULL, alg INTEGER NOT NULL,
  sign_count BIGINT NOT NULL DEFAULT 0, name VARCHAR(120) NOT NULL, aaguid VARCHAR(36) NULL, transports VARCHAR(120) NULL, backed_up INTEGER NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL, last_used_at DATETIME NULL
){{ENGINE}};
CREATE INDEX ix_passkeys_user ON passkeys(user_id);
CREATE TABLE login_links (
  id {{PK}}, user_id {{FK}} NOT NULL REFERENCES users(id) ON DELETE CASCADE, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used_at DATETIME NULL,
  ip VARCHAR(45) NULL, created_at DATETIME NOT NULL
){{ENGINE}};
