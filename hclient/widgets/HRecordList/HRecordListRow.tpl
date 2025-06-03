{assign var="baseURL" value="`$heurist->baseURL()`?db=`$heurist->getSysInfo('dbname')`"} 
{*------------------------------------------------------------*}
{foreach $results as $r} {* Start records loop, do not remove *}
{$r = $heurist->getRecord($r, false)} {* only header *}
{if ($r==null)}
{continue}
{/if}
{$recordIcon = "`$baseURL`&icon=`$r.recTypeID`"}
{$recordThumbnail = $heurist->getRecordThumbnail($r)}
{if ($recordThumbnail==null)}
  {$recordThumbnail = "`$baseURL`&icon=`$r.recTypeID`&version=thumb"}
  {$opacity = 'opacity:0.5;'}
{else}
  {$opacity=''}
{/if}

  <tr data-heurist-rec="{$r.recID}">
    <td>{$r.recID}</td>
  	<td><div class="recordList-icon" style="background-image: url('{$recordIcon}');"></div></td>
  	<td>{$r.recTitle}</td>
    <td>{$r.recAdded}</td>
  </tr>
{/foreach}