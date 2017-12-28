<?php
/**
 * Class CRM_Automembership_BAO_AutoMembership
 */
class CRM_Automembership_BAO_AutoMembership {

  /**
   * Function to determine if a membership needs to be created for a household
   *
   * @param int $householdID household id
   *
   * @throws \CiviCRM_API3_Exception
   *
   * @return void
   */
  public static function computeMembership($householdID) {
    // calculate contribution credits for household based on members in the
    // household
    $householdCreditValues = self::calculateHouseholdCredit($householdID);

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
   * @param int $householdID household id
   *
   * @return array
   */
  public static function calculateHouseholdCredit($householdID) {
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

    // we should consider only last 1 year contributions
    $startDate = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') + 1, date('Y') - 1));

    // get all the valid contributions for all household members
    $query = "SELECT cc.id, DATE_FORMAT(cc.receive_date,'%Y-%m-%d') as receive_date, cc.total_amount
FROM `civicrm_contribution` as cc
WHERE cc.financial_type_id = 1 AND contribution_status_id = 1
  AND cc.contact_id IN (". implode(',', $householdMemberIds).")
  AND cc.`receive_date` > '{$startDate}'
";

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
   * Function to build the membership eligibility summary
   *
   * @param int $householdID  household Id
   * @param boolean $addRefreshButton whether to add refresh button or not
   *
   * @return string $autoMembershipSummary
   */
  public static function buildMembershipSummary($householdID, $addRefreshButton = FALSE) {
    // get the credit amount
    $creditCalculations = self::calculateHouseholdCredit($householdID);

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

    if ($addRefreshButton) {
      $autoMembershipSummary = '
        <div class="action-link">
            <a accesskey="N" href="/civicrm/membershiprefresh?cid='.$householdID.'&reset=1" class="button no-popup"><span><i class="crm-i fa-refresh"></i> Refresh Membership</span></a>
            <br/><br/>
        </div>
        <div>
          <strong>Current Credit: </strong>$'.$creditCalculations['credit'].'
          <br/><br/>
        </div>
      ';
    }

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
}