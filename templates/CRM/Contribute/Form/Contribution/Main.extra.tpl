{if $autoMembershipSummary}
  <div id="automembership-summary">
    {$autoMembershipSummary}
  </div>

{literal}
  <script type="text/javascript">
    CRM.$(function($) {
      $('#priceset-div').before($('#automembership-summary'));
    });
  </script>
{/literal}
{/if}