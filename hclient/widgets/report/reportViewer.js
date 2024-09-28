/**
* reportViewer - smarty report viewer
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


$.widget( "heurist.reportViewer", {

    // default options
    options: {

        eventbased:true, //if false it does not listen global events
        search_realm: null,
        search_initial: null,  //Query or svs_ID for initial search
        recordset: null,
        language: 'def'
    },

    _$: $, //shorthand for this.element.find
    _need_load_content: true,
    _currentTemplate: null,
    
    // the widget's constructor
    _create: function() {
        
        this._$ = selector => this.element.find(selector);
        

        this.element
        // prevent double click to select text
        .disableSelection();
    
        this._loadContent();    

        //this._refresh();

    }, //end _create

    _init: function(){
    },

    //
    //
    //    
    _loadContent:function(){
        if(this._need_load_content){  //load general layout      

            let url = window.hWin.HAPI4.baseURL+'hclient/widgets/report/reportViewer.html';
            
            //let container = $('<div>').appendTo(this.element);
            let that = this;
            this.element.load(url, 
            function(){
                that._need_load_content = false;
                that._initControls();
            });
        }            
    },
    
    
    /* 
    * show/hide buttons depends on current credentials
    */
    _refresh: function(){

    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        if(this._events){
            $(this.document).off(this._events);    
        }
    },
    
    _initControls: function(){
        this._updateTemplatesList();    
        this._initToolbar();    

        
        if(!this.options.eventbased){
            return;
        }
        
        this._events = window.hWin.HAPI4.Event.ON_CREDENTIALS 
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SEARCHSTART
        + ' ' + window.hWin.HAPI4.Event.ON_REC_SELECT;

        let that = this;
        
        $(this.document).on(this._events, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_CREDENTIALS)
            {
                if(!window.hWin.HAPI4.has_access()){ //logout
                    that.options.recordset = null;
                    that._refresh();
                }
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH){ 

                if(!that._isSameRealm(data)) return;
                
                that.options.recordset = data.recordset; //HRecordSet
                that.executeTemplate();

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SEARCHSTART){

                if(!that._isSameRealm(data)) return;

            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){
                
            }
        });
    },
    
    _initToolbar: function() {
        let that = this;
        $.each(this._$('.toolbar > button'), (i, item)=>{
            that._on($(item).button({text:false, icons: { primary: "ui-icon-"+$(item).attr('data-icon')}}),
                {click: that._handleToolbarAction});
        });
        this._$('button[data-action="export"]').find('span.ui-icon').css({'transform':'rotate(180deg)','margin-top':'-9px'});        
    },
    
    _handleToolbarAction: function(event){
        
        let ele = $(event.target);
        if(!ele.is('button')) ele = ele.parent('button');
        
        let action = ele.attr('data-action');
        
        switch ( action ) {
            case 'edit':
                this.onTemplateEdit(false);
                break;
            case 'new':
                this.onTemplateEdit(true);
                break;
            case 'delete':
                this.onTemplateDelete();
                break;
            case 'import':
                this.onTemplateImport();
                break;
            case 'export':
                this.onTemplateExport();
                break;
            case 'get':
                this.onTemplateDownload();
                break;
            case 'publish':
                this.onTemplatePublish();
                break;
            case 'print':
                this.onTemplatePrint();
                break;
            case 'refresh':
            
        }
        
    },

    //
    // Show popup with template editor
    //
    onTemplateEdit: function(isNew) {
        let popup_dialog_options = {path: 'widgets/report/', keep_instance:true, template: isNew?null:this._currentTemplate};
        window.hWin.HEURIST4.ui.showRecordActionDialog('reportEditor', popup_dialog_options);
    },

    //
    //
    //
    onTemplateDelete: function() {
        
    },

    //
    //
    //
    onTemplateImport: function() {
        
    },

    //
    //
    //
    onTemplateExport: function() {
        
    },

    //
    //
    //
    onTemplateDownload: function() {
        
    },

    //
    //
    //
    onTemplatePublish: function() {
        
    },

    //
    //
    //
    onTemplatePrint: function() {
        try{
            let oIframe = this._$("#ifrmPrint")[0];
            let iframe = this._$("#rep_container_frame")[0];
            let iframeDocument = iframe.contentDocument || iframe.contentWindow.document;
            let oContent = iframeDocument.body.innerHTML;
            //.innerHTML
            let oDoc = (oIframe.contentWindow || oIframe.contentDocument);
            if (oDoc.document) oDoc = oDoc.document;
            oDoc.write("<head><title></title>");
            oDoc.write("</head><body onload=\"this.focus(); this.print();\">");
            oDoc.write(oContent);
            oDoc.write('</body>');
            oDoc.close();
        }
        catch(e){
            console.error(e)
        }
    },

    
    _updateTemplatesList: function() {
        
        this._currentTemplate = window.hWin.HAPI4.get_prefs('viewerCurrentTemplate');
        let sel = this._$('#selTemplates');
        window.hWin.HEURIST4.ui.createTemplateSelector(sel, null, this._currentTemplate, null);
        
        this._on(sel,{change:(event)=>{
                let template_file = $(event.target).val();
                window.hWin.HAPI4.SystemMgr.save_prefs({'viewerCurrentTemplate': template_file});
                this.executeTemplate(template_file);
        }});
        
    },        
    
    //
    //
    //
    _isSameRealm: function(data){
        return (!this.options.search_realm && (!data || window.hWin.HEURIST4.util.isempty(data.search_realm)))
        ||
        (this.options.search_realm && (data && this.options.search_realm==data.search_realm));
    },
    
    assignRecordsetAndQuery: function(recordset, query_request, facet_value = null){
        this._currentRecordset = recordset;
        this._currentQuery = query_request;
        this._facet_value = facet_value;
    },
    
    //
    //
    //
    executeTemplate: function(template_file){
        
        let baseurl = window.hWin.HAPI4.baseURL + "viewers/smarty/showReps.php";
        
        if(window.hWin.HEURIST4.util.isnull(template_file)){
            template_file = this._$('#selTemplates').val();    
        }
        this._currentTemplate = template_file;
        let _currentRecordset = this.options.recordset;

        if(_currentRecordset==null){
            return;   
        }
        
        if(!template_file){
            return;    
        }
        
            //limit to  records  smarty-output-limit
            let recset;
            let rec_count = _currentRecordset.count_total()
            if(rec_count>0){
                let limit = window.hWin.HAPI4.get_prefs_def('smarty-output-limit',50);
                if(limit>2000) limit = 2000;
                //rec_count = Math.min(limit, rec_count)
                recset = {recIDs:_currentRecordset.getIds().slice(0, limit-1), recordCount:limit , resultCount:limit};
            }else{
                recset = {recIDs:_currentRecordset.getIds()};
            }

        let request = {db:window.hWin.HAPI4.database, 
                       template:template_file, 
                       recordset:JSON.stringify(recset)}


        if(this._facet_value){
            request['facet_val'] = this._facet_value;
        }

            request['session'] = window.hWin.HEURIST4.msg.showProgress();

            window.hWin.HEURIST4.msg.bringCoverallToFront(this._$('#rep_container'));

            let that = this;
            
            window.hWin.HEURIST4.util.sendRequest(baseurl, request, null, function(response){
                
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                window.hWin.HEURIST4.msg.hideProgress();
                
                if(response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    if(response.message){ //error in php code
                        that._updateReps( response.message );    
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);                       
                    }
                    
                }else{
                    that._updateReps( response );
                }
            }, 'auto');
                
    },
    
    _updateReps: function(context) {

        if(context == 'NAN' || context == 'INF' || context == 'NULL'){
            context = 'No value';
        }
        
        /*
        if(_is_snippet_editor){
            document.getElementById('snippet_output').innerHTML = context;
        }*/
            
            let txt = (context && context.message)?context.message:context
            
            let iframe = document.getElementById("rep_container_frame");
            iframe.contentWindow.document.open();
            iframe.contentWindow.document.write( txt );
            iframe.contentWindow.document.close();
            
            //document.getElementById('rep_container').innerHTML = context;

            //_needSelection = (txt && txt.indexOf("Select records to see template output")>0);
            
        
    },


});
