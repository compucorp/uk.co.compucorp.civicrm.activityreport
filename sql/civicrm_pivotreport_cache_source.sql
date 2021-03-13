-- Add 'source' column into Pivot Cache table.
ALTER TABLE `civicrm_pivotreport_cache` ADD `source` INT(3) UNSIGNED NULL DEFAULT NULL COMMENT 'Source of the cache row (1 - Drush rebuildcache, 2 - Drush rebuildcachecronjob, 3 - PivotReport Admin UI)' AFTER `expired_date`;

-- Drop existing unique indexes to allow having the same group name, path and active values.
ALTER TABLE `civicrm_pivotreport_cache` DROP INDEX `UI_group_path_active`;

-- Add unique index to require unique group name, path, is_active and source values.
ALTER TABLE `civicrm_pivotreport_cache` ADD UNIQUE INDEX `UI_group_path_active_source` (`group_name`, `path`, `is_active`, `source`);
