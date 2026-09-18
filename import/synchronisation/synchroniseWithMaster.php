<?php

use hserv\synchronisation\SyncConfig;
use hserv\synchronisation\SyncFeature;

define('MANAGER_REQUIRED', 1);
define('PDIR', '../../');
require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

$enabled = SyncFeature::isEnabled();
$config = $enabled ? (new SyncConfig($system))->load(false) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex,nofollow">
    <title>Synchronise with master</title>
    <?php includeJQuery(); ?>
    <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; ?>
    <style>
        .sync-controls { display:flex; align-items:center; gap:14px; }
        #sync-working { display:none; }
        #sync-working::before { content:''; display:inline-block; width:13px; height:13px; margin-right:7px;
            border:2px solid #aaa; border-top-color:#2d7d32; border-radius:50%; vertical-align:-2px;
            animation:spin .8s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        #result.error { background:#fbe3e4 !important; color:#8a1f11; }
        #result.success { background:#e6efc2 !important; color:#264409; }
    </style>
</head>
<body class="popup ui-heurist-populate" style="margin:18px 24px;color:#222">
<h2>Synchronise with master</h2>
<?php if (!$enabled): ?>
    <p><?=htmlspecialchars(SyncFeature::UNAVAILABLE_MESSAGE)?></p>
    <script>alert(<?=json_encode(SyncFeature::UNAVAILABLE_MESSAGE)?>);</script>
<?php elseif (($config['role'] ?? '') !== 'satellite'): ?>
    <p>This database is not configured as a satellite. Use <strong>Populate → Configure satellites</strong> first.</p>
<?php else: ?>
    <p><strong>Master database:</strong> <?=htmlspecialchars((string)$config['master']['databaseID'])?></p>
    <p><strong>Master URL:</strong> <?=htmlspecialchars((string)$config['master']['url'])?></p>
    <p>This stage allocates permanent master IDs, transactionally updates local IDs and record pointers, then uploads record content into the reserved master records.</p>
    <p>Uploaded files and satellite-created vocabulary terms are checked and reported, but are not transferred yet.</p>
    <div class="sync-controls">
        <button id="start-sync" type="button" class="ui-button-action">Synchronise new records</button>
        <span id="sync-working">Contacting the master and checking new records…</span>
    </div>
    <pre id="result" style="display:none;white-space:pre-wrap;margin-top:15px;padding:10px;background:#f5f5f5"></pre>
    <script>
    const plainText = value => $('<div>').html(String(value || '').replace(/<br\s*\/?>/gi, '\n')).text().trim();
    const errorText = (result, fallback) => {
        const parts = [];
        if (result?.diagnosticCode) parts.push('Failure type: ' + result.diagnosticCode);
        if (result?.message || result?.msg) parts.push(plainText(result.message || result.msg));
        if (result?.sysmsg) parts.push('Technical detail: ' + plainText(result.sysmsg));
        return parts.join('\n') || fallback;
    };
    $('#start-sync').on('click', async function() {
        const button = $(this).prop('disabled', true);
        const working = $('#sync-working').show();
        const output = $('#result').removeClass('error success').show().text('Contacting master…');
        try {
            const response = await fetch('../../hserv/controller/synchronisationController.php?db=' +
                encodeURIComponent(<?=json_encode($system->dbname())?>), {
                    method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'start_sync'})
                });
            const body = await response.text();
            let result;
            try { result = JSON.parse(body); }
            catch (e) {
                throw new Error('The local synchronisation controller returned a non-JSON response (HTTP ' +
                    response.status + '). Response begins: ' + plainText(body).substring(0, 500));
            }
            if (result.status !== 'ok') throw new Error(errorText(result, 'Synchronisation could not be started.'));
            output.addClass('success');
            output.text('Session: ' + result.data.sessionID + '\nState: ' + result.data.state +
                '\nNew records allocated: ' + result.data.newRecordsAllocated +
                '\nNew records uploaded: ' + result.data.newRecordsUploaded +
                '\nInterrupted mappings completed: ' + result.data.resumedMappings +
                '\nPre-existing records inventoried: ' + result.data.initialRecordsInventoried +
                '\nJournal scanned through: ' + result.data.journalScannedThrough +
                (result.data.masterSessionResumed ? '\nExisting master session resumed.' : '\nNew master session created.'));
        } catch (e) {
            output.addClass('error').text('Synchronisation failed.\n\n' + e.message);
        } finally {
            button.prop('disabled', false);
            working.hide();
        }
    });
    </script>
<?php endif; ?>
</body>
</html>
