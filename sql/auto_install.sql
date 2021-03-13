DROP TABLE IF EXISTS `civicrm_pivotreport_cache`;
-- /*******************************************************
-- *
-- * civicrm_pivotreport_cache
-- *
-- * Table used to build the cache for pivot reports.
-- *
-- *******************************************************/
CREATE TABLE `civicrm_pivotreport_cache` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Unique PivotReportCache ID',
     `group_name` varchar(32) NOT NULL   COMMENT 'Group name for cache element, useful in separating each available entity cache',
     `path` varchar(255)    COMMENT 'Unique path name for cache element',
     `data` longtext    COMMENT 'Data associated with this path',
     `created_date` timestamp   DEFAULT CURRENT_TIMESTAMP COMMENT 'When was the cache item created',
     `expired_date` timestamp NULL  DEFAULT NULL COMMENT 'When should the cache item expire'
,
        PRIMARY KEY (`id`)

    ,     UNIQUE INDEX `UI_group_path`(
        group_name
      , path
  )
  ,     UNIQUE INDEX `UI_group_path_date`(
        group_name
      , path
      , created_date
  )

)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;
