/**
* lookupOpentheso.js
* 
* This file:
*   1) Loads the content of the corresponding html file (lookupOpentheso.html)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
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

$.widget( "heurist.lookupOpentheso", $.heurist.lookupBase, {

    // default options
    options: {

        height: 750,
        width:  700,

        title:  "Search Opentheso records",

        htmlContent: 'lookupOpentheso.html'
    },

    _servers: {},

    _thesauruses: {},

    _collections: {},

    _sel_elements: {
        'server': null,
        'theso': null,
        'lang': null,
        'group': null
    }, // important select elements

    _refreshCollections: false,

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        //this.element => dialog inner content
        //this._as_dialog => dialog container

        const that = this;

        // Extra field styling
        this.element.find('#frm-search .header').css({width: '125px', 'min-width': '125px', display: 'inline-block'});

        // Action button styling
        this.element.find('#btnStartSearch').addClass("ui-button-action");

        // Handling for 'Search' button        
        this._on(this.element.find('#btnStartSearch').button(),{
            click: this._doSearch
        });

        this._sel_elements = {
            server: this.element.find('#inpt_server'),
            theso: this.element.find('#inpt_theso'),
            lang: this.element.find('#inpt_lang'),
            group: this.element.find('#inpt_group')
        };

        // ----- SERVER SELECT -----
        let request = {
            serviceType: 'opentheso',
            service: 'opentheso_get_servers'
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(that.element);

            if(response.status && response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            that._servers = response.data;
            let options = [];
            for(const server in that._servers){

                let url = new URL(that._servers[server]);
                that._servers[server] = { title: url.hostname, uri: url.href };
                options.push({key: server, title: that._servers[server]['title']});
            }
            window.hWin.HEURIST4.ui.fillSelector(that._sel_elements['server'][0], options);
            window.hWin.HEURIST4.ui.initHSelect(that._sel_elements['server'], false, null, {
                onSelectMenu: () => { that._displayThesauruses(); }
            });

            that._updateThesauruses();
        });

        this._on(this._sel_elements['server'], {
            change: this._displayThesauruses
        });

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Retrieving servers...</span>');

        // ----- THESO SELECT -----
        this._on(this._sel_elements['theso'], {
            change: function(){
                this._refreshCollections = true;
                this._displayCollections();
            }
        });

        // ----- GROUP SELECT -----
        this._on(this._sel_elements['group'], {
            change: function(){
                this.element.find('#btn_cnlGroups').show();
            }
        });

        // ----- LANGUAGE SELECT -----
        window.hWin.HEURIST4.ui.createLanguageSelect(this._sel_elements['lang'], [{key: '', title: 'select a language...'}]);

        this.element.find('#inpt_search').focus();

        // ----- REFRESH BUTTONS -----
        this._on(this.element.find('#btn_refTheso').button({showLabel: false, icon: 'ui-icon-refresh'}), {
            click: function(){
                this._updateThesauruses(true);
            }
        });
        this._on(this.element.find('#btn_refGroups').button({showLabel: false, icon: 'ui-icon-refresh'}), {
            click: function(){
                this._updateCollections(true);
            }
        });

        // ----- CANCEL BUTTON -----
        this._on(this.element.find('#btn_cnlGroups').button({showLabel: false, icon: 'ui-icon-cancel'}).hide(), {
            click: function(){
                this._sel_elements['group'].val([]);
                this.element.find('#btn_cnlGroups').hide();
            }
        })

        return this._super();
    },

    /**
     * Retrieve thesauruses for selected server
     * 
     * @param {bool} is_refresh - Update list from opentheso server
     * 
     * @returns void
     */
    _updateThesauruses: function(is_refresh = false){

        const that = this;

        let ser_id = this._sel_elements['server'].val();

        let request = {
            service: 'opentheso_get_thesauruses',
            serviceType: 'opentheso',
            params: {
                servers: is_refresh ? ser_id : null,
                refresh: is_refresh ? 1 : 0
            }
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(that.element);

            if(response.status && response.status != window.hWin.ResponseStatus.OK){
                // display error and show the textbox instead
                window.hWin.HEURIST4.msg.showMsgErr(response);

                return;
            }

            for(const server in that._servers){

                const theso = Object.hasOwn(response, server) ? response[server] : [];
                let options = [];

                if(!Object.hasOwn(that._collections, server)){
                    that._collections[server] = {};
                }

                for(const key in theso){
                    options.push({key: key, title: theso[key]['name']});

                    if(!Object.hasOwn(that._collections[server], key)){
                        that._collections[server][key] = [];
                    }

                    if(theso[key]['groups'].length > 0){
                        for(const g_key in theso[key]['groups']){
                            that._collections[server][key].push({key: g_key, title: theso[key]['groups'][g_key]});
                        }
                    }else if(!is_refresh){
                        that._refreshCollections = true;
                    }
                }

                that._thesauruses[server] = options;
            }

            that._displayThesauruses();
        });

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Retrieving thesauruses...</span>');
    },
    
    /**
     * Populate thesaurus dropdown for selected server
     * 
     * @returns void
     */
    _displayThesauruses: function(){

        if(!this._sel_elements || !this._sel_elements['theso']){
            return;
        }

        this._sel_elements['theso'].empty(); // remove previous options
        if(this._sel_elements['theso'].hSelect('instance') !== undefined){
            this._sel_elements['theso'].hSelect('destroy');
        }

        let server = this._sel_elements['server'].val();
        let options = this._thesauruses[server];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(options)){
            options = [{key: '', title: 'No thesauruses available'}];
        }

        window.hWin.HEURIST4.ui.fillSelector(this._sel_elements['theso'][0], options);
        window.hWin.HEURIST4.ui.initHSelect(this._sel_elements['theso'], true);

        this._sel_elements['theso'].trigger('change');
    },

    /**
     * Retrieve collections for current thesaurus
     * 
     * @param {bool} is_refresh - Update list from opentheso server
     * 
     * @returns void
     */
    _updateCollections: function(is_refresh = false){

        const that = this;

        let ser_id = this._sel_elements['server'].val();
        let th_id = this._sel_elements['theso'].val();

        if(window.hWin.HEURIST4.util.isempty(th_id)){
            return;
        }

        let request = {
            service: 'opentheso_get_collections', // requested metadata
            serviceType: 'opentheso', // requesting service
            params: {
                server: ser_id,
                thesaurus: th_id,
                refresh: is_refresh === true ? 1 : 0
            }
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(that.element);
            that._refreshCollections = false;

            if(response.status && response.status != window.hWin.ResponseStatus.OK){
                // display error and show the textbox instead
                window.hWin.HEURIST4.msg.showMsgErr(response);

                return;
            }

            // Process group response
            let options = [];
            for(const group_id in response.groups){
                options.push({key: group_id, title: response.groups[group_id]});
            }
            that._collections[ser_id][th_id] = options; // cache collection details

            that._displayCollections();
        });

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Retrieving available collections...</span>');
    },

    /**
     * Update multi-select with collections for current theso
     * 
     * @returns void
     */
    _displayCollections: function(){

        if(!this._sel_elements || !this._sel_elements['group']){
            return;
        }

        this._sel_elements['group'].empty(); // remove previous options

        let server = this._sel_elements['server'].val();
        let theso = this._sel_elements['theso'].val();
        let options = this._collections[server][theso];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(options)){

            if(this._refreshCollections){ // update groups
                this._updateCollections(true);
                return;
            }

            options = [{key: '', title: 'No groups available'}];
        }
        this._refreshCollections = false;

        window.hWin.HEURIST4.ui.fillSelector(this._sel_elements['group'][0], options);
        window.hWin.HEURIST4.ui.initHSelect(this._sel_elements['group'], true);

        let length = options.length > 0 ? options.length : 3;
        this._sel_elements['group'].attr('size', length);

        this._sel_elements['group'].find('option').css('padding', '5px 10px');
    },

    /**
     * Result list rendering function called for each record
     * 
     * Param:
     *  recordset (HRecordSet) => Heurist Record Set
     *  record (json) => Current Record being rendered
     * 
     * Return: html
     */
    _rendererResultList: function(recordset, record){

        /**
         * Get field details for displaying
         * 
         * Param:
         *  fldname (string) => mapping field name
         *  width (int) => width for field
         * 
         * Return: html
         */
        function fld(fldname, width){

            let s = recordset.fld(record, fldname);

            s = window.hWin.HEURIST4.util.htmlEscape(s ? s : '');

            let title = s;

            if(fldname == 'uri'){
                s = `<a href="${s}" target="_blank"> view here </a>`;
                title = 'View record';
            }

            if(width>0){
                s = `<div style="display:inline-block;width:${width}ex" class="truncate" title="${title}">${s}</div>`;
            }

            return s;
        }
        
        const recTitle = fld('label', 25) + fld('desc', 70) + fld('uri', 10); 
        recordset.setFld(record, 'rec_Title', recTitle);

        return this._super(recordset, record);
    },

    /**
     * Return record field values in the form of a json array mapped as [dty_ID: value, ...]
     * For multi-values, [dty_ID: [value1, value2, ...], ...]
     * 
     * Param: None
     */
    doAction: function(){

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Preparing values for record editor...</span>');

        // get selected recordset
        let recset = this.recordList.resultList('getSelected', false);

        if(!recset || recset.length() != 1){
            return;
        }

        let res = {};
        let rec = recset.getFirstRecord(); // get selected record

        res['ext_url'] = recset.fld(rec, 'uri'); // add Opentheso link

        for(const field in this.options.mapping.fields){

            let dtyID = this.options.mapping.fields[field];

            if(window.hWin.HEURIST4.util.isempty(dtyID)) { continue; }
            else if(!res[dtyID]) { res[dtyID] = []; }

            if(field == 'term_field'){

                let type = $Db.dty(dtyID, 'dty_Type');
                let trm_val = {
                    label: recset.fld(rec, 'label'),
                    desc: recset.fld(rec, 'desc'),
                    code: recset.fld(rec, 'code'),
                    uri: recset.fld(rec, 'uri'),
                    translations: recset.fld(rec, 'translations')
                };

                if(type != 'enum'){
                    trm_val = type == 'blocktext' ? JSON.stringify(trm_val) : trm_val['label']; // Object.values(trm_val).join(' ; ')
                }

                trm_val = Array.isArray(trm_val) ? trm_val : [trm_val];

                res[dtyID] = res[dtyID].concat(trm_val);
            }else{

                let fld = field.slice(5);

                let value = recset.fld(rec, fld);

                if(fld == 'translations'){
                    value = Object.keys(value).map((lang) => `${lang}: ${value[lang]}`);
                }

                value = Array.isArray(value) ? value : [value];


                res[dtyID] = res[dtyID].concat(value);
            }
        }

        this.closingAction(res);
    },

    /**
     * Create search URL using user input within form
     * Perform server call and handle response
     * 
     * Params: None
     */
    _doSearch: function(){

        let that = this;

        let sURL = this._servers[this._sel_elements['server'].val()]['uri'];
        let th_id = this._sel_elements['theso'].val();

        let search = this.element.find('#inpt_search').val();
        let grouping = this.element.find('#inpt_group').val();
        let language = this._sel_elements['lang'].val();

        if(window.hWin.HEURIST4.util.isempty(th_id)){
            window.hWin.HEURIST4.msg.showMsgFlash('A thesaurus must be selected...', 2000);
            return;
        }
        // Check that something has been entered
        if(window.hWin.HEURIST4.util.isempty(search)){
            window.hWin.HEURIST4.msg.showMsgFlash('Please enter a value in the search field...', 2000);
            return;
        }

        // Add thesaurus
        sURL += `concept/${th_id}/search?`;

        // Add search
        sURL += `q=${encodeURIComponent(search)}`;

        // Add language
        if(!window.hWin.HEURIST4.util.isempty(language)){

            language = window.hWin.HAPI4.sysinfo.common_languages[language]['a2']; // use 2 char version
            sURL += `&lang=${language}`;
        }
        // Add groupings
        if(!window.hWin.HEURIST4.util.isempty(grouping) && !window.hWin.HEURIST4.util.isempty(grouping[0])){
            sURL += `&group=${grouping.join(',')}`;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Performing search...</span>'); // show loading cover

        // for record_lookup.php
        let request = {
            service: sURL, // request url
            serviceType: 'opentheso', // requesting service, otherwise no
            preferred_lang: window.hWin.HEURIST4.util.isempty(language) || language.length != 2 ? 'fr' : language
        };
        // calls /heurist/hserv/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){
            window.hWin.HEURIST4.msg.sendCoverallToBack(that.element); // hide loading cover

            if(window.hWin.HEURIST4.util.isJSON(response)){

                if(response.status && response.status != window.hWin.ResponseStatus.OK){ // Error return
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }else if(response.length > 0){
                    that._onSearchResult(response);
                }else{ // No results

                    window.hWin.HEURIST4.msg.showMsgFlash('No results returned', 3000);

                    that.recordList.show();
                    that.recordList.resultList('updateResultSet', null);
                }
            }
        });
    },

    /**
     * Prepare json for displaying via the Heuirst resultList widget
     *
     * @param {json} json_data - search response
     */
    _onSearchResult: function(json_data){

        json_data = window.hWin.HEURIST4.util.isJSON(json_data);

        if(!json_data){
            return this._super(false);
        }

        let res_records = {}, res_orders = [];

        // Prepare fields for mapping
        let fields = ['rec_ID', 'rec_RecTypeID']; // added for record set
        fields = fields.concat(['label', 'desc', 'code', 'uri', 'translations']);
        
        // Parse json to Record Set
        let i = 1;
        for(const record of json_data){

            let recID = i++;
            let values = [recID, this.options.mapping.rty_ID, ...Object.values(record)];

            res_orders.push(recID);
            res_records[recID] = values;
        }

        let res = res_orders.length > 0 ? {fields: fields, order: res_orders, records: res_records} : false;
        this._super(res);
    }
});