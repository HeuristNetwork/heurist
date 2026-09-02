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
        this._currentSearchResult = null;
        this._hostQueryPending = false;

        if (this.options.recordset) {
            var recordset = this.options.recordset;
            this.options.recordset = null;
            if (!this.options.query) {
                var initialResult = this._prepareRecordsetResult(recordset);
                this._currentSearchResult = initialResult;
                this.options.query = initialResult.effectiveQuery;
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
                // The base class is the only old/new search compatibility boundary.
                // Concrete modules receive one already prepared effective query.
                that._acceptHostSearchResult(that._prepareSearchResult(data));
            } else if (event.type === hapi.Event.ON_REC_SEARCHSTART) {
                if (!that._isSameRealm(data)) return;
                that.options.query = null;
                that.options.selection = null;
                that.clearSelection();
                var startQuery = data && data.query !== undefined
                    ? data.query : data && data.q;
                if (!(data && !data.reset && that._normalizeQuery(startQuery))) {
                    that._acceptHostSearchResult(that._emptySearchResult());
                }
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

    /** Cache one normalized result from either the modern or legacy event. */
    _acceptHostSearchResult(result) {
        this._currentSearchResult = result || this._emptySearchResult();
        return this._acceptHostQuery(this._currentSearchResult.effectiveQuery);
    }

    /** Cache a host-result query and execute it only while the viewer is visible. */
    _acceptHostQuery(query) {
        this.options.query = this._normalizeQuery(query);
        this._hostQueryPending = true;
        if (this._hasActivePersistedSource() || !this._isWidgetVisible()) {
            return Promise.resolve(false);
        }
        return this._applyPendingHostQueryWhenVisible(false);
    }

    /** Apply the latest deferred host query after the containing panel is shown. */
    _applyPendingHostQueryWhenVisible(iframeCreated) {
        if (!this._hostQueryPending || this._hasActivePersistedSource()
            || !this._isWidgetVisible()) return Promise.resolve(false);

        this._hostQueryPending = false;
        // A newly created iframe receives options.query in its bootstrap.
        if (iframeCreated) return Promise.resolve(true);
        return this.setQuery(this.options.query, {
            reload: true,
            // Informational only: modules use effectiveQuery and never reinterpret
            // the original legacy/modern response.
            searchResult: this._searchResultOptions()
        }).catch(function() {
            // _enqueueOrRun already reports genuine module errors. Host event
            // handlers must not create an unhandled rejected promise.
            return false;
        });
    }

    /** Persisted Dataset/MapDocument viewers ignore current-result rendering. */
    _hasActivePersistedSource() {
        return this.options.dataset != null || this.options.mapDocument != null;
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
        var result = this._prepareRecordsetResult(recordset);
        this._currentSearchResult = result;
        return this.setQuery(result.effectiveQuery, $.extend({}, options || {}, {
            searchResult: this._searchResultOptions()
        }));
    }

    /** Convert an explicit HRecordSet input to the shared prepared result. */
    _prepareRecordsetResult(recordset) {
        if (!recordset) return this._emptySearchResult();
        if (typeof recordset === 'string') {
            return this._buildSearchResult(recordset, null, 0, null);
        }
        if ($.isPlainObject(recordset)
            && (recordset.query != null || recordset.q != null)) {
            return this._buildSearchResult(
                recordset.query !== undefined ? recordset.query : recordset.q,
                null, 0, null
            );
        }

        var request = recordset.getRequest();
        var query = request && request.query !== undefined
            ? request.query : request && request.q;
        return this._buildSearchResult(
            query,
            recordset.count_total(),
            recordset.length(),
            function() { return recordset.getOrder(); }
        );
    }

    /** Normalize modern count/IDs responses and the legacy HRecordSet event. */
    _prepareSearchResult(data) {
        data = data || {};
        if (data.response && data.response.total !== undefined) {
            var responseIds = Array.isArray(data.response.ids)
                ? data.response.ids : null;
            var responseTotal = Number(data.response.total);
            var responseQuery = data.response.query !== undefined
                ? data.response.query : data.query;
            return this._buildSearchResult(
                responseQuery,
                responseTotal,
                responseIds ? responseIds.length : 0,
                function() { return responseIds; }
            );
        }

        var recordset = data.recordset;
        if (!recordset) return this._emptySearchResult();
        var request = typeof recordset.getRequest === 'function'
            ? recordset.getRequest() : data.request;
        var query = data.query !== undefined ? data.query
            : request && request.query !== undefined ? request.query
            : request && request.q;
        return this._buildSearchResult(
            query,
            recordset.count_total(),
            recordset.length(),
            function() { return recordset.getOrder(); }
        );
    }

    /**
     * Build the shared descriptor without touching a potentially huge ID array.
     * getIds is invoked only for a complete result small enough for ids:query.
     */
    _buildSearchResult(query, total, loadedCount, getIds) {
        var normalizedQuery = this._normalizeQuery(query);
        var hasTotal = total !== null && total !== undefined
            && Number.isFinite(Number(total)) && Number(total) >= 0;
        var resultTotal = hasTotal ? Number(total) : null;
        var loaded = Number.isFinite(Number(loadedCount))
            ? Math.max(0, Number(loadedCount)) : 0;
        var complete = hasTotal && loaded === resultTotal;
        var effectiveQuery = normalizedQuery;
        if (hasTotal && resultTotal === 0) {
            effectiveQuery = null;
        } else if (complete && loaded <= HEURIST_MODULE_IDS_QUERY_LIMIT) {
            // Reuse an already available complete small result. Never fetch IDs
            // for this optimisation, normalize them again, or slice a large set.
            var ids = typeof getIds === 'function' ? getIds() : null;
            if (Array.isArray(ids) && ids.length === loaded) {
                effectiveQuery = 'ids:' + ids.join(',');
            }
        }
        return {
            query: normalizedQuery,
            effectiveQuery: effectiveQuery,
            total: resultTotal,
            complete: complete
        };
    }

    _emptySearchResult() {
        return {query: null, effectiveQuery: null, total: 0, complete: true};
    }

    /** Serializable summary passed with setQuery for diagnostics and counters. */
    _searchResultOptions() {
        var result = this._currentSearchResult || this._emptySearchResult();
        return {
            query: result.query,
            effectiveQuery: result.effectiveQuery,
            total: result.total,
            complete: result.complete
        };
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
