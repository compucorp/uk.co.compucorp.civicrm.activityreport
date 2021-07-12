<?php

require_once 'pivotreport.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function pivotreport_civicrm_config(&$config) {
  _pivotreport_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function pivotreport_civicrm_xmlMenu(&$files) {
  _pivotreport_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function pivotreport_civicrm_install() {
  _pivotreport_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function pivotreport_civicrm_uninstall() {
  _pivotreport_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function pivotreport_civicrm_enable() {
  _pivotreport_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function pivotreport_civicrm_postInstall() {
  _pivotreport_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function pivotreport_civicrm_disable() {
  _pivotreport_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function pivotreport_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _pivotreport_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function pivotreport_civicrm_managed(&$entities) {
  _pivotreport_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function pivotreport_civicrm_caseTypes(&$caseTypes) {
  _pivotreport_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function pivotreport_civicrm_angularModules(&$angularModules) {
_pivotreport_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function pivotreport_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _pivotreport_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implementation of hook_civicrm_pageRun
 */
function pivotreport_civicrm_pageRun($page) {
  if (get_class($page) === 'CRM_PivotReport_Page_PivotReportAdmin') {
    CRM_Core_Resources::singleton()
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'packages/bootstrap/bootstrap.min.js', 1, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'js/PivotReport.Admin.js', 2, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'js/PivotReport.Preloader.js', 3, 'html-header');
    CRM_Core_Resources::singleton()
      ->addStyleFile('uk.co.compucorp.civicrm.pivotreport', 'packages/bootstrap/bootstrap.min.css', 1)
      ->addStyleFile('uk.co.compucorp.civicrm.pivotreport', 'css/style_admin.css', 2);
  }

  if (get_class($page) === 'CRM_PivotReport_Page_PivotReport') {
    CRM_Core_Resources::singleton()
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'packages/d3/d3.min.js', 1, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'packages/c3/c3.min.js', 1, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'packages/pivottable/pivot.min.js', 1, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'packages/pivottable/c3_renderers.min.js', 2, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'packages/moment.js/moment.min.js', 2, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'packages/bootstrap/bootstrap.min.js', 3, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'packages/bootstrap-sweetalert/sweetalert.min.js', 4, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'js/PivotReport.Preloader.js', 5, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'js/PivotReport.Filter.js', 5, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'js/PivotReport.Dates.js', 6, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'js/PivotReport.Config.js', 7, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'js/FileSaver.js', 8, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'js/PivotReport.Export.js', 9, 'html-header')
      ->addScriptFile('uk.co.compucorp.civicrm.pivotreport', 'js/PivotReport.PivotTable.js', 10, 'html-header');
    CRM_Core_Resources::singleton()
      ->addStyleFile('uk.co.compucorp.civicrm.pivotreport', 'packages/c3/c3.min.css', 1)
      ->addStyleFile('uk.co.compucorp.civicrm.pivotreport', 'packages/pivottable/pivot.min.css', 2)
      ->addStyleFile('uk.co.compucorp.civicrm.pivotreport', 'packages/bootstrap/bootstrap.min.css', 3)
      ->addStyleFile('uk.co.compucorp.civicrm.pivotreport', 'packages/bootstrap-sweetalert/sweetalert.min.css', 4)
      ->addStyleFile('uk.co.compucorp.civicrm.pivotreport', 'css/style.css', 5);
  }
}

/**
 * Implementation of hook_civicrm_permission
 *
 * @param array $permissions
 * @return void
 */
function pivotreport_civicrm_permission(&$permissions) {
  $prefix = ts('CiviCRM Reports') . ': '; // name of extension or module
  $permissions += array(
    'access CiviCRM pivot table reports' => $prefix . ts('access CiviCRM pivot table reports'),
    'Admin Pivot Report' => $prefix . ts('Admin Pivot Report'),
  );
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * @param array $entityTypes
 *   List of entity types
 */
function pivotreport_civicrm_entityTypes(&$entityTypes) {
  _pivotreport_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_alterLogTables().
 *
 * Exclude tables from logging tables since they hold mostly temp data.
 */
function pivotreport_civicrm_alterLogTables(&$logTableSpec) {
  unset($logTableSpec['civicrm_pivotreportcache']);
}
