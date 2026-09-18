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
    <p>This stage creates or resumes a persistent master session, allocates permanent master IDs for new satellite records, and transactionally updates their local IDs and record pointers.</p>
    <p>It does not yet upload the record content to the master.</p>
    <button id="start-sync" type="button" class="ui-button-action">Allocate IDs for new records</button>
    <pre id="result" style="display:none;white-space:pre-wrap;margin-top:15px;padding:10px;background:#f5f5f5"></pre>
    <script>
    $('#start-sync').on('click', async function() {
        const button = $(this).prop('disabled', true);
        const output = $('#result').show().text('Contacting master…');
        try {
            const response = await fetch('../../hserv/controller/synchronisationController.php?db=' +
                encodeURIComponent(<?=json_encode($system->dbname())?>), {
                    method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'start_sync'})
                });
            const result = await response.json();
            if (result.status !== 'ok') throw new Error(result.message || result.msg || 'Synchronisation could not be started');
            output.text('Session: ' + result.data.sessionID + '\nState: ' + result.data.state +
                '\nNew records allocated: ' + result.data.newRecordsAllocated +
                '\nInterrupted mappings completed: ' + result.data.resumedMappings +
                '\nJournal scanned through: ' + result.data.journalScannedThrough +
                (result.data.masterSessionResumed ? '\nExisting master session resumed.' : '\nNew master session created.'));
        } catch (e) {
            output.text('Error: ' + e.message);
        } finally {
            button.prop('disabled', false);
        }
    });
    </script>
<?php endif; ?>
</body>
</html>
