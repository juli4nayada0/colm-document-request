-- ==============================================================================
-- Migration: Add org_chart_members table
-- File: 004_add_org_chart.sql
-- ==============================================================================

USE `colm_rdrts_db`;

CREATE TABLE IF NOT EXISTS `org_chart_members` (
    `member_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `full_name`      VARCHAR(150) NOT NULL,
    `position_title` VARCHAR(200) NOT NULL,
    `department`     VARCHAR(150) NULL,
    -- role_level controls which tier the card is rendered in on the visual chart
    -- vp | registrar | admin_assistant | coordinator | staff
    `role_level`     ENUM('vp','registrar','admin_assistant','coordinator','staff') NOT NULL DEFAULT 'staff',
    `sort_order`     TINYINT UNSIGNED NOT NULL DEFAULT 99,
    `is_active`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ocm_role_level` (`role_level`),
    INDEX `idx_ocm_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed: initial members from the official org chart ──────────────────────────
INSERT INTO `org_chart_members`
    (`full_name`, `position_title`, `department`, `role_level`, `sort_order`, `is_active`)
VALUES
    ('Arnuldo G. Magdaong, LPT, DBA (C)',        'Vice President for Administration and Student Services', NULL,                                              'vp',              1, 1),
    ('Limuel R. Dela Cruz, CFMA, MBA (C)',        'OIC-Registrar (Tertiary)',                               NULL,                                              'registrar',       2, 1),
    ('Rona Mae Biglang-Awa Mendoza, LPT',        'OIC Registrar – Basic Education',                       NULL,                                              'registrar',       3, 1),
    ('Liana Genesis Cruz Flores',                'Administrative Assistant (Basic Education)',             NULL,                                              'admin_assistant', 4, 1),
    ('Czarina Anne E. Ordonio, LPT',             'Scholarship Coordinator',                               NULL,                                              'coordinator',     5, 1),
    ('Shella Marie Venturina',                   'Administrative Assistant (Receiving)',                   NULL,                                              'admin_assistant', 6, 1),
    ('Dianara Pearl Alboleras Cruz, LPT',        'Administrative Assistant (Releasing)',                   NULL,                                              'admin_assistant', 7, 1),
    ('Gilaiza Salvador',                         'Administrative Assistant',                              'HIM / AMD / TED / CSD Departments',               'admin_assistant', 8, 1),
    ('Jerimie Avilledo',                         'Administrative Assistant',                              'Accountancy and Management / Allied Science Dept','admin_assistant', 9, 1),
    ('Michaella Navalta',                        'Administrative Assistant',                              'CJED – Criminal Justice Education Department',    'admin_assistant',10, 1);
