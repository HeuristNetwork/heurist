/**
* Base class for definition importing by CSV, does not include Records
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2024 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * Base class for importing definitions be CSV
 */
class HImportBase{

    #allowed_entities = ['rty', 'dty', 'trm', 'ulf'];
    #entity_details = {
        rty: {name: 'record type', group: 'rtg', script: 'defRecTypes', refresh: 'rty'},
        dty: {name: 'detail type', group: 'dtg', script: 'defDetailTypes', refresh: 'dty,rst'},
        trm: {name: 'term', group: 'trm', script: 'defTerms', refresh: 'trm'},
        vcg: {name: 'vocabulary group', group: 'vcg', script: 'defTerms', refresh: 'trm'},
        ulf: {name: 'file', group: '', script: 'recUploadedFiles', refresh: ''}
    };

    _entity_type = null;

    _parseddata = null;
    _prepareddata = null;

    _field_selectors = [];

    _return_results = false;

    /**
     * Initialise UI elements and validate options/settings
     *
     * @param {integer} group_id - default entity type group ID, if necessary
     * @param {string} entity_type - entity type, short hand format
     * @param {array} field_selectors - array of selectors for field dropdowns to assign columns
     * @param {boolean} return_results - whether to close the window and return the immport results ON SUCCESS ONLY
     * @returns 
     */
    constructor(group_id, entity_type, field_selectors, return_results = false){

        // Validate simple values
        if(this.#allowed_entities.indexOf(entity_type) === -1){
            this._showError('ACTION_BLOCKED', 'Provided entity type is not currently handled', 'Invalid entity type');
            $('body').empty();
            return;
        }
        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(field_selectors)){
            this._showError('ACTION_BLOCKED', 'Missing selectors for field dropdowns', 'No field selectors provided');
            $('body').empty();
            return;
        }

        this._entity_type = entity_type;
        this._field_selectors = field_selectors;
        this._return_results = return_results;

        // Initialise UI elements, e.g. buttons, dropdowns, etc...
        let $uploadWidget = $('#uploadFile');

        let $btnUploadFile = $('#btnUploadFile')
                    .css({width: '120px', 'font-size': '0.8em'})
                    .button({label: window.hWin.HR('Upload File')})
                    .on('click', () => {
                        $uploadWidget.trigger('click');
                    });

        $('#btnParseData')
                    .css('width', '120px')
                    .button({label: window.hWin.HR('Analyse'), icons: {secondary: 'ui-icon-circle-arrow-e'}})
                    .on('click', () => {
                        this.doParse();
                    });
        
        let $btnStartImport = $('#btnImportData')
                    .css('width', '110px')
                    .addClass('ui-button-action')
                    .button({label: window.hWin.HR('Import'), icons: {secondary: 'ui-icon-circle-arrow-e'}})
                    .on('click', () => {
                        this.doPost();
                    });

        window.hWin.HEURIST4.util.setDisabled($btnStartImport, true);
        
        $('#csv_header').on('change', () => { this.redrawPreviewTable() });

        // Setup group selector fo entity
        if(!window.hWin.HEURIST4.util.isempty(this.#entity_details[this._entity_type]['group'])){
            this.setupGroupSelector(group_id);
        }

        window.hWin.HEURIST4.ui.createEncodingSelect($('#csv_encoding'));

        let src_content = '';
        $('#sourceContent').on('keyup', () => {
            let cur_content = $('#sourceContent').val().trim();
            src_content = src_content != cur_content ? cur_content : src_content; 
        });

        $uploadWidget.fileupload({
            url: `${window.hWin.HAPI4.baseURL}hserv/controller/fileUpload.php`, 
            formData: [
                {name:'db', value: window.hWin.HAPI4.database},
                {name:'entity', value:'temp'},
                {name:'max_file_size', value:1024*1024}
            ],
            autoUpload: true,
            sequentialUploads:true,
            dataType: 'json',
            done: (e, response) => {

                response = response.result;

                if(response.status != window.hWin.ResponseStatus.OK){
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

                let data = response.data;
                $.each(data.files, (index, file) => {

                    if(file.error){
                        $('#sourceContent').val(file.error);
                        return;
                    }

                    let url_get = `${file.deleteUrl.replace('fileUpload.php','fileGet.php')}&encoding=${$('#csv_encoding').val()}&db=${window.hWin.HAPI4.database}`;
                    $('#sourceContent').load(url_get, null);
                });

                $btnUploadFile.off('click');
                $btnUploadFile.on({click: function(){
                    $uploadWidget.trigger('click');
                }});                
            }
        });

        $('.column_roles').on('change', (e) => { 

            let $ele = $(e.target);
            if($ele.val() >= 0){
                $('.column_roles').each(function(idx, item){
                    if($(item).attr('id') != $ele.attr('id') && $(item).val() == $ele.val()){
                        $(item).val(-1);
                    }
                });
            }

            //form update array
            this.doPrepare();
        });
    }

    /**
     * Parse the provided input (either uploaded file or from textarea) into rows
     *
     * @returns {void}
     */
    doParse(){

        let content = $('#sourceContent').val();
        if(content == ''){
            window.hWin.HEURIST4.msg.showMsgFlash('No content entered', 2000);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

        let request = { 
            content: content,
            csv_delimiter: $('#csv_delimiter').val(),
            csv_enclosure: $('#csv_enclosure').val(),
            csv_linebreak: 'auto',
            id: window.hWin.HEURIST4.util.random()
        };

        window.hWin.HAPI4.doImportAction(request, (response) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            this._parseddata = response.data;

            this.redrawPreviewTable();
        });
    }

    /**
     * Convert the data rows into usable data for Heurist to interpret server side
     *  Function should be extended by leaf classes
     *
     * @returns {void}
     */
    doPrepare(){
        this._showError('UNKNOWN_ERROR', 'Failed to prepare data, as the function has not been created', 'Unable to prepare data');
        return;
    }

    /**
     * Sends data to server to create the new definitions/values
     *
     * @param {json} request - base of request object, is extended to include general properties
     * @param {function} callback - handles the server response instead of the default below
     * @returns {void} callback function receives the server's response
     */
    doPost(request = {}, callback = null){

        if(this._prepareddata.length < 1){
            return;
        }

        const group_entity = this.#entity_details[this._entity_type];

        const group_ID = $(`#field_${group_entity.group}`).val();

        if($(`#field_${group_entity.group}`).length > 0 && group_ID < 1){
            window.hWin.HEURIST4.msg.showMsgFlash(`Please select a ${group_entity.name} group...`, 2000);
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

        // Prepare request object with default values
        request = $.extend({
            a: 'batch',
            entity: group_entity.script,
            request_id: window.hWin.HEURIST4.util.random()
        }, request);

        // Add missing fields
        if(!Object.hasOwn(request, 'fields') && !Object.hasOwn(request, 'set_translations')){
            request['fields'] = JSON.stringify(this._prepareddata);
        }
        if(!Object.hasOwn(request, 'csv_import') && !Object.hasOwn(request, 'import_data')){
            request['csv_import'] = 1;
        }
        if(group_ID > 0){
            request[`${group_entity.group}_ID`] = group_ID;
        }

        window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack();
            
            if(window.hWin.HEURIST4.util.isFunction(callback)){
                callback.call(this, response);
                return;
            }

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            let entities_refresh = group_entity.refresh;
            entities_refresh += response.data['refresh_terms'] == true ? ',trm' : '';

            if(this._return_results){

                window.hWin.HAPI4.EntityMgr.refreshEntityData(entities_refresh, () => {
                    window.close({ result: response.data });
                }); // update cache and return import results

                return;
            }

            response.refresh = entities_refresh;
            this.handleResults(response);
        });
            }

    /**
     * Draws table based on parsed CSV data
     *
     * @returns {void}
     */
    redrawPreviewTable(){

        if(this._parseddata==null){
            this.doParse();
            return;
        }

        let maxcol = 0;
        for(const row of this._parseddata){
            maxcol = window.hWin.HEURIST4.util.isArrayNotEmpty(row) ? Math.max(maxcol,row.length) : maxcol;
        }

        let $container = $('#divParsePreview').empty();    
        let $table  = $('<table>', {class: 'tbmain'}).appendTo($container);

        // HEADER FIELDS
        let headers = [], ifrom=0;
        if($('#csv_header').is(':checked')){ 

            for(const i in this._parseddata){

                const row = this._parseddata[i];
                if(!window.hWin.HEURIST4.util.isArrayNotEmpty(row)){
                    continue;
                }

                for(let j = 0; j < maxcol; j++){
                    headers.push(j >= row.length || window.hWin.HEURIST4.util.isempty(row[j]) ? `column ${j}` : row[j]);
                }

                ifrom = parseInt(i) + 1;
                break;
            }
        }else{
            for(let i = 0; i < maxcol; i++){
                headers.push(`column ${i}`);
            }
        }

        // TABLE HEADER
        let $header_row = $('<tr>').appendTo($table);
        for(let i = 0; i < maxcol; i++){
            
            let col_width = null;
            if(maxcol > 3){
                col_width = i == 0 ? 20 : 10;
                col_width = i == maxcol - 1 ? 40 : col_width;
                col_width = `width: ${col_width}%`;
            }
            
            $('<th>', {
                style: `${col_width}`,
                class: 'truncate',
                text: headers[i]
            }).appendTo($header_row);
        }

        // TABLE BODY
        for(let i = ifrom; i < this._parseddata.length; i++){

            if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this._parseddata[i])){
                continue;
            }

            let $row = $('<tr>').appendTo($table);
            for(let j = 0; j < maxcol; j++){
                $('<td>', {
                    class: 'truncate',
                    text: j < this._parseddata[i].length ? this._parseddata[i][j] : ' '
                }).appendTo($row);
            }
        }

        // COLUMN ROLES SELECTORS
        $('.column_roles').empty();
        let $field_sels = $(this._field_selectors.join(', '));
        for(let i = -1; i < maxcol; i++){
            let $option = $('<option>',{value: i, text: i < 0 ? 'select...' : headers[i]});
            $option.appendTo($field_sels);
        }

        return [maxcol, headers];
    }

    /**
     * Adds results from import to data table
     *
     * @param {json} response - response from server, includes: 
     *                          data - details about imported definitions
     *                          refresh - which entities cache needs to be updated, both server and client side
     */
    handleResults(response){

        let results = response.data;
        let $rows = $('.tbmain').find('tr');
        let col_num = 0;
        let update_cache = false;

        // Add result header
        if($($rows[0]).find('.post_results').length == 0){
            $('<th>', {class: 'post_results truncate', text: 'Results'}).appendTo($($rows[0]));
        }else{
            col_num = $($rows[0]).find('th').length - 1;
        }

        // Add result data
        for(let i = 1; i < $rows.length; i++){

            let $row = $($rows[i]);
            let data = results[i-1];

            if(data == null){
                continue;
            }
            if($row.length == 0){
                break;
            }

            if(data.indexOf('Created') >= 0){
                update_cache = true;
            }

            if(col_num == 0){
                $('<td>', {class: 'truncate', text: data}).appendTo($row);
            }else{
                $($row.find('td')[col_num]).text(data);
            }
        }

        if(update_cache && !window.hWin.HEURIST4.util.isempty(response.refresh)){
            window.hWin.HAPI4.EntityMgr.refreshEntityData(response.refresh, null); // update cache
        }
    }

    /**
     * Creates the necessary definition group select, for base fields and record types
     *  Vocabularies/Terms currently only allow additions to the sent vocabulary group/parent term
     *
     * @param {integer} def_ID - Default value for group select
     * @returns {void}
     */
    setupGroupSelector(def_ID){

        if(window.hWin.HEURIST4.util.isempty(this._entity_type)){
            return;
        }

        const group_entity = this.#entity_details[this._entity_type];

        let $select = $(`#field_${group_entity.group}`);
        let first_option = [{key: 0, title: `select a ${group_entity.name} group...`}];

        switch (this._entity_type) {
            case 'rty':
                window.hWin.HEURIST4.ui.createRectypeGroupSelect($select[0], first_option);
                break;

            case 'dty':
                window.hWin.HEURIST4.ui.createDetailtypeGroupSelect($select[0], first_option);
                break;

            default:
                break;
        }

        if($select.length == 0 || $select.find('option').length == 0){
            return;
        }

        $select.val(def_ID > 0 ? def_ID : 0);
        if($select.hSelect('instance') !== undefined){
            $select.hSelect('refresh');
        }

        $select.on('change', () => {

            const label = $select.find(':selected').text();
            $select.attr('title', label);
            
            if($select.hSelect('instance') !== undefined){
                $select.hSelect('widget').attr('title', label);
            }

            if($('#field_dtg').val() == 0){
                window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), true);
            }else{
                this.doPrepare();
            }
        });
    }

    /**
     * Display Heurist error message popup, for general use error messages (not server errors)
     *
     * @param {string} type - Heurist error status, needs to fit window.hWin.ResponseStatus otherwise unknown is used
     * @param {string} message - Error message
     * @param {string} title - Error title
     * @returns 
     */
    _showError(type, message, title){

        if(window.hWin.HEURIST4.util.isempty(message)){
            return;
        }

        type = window.hWin.ResponseStatus[type] ?? window.hWin.ResponseStatus.UNKNOWN_ERROR;

        window.hWin.HEURIST4.msg.showMsgErr({message: message, error_title: title, status: type});
    }
}