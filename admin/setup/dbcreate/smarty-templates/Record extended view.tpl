{*
  Record extended view: a simple version of the built-in record view, for any record, in any database.
  Title, type and ID, then the fields of the record type by group, then the related records.
  Empty fields and empty groups are left out.

  Use: Extended view template of a record list. The report must be marked "Single record (card)".
  Styles are inline and the layout is a table: without authorisation of the database, reports lose
  <style> blocks and styles such as display:flex.
*}
{$rowStyle = 'width:100%;border-collapse:collapse;table-layout:fixed;margin-bottom:3px;'}
{$labelStyle = 'width:150px;padding:0 8px 0 0;vertical-align:top;color:#6b7280;'}
{$valueStyle = 'padding:0;vertical-align:top;'}
{foreach $results as $id}
{$r = $heurist->getRecord($id)}
{if $r}
{$fields = $heurist->getRecordStructure($r)}
{$r = $heurist->prepareRecord($r)}
<div data-heurist-rec="{$r.recID}">
  <div style="margin-bottom:8px;">
    <div style="font-size:14px;font-weight:bold;">{$r.recTitle}</div>
    <div style="font-size:11px;color:#6b7280;">{$r.recTypeName}: {$r.recID}</div>
  </div>

  {$groupOpen = false}
  {foreach $fields as $fieldId=>$label}
    {$key = "f`$fieldId`"}
    {$type = $heurist->getFieldType($fieldId)}

    {if $type == 'separator'}
      {if $groupOpen}</div>{/if}
      {$groupOpen = false}
      {if $r[$key] != 'empty' && $r.recGroupCount > 1}
        <div style="margin-top:10px;">
        <div style="padding-bottom:2px;margin-bottom:4px;border-bottom:1px solid #d7dbe0;font-weight:bold;">{$label}</div>
        {$groupOpen = true}
      {/if}
      {continue}
    {/if}

    {$valuesKey = "`$key`s"}
    {$fileKey = "`$key`_originalvalue"}
    {if !empty($r[$key])}
      <table style="{$rowStyle}"><tr>
        <td style="{$labelStyle}">{$label}</td>
        <td style="{$valueStyle}">
          {if $type == 'file'}
            {wrap var=$r[$fileKey] dt="file" width="200" height="auto"}
          {elseif $type == 'geo'}
            {wrap var=$r[$key] dt="geo"}
          {else}
            {foreach $r[$valuesKey] as $value}
              {if $type == 'enum' || $type == 'relationtype'}
                <div>{$value.term}</div>
              {elseif $type == 'resource'}
                {$target = $heurist->getRecord($value, false)}
                {if $target}<div><a href="{$target.recID}" target="_blank" rel="noopener">{$target.recTitle}</a></div>{/if}
              {else}
                <div>{$value}</div>
              {/if}
            {/foreach}
          {/if}
        </td>
      </tr></table>
    {/if}
  {/foreach}
  {if $groupOpen}</div>{/if}

  {$related = $heurist->getRelatedRecords($r)}
  {if $related}
    <div style="margin-top:10px;">
      {foreach $related as $target}
        <table style="{$rowStyle}"><tr>
          <td style="{$labelStyle}">{$target.recRelationType}</td>
          <td style="{$valueStyle}"><a href="{$target.recID}" target="_blank" rel="noopener">{$target.recTitle}</a></td>
        </tr></table>
      {/foreach}
    </div>
  {/if}
</div>
{/if}
{/foreach}
