<?php

use hserv\synchronisation\SyncFeature;

define('MANAGER_REQUIRED', 1);
define('PDIR', '../../');
require_once dirname(__FILE__).'/../../hclient/framecontent/initPageMin.php';

$enabled = SyncFeature::isEnabled();
$dbname = $system->dbname();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex,nofollow">
    <title>Configure satellites</title>
    <?php includeJQuery(); ?>
    <?php include_once dirname(__FILE__).'/../../hclient/framecontent/initPageCss.php'; ?>
    <style>
        body { margin: 18px 24px; color: #222; }
        fieldset { margin: 14px 0; padding: 14px; max-width: 850px; }
        label { display: inline-block; width: 180px; margin: 5px 0; }
        input[type=text], input[type=password], input[type=url], input[type=number], select { width: 360px; }
        table { border-collapse: collapse; width: 100%; margin-top: 8px; }
        th, td { border-bottom: 1px solid #ccc; padding: 5px; text-align: left; }
        table input { width: 95% !important; }
        .message { margin: 12px 0; padding: 10px; display: none; }
        .error { background: #fbe3e4; color: #8a1f11; }
        .success { background: #e6efc2; color: #264409; }
    </style>
</head>
<body class="popup ui-heurist-populate">
<h2>Configure database synchronisation</h2>

<?php if (!$enabled): ?>
    <div class="message error" style="display:block"><?=htmlspecialchars(SyncFeature::UNAVAILABLE_MESSAGE)?></div>
    <script>alert(<?=json_encode(SyncFeature::UNAVAILABLE_MESSAGE)?>);</script>
<?php else: ?>
<p>All participating databases must be registered. Structure is controlled by the master; satellites may collect records and permitted new terms.</p>
<div id="message" class="message"></div>

<fieldset>
    <legend>Database role</legend>
    <label for="role">Role</label>
    <select id="role"><option value="master">Master</option><option value="satellite">Satellite</option></select>
</fieldset>

<fieldset id="master-panel">
    <legend>Authorised satellites (highest priority first)</legend>
    <table id="satellites">
        <thead><tr><th>DB ID</th><th>Name</th><th>URL (optional)</th><th>Priority</th><th>Enabled</th><th>Shared secret</th><th></th></tr></thead>
        <tbody></tbody>
    </table>
    <button id="add-satellite" type="button">Add satellite</button>
</fieldset>

<fieldset id="satellite-panel">
    <legend>Master database</legend>
    <div><label for="master-id">Registered database ID</label><input id="master-id" type="number" min="1"></div>
    <div><label for="master-database">Database name</label><input id="master-database" type="text"></div>
    <div><label for="master-url">Heurist URL</label><input id="master-url" type="url" placeholder="https://example.org/heurist"></div>
    <div><label for="master-secret">Shared secret</label><input id="master-secret" type="password" autocomplete="new-password"></div>
    <p>Leave the secret blank when editing to keep the current secret.</p>
</fieldset>

<button id="save" class="ui-button-action" type="button">Save configuration</button>

<script>
(() => {
    const db = <?=json_encode($dbname)?>;
    const endpoint = '../../hserv/controller/synchronisationController.php?db=' + encodeURIComponent(db);
    const message = (text, error) => {
        $('#message').text(text).removeClass('error success').addClass(error ? 'error' : 'success').show();
    };
    const addRow = (item = {}) => {
        const row = $('<tr>')
            .append($('<td>').append($('<input type="number" min="1" class="dbid">').val(item.databaseID || '')))
            .append($('<td>').append($('<input type="text" class="name">').val(item.name || '')))
            .append($('<td>').append($('<input type="url" class="url">').val(item.url || '')))
            .append($('<td>').append($('<input type="number" min="0" max="100" class="priority">').val(item.priority ?? 50)))
            .append($('<td>').append($('<input type="checkbox" class="enabled">').prop('checked', item.enabled !== false)))
            .append($('<td>').append($('<input type="password" class="secret" autocomplete="new-password">')))
            .append($('<td>').append($('<button type="button">Remove</button>').on('click', () => row.remove())));
        $('#satellites tbody').append(row);
    };
    const showRole = () => {
        const master = $('#role').val() === 'master';
        $('#master-panel').toggle(master);
        $('#satellite-panel').toggle(!master);
    };
    const request = (payload) => fetch(endpoint, {
        method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
    }).then(r => r.json());

    $('#role').on('change', showRole);
    $('#add-satellite').on('click', () => addRow());
    $('#save').on('click', async () => {
        const role = $('#role').val();
        const config = {role};
        if (role === 'master') {
            config.satellites = $('#satellites tbody tr').map(function() {
                return {
                    databaseID: Number($(this).find('.dbid').val()),
                    name: $(this).find('.name').val(),
                    url: $(this).find('.url').val(),
                    priority: Number($(this).find('.priority').val()),
                    enabled: $(this).find('.enabled').prop('checked'),
                    sharedSecret: $(this).find('.secret').val()
                };
            }).get();
        } else {
            config.master = {
                databaseID: Number($('#master-id').val()),
                database: $('#master-database').val(),
                url: $('#master-url').val(),
                sharedSecret: $('#master-secret').val()
            };
        }
        try {
            const result = await request({action: 'save_config', config});
            if (result.status !== 'ok') throw new Error(result.message || result.msg || 'Configuration could not be saved');
            message('Synchronisation configuration saved.', false);
            $('.secret, #master-secret').val('');
        } catch (e) { message(e.message, true); }
    });

    request({action: 'get_config'}).then(result => {
        if (result.status !== 'ok') throw new Error(result.message || result.msg || 'Configuration could not be loaded');
        const config = result.data || {};
        $('#role').val(config.role || 'master');
        (config.satellites || []).forEach(addRow);
        if (config.master) {
            $('#master-id').val(config.master.databaseID || '');
            $('#master-database').val(config.master.database || '');
            $('#master-url').val(config.master.url || '');
        }
        showRole();
    }).catch(e => message(e.message, true));
})();
</script>
<?php endif; ?>
</body>
</html>
