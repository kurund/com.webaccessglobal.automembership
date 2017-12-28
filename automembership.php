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
  if ($objectName == 'Contribution' && ($op == 'create' || $op == 'edit')) {
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
  elseif ($objectName == 'Relationship' && ($op == 'create' || $op == 'edit')) {
    // calculate memberships only if household relationship is added
    if ($objectRef->relationship_type_id == 8) {
      computeMembership($objectRef->contact_id_b);
    }
  }
}

/**
 * Function to determine if a membership needs to be created for a household
 *
 * @param $householdID
 *
 * @throws \CiviCRM_API3_Exception
 */
function computeMembership($householdID) {
  // calculate contribution credits for household based on members in the
  // household
  $householdCreditValues = calculateHouseholdCredit($householdID);

  // based on contribution credit there are 3 conditions
  // 1. Not sufficient for the membership
  // 2. If credits are sufficient add membership to the household
  // 3. Based on credits check if upgrade is possible

  // get membership types
  $membershipTypes = civicrm_api3('MembershipType', 'get', array(
    'sequential' => 1,
    'return'     => array("minimum_fee"),
    'is_active'  => 1,
  ));

  // check eligibility for membership based on the credit amount
  $processMembership = FALSE;
  $eligibleMembershipFee = 0;
  $eligibleMembershipTypeID = 0;
  $membershipTypesArray = array();
  foreach($membershipTypes['values'] as $key => $value) {
    if ($householdCreditValues['credit'] >= $value['minimum_fee']) {
      $processMembership = TRUE;
      if ($eligibleMembershipFee <= $value['minimum_fee']) {
        $eligibleMembershipFee = $value['minimum_fee'];
        $eligibleMembershipTypeID = $value['id'];
      }
    }
    $membershipTypesArray[$value['id']] = $value['minimum_fee'];
  }

  // this means we don't need proceed further as credit is insufficient
  if (!$processMembership) {
    Civi::log()->info("AMC: Insufficient credits for membership, hence aborted. Household ID: {$householdID}.");
    return;
  }

  // check the membership for the household
  $existingHouseHoldMembership = civicrm_api3('Membership', 'get', array(
    'sequential' => 1,
    'status_id'  => array('!=' => "Cancelled"),
    'contact_id' => $householdID,
  ));

  if (!empty($existingHouseHoldMembership['values'][0]['membership_type_id'])) {
    $oldMembershipTypeID = $existingHouseHoldMembership['values'][0]['membership_type_id'];
    $oldMembershipID = $existingHouseHoldMembership['values'][0]['id'];
  }

  // for some reason / user error, if there are more than 1 active membership
  // just write to log and return, admin will have to manually set only 1
  // active membership
  if ($existingHouseHoldMembership['count'] > 1) {
    Civi::log()->info(
      "AMC: There are {$existingHouseHoldMembership['count']} active memberships for the household ID: {$householdID}. Only one membership needs to be active at a time. Hence, auto membership computation is aborted.");
    return;
  }

  // if membership does not exist and is eligible for membership then create
  if ($existingHouseHoldMembership['count'] == 0 && !empty($eligibleMembershipTypeID)) {
    $houseHoldMembership = civicrm_api3('Membership', 'create', array(
      'sequential'         => 1,
      'membership_type_id' => $eligibleMembershipTypeID,
      'contact_id'         => $householdID,
    ));

    $currentMembershipID = $houseHoldMembership['id'];
    Civi::log()->info("AMC: New membership has been created for household ID: {$householdID}.");
  }
  elseif ($eligibleMembershipTypeID == $oldMembershipTypeID) {
    // if household's current membership is same as what's eligible do nothing
    Civi::log()->info("AMC: Current membership eligibility for household ID: {$householdID} is same as current membership hence aborted.");
    return;
  }
  elseif ($eligibleMembershipFee > $membershipTypesArray[$oldMembershipTypeID]) {
    // if $eligibleMembershipFee is greater than current fee, which means
    // household is eligible for the upgrade
    // create new membership
    // calculate dates based on membership type
    $calculateDates = CRM_Member_BAO_MembershipType::getDatesForMembershipType(
      $eligibleMembershipTypeID,
      $existingHouseHoldMembership['values'][0]['join_date'],
      date('YmdHis'));

    $houseHoldMembership = civicrm_api3('Membership', 'create', array(
      'sequential'         => 1,
      'membership_type_id' => $eligibleMembershipTypeID,
      'contact_id'         => $householdID,
      'join_date'          => $calculateDates['join_date'],
      'start_date'         => $calculateDates['start_date'],
      'end_date'           => $calculateDates['end_date'],
    ));

    $currentMembershipID = $houseHoldMembership['id'];
    Civi::log()->info("AMC: Membership has been upgraded for household ID: {$householdID}.");

    // related membership uses static variable, not sure why. Hence, below code is hack to
    // reset the static variable before we update the existing membership
    // I know this is weird but it works :)
    CRM_Member_BAO_Membership::createRelatedMemberships(
      CRM_Core_DAO::$_nullArray, CRM_Core_DAO::$_nullObject, TRUE);

    // cancel old / current membership
    civicrm_api3('Membership', 'create', array(
      'sequential'         => 1,
      'membership_type_id' => $oldMembershipTypeID,
      'contact_id'         => $householdID,
      'is_override'        => 1,
      'status_id'          => 6,
      'skipStatusCal'      => 1,
      'id'                 => $oldMembershipID,
    ));

  }
  else {
    // this mean credit is less than existing membership level hence abort
    Civi::log()->info("AMC: Insufficient credits for membership upgrade, hence aborted. Household ID: {$householdID}.");
    return;
  }

  // link the contribution records with the membership
  if (!empty($householdCreditValues['contribution'])) {
    // create membership payment records
    foreach($householdCreditValues['contribution'] as $contributionID) {
      civicrm_api3('MembershipPayment', 'create', array(
        'sequential'      => 1,
        'membership_id'   => $currentMembershipID,
        'contribution_id' => $contributionID,
      ));
    }
  }
}

/**
 * Function to calculate household credit
 * @param $householdID
 *
 * @return int
 */
function calculateHouseholdCredit($householdID) {
  $returnValues = array();

  // get associated contributions for the household that are not associated with
  // any membership and of type "Donation"

  // get all the household members
  $result = civicrm_api3('Relationship', 'get', array(
    'sequential' => 1,
    'return' => array("contact_id_a"),
    'contact_id_b' => $householdID,
    'relationship_type_id' => 8, // household member is
    'is_active' => 1,
  ));


  $householdMemberIds = array();
  foreach($result['values'] as $dontCare => $value) {
    $householdMemberIds[$value['contact_id_a']] = $value['contact_id_a'];
  }

  // get the start date of latest cancelled / expired membership
  $query = "SELECT id, start_date, status_id
FROM `civicrm_membership`
WHERE contact_id = {$householdID}
  AND status_id != 6
ORDER BY start_date DESC, `status_id` ASC LIMIT 1";

  $result = CRM_Core_DAO::executeQuery($query);
  $result->fetch();

  // if there is membership, consider all the contributions > start date
  if (!empty($result->start_date)) {
    $startDate = $result->start_date;
  }
  else {
    // if no membership, then consider only last 1 year contributions
    $startDate = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') + 1, date('Y') - 1));
  }

  // get all the valid contributions for all household members
  $query = "SELECT cc.id, DATE_FORMAT(cc.receive_date,'%Y-%m-%d') as receive_date, cc.total_amount
FROM `civicrm_contribution` as cc
  LEFT JOIN `civicrm_membership_payment` as cmp ON cc.id = cmp.contribution_id
WHERE cc.financial_type_id = 1 AND contribution_status_id = 1
  AND cc.contact_id IN (". implode(',', $householdMemberIds).")
  AND cc.`receive_date` > '{$startDate}'
  AND cmp.contribution_id IS NULL";

  //CRM_Core_Error::debug_var('$query', $query);

  $contributions = CRM_Core_DAO::executeQuery($query);
  $returnValues['contribution'] = array();
  $returnValues['credit'] = 0;
  while($contributions->fetch()) {
    $returnValues['contribution'][] = $contributions->id;

    // give full credit to all the today and future contributions
    if ($contributions->receive_date >= date('Y-m-d')) {
      $returnValues['credit'] += $contributions->total_amount;
    }
    else {
      // calculate credits based on pro-rata basis
      // end date calculation based on contribution receive date
      $date  = explode('-', $contributions->receive_date);
      $year  = $date[0];
      $month = $date[1];
      $day   = $date[2];

      // this should be 1 year after contribution receive date
      $endDate = date('Y-m-d', mktime(0, 0, 0, $month, $day - 1, $year + 1));

      // find the difference between today and end of year since contribution was
      // received
      $interval = strtotime($endDate) - time();

      // for contributions less than today use pro-rata calculations
      $interval = floor($interval / (60 * 60 * 24));

      // compute credit based on pro-rata
      $returnValues['credit'] += ($interval / 365) * $contributions->total_amount;
    }
  }

  // round to 2 decimal places
  $returnValues['credit'] = round($returnValues['credit'], 2);

  //CRM_Core_Error::debug_var('$returnValues', $returnValues);

  return $returnValues;
}

/**
 * Implements hook_civicrm_pageRun().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_pageRun/
 */
function automembership_civicrm_pageRun(&$page) {
  $pageName = $page->getVar('_name');
  $action = $page->getVar('_action'); // 16 browse action
  if ($pageName == 'CRM_Member_Page_Tab' && $action == 16) {
    // we should show summary only for household contact
    $result = civicrm_api3('Contact', 'get', array(
      'sequential' => 1,
      'return' => array("contact_type"),
      'id' => $page->_contactId,
    ));

    $autoMembershipSummary = '';
    if ($result['values'][0]['contact_type'] == 'Household') {
      $autoMembershipSummary = buildMembershipSummary($page->_contactId);
    }
    $page->assign('autoMembershipSummary', $autoMembershipSummary);
  }
}

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess/
 */
function automembership_civicrm_preProcess($formName, &$form) {
  if ($formName == 'CRM_Contribute_Form_Contribution_Main') {
    $autoMembershipSummary = '';

    // if use is logged in
    if ($form->_membershipContactID > 0) {
      // get the contact id of contributor
      // get the household for the contributor
      $result = civicrm_api3('Relationship', 'get', array(
        'sequential' => 1,
        'return' => array("contact_id_b"),
        'contact_id_a' => $form->_membershipContactID,
        'relationship_type_id' => 8, // household member of
        'is_active' => 1,
      ));

      // compute membership only if household exist
      if ($result['count'] > 0) {
        $autoMembershipSummary = buildMembershipSummary($result['values'][0]['contact_id_b']);
      }
    }
    $form->assign('autoMembershipSummary', $autoMembershipSummary);
  }
}

/**
 * Function to build the membership eligibility summary
 *
 * @param $householdID
 * @return string
 *
 */
function buildMembershipSummary($householdID) {
  // get the credit amount
  $creditCalculations = calculateHouseholdCredit($householdID);

  // check the membership for the household
  $result = civicrm_api3('Membership', 'get', array(
    'sequential' => 1,
    'return' => array("membership_type_id"),
    'contact_id' => $householdID,
    'status_id' => array('IN' => array("New", "Current", "Grace")),
    'api.MembershipType.get' => array('return' => "minimum_fee"),
  ));

  $minimumFeeOfExistingMembership = 0;
  if ($result['count'] > 0) {
    $minimumFeeOfExistingMembership = $result['values'][0]['api.MembershipType.get']['values'][0]['minimum_fee'];
  }

  // get membership types
  $membershipTypes = civicrm_api3('MembershipType', 'get', array(
    'sequential' => 1,
    'return'     => array("minimum_fee", 'name'),
    'is_active'  => 1,
  ));

  // build membership listing based on the credit amount
  foreach($membershipTypes['values'] as $key => $value) {
    if ($value['minimum_fee'] > $minimumFeeOfExistingMembership) {
      $amountNeeded = $value['minimum_fee'] - $creditCalculations['credit'];
      $autoMembershipSummary .=
        '<tr>
            <td><strong>' . $value['name'] . '</strong></td>
            <td>$' . $amountNeeded . '</td>
           </tr>';
    }
  }

  if (!empty($autoMembershipSummary)) {
    $autoMembershipSummary = '
<table class="report">
  <tr class="columnheader-dark">
    <th>Membership</th>
    <th>Donation Amount</th>
  </tr>
  ' . $autoMembershipSummary . '
</table>';
  }

  return $autoMembershipSummary;
}
