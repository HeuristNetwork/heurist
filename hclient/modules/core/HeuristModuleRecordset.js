/**
 * HeuristModuleRecordset.js - Base class for record-driven Heurist modules
 *
 * Adds search-query, HRecordSet boundary conversion and host search/selection
 * synchronization to HeuristModuleViewer. Concrete modules only implement the
 * operations that render a query or selection.
 *
 * @project     Heurist academic knowledge management system
 * @package     hclient.modules
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @since       7.0
 */

/** Maximum result set represented as an explicit ids: query. */
const HEURIST_MODULE_IDS_QUERY_LIMIT = 5000;

/** Base class for viewers driven by Heurist record searches. */
class HeuristModuleRecordset extends HeuristModuleViewer {

    constructor(element, options) {
        super(element, options);
        this._events = null;
        this._currentQuery = null;

        if (this.options.recordset) {
            var recordset = this.options.recordset;
            this.options.recordset = null;
            if (!this.options.query) {
                this.options.query = this._recordsetToQuery(recordset);
            }
        }
    }

    /** Bind the standard Heurist search, selection and session events. */
    _bindHostEvents() {
        if (!this.options.eventbased) return;

        var that = this;
        var hapi = window.hWin && window.hWin.HAPI4;
        if (!hapi || !hapi.Event) return;

        this._events = hapi.Event.ON_CREDENTIALS
            + ' ' + hapi.Event.ON_LAYOUT_RESIZE
            + ' ' + hapi.Event.ON_REC_SELECT
            + ' ' + hapi.Event.ON_SYSTEM_INITED
            + ' ' + hapi.Event.ON_REC_SEARCH_FINISH
            + ' ' + hapi.Event.ON_REC_SEARCHSTART;

        $(this.document).on(this._events, function(event, data) {
            if (event.type === hapi.Event.ON_CREDENTIALS) {
                if (that.options.query != null && !hapi.has_access()) {
                    that.options.query = null;
                    that.options.selection = null;
                    that.setQuery(null);
                }
            } else if (event.type === hapi.Event.ON_LAYOUT_RESIZE) {
                that._scheduleResize(400);
            } else if (event.type === hapi.Event.ON_REC_SEARCH_FINISH) {
                if (!((data && data.search_realm === 'mapping_recordset') ||
                    that._isSameRealm(data))) return;
                var result = that._searchResult(data);
                that.setQuery(that._currentResultsQuery(
                    result.ids, data && data.query, result.total
                ));
            } else if (event.type === hapi.Event.ON_REC_SEARCHSTART) {
                if (!that._isSameRealm(data)) return;
                that.options.query = null;
                that.options.selection = null;
                that.clearSelection();
                if (!(data && !data.reset && data.q !== '')) that.setQuery(null);
            } else if (event.type === hapi.Event.ON_REC_SELECT) {
                if (!that._isSameRealm(data) ||
                    (data && data.source === that.element.attr('id'))) return;
                if (data && data.reset) {
                    that.clearSelection();
                } else {
                    that._doVisualizeSelection(
                        hapi.getSelection(data && data.selection, true)
                    );
                }
            } else if (event.type === hapi.Event.ON_SYSTEM_INITED) {
                that.refresh();
            }
        });
    }

    /** Apply a host selection using the concrete module's selection renderer. */
    _doVisualizeSelection(selection) {
        return this.setSelection(selection, {replace: true, zoom: true});
    }

    /** Queue query rendering until the concrete module API is ready. */
    setQuery(query, options) {
        this.options.query = this._normalizeQuery(query);
        return this._enqueueOrRun('_setQueryNow', [this.options.query, options || {}]);
    }

    /** Accept HRecordSet only at the host boundary and immediately discard it. */
    setRecordSet(recordset, options) {
        return this.setQuery(this._recordsetToQuery(recordset), options);
    }

    /** Convert the stable HRecordSet contract to a query. */
    _recordsetToQuery(recordset) {
        if (!recordset) return null;
        if (typeof recordset === 'string') return this._normalizeQuery(recordset);
        if ($.isPlainObject(recordset) && recordset.q != null) {
            return this._normalizeQuery(recordset.q);
        }

        var request = recordset.getRequest();
        var query = request && request.query !== undefined
            ? request.query : request && request.q;
        return this._currentResultsQuery(
            this._normalizeRecordIds(recordset.getOrder()),
            query,
            recordset.count_total()
        );
    }

    /** Read a completed search event using its documented response contracts. */
    _searchResult(data) {
        data = data || {};
        if (data.response && Array.isArray(data.response.ids)) {
            var responseIds = this._normalizeRecordIds(data.response.ids);
            var responseTotal = Number(data.response.total);
            return {
                ids: responseIds,
                total: Number.isFinite(responseTotal) && responseTotal >= 0
                    ? responseTotal : responseIds.length
            };
        }

        var recordset = data.recordset;
        if (!recordset) return {ids: [], total: 0};
        return {
            ids: this._normalizeRecordIds(recordset.getOrder()),
            total: Number(recordset.count_total())
        };
    }

    /** Prefer explicit IDs for a complete small result; otherwise reuse its query. */
    _currentResultsQuery(recordIds, executedQuery, total) {
        var ids = this._normalizeRecordIds(recordIds);
        if (!ids.length) return null;
        var resultTotal = Number(total);
        var partial = Number.isFinite(resultTotal) && resultTotal > ids.length;
        if (!partial && ids.length <= HEURIST_MODULE_IDS_QUERY_LIMIT) {
            return 'ids:' + ids.join(',');
        }
        return this._normalizeQuery(executedQuery) || 'ids:' + ids.join(',');
    }

    _normalizeQuery(query) {
        if (query == null) return null;
        if (typeof query === 'string') return $.trim(query) || null;
        if ($.isPlainObject(query)) return $.extend(true, {}, query);
        return query;
    }

    /** Unbind shared host events. Called by concrete module destruction. */
    _unbindHostEvents() {
        if (this._events) $(this.document).off(this._events);
        this._events = null;
    }
}

window.HeuristModuleRecordset = HeuristModuleRecordset;
