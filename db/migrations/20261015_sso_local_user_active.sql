-- Migration: 20261015_sso_local_user_active.sql
-- Description: Additive migration adding boolean-compatible active column to users table with safe default for existing users
-- Target: MariaDB 10.11+
--
-- =========================================================================
-- ROLLBACK INSTRUCTIONS (Documented Rollback):
-- To undo this migration, run the following SQL statement:
--
-- ALTER TABLE `users` DROP COLUMN IF EXISTS `active`;
-- =========================================================================

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `active` tinyint(1) NOT NULL DEFAULT 1;
