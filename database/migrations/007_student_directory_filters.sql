-- Add education-level and section fields used by the student directories.
USE `colm_rdrts_db`;

SET @education_level_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'enrollment_records'
      AND column_name = 'education_level'
);
SET @education_level_sql = IF(
    @education_level_exists = 0,
    'ALTER TABLE `enrollment_records` ADD COLUMN `education_level` VARCHAR(30) NULL AFTER `program`',
    'SELECT 1'
);
PREPARE education_level_statement FROM @education_level_sql;
EXECUTE education_level_statement;
DEALLOCATE PREPARE education_level_statement;

SET @section_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'enrollment_records'
      AND column_name = 'section'
);
SET @section_sql = IF(
    @section_exists = 0,
    'ALTER TABLE `enrollment_records` ADD COLUMN `section` VARCHAR(80) NULL AFTER `year_level`',
    'SELECT 1'
);
PREPARE section_statement FROM @section_sql;
EXECUTE section_statement;
DEALLOCATE PREPARE section_statement;

UPDATE `enrollment_records`
SET `education_level` = CASE
    WHEN LOWER(`program`) LIKE '%junior high%' OR LOWER(`program`) LIKE '%grade 7%'
        OR LOWER(`program`) LIKE '%grade 8%' OR LOWER(`program`) LIKE '%grade 9%'
        OR LOWER(`program`) LIKE '%grade 10%' THEN 'Junior High School'
    WHEN LOWER(`program`) LIKE '%senior high%' OR LOWER(`program`) LIKE '%stem%'
        OR LOWER(`program`) LIKE '%abm%' OR LOWER(`program`) LIKE '%humss%'
        OR LOWER(`program`) LIKE '%gas%' THEN 'Senior High School'
    ELSE 'College'
END
WHERE `education_level` IS NULL OR `education_level` = '';

SET @education_index_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'enrollment_records'
      AND index_name = 'idx_enrollment_education_level'
);
SET @education_index_sql = IF(@education_index_exists = 0,
    'CREATE INDEX `idx_enrollment_education_level` ON `enrollment_records` (`education_level`)',
    'SELECT 1');
PREPARE education_index_statement FROM @education_index_sql;
EXECUTE education_index_statement;
DEALLOCATE PREPARE education_index_statement;

SET @section_index_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'enrollment_records'
      AND index_name = 'idx_enrollment_section'
);
SET @section_index_sql = IF(@section_index_exists = 0,
    'CREATE INDEX `idx_enrollment_section` ON `enrollment_records` (`section`)',
    'SELECT 1');
PREPARE section_index_statement FROM @section_index_sql;
EXECUTE section_index_statement;
DEALLOCATE PREPARE section_index_statement;