<?php
use hserv\synchronisation\SyncConfig;use hserv\synchronisation\SyncFeature;
define('MANAGER_REQUIRED',1);define('PDIR','../../');require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';
$enabled=SyncFeature::isEnabled();$config=$enabled?(new SyncConfig($system))->load(false):[];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow">
<title>Synchronise with Master</title><?php includeJQuery();include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php';?>
<style>body{margin:18px 24px;color:#222}.controls{display:flex;gap:12px;align-items:center}#working{display:none}#summary,#error{display:none;margin-top:14px;padding:10px}#summary{background:#e6efc2;color:#264409}#error{background:#fbe3e4;color:#8a1f11}details{margin-top:10px}pre{white-space:pre-wrap;background:#f5f5f5;padding:10px}</style></head>
<body class="popup ui-heurist-populate">
<?php if(!$enabled):?><p><?=htmlspecialchars(SyncFeature::UNAVAILABLE_MESSAGE)?></p>
<?php elseif(($config['role']??'')!=='satellite'):?><p>This database is not configured as a Satellite. Use <strong>Design → Master-satellite setup</strong>.</p>
<?php else:$master=(string)$config['master']['database'];?>
<h2>Synchronise with Master (<?=htmlspecialchars($master)?>)</h2>
<p>This stage compares complete term and file manifests. Master content is authoritative. Record transfer is deliberately disabled until its separate specification is implemented.</p>
<div class="controls"><button id="start" class="ui-button-action">Synchronise terms and files</button><span id="working">Synchronisation in progress…</span></div>
<div id="summary"></div><div id="error"></div><details open><summary>Full synchronisation log</summary><pre id="log"></pre></details>
<script>
const endpoint='../../hserv/controller/synchronisationController.php?db='+encodeURIComponent(<?=json_encode($system->dbname())?>);
const text=v=>$('<div>').html(String(v||'').replace(/<br\s*\/?>/gi,'\n')).text();
async function call(action){const r=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action})});const raw=await r.text();try{return JSON.parse(raw)}catch(e){throw new Error('Invalid local response (HTTP '+r.status+'): '+text(raw).slice(0,500));}}
async function progress(){const r=await call('sync_progress');if(r.status==='ok'){$('#log').text(r.data.log||r.data.message||'');$('#working').text(r.data.message||'Synchronisation in progress…');}}
$('#start').on('click',async function(){const button=$(this).prop('disabled',true);$('#working').show();$('#summary,#error').hide();$('#log').text('Starting complete term and file comparison…');let poll;
try{await call('reset_progress');poll=setInterval(()=>progress().catch(()=>{}),500);const result=await call('start_sync');if(result.status!=='ok')throw new Error(text(result.message||result.msg||'Synchronisation failed.')+(result.sysmsg?'\n'+text(result.sysmsg):''));await progress();const d=result.data;
$('#summary').text('Completed: '+d.termsAddedToMaster+' terms and '+d.filesSentToMaster+' files added to Master; '+d.termsAddedToSatellite+' terms added, '+d.termsUpdatedOnSatellite+' terms updated and '+d.filesReceivedBySatellite+' files received by this Satellite. Records were not transferred.').show();
$('#log').append('\n\nState: '+d.state+'\nSatellite term concepts: '+d.satelliteTermConcepts+'\nMaster term concepts: '+d.masterTermConcepts+'\nSatellite file concepts: '+d.satelliteFileConcepts+'\nMaster file concepts: '+d.masterFileConcepts+'\nMD5 checksums calculated: '+d.checksumsCalculated+(d.missingPhysicalFiles.length?'\nMissing physical files: '+d.missingPhysicalFiles.join(', '):''));
}catch(e){await progress().catch(()=>{});$('#error').text(e.message).show();}finally{if(poll)clearInterval(poll);button.prop('disabled',false);$('#working').hide();}});
</script><?php endif;?></body></html>
