-- users.username values backed up before dropping the column (2026-07-04 22:56:56)
-- Restore: ALTER TABLE users ADD COLUMN username VARCHAR(80) NULL AFTER email; then run the UPDATEs.
UPDATE users SET username = 'finance' WHERE userID = 16;
UPDATE users SET username = 'student' WHERE userID = 23;
UPDATE users SET username = 'aolf123' WHERE userID = 40;
