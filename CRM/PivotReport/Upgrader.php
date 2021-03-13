<?php

/**
 * Collection of upgrade steps.
 */
class CRM_PivotReport_Upgrader extends CRM_PivotReport_Upgrader_Base {

  /**
   * List of scheduled jobs provided by the extension.
   *
   * @var array
   */
  private $scheduledJobs = array(
    'rebuildcachechunk',
  );

  /**
   * Installation logic.
   *
   * @return boolean
   */
  public function install() {
    $this->upgrade_0001();
    $this->upgrade_0003();
    $this->upgrade_0006();
    $this->upgrade_0007();
    $this->upgrade_0009();
    $this->upgrade_0010();
    $this->upgrade_0011();
    $this->upgrade_0012();

    return TRUE;
  }

  /**
   * Uninstallation logic.
   *
   * @return boolean
   */
  public function uninstall()
  {
    $this->deleteScheduledJobs();

    $pivotID = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'pivotreport', 'id', 'name');
    CRM_Core_DAO::executeQuery("DELETE FROM `civicrm_navigation` WHERE parent_id = $pivotID");
    CRM_Core_DAO::executeQuery("DELETE FROM `civicrm_navigation` WHERE name IN ('pivotreport', 'Pivot Report Config')");
    CRM_Core_BAO_Navigation::resetNavigation();

    return TRUE;
  }

  /**
   * Install Pivot Report link under Reports menu.
   *
   * @return boolean
   */
  public function upgrade_0001() {
    CRM_Core_DAO::executeQuery("DELETE FROM `civicrm_navigation` WHERE name = 'pivotreport' and parent_id IS NULL");
    $reportsNavId = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Reports', 'id', 'name');
    $navigation = new CRM_Core_DAO_Navigation();
    $params = array (
        'domain_id'  => CRM_Core_Config::domainID(),
        'label'      => ts('Pivot Report'),
        'name'       => 'pivotreport',
        'url'        => 'civicrm/activity-report',
        'parent_id'  => $reportsNavId,
        'weight'     => 0,
        'permission' => 'access CiviCRM pivot table reports',
        'separator'  => 1,
        'is_active'  => 1
    );
    $navigation->copyValues($params);
    $navigation->save();
    CRM_Core_BAO_Navigation::resetNavigation();

    return TRUE;
  }

  /**
   * Installs Pivot Report config db table (if does not exist).
   *
   * @return TRUE
   */
  public function upgrade_0003() {
    $this->executeSqlFile('sql/civicrm_pivotreport_config_install.sql');

    return TRUE;
  }

  /**
   * Executes auto_install SQL script.
   *
   * @return boolean
   */
  public function upgrade_0005() {
    $this->executeSqlFile('sql/auto_install.sql');

    return TRUE;
  }

  /**
   * Installs Pivot Report Config page link into Administer menu.
   *
   * @return bool
   */
  public function upgrade_0006() {
    $administerNavId = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Administer', 'id', 'name');

    $navigation = new CRM_Core_DAO_Navigation();
    $params = array (
        'domain_id'  => CRM_Core_Config::domainID(),
        'label'      => ts('Pivot Report Config'),
        'name'       => 'Pivot Report Config',
        'url'        => 'civicrm/pivot-report-config',
        'parent_id'  => $administerNavId,
        'weight'     => CRM_Core_BAO_Navigation::calculateWeight($administerNavId),
        'permission' => 'Admin Pivot Report',
        'is_active'  => 1
    );
    $navigation->copyValues($params);
    $navigation->save();

    CRM_Core_BAO_Navigation::resetNavigation();

    return TRUE;
  }

  /**
   * Add menu items for each enabled entity as a sub item of Pivot Report.
   *
   * @return bool
   */
  public function upgrade_0007() {
    $reportsNavId = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'Reports', 'id', 'name');

    CRM_Core_DAO::executeQuery("DELETE FROM `civicrm_navigation` WHERE name = 'pivotreport'");
    $this->createNavigationItem(array(
      'domain_id'  => CRM_Core_Config::domainID(),
      'label'      => ts('Pivot Report'),
      'name'       => 'pivotreport',
      'url'        => '',
      'parent_id'  => $reportsNavId,
      'weight'     => 0,
      'permission' => 'access CiviCRM pivot table reports',
      'has_separator'  => 1,
      'is_active'  => 1
    ));

    $entities = CRM_PivotReport_Entity::getSupportedEntities();
    $pivotID = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'pivotreport', 'id', 'name');
    $weight = 0;

    foreach ($entities as $currentItem) {
      $itemName = strtolower($currentItem) . '-report';
      CRM_Core_DAO::executeQuery("DELETE FROM `civicrm_navigation` WHERE name = '$itemName'");
      $this->createNavigationItem(array(
        'domain_id'  => CRM_Core_Config::domainID(),
        'label'      => ts($currentItem),
        'name'       => $itemName,
        'url'        => 'civicrm/' . strtolower($currentItem) . '-report',
        'parent_id'  => $pivotID,
        'weight'     => $weight++,
        'permission' => 'access CiviCRM pivot table reports',
        'has_separator'  => 0,
        'is_active'  => 1
      ));
    }

    CRM_Core_BAO_Navigation::resetNavigation();

    return TRUE;
  }

  /**
   * Changes 'Pivot Report Config' label to 'Pivot Report Configuration'.
   * @return bool
   */
  public function upgrade_0008() {
    $configItem = civicrm_api3('Navigation', 'get', array(
      'sequential' => 1,
      'name' => 'Pivot Report Config',
    ));

    if (!empty($configItem['id'])) {
      civicrm_api3('Navigation', 'create', array(
        'id' => $configItem['id'],
        'label' => ts('Pivot Report Configuration'),
      ));

      CRM_Core_BAO_Navigation::resetNavigation();
    }

    return TRUE;
  }

  /**
   * Adds 'is_active' field into 'civicrm_pivotreport_cache' table and
   * sets its value to '1' for all existing cache rows.
   *
   * @return boolean
   */
  public function upgrade_0009() {
    $this->executeSqlFile('sql/civicrm_pivotreport_cache_is_active.sql');

    return TRUE;
  }

  /**
   * Adds 'source' field into 'civicrm_pivotreport_cache' table.
   *
   * @return boolean
   */
  public function upgrade_0010() {
    $this->executeSqlFile('sql/civicrm_pivotreport_cache_source.sql');

    return TRUE;
  }

  /**
   * Updates 'source' field's comment and deletes 'cron_job_status' entry.
   * 'cron_job_status' is now named 'chunk_status'.
   *
   * @return boolean
   */
  public function upgrade_0011() {
    $this->executeSqlFile('sql/civicrm_pivotreport_cache_source_comment.sql');
    $this->executeSqlFile('sql/delete_cron_job_status_entry.sql');

    return TRUE;
  }

  /**
   * Recreates scheduled jobs.
   *
   * @return boolean
   */
  public function upgrade_0012() {
    $this->deleteScheduledJob('rebuildcache');
    $this->createScheduledJobs();

    return TRUE;
  }

  /**
   * Rename pivot cache table.
   *
   * @return boolean
   */
  public function upgrade_0013() {
    $this->renameCacheTable();

    return TRUE;
  }

  /**
   * Rename civicrm_pivotreportcache table
   * and drop the log table if it exist.
   */
  private function renameCacheTable() {
    if (CRM_Core_DAO::checkTableExists('civicrm_pivotreportcache')) {
      CRM_Core_DAO::executeQuery("RENAME TABLE civicrm_pivotreportcache TO civicrm_pivotreport_cache");
      CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS log_civicrm_pivotreportcache;");
    }
  }

  /**
   * Creates new menu item using provided parameters.
   *
   * @param array $params
   */
  private function createNavigationItem($params) {
    $navigation = new CRM_Core_DAO_Navigation();
    $navigation->copyValues($params);
    $navigation->save();
  }

  /**
   * Logic which is executing when enabling extension.
   *
   * @return boolean
   */
  public function onEnable() {
    $this->setScheduledJobsIsActive(TRUE);

    $pivotID = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'pivotreport', 'id', 'name');
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_navigation
      SET is_active = 1
      WHERE name IN ('pivotreport', 'Pivot Report Config')
      OR parent_id = $pivotID
    ");
    CRM_Core_BAO_Navigation::resetNavigation();

    return TRUE;
  }

  /**
   * Logic which is executing when disabling extension.
   *
   * @return boolean
   */
  public function onDisable() {
    $this->setScheduledJobsIsActive(FALSE);

    $pivotID = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_Navigation', 'pivotreport', 'id', 'name');
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_navigation
      SET is_active = 0
      WHERE name IN ('pivotreport', 'Pivot Report Config')
      OR parent_id = $pivotID
    ");
    CRM_Core_BAO_Navigation::resetNavigation();

    return TRUE;
  }

  /**
   * Returns an ID of schedule job with specified action.
   * Returns NULL if the job does not exist.
   *
   * @param string $action
   *
   * @return int|NULL
   */
  private function getScheduledJobId($action) {
    $result = civicrm_api3('Job', 'get', array(
      'sequential' => 1,
      'api_entity' => 'PivotReport',
      'api_action' => $action,
      'limit' => 1,
    ));

    if (empty($result['id'])) {
      return NULL;
    }

    return $result['id'];
  }

  /**
   * Sets schedule job active state.
   *
   * @param string $action
   *
   * @param bool $isActive
   */
  private function setScheduledJobIsActive($action, $isActive) {
    $id = $this->getScheduledJobId($action);
    if (!$id) {
      return NULL;
    }

    civicrm_api3('Job', 'update', array(
      'id' => $id,
      'is_active' => (int) $isActive,
    ));
  }

  /**
   * Sets all scheduled jobs as active.
   *
   * @param bool $isActive
   */
  private function setScheduledJobsIsActive($isActive) {
    foreach ($this->scheduledJobs as $job) {
      $this->setScheduledJobIsActive($job, $isActive);
    }
  }

  /**
   * Creates a scheduled job entries.
   */
  private function createScheduledJobs() {
    if (!$this->getScheduledJobId('rebuildcachechunk')) {
      civicrm_api3('Job', 'create', array(
        'run_frequency' => 'Hourly',
        'name' => 'Pivot Report Cache Build (chunk)',
        'description' => 'Job to create Pivot Report cache partials. Depending on the amount of records, it might take numbers of runs to complete a new report cache.',
        'api_entity' => 'PivotReport',
        'api_action' => 'rebuildcachechunk',
        'is_active' => 0,
      ));
    }
  }

  /**
   * Deletes schedule job by specified action.
   *
   * @param string $action
   */
  private function deleteScheduledJob($action) {
    $id = $this->getScheduledJobId($action);
    if (!$id) {
      return NULL;
    }

    civicrm_api3('Job', 'delete', array(
      'id' => $id,
    ));
  }

  /**
   * Deletes scheduled jobs.
   */
  private function deleteScheduledJobs() {
    foreach ($this->scheduledJobs as $job) {
      $this->deleteScheduledJob($job);
    }
  }
}
