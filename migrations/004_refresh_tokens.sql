-- Refresh tokens for JWT rotation (reference).
-- Applied automatically by: php scripts/migrate.php

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    token_hash  VARCHAR(64) NOT NULL UNIQUE,
    active_role VARCHAR(50) NOT NULL,
    expires_at  TEXT NOT NULL,
    revoked_at  TEXT,
    created_at  TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_refresh_tokens_user_id ON refresh_tokens (user_id);
