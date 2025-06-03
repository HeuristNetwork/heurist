{assign var="baseURL" value="`$heurist->baseURL()`?db=`$heurist->getSysInfo('dbname')`"} 
{*------------------------------------------------------------*}
{foreach $results as $r} {* Start records loop, do not remove *}
{$r = $heurist->getRecord($r)}

{if ($r==null)}
{continue}
{/if}

{$rts = $heurist->getRecordStructure($r)}

{$recordThumbnail = $heurist->getRecordThumbnail($r)}
{if ($recordThumbnail==null)}
  {$recordThumbnail = "`$baseURL`&icon=`$r.recTypeID`&version=thumb"}
  {$opacity = ' semitransparent'}
{else}
  {$opacity=''}
{/if}

{* <div class="card-img-top recordList-thumb{$opacity}" style="background-image: url('{$recordThumbnail}');"> *}

<div class="col" data-heurist-rec="{$r.recID}"><div class="recordList-item border round-1" style="height:auto;width:98%;">
    
<div class="HeaderRow" style="margin-bottom:15px;min-height:0px;">
	<h2 style="text-transform:none;line-height:16px;font-size:1.4em;margin-bottom:0;">{$r.recTitle}</h2>

  <div style="padding:0 10px 0 22px;margin:10px 0 0;height:20px;background-repeat: no-repeat;background-image:url({$baseURL}&amp;icon={$r.recTypeID})" title="desccccww">
            &nbsp;<strong>{$r.recTypeName}</strong>: id {$r.recID}
        
            <span class="link"><a id="edit-link" class="normal" target="_new" href="{$baseURL}&amp;fmt=edit&amp;recID={$r.recID}">
                <img class="rv-editpencil" src="{$heurist->baseURL()}/hclient/assets/edit-pencil.png" alt="Edit record" title="Edit record" style="vertical-align: top"></a>
            </span>
   </div>
</div>    

{* detect empty groups *}
{$r = $heurist->prepareRecord($r)}


{* fields *}
{$isClose = false}

{foreach $rts as $dty_ID=>$label}
    
{$dtyKey ="f`$dty_ID`"}
{$dtyType = $heurist->getFieldType($dty_ID)}

{if $dtyType=='separator'}
{if $isClose }
</fieldset>
{/if}
{if $r[$dtyKey]=='empty' || $r.recGroupCount<2}
{$isClose = false}
{else}
<fieldset id="#{$dty_ID}">
<legend data-order="{$dty_ID}" style="font-size: 1.1em; text-transform: uppercase;">{$label}</legend>
{$isClose = true}
{/if}
{continue}
{/if}

{if count($r[$dtyKey])>0 }
	<div class="detailRow">
  	<div class="detailType">{$label} {$dtyKey}</div>
  	<div class="detail">
{foreach $r["`$dtyKey`s"] as $val}
        <span class="value">
          {if $dtyType=='enum'}
            {$val.label}&nbsp;
          {elseif $dtyType=='resource'}
            {$heurist->composeRecLink($val, 'Base%20template')}
          {else}
         		{$val}<br>
          {/if}  
        </span>
{/foreach}
  	</div>
  </div>
{/if}

    
{/foreach}

{if $isClose }
</fieldset>
{/if}

</div></div>
{/foreach}

