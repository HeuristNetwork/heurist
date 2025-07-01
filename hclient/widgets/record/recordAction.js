/**
* @file recordAction.js
* @brief Provides a base jQuery widget for actions performed on a scope of records.
* @fileOverview This file defines the `recordAction` widget, a base class for various record-specific
* actions within the Heurist system. It handles common functionalities like record scope selection
* (e.g., all, selected, current) and progress display for long-running actions. Widgets extending
* `recordAction` can implement specific operations on records.
*
* @project     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       4.0
*/



/**
 * @widget heurist.recordAction
 * @extends $.heurist.baseAction
 * @description Base jQuery widget for actions that operate on a scope of records.
 * This widget provides common infrastructure for selecting a set of records (e.g., selected, current search results)
 * and displaying progress for actions performed on these records.
 *
 * @param {object} options - Configuration options for the widget.
 * @param {string} [options.default_palette_class='ui-heurist-explore'] - Default CSS class for the widget palette.
 * @param {string} [options.path='widgets/record/'] - Path to the widget's HTML and other resources.
 * @param {?Array<string>|string} options.scope_types - Defines the available scope selection options.
 *        Can be an array of strings (e.g., ['all', 'selected', 'current', 'rectype_id']) or 'none'.
 *        If null or empty, defaults may include 'all', 'selected', 'current', and available record types.
 * @param {string} [options.init_scope=''] - The initially selected scope.
 * @param {?HRecordSet} options.currentRecordset - The current recordset object to operate on. If not provided, it may use the global current recordset.
 * @param {string} [options.htmlContent='recordAction.html'] - The HTML file to load for the widget's content.
 */
$.widget( "heurist.recordAction", $.heurist.baseAction, {

    /**
     * @namespace options
     * @memberof heurist.recordAction
     * @type {object}
     * @property {string} [default_palette_class='ui-heurist-explore'] - Default CSS class for the widget palette.
     * @property {string} [path='widgets/record/'] - Path to the widget's HTML and other resources.
     * @property {?Array<string>|string} scope_types - Defines the available scope selection options.
     *           Can be an array of strings (e.g., ['all', 'selected', 'current', 'rectype_id']) or 'none'.
     *           If null or empty, defaults may include 'all', 'selected', 'current', and available record types.
     * @property {string} [init_scope=''] - The initially selected scope.
     * @property {?HRecordSet} currentRecordset - The current recordset object to operate on. If not provided, it may use the global current recordset.
     * @property {string} [htmlContent='recordAction.html'] - The HTML file to load for the widget's content.
     */
    options: {
        default_palette_class: 'ui-heurist-explore', 
        path: 'widgets/record/',
        
        //parameters
        scope_types: null, // [all, selected, collected, current, rectype ids, none]
        init_scope: '',    // inital selection
        currentRecordset: null,
        htmlContent: 'recordAction.html'
    },  
      
    /**
     * @member {?HRecordSet} _currentRecordset
     * @memberof heurist.recordAction
     * @private
     * @description The Heurist recordset that the action will be performed on.
     */
    _currentRecordset:null,
    /**
     * @member {?Array<number>} _currentRecordsetSelIds
     * @memberof heurist.recordAction
     * @private
     * @description Array of IDs of selected records in the `_currentRecordset`.
     */
    _currentRecordsetSelIds:null,
    /**
     * @member {?Array<number>} _currentRecordsetColIds
     * @memberof heurist.recordAction
     * @private
     * @description Array of IDs of collected records in the `_currentRecordset`. (Note: Usage of 'collected' needs clarification from code context, might be similar to selected or a distinct set).
     */
    _currentRecordsetColIds: null,
    
    /**
     * @member {?number} _progressInterval
     * @memberof heurist.recordAction
     * @private
     * @description Interval ID for the progress update mechanism. Used by `_showProgress` and `_hideProgress`.
     */
    _progressInterval:null,
    
    /**
     * @member {?jQuery} selectRecordScope
     * @memberof heurist.recordAction
     * @description jQuery object representing the dropdown/select element used for choosing the record scope.
     */
    selectRecordScope:null,
    
    /**
     * @function _init
     * @memberof heurist.recordAction
     * @private
     * @description Initializes the widget. Determines the `_currentRecordset` based on options or global HAPI4 state.
     * Calls the parent widget's `_init` method.
     */
    _init: function() {
        
        if(this.options.currentRecordset){  //take recordset from options
            this._currentRecordset  = this.options.currentRecordset;
            this._currentRecordsetSelIds = null;
            this._currentRecordsetColIds = null;
        }else if(window.hWin.HAPI4.currentRecordset){ //take global recordset
            this._currentRecordset = window.hWin.HAPI4.currentRecordset;
            this._currentRecordsetSelIds = window.hWin.HAPI4.currentRecordsetSelection;
            this._currentRecordsetColIds = window.hWin.HAPI4.currentRecordsetCollected;
        }else{
            //Testing
            this._currentRecordset = new HRecordSet({count: "0",offset: 0,reccount: 0,records: [], rectypes:[]});
            this._currentRecordsetSelIds = null;
            this._currentRecordsetColIds = null;
        }
        
        this._super();
    },
    
     
    /**
     * @function _initControls
     * @memberof heurist.recordAction
     * @private
     * @description Initializes the controls for the widget after HTML content is loaded.
     * Sets up the record scope selector (`selectRecordScope`). Closes the dialog if scope setup fails.
     * Calls the parent widget's `_initControls` method.
     * @returns {boolean|undefined} Returns `false` and closes dialog if scope selection setup fails.
     */
    _initControls:function(){
        
        this._$('label[for="sel_record_scope"]').text(window.hWin.HR('recordAction_select_lbl'));
        
        this.selectRecordScope = this._$('#sel_record_scope');
        if(this.selectRecordScope.length>0 && this._fillSelectRecordScope()===false){
            this.closeDialog();                
            return false;
        }

        return this._super();
    },

    /**
     * @function _destroy
     * @memberof heurist.recordAction
     * @private
     * @description Cleans up the widget before it is removed. Removes the `selectRecordScope` element.
     */
    _destroy: function() {
        // remove generated elements
        if(this.selectRecordScope) this.selectRecordScope.remove();
    },


    /**
     * @function _fillSelectRecordScope
     * @memberof heurist.recordAction
     * @private
     * @description Populates the record scope selector dropdown (`selectRecordScope`) with available options
     * such as 'All records', 'Selected results set', 'Current results set', and specific record types.
     * The available options are determined by `this.options.scope_types` and the state of `_currentRecordset`.
     * Attaches a change event listener to the selector.
     * @returns {undefined|false} Returns `false` if the dialog should be closed (e.g. error condition, though not explicitly shown in current snippet).
     */
    _fillSelectRecordScope: function (){

        let scope_types = this.options.scope_types;
        this.selectRecordScope.empty();
        
        if(scope_types=='none'){
            this.selectRecordScope.parent().hide();
            return;    
        }

        let selScope = this.selectRecordScope.get(0);

        window.hWin.HEURIST4.ui.addoption(selScope,'',window.hWin.HR('recordAction_select_hint'));
        
        let is_initscope_empty = window.hWin.HEURIST4.util.isempty(scope_types);
        if(is_initscope_empty) scope_types = [];   
        
        if(scope_types.indexOf('all')>=0){
            window.hWin.HEURIST4.ui.addoption(selScope,'all',window.hWin.HR('All records'));
        }
        
        if ((is_initscope_empty || scope_types.indexOf('selected')>=0)
            && (this._currentRecordsetSelIds &&  this._currentRecordsetSelIds.length > 0)){
                
            window.hWin.HEURIST4.ui.addoption(selScope,'selected',
                window.hWin.HR('Selected results set (count=') + this._currentRecordsetSelIds.length+')');
        }

        if ((is_initscope_empty || scope_types.indexOf('collected')>=0)
            && (this._currentRecordsetColIds &&  this._currentRecordsetColIds.length > 0)){
                
            window.hWin.HEURIST4.ui.addoption(selScope,'selected',
                window.hWin.HR('Collected results set (count=') + this._currentRecordsetColIds.length+')');
        }
        
        if ((is_initscope_empty || scope_types.indexOf('current')>=0)
            && (this._currentRecordset &&  this._currentRecordset.length() > 0)){
                
            window.hWin.HEURIST4.ui.addoption(selScope,'current',
                window.hWin.HR('Current results set (count=') + this._currentRecordset.length()+')');
        }

        let rectype_Ids = [];
        if (!is_initscope_empty){
            for (let rty in scope_types)
            if(rty>=0 && scope_types[rty]>0 && $Db.rty(scope_types[rty],'rty_Name')){ 
                rectype_Ids.push(scope_types[rty]);
            }
        }else if(this._currentRecordset &&  this._currentRecordset.length() > 0){
            rectype_Ids = this._currentRecordset.getRectypes();
        }
        
        rectype_Ids.forEach(rty => {
                let name = $Db.rty(rty,'rty_Plural');
                if(!name) name = $Db.rty(rty,'rty_Name');
                
                window.hWin.HEURIST4.ui.addoption(selScope,rty,window.hWin.HR('only:')+' '+name);
        });

        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        this.selectRecordScope.val(this.options.init_scope);    
        if(selScope.selectedIndex<0) selScope.selectedIndex=0;
        this._onRecordScopeChange();
    },

    /**
     * @function _onRecordScopeChange
     * @memberof heurist.recordAction
     * @private
     * @description Event handler for when the record scope selection changes.
     * Disables or enables the main action button based on whether a valid scope is selected.
     * @returns {boolean} True if the action button is disabled, false otherwise.
     */
    _onRecordScopeChange: function () 
    {
        let isdisabled = (this.selectRecordScope.val()=='');
        window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('.btnDoAction'), isdisabled );
        
        return isdisabled;
    },
    
    
    //   @todo use msg.showProgress
    //
    /**
     * @function _showProgress
     * @memberof heurist.recordAction
     * @private
     * @description Displays a progress indicator for long-running actions.
     * It hides the main content and shows a progress bar, periodically updating it by polling a progress URL.
     * Provides an 'Abort' button to terminate the operation.
     * @param {number} session_id - A unique session ID for tracking the progress on the server.
     * @param {boolean} is_autohide - If true, the progress indicator hides automatically when the server reports completion or no data.
     *                               If false, `_hideProgress` must be called explicitly.
     * @param {number} t_interval - The interval in milliseconds at which to poll for progress updates.
     */
    _showProgress: function ( session_id, is_autohide, t_interval ){

        if(!(session_id>0)) {
             this._hideProgress();
             return;
        }
        let that = this;
       
        let progress_url = window.hWin.HAPI4.baseURL + "hserv/controller/progress.php";

        this._$('#div_fieldset').hide();
        this._$('.ent_wrapper').hide();
        let progress_div = this._$('.progressbar_div').show();
        $('body').css('cursor','progress');
        let btn_stop = progress_div.find('.progress_stop').button({label:window.hWin.HR('Abort')});
        
        this._on(btn_stop,{click: function() {
            
                let request = {terminate:1, t:(new Date()).getMilliseconds(), session:session_id};
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    that._hideProgress();
                });
            }});
        
        let div_loading = progress_div.find('.loading').show();
        let pbar = progress_div.find('#progressbar');
        let progressLabel = pbar.find('.progress-label').text('');
        pbar.progressbar({value:0});
        
        this._progressInterval = setInterval(function(){ 
            
            let request = {t:(new Date()).getMilliseconds(), session:session_id};            
            
            window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
               
                if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){
                    that._hideProgress();
                }else{
                    //it may return terminate,done,
                    let resp = response?response.split(','):[];
                    if(response=='terminate' || resp.length!=2){
                        if(response=='terminate' || is_autohide){
                            that._hideProgress();
                        }else{
                            div_loading.show();    
                           
                           
                        }
                    }else{
                        div_loading.hide();
                        if(resp[0]>0 && resp[1]>0){
                            let val = resp[0]*100/resp[1];
                            pbar.progressbar( "value", val );
                            progressLabel.text(resp[0]+' of '+resp[1]);
                        }else{
                            progressLabel.text(window.hWin.HR('preparing')+'...');
                            pbar.progressbar( "value", 0 );
                        }
                    }
                    
                }
            },'text');
          
        
        }, t_interval);                
        
    },
    
    /**
     * @function _hideProgress
     * @memberof heurist.recordAction
     * @private
     * @description Hides the progress indicator and restores the main widget content.
     * Clears any active progress polling interval.
     */
    _hideProgress: function (){
        
        $('body').css('cursor','auto');
        
        if(this._progressInterval!=null){
            
            clearInterval(this._progressInterval);
            this._progressInterval = null;
        }
        this._$('.progressbar_div').hide();
        this._$('#div_fieldset').show();
        
    },
    
  
});

