<?php
/**
* testMapControllers.php - Browser test for MapPublishedController and UserController
*
* Verifies the new FrontController-based map publication and user preference
* controller actions using the current browser session. The script is intended
* for development/testing only and should not be deployed permanently.
*
* @project     Heurist academic knowledge management system
* @package     Tests
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

$db = isset($_GET['db']) && preg_match('/^[A-Za-z0-9_]+$/', $_GET['db'])
    ? $_GET['db']
    : 'osmak_mapping';
$base = isset($_GET['base']) ? trim($_GET['base']) : '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Heurist controller tests</title>
<style>
body { font: 14px/1.45 Arial, sans-serif; margin: 24px; color: #222; }
h1 { font-size: 20px; }
.controls { margin-bottom: 18px; }
.controls label { margin-right: 14px; }
input[type=text] { width: 260px; padding: 4px 6px; }
button { padding: 5px 12px; cursor: pointer; }
table { border-collapse: collapse; width: 100%; max-width: 1100px; }
th, td { border-bottom: 1px solid #ddd; padding: 7px 9px; text-align: left; vertical-align: top; }
th { background: #f5f5f5; }
.pass { color: #197128; font-weight: bold; }
.fail { color: #a21c1c; font-weight: bold; }
.skip { color: #777; font-weight: bold; }
#summary { margin: 14px 0; font-weight: bold; }
pre { margin: 2px 0; white-space: pre-wrap; word-break: break-word; font-size: 12px; }
.note { color: #666; max-width: 1000px; }
</style>
</head>
<body>
<h1>MapPublishedController / UserController tests</h1>
<p class="note">
Uses the current browser login session. It tries both Heurist FrontController locations,
updates the <code>heurist-map</code> preference temporarily and restores its original value,
and creates/deletes one temporary published map.
</p>
<div class="controls">
    <label>Database <input id="db" type="text" value="<?=htmlspecialchars($db, ENT_QUOTES, 'UTF-8')?>"></label>
    <label>Heurist base <input id="base" type="text" value="<?=htmlspecialchars($base, ENT_QUOTES, 'UTF-8')?>" placeholder="auto, e.g. /heurist/"></label>
    <button id="run">Run tests</button>
</div>
<div id="endpoint"></div>
<div id="summary"></div>
<table>
<thead><tr><th>#</th><th>Test</th><th>Result</th><th>Details</th></tr></thead>
<tbody id="results"></tbody>
</table>

<script>
(function () {
    'use strict';

    const results = document.getElementById('results');
    const summary = document.getElementById('summary');
    const endpointInfo = document.getElementById('endpoint');
    const runButton = document.getElementById('run');

    let testNo = 0;
    let passed = 0;
    let failed = 0;

    function normaliseBase(value) {
        value = String(value || '').trim();
        if (!value) return '';
        if (!/^https?:\/\//i.test(value)) {
            if (!value.startsWith('/')) value = '/' + value;
            value = location.origin + value;
        }
        return value.replace(/\/+$/, '') + '/';
    }

    function detectBase() {
        const requested = normaliseBase(document.getElementById('base').value);
        if (requested) return requested;

        // Normally this test is copied into the Heurist root folder. If it is
        // placed below it, prefer the path through the first /heurist/ segment.
        const path = location.pathname;
        const marker = '/heurist/';
        const pos = path.toLowerCase().indexOf(marker);
        if (pos >= 0) {
            return location.origin + path.substring(0, pos + marker.length);
        }

        return new URL('./', location.href).href;
    }

    function resetResults() {
        results.innerHTML = '';
        summary.textContent = '';
        endpointInfo.textContent = '';
        testNo = 0;
        passed = 0;
        failed = 0;
    }

    function addResult(name, ok, details, status) {
        testNo += 1;
        if (status === 'skip') {
            // no counters
        } else if (ok) {
            passed += 1;
        } else {
            failed += 1;
        }

        const tr = document.createElement('tr');
        const state = status === 'skip' ? 'SKIP' : (ok ? 'PASS' : 'FAIL');
        const cls = status === 'skip' ? 'skip' : (ok ? 'pass' : 'fail');
        tr.innerHTML = '<td></td><td></td><td></td><td></td>';
        tr.children[0].textContent = testNo;
        tr.children[1].textContent = name;
        tr.children[2].innerHTML = '<span class="' + cls + '">' + state + '</span>';
        const pre = document.createElement('pre');
        pre.textContent = details == null ? '' :
            (typeof details === 'string' ? details : JSON.stringify(details, null, 2));
        tr.children[3].appendChild(pre);
        results.appendChild(tr);
    }

    function isOkJson(json) {
        if (!json || typeof json !== 'object') return false;
        if (!Object.prototype.hasOwnProperty.call(json, 'status')) return false;
        const status = json.status;
        return status === 0 || status === '0' || String(status).toLowerCase() === 'ok';
    }

    async function request(url, params, method) {
        const options = {
            method: method || 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        };

        if (options.method === 'POST') {
            const body = new URLSearchParams();
            Object.entries(params || {}).forEach(([key, value]) => {
                if (value !== undefined && value !== null) body.set(key, value);
            });
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
            options.body = body.toString();
        } else if (params) {
            const target = new URL(url, location.href);
            Object.entries(params).forEach(([key, value]) => {
                if (value !== undefined && value !== null) target.searchParams.set(key, value);
            });
            url = target.toString();
        }

        const response = await fetch(url, options);
        const text = await response.text();
        let json = null;
        try { json = JSON.parse(text); } catch (e) { /* preserve raw response */ }
        return { response, text, json, url };
    }

    function controllerUrl(endpoint, db, controller, action, extra) {
        const url = new URL(endpoint, location.href);
        url.searchParams.set('db', db);
        url.searchParams.set('controller', controller);
        url.searchParams.set('action', action);
        Object.entries(extra || {}).forEach(([key, value]) => {
            if (value !== undefined && value !== null) url.searchParams.set(key, value);
        });
        return url.toString();
    }

    async function chooseEndpoint(base, db) {
        const candidates = [
            base,
            base.replace(/\/+$/, '') + '/hserv/cotroller'
        ];
        const unique = [...new Set(candidates)];
        const attempts = [];

        for (const endpoint of unique) {
            const url = controllerUrl(endpoint, db, 'UserController', 'get_prefs', { key: 'heurist-map' });
            try {
                const result = await request(url);
                attempts.push({ endpoint, http: result.response.status, json: result.json });
                if (result.response.ok && isOkJson(result.json)) {
                    return { endpoint, attempts };
                }
            } catch (error) {
                attempts.push({ endpoint, error: error.message });
            }
        }
        throw new Error('Neither controller endpoint succeeded. ' + JSON.stringify(attempts));
    }

    function sameValue(a, b) {
        return JSON.stringify(a) === JSON.stringify(b);
    }

    async function runTests() {
        resetResults();
        runButton.disabled = true;

        const db = document.getElementById('db').value.trim();
        const base = detectBase();
        document.getElementById('base').value = base;

        let endpoint;
        let originalPreference;
        let originalPreferenceKnown = false;
        let mapId = null;

        try {
            const selected = await chooseEndpoint(base, db);
            endpoint = selected.endpoint;
            endpointInfo.innerHTML = '<p><strong>Endpoint:</strong> <code>' + endpoint + '</code></p>';
            addResult('Find working FrontController endpoint', true, selected.attempts);
        } catch (error) {
            addResult('Find working FrontController endpoint', false, error.message);
            summary.textContent = 'Cannot continue: no authenticated controller endpoint was found.';
            runButton.disabled = false;
            return;
        }

        try {
            // USER CONTROLLER -------------------------------------------------
            let result = await request(controllerUrl(endpoint, db, 'UserController', 'get_prefs'));
            addResult('UserController get_prefs (all)', result.response.ok && isOkJson(result.json) && result.json.data && typeof result.json.data === 'object', result.json || result.text);

            result = await request(controllerUrl(endpoint, db, 'UserController', 'get_prefs', { key: 'heurist-map' }));
            const getOriginalOk = result.response.ok && isOkJson(result.json);
            if (getOriginalOk) {
                originalPreference = result.json.data;
                originalPreferenceKnown = true;
            }
            addResult('UserController get_prefs key=heurist-map', getOriginalOk, result.json || result.text);

            const marker = 'controller-test-' + Date.now();
            const testPreference = {
                format: 'heurist-map-settings',
                version: 1,
                options: { ui: { enabled: true, showPublish: false } },
                config: { currentResultsLayer: { options: { maxAllowedFeatures: 17, dynamicRequests: false } } },
                _testMarker: marker
            };

            result = await request(
                controllerUrl(endpoint, db, 'UserController', 'save_prefs'),
                { key: 'heurist-map', value: JSON.stringify(testPreference) },
                'POST'
            );
            addResult('UserController save_prefs key/value', result.response.ok && isOkJson(result.json), result.json || result.text);

            result = await request(controllerUrl(endpoint, db, 'UserController', 'get_prefs', { key: 'heurist-map' }));
            const keyedRoundTripOk = result.response.ok && isOkJson(result.json) && sameValue(result.json.data, testPreference);
            addResult('UserController keyed preference round-trip', keyedRoundTripOk, result.json || result.text);

            if (originalPreferenceKnown) {
                result = await request(
                    controllerUrl(endpoint, db, 'UserController', 'save_prefs'),
                    { key: 'heurist-map', value: typeof originalPreference === 'string' ? originalPreference : JSON.stringify(originalPreference) },
                    'POST'
                );
                addResult('Restore original heurist-map preference', result.response.ok && isOkJson(result.json), result.json || result.text);
            }

            // MAP CONTROLLER --------------------------------------------------
            const mapMarker = 'published-controller-test-' + Date.now();
            const published = {
                options: {
                    ui: {
                        enabled: true,
                        placement: 'overlay',
                        position: 'top-right',
                        initiallyExpanded: true,
                        showCurrentDocument: true,
                        showMapDocuments: true,
                        showLayers: true,
                        showBaseMaps: true,
                        showLegend: true,
                        showZoomControl: true,
                        showSearch: false,
                        showPublish: false
                    }
                },
                config: {
                    dynamicDocument: { enabled: true, title: mapMarker },
                    currentResultsLayer: {
                        title: 'Controller test',
                        visible: true,
                        selectable: true,
                        options: {
                            markerClustering: false,
                            maxAllowedFeatures: 17,
                            dynamicRequests: false
                        }
                    }
                },
                state: {
                    query: 'ids:1',
                    _testMarker: mapMarker
                },
                // This must be stripped by MapPublishedController's allowlist.
                accessToken: 'MUST-NOT-BE-SAVED'
            };

            result = await request(
                controllerUrl(endpoint, db, 'MapPublishedController', 'save'),
                { data: JSON.stringify(published) },
                'POST'
            );
            const saveMapOk = result.response.ok && isOkJson(result.json) && result.json.data && result.json.data.id;
            if (saveMapOk) mapId = result.json.data.id;
            addResult('MapPublishedController save', Boolean(saveMapOk), result.json || result.text);

            if (mapId) {
                result = await request(controllerUrl(endpoint, db, 'MapPublishedController', 'get', { id: mapId }));
                const got = result.json && result.json.data;
                const getMapOk = result.response.ok && isOkJson(result.json) && got &&
                    got.format === 'heurist-map-publish' && got.version === 1 &&
                    got.config && got.config.dynamicDocument && got.config.dynamicDocument.title === mapMarker &&
                    got.state && got.state._testMarker === mapMarker &&
                    !Object.prototype.hasOwnProperty.call(got, 'accessToken');
                addResult('MapPublishedController get + saved envelope', Boolean(getMapOk), result.json || result.text);

                const showUrl = controllerUrl(endpoint, db, 'MapPublishedController', 'show', { id: mapId });
                const showResponse = await fetch(showUrl, { credentials: 'same-origin', cache: 'no-store' });
                const showText = await showResponse.text();
                const showOk = showResponse.ok && /<!doctype html>/i.test(showText) &&
                    showText.includes('window.heuristMapPublished=') &&
                    showText.includes('heurist-map.js');
                addResult('MapPublishedController show standalone HTML', showOk, 'HTTP ' + showResponse.status + '; ' + showText.substring(0, 350));

                result = await request(
                    controllerUrl(endpoint, db, 'MapPublishedController', 'delete', { id: mapId }),
                    {},
                    'POST'
                );
                const deleteOk = result.response.ok && isOkJson(result.json) && result.json.data === true;
                addResult('MapPublishedController delete', deleteOk, result.json || result.text);

                if (deleteOk) {
                    const deletedId = mapId;
                    mapId = null;
                    result = await request(controllerUrl(endpoint, db, 'MapPublishedController', 'get', { id: deletedId }));
                    const goneOk = !isOkJson(result.json);
                    addResult('MapPublishedController get after delete fails', goneOk, result.json || result.text);
                }
            }

        } catch (error) {
            addResult('Unexpected test exception', false, error.stack || error.message);
        } finally {
            // Best-effort cleanup if a test failed after creating a map.
            if (endpoint && mapId) {
                try {
                    await request(controllerUrl(endpoint, db, 'MapPublishedController', 'delete', { id: mapId }), {}, 'POST');
                } catch (e) { /* report already contains the actual failure */ }
            }

            // Best-effort preference restoration after an exception occurring
            // between the write and the normal restore step.
            if (endpoint && originalPreferenceKnown) {
                try {
                    const check = await request(controllerUrl(endpoint, db, 'UserController', 'get_prefs', { key: 'heurist-map' }));
                    if (isOkJson(check.json) && check.json.data && check.json.data._testMarker) {
                        await request(
                            controllerUrl(endpoint, db, 'UserController', 'save_prefs'),
                            { key: 'heurist-map', value: typeof originalPreference === 'string' ? originalPreference : JSON.stringify(originalPreference) },
                            'POST'
                        );
                    }
                } catch (e) { /* best effort */ }
            }

            summary.textContent = 'Completed: ' + passed + ' passed, ' + failed + ' failed.';
            summary.className = failed ? 'fail' : 'pass';
            runButton.disabled = false;
        }
    }

    runButton.addEventListener('click', runTests);
})();
</script>
</body>
</html>
