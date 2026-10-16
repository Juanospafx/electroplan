-- Migration: 20261012_sso_identity_links.sql
-- Description: Additive migration creating exact unique provider plus hub_user_id links to users.id
-- Target: MariaDB 10.11+
--
-- =========================================================================
-- ROLLBACK INSTRUCTIONS (Documented Rollback):
-- To undo this migration, run the following SQL statement:
--
-- DROP TABLE IF EXISTS `sso_identity_links`;
-- =========================================================================

CREATE TABLE IF NOT EXISTS `sso_identity_links` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `provider` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `hub_user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sso_identity_links_provider_hub_user` (`provider`, `hub_user_id`),
  KEY `idx_sso_identity_links_user_id` (`user_id`),
  KEY `idx_sso_identity_links_lookup` (`provider`, `hub_user_id`, `active`),
  CONSTRAINT `fk_sso_identity_links_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
