{if $autoMembershipSummary}
  <div id="automembership-summary">
    {$autoMembershipSummary}
  </div>

{literal}
  <script type="text/javascript">
    CRM.$(function($) {
      $('div.view-content > div.action-link').after($('#automembership-summary'));
    });
  </script>
{/literal}
{/if}