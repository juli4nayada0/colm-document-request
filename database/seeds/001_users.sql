-- ==============================================================================
-- COLM REGISTRAR DOCUMENT REQUEST AND TRACKING SYSTEM
-- Seed File: 001_users.sql
-- Default development test accounts with bcrypt hashed passwords
-- Note: Default password for all seed accounts is: Password123!
-- ==============================================================================

USE `colm_rdrts_db`;

-- Passwords hashed using standard PHP password_hash('Password123!', PASSWORD_BCRYPT)
INSERT INTO `users` (`user_id`, `username`, `password_hash`, `role`, `is_active`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$4TLgSOE3Y4U7vbnPIj0T2e6lcnV77X5C9Pvlf980bkUDdQVu0FYEi', 'Admin', 1, NULL, NOW(), NOW()),
(2, 'registrar', '$2y$10$4TLgSOE3Y4U7vbnPIj0T2e6lcnV77X5C9Pvlf980bkUDdQVu0FYEi', 'Registrar', 1, NULL, NOW(), NOW()),
(3, 'personnel1', '$2y$10$4TLgSOE3Y4U7vbnPIj0T2e6lcnV77X5C9Pvlf980bkUDdQVu0FYEi', 'Personnel', 1, NULL, NOW(), NOW()),
(4, 'personnel2', '$2y$10$4TLgSOE3Y4U7vbnPIj0T2e6lcnV77X5C9Pvlf980bkUDdQVu0FYEi', 'Personnel', 1, NULL, NOW(), NOW()),
(5, '2026-00001', '$2y$10$4TLgSOE3Y4U7vbnPIj0T2e6lcnV77X5C9Pvlf980bkUDdQVu0FYEi', 'Student', 1, NULL, NOW(), NOW()),
(6, '2026-00002', '$2y$10$4TLgSOE3Y4U7vbnPIj0T2e6lcnV77X5C9Pvlf980bkUDdQVu0FYEi', 'Student', 1, NULL, NOW(), NOW()),
(7, '2026-00003', '$2y$10$4TLgSOE3Y4U7vbnPIj0T2e6lcnV77X5C9Pvlf980bkUDdQVu0FYEi', 'Student', 1, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
