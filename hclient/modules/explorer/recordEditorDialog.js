/**
 * recordEditorDialog.js - the new record editor (Explorer in editor mode) in a
 * jQuery dialog, for legacy pages (heurist-explorer plan 21 §2.4, Phase 6).
 *
 *   HeuristRecordEditor.open({recordId: 151}).then(function(result){ ... });
 *   HeuristRecordEditor.open({rectypeId: 12});
 *
 * The dialog holds an iframe with hclient/modules/explorer/explorerViewer.html; its
 * bridge `heuristExplorerHost` gives the bootstrap (runtimeMode "editor", the record
 * in `state`) and receives `onRecordSaved` / `onEditorClosed`. The promise resolves
 * with {saved, recordId, record} when the editor closes. Closing the dialog asks the
 * editor first (unsaved changes are confirmed there).
 *
 * Legacy callers of the old record editor are not switched to it yet (plan 21).
 *
 * @project     Heurist academic knowledge management system
 * @package     hclient.modules
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2026 Heurist Network Association. All rights reserved.
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       8.0
 */
(function() {
    'use strict';

    /** Bootstrap of the editor mode for a record or a new record of a type. */
    function editorBootstrap(target, options) {
        var hapi = window.hWin && window.hWin.HAPI4;
        var lang = hapi && typeof hapi.getLangCode3 === 'function'
            ? hapi.getLangCode3(hapi.get_prefs_def('layout_language', 'eng'), 'eng')
            : 'eng';
        var recordId = Number(target.recordId) || 0;
        return {
            runtime: {
                runtimeMode: 'editor',
                language: lang,
                database: options.database || (hapi && hapi.database),
                apiBaseUrl: options.apiBaseUrl || (hapi && hapi.baseURL + 'api'),
                baseUrl: hapi ? hapi.baseURL : null,
                moduleUrls: $.extend(true, {}, options.moduleUrls || {})
            },
            settings: {},
            state: recordId > 0 ? { recordId: recordId } : { rectypeId: target.rectypeId, access: target.access || null }
        };
    }

    /**
     * Open the new record editor in a dialog.
     *
     * @param {{recordId?: number, rectypeId?: number|string, access?: object}} target Record to edit,
     *        or the record type of a new record.
     * @param {{title?: string, database?: string, apiBaseUrl?: string, onSaved?: Function}} [options]
     * @returns {Promise<{saved: boolean, recordId: number, record: object|null}>} When the editor closes.
     */
    function open(target, options) {
        target = target || {};
        options = options || {};
        var hapi = window.hWin && window.hWin.HAPI4;
        var url = (options.explorerApplicationUrl || (hapi ? hapi.baseURL + 'hclient/modules/explorer/explorerViewer.html' : null));
        if (!url) return Promise.reject(new Error('Heurist base URL is not known'));
        var bootstrap = editorBootstrap(target, options);

        return new Promise(function(resolve) {
            var settled = false;
            var result = { saved: false, recordId: Number(target.recordId) || 0, record: null };
            var $container = $('<div>').css({ padding: 0, overflow: 'hidden' }).appendTo('body');
            var frame = $('<iframe>').attr({ title: 'Record editor', frameborder: '0' })
                .css({ width: '100%', height: '100%', border: 0, display: 'block' })
                .appendTo($container)[0];

            function finish() {
                if (settled) return;
                settled = true;
                if ($container.hasClass('ui-dialog-content')) $container.dialog('destroy');
                $container.remove();
                resolve(result);
            }

            function editorApi() {
                try {
                    return frame.contentWindow && frame.contentWindow.heuristExplorer;
                } catch (error) {
                    return null;
                }
            }

            function installBridge() {
                frame.heuristExplorerHost = {
                    getConfiguration: function() { return $.extend(true, {}, bootstrap); },
                    onRecordSaved: function(detail) {
                        result.saved = true;
                        result.record = detail && detail.record || result.record;
                        result.recordId = Number(result.record && result.record.rec_ID) || result.recordId;
                        if (typeof options.onSaved === 'function') options.onSaved(detail);
                    },
                    onEditorClosed: function(detail) {
                        if (detail) {
                            result.saved = result.saved || detail.saved === true;
                            result.recordId = Number(detail.recordId) || result.recordId;
                            result.record = detail.record || result.record;
                        }
                        finish();
                    }
                };
            }

            installBridge();
            $(frame).on('load', installBridge);
            frame.src = url;

            $container.dialog({
                title: options.title || (result.recordId > 0 ? 'Edit record' : 'New record'),
                modal: true,
                width: Math.min(1200, Math.max(700, $(window).width() - 80)),
                height: Math.min(900, Math.max(520, $(window).height() - 60)),
                resizable: true,
                // the dialog close button: the editor asks about unsaved changes first
                beforeClose: function() {
                    if (settled) return true;
                    var api = editorApi();
                    if (!api || typeof api.close !== 'function') return true;
                    Promise.resolve(api.close()).then(function(closed) {
                        // the editor reports onEditorClosed itself; a page without a record closes here
                        if (closed && !settled) finish();
                    });
                    return false;
                },
                close: function() { finish(); }
            });
        });
    }

    window.HeuristRecordEditor = { open: open };
})();
