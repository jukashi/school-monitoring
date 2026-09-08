UPDATE announcements
SET expires_at = NULL
WHERE published_at IS NOT NULL
  AND expires_at IS NOT NULL
  AND expires_at <= published_at;

ALTER TABLE announcements
    ADD CONSTRAINT chk_announcement_window
    CHECK (expires_at IS NULL OR published_at IS NULL OR expires_at > published_at);
