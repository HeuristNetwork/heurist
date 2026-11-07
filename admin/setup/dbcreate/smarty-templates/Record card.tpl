{*
  Record card: ID, title, thumbnail and short description of any record, in any database.

  Use: Card view template of a record list. The report must be marked "Single record (card)".
  Styles are inline: without authorisation of the database, reports lose <style> blocks.
  Flex and grid styles are allowed in inline styles.
*}
{$summaryField = $heurist->constant('DT_SHORT_SUMMARY')}
{foreach $results as $id}
{$r = $heurist->getRecord($id)}
{if $r}
{$thumbnail = $heurist->getRecordThumbnail($r)}
{$summaryKey = "f`$summaryField`"}
<div data-heurist-rec="{$r.recID}" style="display:flex;gap:8px;align-items:flex-start;">
  {if $thumbnail}
  <img src="{$thumbnail}" alt="" style="flex:none;width:64px;height:64px;object-fit:cover;border-radius:3px;">
  {/if}
  <div style="flex:1;min-width:0;overflow-wrap:anywhere;">
    <div style="font-size:11px;color:#6b7280;">{$r.recID}</div>
    <div style="font-weight:bold;">{$r.recTitle}</div>
    {if !empty($r[$summaryKey])}
    <div style="margin-top:4px;">{$r[$summaryKey]|strip_tags|truncate:200}</div>
    {/if}
  </div>
</div>
{/if}
{/foreach}
