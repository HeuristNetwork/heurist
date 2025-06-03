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

  <div class="col" data-heurist-rec="{$r.recID}"><div class="recordList-item d-flex" style="width:14rem;height:4rem;">
            <div class="recordList-thumb" style="{$opacity}background-image: url('{$recordThumbnail}');min-width:4rem">
        </div>
        <div class="ps-1 recordList-text" style="max-height: 3rem;line-height: 1rem;">
            {$r.recTitle}
        </div>
  </div></div>
{/foreach}