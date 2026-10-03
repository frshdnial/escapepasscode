-- Escape the Passcode: leaderboard database (MySQL 5.7+ / MariaDB 10.3+, Laragon default)
-- Run in HeidiSQL: open a Query tab, paste this, press F9.

CREATE DATABASE IF NOT EXISTS `escape_passcode`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `escape_passcode`;

-- One row per playthrough. Only finished runs with at least one solved case appear on the leaderboard.
CREATE TABLE IF NOT EXISTS `runs` (
  `id`              CHAR(32)     NOT NULL,                          -- private run token
  `player_name`     VARCHAR(32)  NOT NULL,
  `status`          ENUM('in_progress','completed','failed') NOT NULL DEFAULT 'in_progress',
  `current_case`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `case_started_at` BIGINT UNSIGNED  NULL,                          -- unix ms, NULL until the case is opened
  `wrong_attempts`  TINYINT UNSIGNED NOT NULL DEFAULT 0,            -- for the current case
  `cases_solved`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `score`           INT UNSIGNED NOT NULL DEFAULT 0,
  `total_time_ms`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      BIGINT UNSIGNED  NOT NULL,                      -- unix ms
  `finished_at`     BIGINT UNSIGNED  NULL,                          -- unix ms
  PRIMARY KEY (`id`),
  KEY `idx_runs_board` (`status`, `score`, `total_time_ms`, `finished_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
