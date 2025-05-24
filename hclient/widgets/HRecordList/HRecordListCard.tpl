{assign var="baseURL" value="`$heurist->baseURL()`?db=`$heurist->getSysInfo('dbname')`"} 
{*------------------------------------------------------------*}
{foreach $results as $r} {* Start records loop, do not remove *}
{$r = $heurist->getRecord($r, false)} {* only header *}
{if ($r==null)}
{continue}
{/if}
{$recordThumbnail = $heurist->getRecordThumbnail($r)}
{if ($recordThumbnail==null)}
  {$recordThumbnail = "`$baseURL`&icon=`$r.recTypeID`&version=thumb"}
  {$opacity = 'opacity:0.5;'}
{else}
  {$opacity=''}
{/if}

  <div class="col" data-heurist-rec="{$r.recID}"><div class="card recordList-item">
  		  <div class="card-img-top recordList-thumb" style="{$opacity}background-image: url('{$recordThumbnail}');">
        </div>
  		<div class="card-body p-1 recordList-text">
        <p class="card-text">AA{$r.recTitle}</p>
      </div>
  </div></div>
{/foreach}