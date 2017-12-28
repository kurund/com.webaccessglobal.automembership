<?php
/**
 * Class CRM_Automembership_Page_AutoMembership
 */
class CRM_Automembership_Page_AutoMembership {
  public static function refreshMembership() {
    // get the household id
    $householdId = CRM_Utils_Request::retrieve('cid', 'Positive', CRM_Core_DAO::$_nullObject);

    // compute membership
    CRM_Automembership_BAO_AutoMembership::computeMembership($householdId);

    // redirect back to membership page
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contact/view/',
      "reset=1&cid={$householdId}&selectedChild=member"
    ));
  }
}