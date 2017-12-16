<?php

require_once 'automembership.civix.php';
use CRM_Automembership_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function automembership_civicrm_config(&$config) {
  _automembership_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function automembership_civicrm_xmlMenu(&$files) {
  _automembership_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function automembership_civicrm_install() {
  _automembership_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function automembership_civicrm_postInstall() {
  _automembership_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function automembership_civicrm_uninstall() {
  _automembership_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function automembership_civicrm_enable() {
  _automembership_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function automembership_civicrm_disable() {
  _automembership_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function automembership_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _automembership_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function automembership_civicrm_managed(&$entities) {
  _automembership_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function automembership_civicrm_caseTypes(&$caseTypes) {
  _automembership_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function automembership_civicrm_angularModules(&$angularModules) {
  _automembership_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function automembership_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _automembership_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

// --- Functions below this ship commented out. Uncomment as required. ---


/**
 * Implements hook_civicrm_post().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_post/
 */
function automembership_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if ($objectName == 'Contribution' && ($op == 'create' || $op == 'edit' )) {
    // get the contact id of contributor
    // get the household for the contributor
    $result = civicrm_api3('Relationship', 'get', array(
      'sequential' => 1,
      'return' => array("contact_id_b"),
      'contact_id_a' => $objectRef->contact_id,
      'relationship_type_id' => 8, // household member of
      'is_active' => 1,
    ));

    // compute membership only if household exist
    if ($result['count'] > 0) {
      computeMembership($result['values'][0]['contact_id_b']);
    }
  }
}

function computeMembership($householdID) {
  // check the membership for the household
  $result = civicrm_api3('Membership', 'get', array(
    'sequential' => 1,
    'contact_id' => $householdID,
  ));

  // calculate contribution credits for household based on members in the
  // household

  // based on contribution credit there are 3 conditions
  // 1. No sufficient for the membership
  // 2. If credits are sufficient add membership to the household
  // 3. Based on credits check if upgrade is possible

  // create new membership

  // upgrade existing membership

  // link the contribution records with the membership
}
