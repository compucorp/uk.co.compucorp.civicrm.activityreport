ALTER TABLE civicrm_pivotreport_cache MODIFY source INT(3) UNSIGNED NULL COMMENT 'Source of the cache row (1 - rebuildcache, 2 - rebuildcachechunk, 3 - PivotReport Admin UI)';
