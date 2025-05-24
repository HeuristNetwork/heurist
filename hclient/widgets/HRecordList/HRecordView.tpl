{assign var="baseURL" value="`$heurist->baseURL()`?db=`$heurist->getSysInfo('dbname')`"} 
{if (false)}
<html>
<head>
<script type="text/javascript" src="{$heurist->baseURL()}hclient/core/detectHeurist.js"></script>
<link rel="stylesheet" type="text/css" href="{$heurist->baseURL()}h4styles.css" />
<style>
.detail a, .detail{
	max-width: 600px;
}
.detailType{
  min-width:150px;
  font-size:0.9em;
}
</style>
<script>
function open_link(link){

 let url = link.href;
 let target = !link.getAttribute("target") ? '_popup' : link.getAttribute("target");

  if(window.hWin && window.hWin.HEURIST4 && window.hWin.HEURIST4.msg && target == '_popup'){
	   window.hWin.HEURIST4.msg.showDialog(url);
  }else{
     window.open(url,target);
	}
  //window.hWin.HEURIST4.util.stopEvent(event);
  let e = event??window.event;
        if (e) {
            e.cancelBubble = true;
            if (e.stopPropagation) e.stopPropagation();
            e.returnValue = false;
            e.preventDefault();
        }
  
  return false;
}
</script>
</head>
<body>
{/if} {* no header *}
<div>
{*------------------------------------------------------------*}
{foreach $results as $r} {* Start records loop, do not remove *}
{$r = $heurist->getRecord($r)}

{$rts = $heurist->getRecordStructure($r)}
    
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

    
{/foreach}
</div>
{if (false)}
</body>
</html>
{/if}
