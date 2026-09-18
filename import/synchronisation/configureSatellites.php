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
        fieldset { margin: 14px 0; padding: 14px; max-width: 1100px; }
        .form-label { display: inline-block; width: 220px; margin: 5px 0; }
        input[type=text], input[type=password], input[type=url], input[type=number] { width: 360px; }
        .role-choice { display: inline-block; margin: 4px 30px 4px 0; }
        table { border-collapse: collapse; width: 100%; margin-top: 8px; }
        th, td { border-bottom: 1px solid #ccc; padding: 5px; text-align: left; white-space: nowrap; }
        table input { width: 95% !important; }
        td.database-cell { min-width: 165px; }
        td.database-cell input { width: 88px !important; }
        .message { margin: 12px 0; padding: 10px; display: none; max-width: 1050px; }
        .error { background: #fbe3e4; color: #8a1f11; }
        .success { background: #e6efc2; color: #264409; }
        .hint { color: #555; font-size: 0.92em; margin: 4px 0 8px 224px; }
        .save-row { display: flex; align-items: center; gap: 16px; margin-top: 12px; }
        .secret-note { color: #555; }
        .working { display: none; }
        .working::before { content: ''; display: inline-block; width: 13px; height: 13px; margin-right: 7px;
            border: 2px solid #aaa; border-top-color: #2d7d32; border-radius: 50%; vertical-align: -2px;
            animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        #reference-dialog { display:none; }
        #reference-search { width: 360px; margin-bottom: 8px; }
        #reference-list { width: 100%; border-collapse: collapse; }
        #reference-list tbody tr { cursor: pointer; }
        #reference-list tbody tr:hover { background: #eaf3ff; }
        #reference-list td { white-space: normal; }
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
    <label class="role-choice"><input type="radio" name="role" value="master"> Master</label>
    <label class="role-choice"><input type="radio" name="role" value="satellite"> Satellite</label>
</fieldset>

<fieldset id="master-panel">
    <legend>Authorised satellites (highest priority first)</legend>
    <table id="satellites">
        <thead><tr><th>Registered database</th><th>Name</th><th>Heurist URL (optional)</th><th>Priority</th><th>Enabled</th><th>Shared secret</th><th></th></tr></thead>
        <tbody></tbody>
    </table>
    <p class="hint" style="margin-left:0">Select databases from the Reference Index. If the URL is blank, the satellite is assumed to use this Heurist server.</p>
    <button id="add-satellite" type="button">Add satellite</button>
</fieldset>

<fieldset id="satellite-panel">
    <legend>Master database</legend>
    <div><span class="form-label">Registered database</span><input id="master-id" type="number" min="1"> <button id="browse-master" type="button">Browse Reference Index</button></div>
    <div><label class="form-label" for="master-database">Database name</label><input id="master-database" type="text"></div>
    <div><label class="form-label" for="master-url">Heurist codebase URL</label><input id="master-url" type="url" placeholder="https://example.org/HEURIST/h7-alpha/"></div>
    <p class="hint">The main Heurist URL only—not a script URL and not <code>?db=database_name</code>. A pasted database URL will be corrected automatically.</p>
    <div><label class="form-label" for="master-secret">Shared secret</label><input id="master-secret" type="password" autocomplete="new-password"></div>
</fieldset>

<div class="save-row">
    <button id="save" class="ui-button-action" type="button">Save configuration</button>
    <span id="save-working" class="working">Saving configuration…</span>
    <span class="secret-note">A row of asterisks means a secret is already set. Leave it unchanged to retain that secret.</span>
</div>

<div id="reference-dialog" title="Select a registered database">
    <input id="reference-search" type="search" placeholder="Filter by ID, database name or description">
    <div id="reference-status">Loading Reference Index…</div>
    <table id="reference-list">
        <thead><tr><th>ID</th><th>Database</th><th>Description</th><th>URL</th></tr></thead>
        <tbody></tbody>
    </table>
</div>

<script>
(() => {
    const db = <?=json_encode($dbname)?>;
    const endpoint = '../../hserv/controller/synchronisationController.php?db=' + encodeURIComponent(db);
    let messageTimer = null;
    let referenceDatabases = null;
    let referenceTarget = null;

    const plainText = value => $('<div>').html(String(value || '').replace(/<br\s*\/?>/gi, '\n')).text().trim();
    const diagnostic = result => [result?.message || result?.msg, result?.sysmsg].filter(Boolean).map(plainText).join('\n');
    const message = (text, error, autoHide = false) => {
        clearTimeout(messageTimer);
        $('#message').text(text).removeClass('error success').addClass(error ? 'error' : 'success').stop(true, true).show();
        if (autoHide) messageTimer = setTimeout(() => $('#message').fadeOut(500), 5000);
    };
    const setSecretState = (input, isSet) => input
        .attr('placeholder', isSet ? '******** (set)' : '')
        .attr('data-secret-set', isSet ? '1' : '0');
    const normaliseDatabaseUrl = value => {
        try {
            const url = new URL(value);
            const database = url.searchParams.get('db') || '';
            const hservAt = url.pathname.indexOf('/hserv/');
            if (hservAt >= 0) url.pathname = url.pathname.substring(0, hservAt + 1);
            url.search = '';
            url.hash = '';
            return {url: url.toString().replace(/\/$/, ''), database};
        } catch (e) {
            return {url: String(value || '').replace(/\/$/, ''), database: ''};
        }
    };
    const request = async payload => {
        const response = await fetch(endpoint, {
            method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
        });
        const text = await response.text();
        try { return JSON.parse(text); }
        catch (e) { throw new Error('The server returned a non-JSON response (HTTP ' + response.status + '): ' + plainText(text).substring(0, 500)); }
    };

    const addRow = (item = {}) => {
        const id = $('<input type="number" min="1" class="dbid">').val(item.databaseID || '');
        const browse = $('<button type="button" title="Select from the Heurist Reference Index">Browse…</button>');
        const secret = setSecretState($('<input type="password" class="secret" autocomplete="new-password">'), !!item.hasSharedSecret);
        const row = $('<tr>')
            .append($('<td class="database-cell">').append(id, ' ', browse))
            .append($('<td>').append($('<input type="text" class="name">').val(item.name || '')))
            .append($('<td>').append($('<input type="url" class="url">').val(item.url || '')))
            .append($('<td>').append($('<input type="number" min="0" max="100" class="priority">').val(item.priority ?? 50)))
            .append($('<td>').append($('<input type="checkbox" class="enabled">').prop('checked', item.enabled !== false)))
            .append($('<td>').append(secret))
            .append($('<td>').append($('<button type="button">Remove</button>').on('click', () => row.remove())));
        browse.on('click', () => openReferenceBrowser(record => {
            row.find('.dbid').val(record.id);
            row.find('.name').val(record.database);
            row.find('.url').val(record.url);
        }));
        $('#satellites tbody').append(row);
    };
    const currentRole = () => $('input[name=role]:checked').val() || 'master';
    const showRole = () => {
        const master = currentRole() === 'master';
        $('#master-panel').toggle(master);
        $('#satellite-panel').toggle(!master);
    };

    const decodeReferenceData = data => {
        const fields = data?.fields || [];
        const records = Array.isArray(data?.records) ? data.records : Object.values(data?.records || {});
        return records.map(record => {
            const value = name => Array.isArray(record) ? record[fields.indexOf(name)] : record[name];
            const id = Number(value('rec_ID'));
            const rawUrl = String(value('rec_URL') || '');
            const parts = normaliseDatabaseUrl(rawUrl);
            return {id, database: parts.database, description: String(value('rec_Title') || ''), url: parts.url};
        }).filter(item => item.id > 0 && item.database);
    };
    const loadReferenceDatabases = async () => {
        if (referenceDatabases) return referenceDatabases;
        const url = '../../hserv/controller/record_search.php?db=' + encodeURIComponent(db) +
            '&remote=master&detail=header&limit=5000';
        const response = await fetch(url, {credentials: 'same-origin'});
        const text = await response.text();
        let result;
        try { result = JSON.parse(text); }
        catch (e) { throw new Error('The Reference Index returned a non-JSON response (HTTP ' + response.status + ').'); }
        if (result.status !== 'ok') throw new Error(diagnostic(result) || 'The Reference Index could not be read.');
        referenceDatabases = decodeReferenceData(result.data).sort((a, b) => a.database.localeCompare(b.database));
        return referenceDatabases;
    };
    const renderReferenceList = filter => {
        const needle = String(filter || '').toLowerCase();
        const tbody = $('#reference-list tbody').empty();
        const matches = referenceDatabases.filter(item => !needle ||
            String(item.id).includes(needle) || item.database.toLowerCase().includes(needle) ||
            item.description.toLowerCase().includes(needle));
        matches.slice(0, 500).forEach(item => {
            $('<tr>').append($('<td>').text(item.id), $('<td>').text(item.database),
                $('<td>').text(item.description), $('<td>').text(item.url))
                .on('click', () => { referenceTarget(item); $('#reference-dialog').dialog('close'); })
                .appendTo(tbody);
        });
        $('#reference-status').text(matches.length ?
            (matches.length > 500 ? 'Showing the first 500 of ' + matches.length + ' matches.' : matches.length + ' registered databases.') :
            'No matching registered databases.');
    };
    const openReferenceBrowser = async callback => {
        referenceTarget = callback;
        $('#reference-dialog').dialog({modal:true, width:1000, height:650}).dialog('open');
        $('#reference-status').text('Loading Reference Index…');
        $('#reference-list tbody').empty();
        try {
            await loadReferenceDatabases();
            renderReferenceList($('#reference-search').val());
        } catch (e) {
            $('#reference-status').text(e.message);
        }
    };

    $('input[name=role]').on('change', showRole);
    $('#reference-search').on('input', function() { if (referenceDatabases) renderReferenceList(this.value); });
    $('#add-satellite').on('click', () => addRow());
    $('#browse-master').on('click', () => openReferenceBrowser(record => {
        $('#master-id').val(record.id);
        $('#master-database').val(record.database);
        $('#master-url').val(record.url);
    }));
    $('#master-url').on('change blur', function() {
        const parts = normaliseDatabaseUrl(this.value);
        this.value = parts.url;
        if (parts.database && !$('#master-database').val()) $('#master-database').val(parts.database);
    });

    $('#save').on('click', async () => {
        const role = currentRole();
        const config = {role};
        if (role === 'master') {
            config.satellites = $('#satellites tbody tr').map(function() {
                const url = normaliseDatabaseUrl($(this).find('.url').val()).url;
                return {databaseID: Number($(this).find('.dbid').val()), name: $(this).find('.name').val(), url,
                    priority: Number($(this).find('.priority').val()), enabled: $(this).find('.enabled').prop('checked'),
                    sharedSecret: $(this).find('.secret').val()};
            }).get();
        } else {
            const masterUrl = normaliseDatabaseUrl($('#master-url').val());
            if (masterUrl.database && !$('#master-database').val()) $('#master-database').val(masterUrl.database);
            $('#master-url').val(masterUrl.url);
            config.master = {databaseID: Number($('#master-id').val()), database: $('#master-database').val(),
                url: masterUrl.url, sharedSecret: $('#master-secret').val()};
        }
        const button = $('#save').prop('disabled', true);
        $('#save-working').show();
        try {
            const result = await request({action: 'save_config', config});
            if (result.status !== 'ok') throw new Error(diagnostic(result) || 'Configuration could not be saved.');
            message('Synchronisation configuration saved.', false, true);
            $('.secret, #master-secret').val('');
            const saved = result.data || {};
            (saved.satellites || []).forEach(item => {
                const row = $('#satellites tbody tr').filter(function() {
                    return Number($(this).find('.dbid').val()) === Number(item.databaseID);
                }).first();
                setSecretState(row.find('.secret'), !!item.hasSharedSecret);
            });
            setSecretState($('#master-secret'), !!saved.master?.hasSharedSecret);
        } catch (e) { message(e.message, true); }
        finally { button.prop('disabled', false); $('#save-working').hide(); }
    });

    request({action: 'get_config'}).then(result => {
        if (result.status !== 'ok') throw new Error(diagnostic(result) || 'Configuration could not be loaded.');
        const config = result.data || {};
        $('input[name=role][value="' + (config.role || 'master') + '"]').prop('checked', true);
        (config.satellites || []).forEach(addRow);
        if (config.master) {
            $('#master-id').val(config.master.databaseID || '');
            $('#master-database').val(config.master.database || '');
            $('#master-url').val(config.master.url || '');
            setSecretState($('#master-secret'), !!config.master.hasSharedSecret);
        }
        showRole();
    }).catch(e => message(e.message, true));
})();
</script>
<?php endif; ?>
</body>
</html>
