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
        #result.success { background:#e6efc2 !important; color:#264409; }
        #sync-status { display:none; white-space:pre-wrap; margin-top:15px; padding:10px; }
        #sync-status.error { display:block; background:#fbe3e4; color:#8a1f11; }
        #sync-status.awaiting { display:block; background:#eee5b5; color:#544b16; border-left:4px solid #a49336; }
    </style>
</head>
<body class="popup ui-heurist-populate" style="margin:18px 24px;color:#222">
<h2>Synchronise with master</h2>
<?php if (!$enabled): ?>
    <p><?=htmlspecialchars(SyncFeature::UNAVAILABLE_MESSAGE)?></p>
    <script>alert(<?=json_encode(SyncFeature::UNAVAILABLE_MESSAGE)?>);</script>
<?php elseif (($config['role'] ?? '') !== 'satellite'): ?>
    <p>This database is not configured as a satellite. Use <strong>Design → Master-satellite setup</strong> first.</p>
<?php else: ?>
    <p><strong>Master database:</strong> <?=htmlspecialchars((string)$config['master']['database'])?>
        (registered ID <?=htmlspecialchars((string)$config['master']['databaseID'])?>)</p>
    <p><strong>Master URL:</strong> <?=htmlspecialchars((string)$config['master']['url'])?></p>
    <p>This updates the local structure from the master, allocates permanent master IDs, uploads local changes, then downloads records and dependencies contributed through the master by other databases.</p>
    <div class="sync-controls">
        <button id="start-sync" type="button" class="ui-button-action">Synchronise with master</button>
        <span id="sync-working">Counting new and updated records…</span>
    </div>
    <pre id="result" style="display:none;white-space:pre-wrap;margin-top:15px;padding:10px;background:#f5f5f5"></pre>
    <pre id="sync-status"></pre>
    <script>
    const plainText = value => $('<div>').html(String(value || '').replace(/<br\s*\/?>/gi, '\n')).text().trim();
    const errorText = (result, fallback) => {
        const parts = [];
        if (result?.diagnosticCode) parts.push('Failure type: ' + result.diagnosticCode);
        if (result?.message || result?.msg) parts.push(plainText(result.message || result.msg));
        if (result?.sysmsg) parts.push('Technical detail: ' + plainText(result.sysmsg));
        return parts.join('\n') || fallback;
    };
    const progressText = data => {
        if (data?.state === 'COUNTING') {
            const message = String(data?.message || 'Counting new and updated records…');
            return message === 'Counting new and updated records…'
                ? message
                : 'Counting new and updated records…\n\nSteps:\n' + message;
        }
        const log = String(data?.log || '').split('\n').slice(2).filter(Boolean);
        return [
            'New records found: ' + Number(data?.newRecords || 0),
            'Updated records found: ' + Number(data?.updatedRecords || 0),
            'New records transferred: ' + Number(data?.newCompleted || 0),
            'Updated records transferred: ' + Number(data?.updatedCompleted || 0),
            '',
            'Steps:',
            ...log
        ].join('\n');
    };
    const readProgress = async output => {
        const response = await fetch('../../hserv/controller/synchronisationController.php?db=' +
            encodeURIComponent(<?=json_encode($system->dbname())?>), {
                method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'sync_progress'})
            });
        const result = await response.json();
        if (result.status === 'ok') {
            output.text(progressText(result.data));
            $('#sync-working').text(result.data.message || 'Synchronisation in progress…');
            return result.data;
        }
        throw new Error(errorText(result, 'Progress could not be read.'));
    };
    const resetProgress = async output => {
        const response = await fetch('../../hserv/controller/synchronisationController.php?db=' +
            encodeURIComponent(<?=json_encode($system->dbname())?>), {
                method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'reset_progress'})
            });
        const result = await response.json();
        if (result.status !== 'ok') throw new Error(errorText(result, 'Progress could not be initialised.'));
        output.text(progressText(result.data));
    };
    $('#start-sync').on('click', async function() {
        const button = $(this).prop('disabled', true);
        const working = $('#sync-working').show();
        const output = $('#result').removeClass('success').show().text('Counting new and updated records…');
        const status = $('#sync-status').removeClass('error awaiting').hide().text('');
        let poller = null;
        try {
            await resetProgress(output);
            poller = setInterval(() => readProgress(output).catch(() => {}), 200);
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
            let completedProgress = null;
            try { completedProgress = await readProgress(output); } catch (ignored) {}
            const report = completedProgress ? progressText(completedProgress) :
                'New records found: ' + result.data.newRecordsFound +
                '\nUpdated records found: ' + result.data.updatedRecordsFound +
                '\nNew records transferred: ' + result.data.newRecordsUploaded +
                '\nUpdated records transferred: ' + result.data.updatedRecordsUploaded;
            output.text(report + '\n\nSession: ' + result.data.sessionID + '\nState: ' + result.data.state +
                '\nNew terms transferred: ' + result.data.newTermsUploaded +
                '\nNew files transferred: ' + result.data.newFilesUploaded +
                '\nMaster records applied locally: ' + result.data.masterRecordsDownloaded +
                '\nMaster structure entries synchronised: ' + result.data.masterDefinitionsApplied +
                '\nMaster terms added locally: ' + result.data.masterTermsDownloaded +
                '\nMaster files added locally: ' + result.data.masterFilesDownloaded +
                '\nInterrupted mappings completed: ' + result.data.resumedMappings +
                '\nPre-existing records inventoried: ' + result.data.initialRecordsInventoried +
                '\nJournal scanned through: ' + result.data.journalScannedThrough +
                (result.data.masterSessionResumed ? '\nExisting master session resumed.' : '\nNew master session created.'));
            if (result.data.state === 'AWAITING_USER_ACTION') {
                status.addClass('awaiting').text('Synchronisation awaiting user action.\n\n' +
                    String(result.data.userActionNotice || '')).show();
            } else {
                output.addClass('success');
            }
        } catch (e) {
            try { await readProgress(output); } catch (ignored) {}
            status.addClass('error').text('Synchronisation failed.\n\n' + e.message).show();
        } finally {
            if (poller) clearInterval(poller);
            button.prop('disabled', false);
            working.hide().text('Counting new and updated records…');
        }
    });
    </script>
<?php endif; ?>
</body>
</html>
