{*
  Team member: round thumbnail, name, role and short description of a Person, in any database.

  Role is the field "Person role" (concept code 1252-619). A database without this field shows no role;
  to use another field, put its concept code in the next line. A term is shown without its parent terms.
  Use: the report must be marked "Single record (card)" to be chosen for cards.
  Styles are inline: without authorisation of the database, reports lose <style> blocks.
  Flex and grid styles are allowed in inline styles.
*}
{$roleField = $heurist->dty_id('1252-619')}
{$summaryField = $heurist->constant('DT_SHORT_SUMMARY')}
{foreach $results as $id}
{$r = $heurist->getRecord($id)}
{if $r}
{$thumbnail = $heurist->getRecordThumbnail($r)}
{$roleKey = "f`$roleField`s"}
{$summaryKey = "f`$summaryField`"}
<div data-heurist-rec="{$r.recID}" style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:8px;text-align:center;">
  {if $thumbnail}
  <img src="{$thumbnail}" alt="" style="width:120px;height:120px;object-fit:cover;border-radius:50%;">
  {/if}
  <div style="font-size:14px;font-weight:bold;">{$r.recTitle}</div>
  {if !empty($r[$roleKey])}
  <div style="color:#6b7280;">{foreach $r[$roleKey] as $role}{$role.label}{if !$role@last}, {/if}{/foreach}</div>
  {/if}
  {if !empty($r[$summaryKey])}
  <div>{$r[$summaryKey]}</div>
  {/if}
</div>
{/if}
{/foreach}
