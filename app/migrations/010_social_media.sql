-- Pictures uploaded for social posts. Served publicly from /social/m/{key}.jpg on the site's own (verified) domain,
-- because TikTok only pulls media from domains the app owner has verified.
CREATE TABLE social_media (
  id {{PK}}, skey CHAR(40) NOT NULL UNIQUE, mime VARCHAR(60) NOT NULL, data {{TEXT}} NOT NULL, width INTEGER NULL, height INTEGER NULL,
  bytes INTEGER NOT NULL, created_by VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL
){{ENGINE}};
