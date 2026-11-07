{*
  Record card: ID, title, thumbnail and short description of any record, in any database.

  Use: Card view template of a record list. The report must be marked "Single record (card)".
  Styles are inline and the layout is a table: without authorisation of the database, reports lose
  <style> blocks and styles such as display:flex.
*}
{$summaryField = $heurist->constant('DT_SHORT_SUMMARY')}
{foreach $results as $id}
{$r = $heurist->getRecord($id)}
{if $r}
{$thumbnail = $heurist->getRecordThumbnail($r)}
{$summaryKey = "f`$summaryField`"}
<div data-heurist-rec="{$r.recID}">
  <table style="width:100%;border-collapse:collapse;">
    <tr>
      {if $thumbnail}
      <td style="width:72px;padding:0;vertical-align:top;">
        <div style="width:64px;height:64px;background-image:url('{$thumbnail}');background-size:cover;background-position:center;border-radius:3px;"></div>
      </td>
      {/if}
      <td style="padding:0;vertical-align:top;">
        <div style="font-size:11px;color:#6b7280;">{$r.recID}</div>
        <div style="font-weight:bold;">{$r.recTitle}</div>
        {if !empty($r[$summaryKey])}
        <div style="margin-top:4px;">{$r[$summaryKey]|strip_tags|truncate:200}</div>
        {/if}
      </td>
    </tr>
  </table>
</div>
{/if}
{/foreach}
