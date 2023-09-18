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

$.widget( "heurist.lookupOpentheso", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 750,
        width:  650,
        modal:  true,
        
        title:  "Search Opentheso records",
        
        htmlContent: 'lookupOpentheso.html',
        helpContent: null, //in context_help folder

        mapping: null, //configuration from record_lookup_config.json
               
        add_new_record: false, //if true it creates new record on selection
        
        pagesize: 20 // result list's number of records per page
    },
    
    recordList: null,

    tabs_container: null,

    _forceClose: false, // skip saving additional mapping and close dialog

    _servers: {
        'pactols': {title: 'pactols.frantiq.fr', uri: 'https://pactols.frantiq.fr/opentheso/openapi/v1/concept/{TH_ID}/search?'},
        'huma-num': {title: 'opentheso.huma-num.fr', uri: 'https://opentheso.huma-num.fr/opentheso/openapi/v1/concept/{TH_ID}/search?'}
    },

    _thesauruses: { // retrieved from php
        'pactols': [],
        'huma-num': []
    },

    _sel_elements: {
        'server': null,
        'theso': null,
        'lang': null
    }, // important select elements

    //  
    // invoked from _init after loading of html content
    //
    _initControls: function(){

        //this.element => dialog inner content
        //this._as_dialog => dialog container

        var that = this;

        // Extra field styling
        this.element.find('#frm-search .header').css({width: '125px', 'min-width': '125px', display: 'inline-block'});

        // Action button styling
        this.element.find('#btnStartSearch').addClass("ui-button-action");

        // Prepare result list options
        this.options.resultList = $.extend(this.options.resultList, 
        {
            recordDivEvenClass: 'recordDiv_blue',
            eventbased: false,  //do not listent global events

            multiselect: false, // allow only one record to be selected
            select_mode: 'select_single', // only accept one record for selection

            view_mode: 'list', // result list viewing mode [list, icon, thumb]
            show_viewmode:false,
            
            entityName: this._entityName,
            
            pagesize: this.options.pagesize, // number of records to display per page
            empty_remark: '<div style="padding:1em 0 1em 0">No records match the search</div>', // For empty results
            renderer: this._rendererResultList // Record render function, is called on resultList updateResultSet
        });                

        // Init record list
        this.recordList = this.element.find('.div_result');

        this.recordList.resultList( this.options.resultList );
        this.recordList.resultList('option', 'pagesize', this.options.pagesize); // so the pagesize doesn't get set to a different value
        
        // Init select & double click events for result list
        this._on( this.recordList, {        
            "resultlistonselect": function(event, selected_recs){
                window.hWin.HEURIST4.util.setDisabled( 
                    this.element.parents('.ui-dialog').find('#btnDoAction'), 
                    (selected_recs && selected_recs.length()!=1));
            },
            "resultlistondblclick": function(event, selected_recs){
                if(selected_recs && selected_recs.length()==1){
                    this.doAction();                                
                }
            }
        });        

        // Handling for 'Search' button        
        this._on(this.element.find('#btnStartSearch').button(),{
            'click':this._doSearch
        });

        // For capturing the 'Enter' key while typing
        this._on(this.element.find('input'),{
            'keypress':this.startSearchOnEnterPress
        });

        this._sel_elements = {
            server: this.element.find('#inpt_server'),
            theso: this.element.find('#inpt_theso'),
            lang: this.element.find('#inpt_lang')
        };

        // ----- THESAURUS SELECT -----
        let request = {
            serviceType: 'opentheso',
            service: 'opentheso_get_thesauruses'
        };

        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack(that.element);

            if(response.status && response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            // Store response
            for(const server in that._servers){

                const theso = Object.hasOwn(response, server) ? response[server] : [];
                let options = [];

                for(const key in theso){
                    options.push({key: key, title: theso[key]});
                }

                that._thesauruses[server] = options;
            }

            // Update select
            that._displayThesauruses();
        });

        // ----- SERVER SELECT -----
        let options = [];
        for(const server in this._servers){
            options.push({key: server, title: this._servers[server]['title']});
        }
        window.hWin.HEURIST4.ui.fillSelector(this._sel_elements['server'][0], options);
        window.hWin.HEURIST4.ui.initHSelect(this._sel_elements['server'], null, {
            onSelectMenu: this._displayThesauruses
        });

        /*
        this._on(this._sel_elements['server'], {
            change: this._displayThesauruses
        });
        */

        // ----- LANGUAGE SELECT -----
        window.hWin.HEURIST4.ui.createLanguageSelect(this._sel_elements['lang'], [{key: '', title: 'select a language...'}]);

        this.tabs_container = this.element.find('#tabs-cont').tabs();
        this.element.find('#inpt_search').focus();

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Retrieving thesauruses...</span>');

        return this._super();
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
        window.hWin.HEURIST4.ui.initHSelect(this._sel_elements['theso']);
    },
    
    /**
     * Function handler for pressing the enter button while focused on input element
     * 
     * Param:
     *  e (event trigger)
     */
    startSearchOnEnterPress: function(e){
        
        var code = (e.keyCode ? e.keyCode : e.which);
        if (code == 13) {
            window.hWin.HEURIST4.util.stopEvent(e);
            e.preventDefault();
            this._doSearch();
        }

    },
    
    /**
     * Result list rendering function called for each record
     * 
     * Param:
     *  recordset (hRecordSet) => Heurist Record Set
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

            s = window.hWin.HEURIST4.util.htmlEscape(s?s:'');

            title = s;

            if(fldname == 'uri'){
                s = '<a href="' + s + '" target="_blank"> view here </a>';
                title = 'View record';
            }

            if(width>0){
                s = '<div style="display:inline-block;width:'+width+'ex" class="truncate" title="'+title+'">'+s+'</div>';
            }

            return s;
        }

        // Generic details, not completely necessary
        const recID = fld('rec_ID');
        const rectypeID = fld('rec_RecTypeID');
        const recIcon = window.hWin.HAPI4.iconBaseURL + rectypeID;
        const html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;' + window.hWin.HAPI4.iconBaseURL + rectypeID + '&version=thumb&quot;);"></div>';

        const recTitle = fld('label', 25) + fld('desc', 70) + fld('uri', 10); 

        return '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" rectype="'+rectypeID+'">'
            + html_thumb
                + '<div class="recordIcons">'
                +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
                +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);"/>' 
                + '</div>'
                +  recTitle
            + '</div>';
    },

    /**
     * Initial dialog buttons on bottom bar, _getActionButtons() under recordAction.js
     */
    _getActionButtons: function(){
        let res = this._super(); //dialog buttons
        res[1].text = window.hWin.HR('Select');
        return res;
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

        if(recset && recset.length() == 1){

            let res = {};
            let rec = recset.getFirstRecord(); // get selected record

            res['ext_url'] = recset.fld(rec, 'uri'); // add Opentheso link

            if(this.options.mapping.options.create_terms === true || this.options.mapping.options.create_type == 'enum'){
                let dtyID = this.options.mapping.fields.term_field;
                res[dtyID] = [{
                    'label': recset.fld(rec, 'label'),
                    'desc': recset.fld(rec, 'desc'),
                    'code': recset.fld(rec, 'code'),
                    'uri': recset.fld(rec, 'uri'),
                    'translations': recset.fld(rec, 'translations')
                }];
            }
            this.closingAction(res);
        }
    },


    /**
     * Perform final actions before exiting popup
     * 
     * Param:
     *  dlg_reponse (json) => mapped values to fields
     */
    closingAction: function(dlg_response){

        var that = this;

        if(window.hWin.HEURIST4.util.isempty(dlg_response)){
            dlg_response = {};
        }

        window.hWin.HEURIST4.msg.sendCoverallToBack(that.element);

        // Pass mapped values back and close dialog
        this._context_on_close = dlg_response;
        this._as_dialog.dialog('close');
    },
    
    /**
     * Create search URL using user input within form
     * Perform server call and handle response
     * 
     * Params: None
     */
    _doSearch: function(){

        var that = this;

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

        sURL = sURL.replace('{TH_ID}', th_id);

        // Add search
        sURL += 'q=' + encodeURIComponent(search);

        // Add language
        if(!window.hWin.HEURIST4.util.isempty(language)){

            lang = window.hWin.HAPI4.sysinfo.common_languages[lang]['a2']; // use 2 char version
            sURL += '&lang=' + language;
        }
        // Add groupings
        if(!window.hWin.HEURIST4.util.isempty(grouping)){
            sURL += '&group=' + grouping.replace(' ', '');
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(this.element, null, '<span style="color: white;">Performing search...</span>'); // show loading cover

        // for record_lookup.php
        var request = {
            service: sURL, // request url
            serviceType: 'opentheso' // requesting service, otherwise no
        };
        // calls /heurist/hsapi/controller/record_lookup.php
        window.hWin.HAPI4.RecordMgr.lookup_external_service(request, function(response){
            window.hWin.HEURIST4.msg.sendCoverallToBack(that.element); // hide loading cover

            if(window.hWin.HEURIST4.util.isJSON(response)){

                if(response.status && response.status != window.hWin.ResponseStatus.OK){ // Error return
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }else if(response.length > 0){
                    that._onSearchResult(response);
                }else{ // No results
                    that.recordList.show();
                    that.recordList.resultList('updateResultSet', null);
                }
            }
        });
    },
    
    /**
     * Prepare json for displaying via the Heuirst resultList widget
     * 
     * Param:
     *  json_data (json) => search response
     */
    _onSearchResult: function(json_data){

        this.recordList.show();

        var is_wrong_data = true;

        json_data = window.hWin.HEURIST4.util.isJSON(json_data);

        if (json_data) {

            let res_records = {}, res_orders = [];

            // Prepare fields for mapping
            // the fields used here are defined within /heurist/hsapi/controller/record_lookup_config.json where "service" = bnfLibrary
            let fields = ['rec_ID', 'rec_RecTypeID']; // added for record set
            fields = fields.concat(['label', 'desc', 'code', 'uri', 'translations']);
            
            // Parse json to Record Set
            let i=0;
            for(;i<json_data.length;i++){

                let record = json_data[i];             
                let recID = i+1;
                let values = [recID, this.options.mapping.rty_ID, ...Object.values(record)];

                res_orders.push(recID);
                res_records[recID] = values;
            }

            if(res_orders.length>0){
                // Create the record set for the resultList
                let res_recordset = new hRecordSet({
                    count: res_orders.length,
                    offset: 0,
                    fields: fields,
                    rectypes: [this.options.mapping.rty_ID],
                    records: res_records,
                    order: res_orders,
                    mapenabled: true
                });
                this.recordList.resultList('updateResultSet', res_recordset);            
                is_wrong_data = false;
            }
        }

        if(is_wrong_data){
            this.recordList.resultList('updateResultSet', null);
            window.hWin.HEURIST4.msg.showMsgErr('Service did not return data in an appropriate format');
        }else{
            this.tabs_container.tabs('option', 'active', 1); // switch to results tab
        }
    }
});