<?php
/**
 * Class CRM_Automembership_Page_AutoMembership
 */
class CRM_Automembership_Page_AutoMembership {
  public static function refreshMembership() {
    // get the household id
    $householdID = CRM_Utils_Request::retrieve('cid', 'Positive', CRM_Core_DAO::$_nullObject);

    // compute membership
    CRM_Automembership_BAO_AutoMembership::computeMembership($householdID);

    // redirect back to membership page
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contact/view',
      "reset=1&cid={$householdID}&selectedChild=member"
    ));
  }
}