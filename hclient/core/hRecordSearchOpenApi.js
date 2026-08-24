/**
 * hRecordSearchOpenApi.js - Client adapter for the modern records search API
 *
 * Translates the legacy RecordMgr search request into the public
 * /api/{db}/records contract and converts its IDs-only response to the response
 * envelope expected by existing Heurist callbacks and search events.
 *
 * @project     Heurist academic knowledge management system
 * @package     HAPI
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Ian Johnson     <ian.johnson@huma-num.fr>
 * @since       7.0
 */

/* global HRecordSet */

/** Provides an experimental, behaviour-compatible OpenAPI record search. */
function HRecordSearchOpenApi() {
    const MAX_PAGE_SIZE = 100000;
    const MAX_RESULT_IDS = 1000000;

    /** Execute a search and return an abort-compatible request handle. */
    function search(request, callback) {
        request = request || {};
        const eventDocument = window.hWin.HEURIST4.util.isFunction(callback) ? null : callback;
        const queryId = request.id || window.hWin.HEURIST4.util.random();
        request.id = queryId;

        if(!window.hWin.HEURIST4.util.isFunction(callback)){
            if(eventDocument && !request.increment){
                eventDocument.trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [request]);
            }
            callback = function(response){
                let recordset = null;
                if(response.status === window.hWin.ResponseStatus.OK){
                    recordset = new HRecordSet(response.data);
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
                if(eventDocument){
                    eventDocument.trigger(
                        window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH,
                        {resultset: recordset}
                    );
                }
            };
        }

        const payload = buildPayload(request);
        const firstOffset = payload.offset;
        const requestedLimit = Math.min(
            MAX_RESULT_IDS,
            normalizePositiveInt(request.limit, MAX_PAGE_SIZE)
        );
        const fetchAll = request.needall == 1 || request.needall === true;
        const state = {
            aborted: false,
            xhr: null,
            ids: [],
            total: 0
        };
        const handle = {
            abort: function(){
                state.aborted = true;
                if(state.xhr && window.hWin.HEURIST4.util.isFunction(state.xhr.abort)){
                    state.xhr.abort();
                }
            }
        };

        payload.limit = Math.min(MAX_PAGE_SIZE, requestedLimit);
        requestPage(payload, request, state, function(dto){
            state.total = Number(dto.total) || 0;
            state.ids = state.ids.concat(normalizeIds(dto.ids));

            const consumed = state.ids.length;
            const available = Math.max(0, state.total - firstOffset);
            const wanted = fetchAll
                ? Math.min(MAX_RESULT_IDS, available)
                : Math.min(MAX_RESULT_IDS, requestedLimit, available);
            if(!state.aborted && consumed < wanted && dto.ids && dto.ids.length){
                const nextPayload = $.extend({}, payload, {
                    offset: firstOffset + consumed,
                    limit: Math.min(MAX_PAGE_SIZE, wanted - consumed)
                });
                requestNextPage(nextPayload, request, state, callback, handle, firstOffset, wanted);
            }else if(!state.aborted){
                callback(successResponse(request, state.ids.slice(0, wanted), state.total, firstOffset));
            }
        }, function(response){
            if(!state.aborted){ callback(response); }
        });

        return handle;
    }

    /** Continue a legacy needall request without exceeding the server page cap. */
    function requestNextPage(payload, request, state, callback, handle, firstOffset, wanted){
        requestPage(payload, request, state, function(dto){
            const pageIds = normalizeIds(dto.ids);
            state.ids = state.ids.concat(pageIds);
            if(!state.aborted && state.ids.length < wanted && pageIds.length){
                const nextPayload = $.extend({}, payload, {
                    offset: firstOffset + state.ids.length,
                    limit: Math.min(MAX_PAGE_SIZE, wanted - state.ids.length)
                });
                requestNextPage(nextPayload, request, state, callback, handle, firstOffset, wanted);
            }else if(!state.aborted){
                callback(successResponse(
                    request,
                    state.ids.slice(0, wanted),
                    state.total,
                    firstOffset
                ));
            }
        }, function(response){
            if(!state.aborted){ callback(response); }
        });
    }

    /** Send one JSON request to the public records collection endpoint. */
    function requestPage(payload, request, state, onSuccess, onError){
        const hapi = window.hWin.HAPI4;
        const database = encodeURIComponent(request.db || hapi.database);
        const url = hapi.baseURL + 'api/' + database + '/records';
        state.xhr = $.ajax({
            url: url,
            type: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json; charset=UTF-8',
            dataType: 'json',
            cache: false,
            xhrFields: {withCredentials: true},
            success: function(dto){ onSuccess(dto || {}); },
            error: function(jqXHR, textStatus){
                if(textStatus === 'abort'){ return; }
                let response = jqXHR.responseJSON;
                if(!response || response.status === undefined){
                    response = window.hWin.HEURIST4.util.interpretServerError(
                        jqXHR,
                        url,
                        {script: 'api/records', action: 'search'}
                    );
                }
                response = response || {};
                response.queryid = request.id;
                onError(response);
            }
        });
    }

    /** Keep only fields belonging to the modern public request contract. */
    function buildPayload(request){
        const payload = {
            query: request.query !== undefined ? request.query : request.q,
            limit: Math.min(MAX_PAGE_SIZE, normalizePositiveInt(request.limit, MAX_PAGE_SIZE)),
            offset: Math.max(0, Number(request.offset !== undefined ? request.offset : request.o) || 0)
        };
        if(request.ids !== undefined){ payload.ids = request.ids; }
        if(request.rules !== undefined){ payload.rules = request.rules; }
        if(request.fields !== undefined){ payload.fields = request.fields; }
        if(request.detail !== undefined){ payload.detail = request.detail; }

        // The public API expresses the bookmark domain in the query language.
        if(isBookmarkDomain(request.w)){
            payload.query = appendQueryPredicate(payload.query, 'usr');
        }
        if(payload.query === undefined || payload.query === null || payload.query === ''){
            delete payload.query;
        }
        return payload;
    }

    function isBookmarkDomain(domain){
        domain = String(domain || '').toLowerCase();
        return domain === 'b' || domain === 'bookmark' || domain === 'bookmarks';
    }

    function appendQueryPredicate(query, predicate){
        if(Array.isArray(query)){
            const result = query.slice();
            result.push({[predicate]: 'current'});
            return result;
        }
        if(query && typeof query === 'object'){
            const result = Object.keys(query).map(function(key){
                return {[key]: query[key]};
            });
            result.push({[predicate]: 'current'});
            return result;
        }
        const text = String(query || '').trim();
        return text === '' ? predicate : text + ' ' + predicate;
    }

    function normalizeIds(ids){
        return Array.isArray(ids) ? ids.map(function(id){ return Number(id); }) : [];
    }

    function normalizePositiveInt(value, fallback){
        value = Number(value);
        return Number.isFinite(value) && value > 0 ? Math.floor(value) : fallback;
    }

    function successResponse(request, ids, total, offset){
        return {
            status: window.hWin.ResponseStatus.OK,
            queryid: request.id,
            data: {
                queryid: request.id,
                ids: ids,
                total: total,
                offset: offset,
                limit: ids.length,
                resultToken: null
            }
        };
    }

    return {search: search};
}
