-- Phone-based auth migration (reference).
-- For existing databases run: php scripts/migrate_auth_phone.php

-- New users table shape:
-- first_name VARCHAR(100) NOT NULL
-- last_name  VARCHAR(100) NOT NULL
-- phone      VARCHAR(20) NOT NULL UNIQUE
-- (username and full_name removed)
