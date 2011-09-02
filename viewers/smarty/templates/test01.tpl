
{* name of template 1 *}

<table border="1">
{foreach $results as $record}
{strip}
   <tr bgcolor="{cycle values="#aaaaaa,#bbbbbb"}">
      <td>{$record.recID}</td>
      <td>{$record.recTitle}</td>
      {*<td>{$record.Title222}</td>
      <td>{$blabla.albalb}</td>*}
   </tr>
{/strip}
{/foreach}
</table>