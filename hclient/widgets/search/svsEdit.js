
/**
* @file svsEdit.js
* @brief Provides functions to edit and save saved searches/filters and visualizations.
* @fileOverview This file defines the HSvsEdit factory function, which creates an object
* responsible for managing the dialog and logic for creating, editing, and
* saving user-defined searches (filters) and visualizations. It handles
* different modes of operation, including standard saved searches, rule-based
* searches, and faceted search configurations (by invoking the
* search_faceted_wiz widget).
*
* @package     Heurist academic knowledge management system
* @subpackage  hclient\widgets\search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/* global translationToUI, translationFromUI, showSearchFacetedWizard */

/**
 * Factory function to create an SvsEdit instance.
 * This instance provides methods to manage the dialog for editing and saving
 * various types of saved searches and visualizations.
 *
 * @function HSvsEdit
 * @param {Object} [args] - Arguments for the SvsEdit instance (currently not used, but reserved for future extension).
 * @returns {Object} An object with methods to manage saved search editing.
 * @property {function} getClass Returns the class name.
 * @property {function} isA Checks if the instance is of a given class.
 * @property {function} getVersion Returns the version of the SvsEdit module.
 * @property {function} remove Removes the edit dialog from the DOM.
 * @property {function} isModified Checks if the content of the edit dialog has been modified.
 * @property {function} closeEditDialog Closes the edit dialog.
 * @property {function} showSavedFilterEditDialog Shows the dialog for editing/creating a saved filter or visualization.
 */
function HSvsEdit(args) {
    /** @const {string} _className - Internal class name identifier. */
    const _className = "SvsEdit",
    /** @const {string} _version - Internal version number. */
    _version   = "0.4";
    /**
     * @private
     * @type {?jQuery}
     * @description jQuery object representing the main edit dialog. Null if not initialized.
     */
    let edit_dialog = null,
    /**
     * @private
     * @type {Object<string, (string|boolean)>}
     * @description Stores the initial values of the dialog fields to check for modifications.
     */
    keep_values = {'svs_Name':'','svs_Query':'','svs_UGrpID':'','svs_Rules':'','svs_RulesOnly':false,'svs_Notes':'','svs_ViewMode':''},
    /**
     * @private
     * @type {boolean}
     * @description Flag to prevent concurrent save operations.
     */
    _save_in_porgress = false,
    /**
     * @private
     * @type {?function}
     * @description Callback function to be executed after a successful save operation.
     */
    callback_method,
    /**
     * @private
     * @type {?function}
     * @description Callback function related to menu locking behavior when the dialog is open.
     */
    _menu_locked;

    /**
     * Checks if the data in the edit dialog has been modified compared to its initial state.
     * @private
     * @function _isModified
     * @returns {boolean} True if modified, false otherwise.
     */
    function _isModified(){

        let $dlg = edit_dialog;
        if($dlg){
            let keys = Object.keys(keep_values);
            for (let idx in keys){
                let key = keys[idx];
                let ele = $dlg.find('#'+key);
                if(keep_values[key] !== ((ele.attr('type')=='checkbox')?ele.is(':checked'):ele.val())){
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
    * Assigns values from data to the UI input controls in the edit dialog.
    * This function populates the form fields based on whether it's an existing
    * saved search (svsID) or a new one (squery, groupID).
    *
    * @private
    * @function _fromDataToUI
    * @param {?number} svsID - The ID of the saved search if editing an existing one, otherwise null or undefined.
    * @param {string|Object} [squery] - The search query string or query object for a new search.
    *                                 If an object, it can contain `q` (query string) and `rules`.
    * @param {string|number} [groupID] - The group ID for the saved search.
    * @param {boolean} [allowChangeGroupID=false] - Whether the group ID selection should be enabled.
    * @returns {boolean} True if the form is configured for a rules-only search, false otherwise (i.e., it's a standard filter or includes a query string).
    */
    function _fromDataToUI(svsID, squery, groupID, allowChangeGroupID){

        let $dlg = edit_dialog;
        if($dlg){
            $dlg.find('.messages').empty();

            let svs_id = $dlg.find('#svs_ID');
            let svs_name = $dlg.find('#svs_Name');
            let svs_query = $dlg.find('#svs_Query');
            let svs_ugrid = $dlg.find('#svs_UGrpID');
            let svs_rules = $dlg.find('#svs_Rules');
            let svs_rules_full = $dlg.find('#svs_Rules2'); //full format - hidden field
            let svs_rules_only = $dlg.find('#svs_RulesOnly');
            let svs_notes = $dlg.find('#svs_Notes');
            let svs_viewmode = $dlg.find('#svs_ViewMode');
           
           

            let selObj = svs_ugrid.get(0);
            window.hWin.HEURIST4.ui.createUserGroupsSelect(selObj, null, 
                [{key:'bookmark', title:window.hWin.HR('My Bookmarks (private)')},
                    {key:'all', title:window.hWin.HR('My Filters (private)')}
                    //{key:0, title:window.hWin.HR('Searches for guests')}  removed 2016-02-18
                ],
                function(){
                    if(groupID == window.hWin.HAPI4.currentUser.ugr_ID){
                        groupID = '';
                    }
                    svs_ugrid.val(window.hWin.HEURIST4.util.isempty(groupID)?'all':groupID);
            });

            let isEdit = (parseInt(svsID)>0);
            let svs = null;
            if(isEdit){
                svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
            }
            
            let request = {};

            if(isEdit && !window.hWin.HEURIST4.util.isnull(svs)){

                request = window.hWin.HEURIST4.query.parseHeuristQuery(svs[1]); //Hul._QUERY
                let domain  = request.w;
                svs_ugrid.val(svs[2]==window.hWin.HAPI4.currentUser.ugr_ID ?domain:svs[2]); //Hul._GRPID

                //ART 2018-02-26 
               
               

                svs_id.val(svsID);
                svs_name.val(svs[0]); //Hul._NAME
                svs_query.val( !window.hWin.HEURIST4.util.isempty(squery)
                                    ?squery  //overwrite (used in save fixed order)
                                    : (Array.isArray(request.q)?JSON.stringify(request.q):request.q) );
                                    
                let crules = window.hWin.HEURIST4.query.cleanRules( request.rules );                                                        
                svs_rules.val( crules==null?'':JSON.stringify(crules) );
                svs_rules_full.val( crules==null?'':request.rules );
                
                let is_rules_only = (request.rulesonly>0 || request.rulesonly==true);
                svs_rules_only.prop('checked', is_rules_only);
                
                $dlg.find('#svs_RulesOnly'+request.rulesonly).prop('checked', true);
                
                svs_notes.val( request.notes );
                svs_viewmode.val( request.viewmode );
                
                if(!request.name) request.name = svs[0]; //Hul._NAME


            }else{ //add new saved search
                isEdit = false;
                svsID = -1;

                svs_id.val('');
                svs_name.val('');
                svs_rules.val('');
                svs_rules_full.val('');
                svs_rules_only.prop('checked', false);
                $dlg.find('#divRulesOnly').hide();
                svs_notes.val('');
                svs_viewmode.val('');
                svs_ugrid.parent().show();

                if(Array.isArray(squery)) { //this is RULES!!!
                    svs_rules.val(JSON.stringify(window.hWin.HEURIST4.query.cleanRules(squery)));
                    svs_rules_full.val(JSON.stringify(squery))
                    svs_query.val('');

                } else if( squery && (squery.q || squery.rules) ) {

                    svs_query.val( window.hWin.HEURIST4.util.isempty(squery)?'': (Array.isArray(squery.q)?JSON.stringify(squery.q):squery.q) );
                    
                    let crules = window.hWin.HEURIST4.query.cleanRules( squery.rules );                                                        
                    svs_rules.val( crules==null?'':JSON.stringify(crules) );
                    let rules = Array.isArray(squery.rules)?JSON.stringify(squery.rules):squery.rules;
                    svs_rules_full.val( rules==null?'':rules );

                } else if(!window.hWin.HEURIST4.util.isempty(squery)){
                    svs_query.val( squery );
                } else {
                    svs_query.val( '' );
                }

                svs_ugrid.attr('disabled', !(allowChangeGroupID || window.hWin.HEURIST4.util.isempty(groupID)) );
            }
            
            translationToUI(request, $dlg, 'ui_name', 'svs_Name', false);
            translationToUI(request, $dlg, 'ui_notes', 'svs_Notes', true);
            
            for (let i=0; i<Object.keys(keep_values).length; i++){
                let key = Object.keys(keep_values)[i];
                let ele = $dlg.find('#'+key);
                keep_values[key] = (ele.attr('type')=='checkbox')?ele.is(':checked'):ele.val();                
            }

            svs_rules_only.on('change',function(e){
                $dlg.find('#divRulesOnly').css('display',$(e.target).is(':checked')?'block':'none');    
            });
            svs_rules_only.trigger('change');
                
            
            let isRules = window.hWin.HEURIST4.util.isempty(svs_query.val()) && !window.hWin.HEURIST4.util.isempty(svs_rules.val());

            if(isRules){ //ruleset only
                svs_query.parent().hide();
               
                return true;
            }else{
                svs_query.parent().show();
               
                return false;
            }

        }
    }

    /**
    * Shows the faceted search wizard.
    * Dynamically loads `search_faceted_wiz.js` if it's not already available.
    *
    * @private
    * @function _showSearchFacetedWizard
    * @param {Object} params - Parameters to pass to the `showSearchFacetedWizard` global function.
    *                          See `hclient/widgets/search/search_faceted_wiz.js` for details on these parameters.
    * @returns {jQuery|undefined} The jQuery object for the faceted search wizard dialog, or undefined if loading failed.
    */
    function _showSearchFacetedWizard ( params ){

        if(window.hWin.HEURIST4.util.isFunction($('body').search_faceted_wiz)){ //already loaded
            return showSearchFacetedWizard(params);  //this function from search_faceted_wiz.js
        }else{
            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/search/search_faceted_wiz.js', 
                        function(){ showSearchFacetedWizard(params); } );
        }

    }

    /**
     * Opens the rule builder dialog for editing search rules.
     *
     * @private
     * @function _editRules
     * @param {jQuery} ele_rules - jQuery object for the input field storing the cleaned/simplified rules JSON string.
     * @param {jQuery} ele_rules_full - jQuery object for the hidden input field storing the full rules JSON string.
     * @param {string|Object} squery - The current search query, used if creating new rules in context.
     * @param {string|number} groupID - The current group ID.
     * @param {Object} [dlg_options={}] - Additional options for the rule builder dialog.
     */
    function _editRules(ele_rules, ele_rules_full, squery, groupID, dlg_options) {

       let that = this;

        let url = window.hWin.HAPI4.baseURL+ "hclient/widgets/search/ruleBuilderDialog.php?db=" + window.hWin.HAPI4.database;
        if(!window.hWin.HEURIST4.util.isnull(ele_rules_full)){
            url = url + '&rules=' + encodeURIComponent(ele_rules_full.val());
        }
        
        if(!dlg_options) dlg_options = {};
        dlg_options['closeOnEscape'] = true;
        dlg_options['isPopupDlg'] = true;
        dlg_options['width'] = 1200;
        dlg_options['height'] = 600;
        if(!dlg_options['title']) dlg_options['title'] = 'Ruleset Editor';
        dlg_options['callback'] = function(res)
        {
                if(!window.hWin.HEURIST4.util.isempty(res)) {

                    if(res.mode == 'save') {
                        if(window.hWin.HEURIST4.util.isnull(ele_rules_full)){ //call from resultListMenu - create new rule

                             //replace rules
                             if(!window.hWin.HEURIST4.util.isObject(squery)){
                                squery = window.hWin.HEURIST4.query.parseHeuristQuery(squery);
                             }
                             squery.rules = res.rules;
                            

                            //mode, groupID, svsID, squery, callback
                            _showDialog('saved', groupID, null, squery ); //open new dialog
                        }else{
                            ele_rules_full.val( JSON.stringify(res.rules) ); //assign new rules
                            
                            let crules = window.hWin.HEURIST4.query.cleanRules( res.rules );                                                        
                            ele_rules.val( crules==null?'':JSON.stringify(crules) );

                        }
                    }
                }
        };

        window.hWin.HEURIST4.msg.showDialog(url, dlg_options);

    }

    /**
     * Checks if a given query string contains rules and/or a query part.
     *
     * @private
     * @function _hasRules
     * @param {string} query - The Heurist query string to parse.
     * @returns {number}
     *         - -1: If both query and rules are empty.
     *         -  0: If query is present but rules are empty.
     *         -  1: If both query and rules are present.
     *         -  2: If rules are present but query is empty (rules-only).
     */
    function  _hasRules (query){
        let prms = window.hWin.HEURIST4.query.parseHeuristQuery(query);
        if( window.hWin.HEURIST4.util.isempty(prms.q)){
            return window.hWin.HEURIST4.util.isempty(prms.rules) ?-1:2;
        }else {
            return window.hWin.HEURIST4.util.isempty(prms.rules) ?0:1;
        }
    }
    
    /**
    * Shows the main dialog for editing or creating a saved search/filter or visualization.
    * This function determines which specialized UI to show (standard form, faceted wizard, or rule editor)
    * based on the `mode` and existing `svsID` or `squery` content.
    *
    * @private
    * @function _showDialog
    * @param {string} mode - The mode of operation: 'faceted', 'rules', or 'saved'.
    * @param {string|number} groupID - The target group ID for saving.
    * @param {?number} svsID - The ID of the saved search if editing an existing one.
    * @param {string|Object} [squery] - The search query string or object for a new item or when editing rules.
    * @param {boolean} [is_short=false] - If true, shows a compact version of the dialog (applies only when adding).
    * @param {Object|string|jQuery|Element|Event} [position] - Position for the dialog, as per jQuery UI dialog `position` option.
    * @param {?function} [callback] - Callback function to execute after a successful save.
    * @param {boolean} [is_modal=true] - Whether the dialog should be modal.
    * @param {boolean} [is_h6style=false] - Whether to apply H6 styling.
    * @param {?function} [menu_locked] - Callback function for menu locking.
    * @param {boolean} [reset_svs_edit=true] - If true, forces re-initialization of UI from data.
    * @returns {jQuery|undefined} The jQuery object for the dialog, or undefined if a specialized wizard is invoked.
    */
    function _showDialog( mode, groupID, svsID, squery, is_short, position, callback, is_modal, is_h6style, menu_locked, reset_svs_edit){
        
        is_short = (!(svsID>0) && is_short===true);
        is_modal = (is_modal!==false);
        is_h6style = (is_h6style===true);
        reset_svs_edit = (reset_svs_edit!==false);
        _menu_locked = menu_locked;
        
        if(parseInt(svsID)>0){
            let svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
            if(window.hWin.HEURIST4.util.isnull(svs)){
                //verify that svsID is still in database
                window.hWin.HAPI4.SystemMgr.ssearch_get( {svsIDs: [svsID],
                                    UGrpID: window.hWin.HAPI4.currentUser.ugr_ID},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            if(response.data && response.data[svsID]){
                                window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise edit for this saved search. '
                                +'It does not belong to your group.')+' Owner is user id '+response.data[svsID][2], //Hul._GRPID
                                 null, "Error");
                            }else{
                                window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise edit for this saved search. '
                                    +'It appears it was removed. Reload page to update tree of saved searches'), null, "Error");
                            }
                        }
                    });
                
                return;
            }
            
            svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
            let qsearch = svs[1]; //Hul._QUERY
            let r = window.hWin.HEURIST4.query.parseHeuristQuery(qsearch);
            mode = 'saved';
            if(r.type==3){
                mode = 'faceted';
            }else if(window.hWin.HEURIST4.util.isempty(r.q)){
                mode = 'rules';
            }
        }
        
        //if not defined get last used
        let allowChangeGroupID = false;
        if(window.hWin.HEURIST4.util.isempty(groupID)){
              groupID = window.hWin.HAPI4.get_prefs('last_savedsearch_groupid');
              allowChangeGroupID = true;
        }
        
        if(callback){
            callback_method = callback;
        }

        if (mode == 'faceted'){

            let facet_params = null;
            if(svsID>0){
                let svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
                if(svs){
                    try {
                        facet_params = JSON.parse(svs[1]); //Hul._QUERY
                    }catch (err) {
                        // TODo something about the exception here
                        window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise edit for faceted search due to corrupted parameters. Please remove and re-create this search.'), null, "Error");
                        return;
                    }
                }
            }
            
            let opts = {svsID:svsID, domain:groupID, 
                        position: position, onsave: callback_method, 
                        is_h6style:is_h6style, is_modal:is_modal, menu_locked:menu_locked };
                        
            if(!facet_params) opts.params = facet_params;
            return _showSearchFacetedWizard( opts );
                        
           

        }else if (mode == 'rules' && window.hWin.HEURIST4.util.isnull(svsID)){ //it happens for new rules only


            if(window.hWin.HEURIST4.util.isnull(squery)) squery = {};
             squery.q = ''; // from rule builder we always save pure query only
             
             let dlg_options = null;
             if(is_h6style){
                 dlg_options = {is_h6style:true, position:position, maximize:true};
             }
             
             _editRules(null, null, squery, groupID, dlg_options);

        }else if(null == edit_dialog){
            //create new dialog

            let $dlg = edit_dialog = $( "<div>" ).addClass('save-filter-dialog ui-heurist-bg-light').appendTo(  $('body') );

            //load edit dialogue
            $dlg.load(window.hWin.HAPI4.baseURL+"hclient/widgets/search/svsEdit.html?t="+(new Date().time), function(){

                //find all labels and apply localization
                $dlg.find('label').each(function(){
                    $(this).html(window.hWin.HR($(this).html()));
                })

               

                $dlg.find("#svs_Rules_edit")
                .button({icon: "ui-icon-pencil", showLabel:false})
                .attr('title', window.hWin.HR('Edit RuleSet'))
                .css({'height':'16px', 'width':'16px'})
                .on('click', function( event ) {
                    //that.
                    
                    let dlg_options = null;
                    if(is_h6style){
                        dlg_options = {
                            is_h6style:true, 
                            close:function(){
                                if(menu_locked && window.hWin.HEURIST4.util.isFunction(menu_locked)){
                                    menu_locked.call( this, false, false); //unlock
                                }
                            }
                            };
                    }
                    if(menu_locked && window.hWin.HEURIST4.util.isFunction(menu_locked)){
                        menu_locked.call( this, true, false); //lock
                    }
                    
                    _editRules( $dlg.find('#svs_Rules'), $dlg.find('#svs_Rules2'), '', groupID, dlg_options);
                });

                $dlg.find("#svs_Rules_clear")
                .button({icon: "ui-icon-close", showLabel:false})
                .attr('title', window.hWin.HR('Clear RuleSet'))
                .css({'height':'16px', 'width':'16px'})
                .on('click', function( event ) {
                    $dlg.find('#svs_Rules').val('');
                    $dlg.find('#svs_Rules2').val('');
                });
                
                
                /* this button is moved to bottom panel
                $dlg.find("#svs_GetQuery").button({
                        label:'Get filter + rules as string',
                        title:'Gety query string for Mappable Query'})
                .on('click',__getFilterString);
                */

                let allFields = $dlg.find('input, textarea');

                //that.
                let isRules = _fromDataToUI(svsID, squery, groupID, allowChangeGroupID);

                function __doSave(need_check_same_name){   //save search

                    let message = $dlg.find('.messages');
                    let svs_id = $dlg.find('#svs_ID');
                    let svs_name = $dlg.find('#svs_Name');
                    let svs_query = $dlg.find('#svs_Query');
                    let svs_ugrid = $dlg.find('#svs_UGrpID');
                    let svs_rules = $dlg.find('#svs_Rules'); 
                    let svs_rules_full = $dlg.find('#svs_Rules2'); //hidden rules in full format
                    let svs_notes = $dlg.find('#svs_Notes');
                    let svs_viewmode = $dlg.find('#svs_ViewMode');
                    let svs_rules_only = $dlg.find('#svs_RulesOnly');
                    
                    allFields.removeClass( "ui-state-error" );
                    
                    svs_ugrid = svs_ugrid.val();
                    let domain = 'all';
                    if(svs_ugrid=="all" || svs_ugrid=="bookmark"){
                        domain = svs_ugrid;
                        svs_ugrid = window.hWin.HAPI4.currentUser.ugr_ID;
                    }

                    let bValid = window.hWin.HEURIST4.msg.checkLength( svs_name, "Name", null, 3, 64 );

                    if(bValid){
                        
                        //validate that name is unique within group
                        if(need_check_same_name!==false)
                        {
                            for (let id in window.hWin.HAPI4.currentUser.usr_SavedSearch){
                                let svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[id];
                                if(svs[0]==svs_name.val() && svs[2]==svs_ugrid && id!=svs_id.val()){ //Hul._NAME  _GRPID
                                    
                                    if(menu_locked && window.hWin.HEURIST4.util.isFunction(menu_locked)){
                                        menu_locked.call( this, true, false); //unlock
                                    }
                                    
                                    let $mdlg = window.hWin.HEURIST4.msg.showMsgDlg('Filter with such name already exists in group',
                                    [
                                      {text:'Replace existing', click: function(){ 
                                            svs_id.val(id);
                                            __doSave(false);
                                            $mdlg.dialog( "close" );
                                            
                                            if(menu_locked && window.hWin.HEURIST4.util.isFunction(menu_locked)){
                                                menu_locked.call( this, false, false); //unlock
                                            }
                                      }},
                                      {text:'Cancel', click: function(){ 
                                            $mdlg.dialog( "close" ); 
                                            svs_name.trigger('focus');
                                            if(menu_locked && window.hWin.HEURIST4.util.isFunction(menu_locked)){
                                                menu_locked.call( this, false, false); //unlock
                                            }
                                      }}
                                    ]
                                    );
                                    return;
                                }
                            }
                        }

                        let bOk = isRules || window.hWin.HEURIST4.msg.checkLength( svs_query, "Query", null, 1 );
                        if(!bOk) bOk = window.hWin.HEURIST4.msg.checkLength( svs_rules, "Rules", 'Rules are required if there is no filter string', 1 );
                        if(!bOk){
                            message.text("Define filter, rules or both.");
                            message.addClass( "ui-state-highlight" );
                            setTimeout(function() {
                                message.removeClass( "ui-state-highlight", 1500 );
                                }, 500 );
                            bValid = false;
                        }
                    }

                    if(bValid){
                        
                        if(_save_in_porgress===true) return;
                        _save_in_porgress = true;
                        
                        let rules = null;
                        let rules_c = window.hWin.HEURIST4.query.cleanRules(svs_rules_full.val());  
                        if(rules_c!=null){ 
                            rules = svs_rules_full.val();
                        }
                        
                        let rules_only = 0;
                        if(svs_rules_only.is(':checked')){
                            rules_only = $dlg.find('input[name="svs_RulesOnly"]:checked').val();
                        }

                        let params = {};
                        translationFromUI(params, $dlg, 'ui_name', 'svs_Name', false);
                        translationFromUI(params, $dlg, 'ui_notes', 'svs_Notes', true);
                        params = $.extend(params, {q:svs_query.val(), 
                                    w:domain, 
                                    rules:rules, 
                                    rulesonly:rules_only, 
                                    viewmode:svs_viewmode.val()  });
                        
                        let request = {  //svs_ID: svsID, //?svs_ID:null,
                            svs_Name: svs_name.val(),
                            svs_Query: JSON.stringify(params),
                            svs_UGrpID: svs_ugrid,
                            domain:domain};

                        let isEdit = ( parseInt(svs_id.val()) > 0 );
                        if(isEdit){
                            request.svs_ID = svs_id.val();
                        }

                        //
                        window.hWin.HAPI4.SystemMgr.ssearch_save(request,
                            function(response){
                                _save_in_porgress = false;
                                if(response.status == window.hWin.ResponseStatus.OK){

                                    let svsID = response.data;

                                    if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){
                                        window.hWin.HAPI4.currentUser.usr_SavedSearch = {};
                                    }

                                    window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];
                                    
                                    window.hWin.HAPI4.save_pref('last_savedsearch_groupid', request.svs_UGrpID);

                                    $dlg.dialog( "close" );

                                    request.new_svs_ID = svsID;
                                    request.isNewSavedFilter = !isEdit;

                                    callback_method.call(that, null, request);
                                   


                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response, true);
                                   
                                   
                                }
                            }

                        );

                    }
                }

                //
                //  open dialog to copy filter+rules as json query
                //
                function __getFilterString(){
                    
                    let filter = $dlg.find('#svs_Query').val();
                    if(filter.trim()!=''){

                        let req = {svs: $dlg.find('#svs_ID'), db:window.hWin.HAPI4.database};

                        window.hWin.HEURIST4.query.hQueryCopyPopup(req, {my: 'center bottom', at: 'center bottom', of: $dlg});
                    }
                }
                
                
                window.hWin.HEURIST4.ui.disableAutoFill(allFields);
                
                allFields
                    .on("keypress",function(event){
                        let code = (event.keyCode ? event.keyCode : event.which);
                        if (code == 13) {
                            window.hWin.HEURIST4.util.stopEvent(event);
                            __doSave(true);
                        }
                    });
                

                
                
                edit_dialog = $dlg.dialog({
                    autoOpen: false,
                    height: is_short?360:520,
                    width: 650,                                                                                               
                    modal: is_modal, //!is_h6style
                    resizable: false,
                    draggable: !is_h6style,
                    title: window.hWin.HR(isRules?'Edit RuleSet':'Save filter criteria'),
                    position: position,
                    buttons: [
                        {text:window.hWin.HR('Get filter as string'), 
                            click: __getFilterString, css:{'margin-right':'60px'} },
                        {text:window.hWin.HR('Save'), 
                            id:'btnSave2',
                            class:'ui-button-action', 
                            click: __doSave, css:{'margin-right':'10px'}},
                        {text:window.hWin.HR('Cancel'), click: function() {
                            $( this ).dialog( "close" );
                        }}
                    ],
                    close: function() {
                        allFields.removeClass( "ui-state-error" );
                        if(!isRules && menu_locked && window.hWin.HEURIST4.util.isFunction(menu_locked)){
                                menu_locked.call( this, 'close'); //is_locked, is_mouseleave    
                        }
                    },
                    show: {
                        effect: 'fade',
                        duration: 500
                    }
                });
                
                if(is_short){
                    edit_dialog.find('.hide-if-short').hide();
                }else{
                    edit_dialog.find('.hide-if-short').show();    
                }
                
                $dlg.dialog("open");
                $dlg.parent().addClass('ui-dialog-heurist');
                if(is_h6style){
                    $dlg.parent().addClass('ui-heurist-explore');
                }
                if(window.hWin.HEURIST4.util.isFunction(menu_locked)){  //@todo add call on open rulebuilder
                    $dlg.parent('.ui-dialog').on({
                        mouseover:function(){ 
                            let is_mod = _isModified();
                            menu_locked.call( this, is_mod?'delay':false, false ); //is_locked, is_mouseleave
                        },  
                        mouseleave: function(e){ menu_locked.call( this, false, true ) }});
                }

            });
        }else{
            //show dialogue
            let isRules = false;
            
            if(reset_svs_edit){
                isRules = _fromDataToUI(svsID, squery, groupID, allowChangeGroupID);
            }
            
            edit_dialog.dialog("option",'title', window.hWin.HR(isRules?'Edit RuleSet':'Edit saved filter'));

            edit_dialog.dialog("option",'height', is_short?360:520 );
            if(is_short){
                edit_dialog.find('.hide-if-short').hide();
            }else{
                edit_dialog.find('.hide-if-short').show();    
            }
            
            if(position!=null){
                edit_dialog.dialog( 'option', 'position', position );   
            }
            edit_dialog.dialog( 'option', 'modal', is_modal );   
            
            edit_dialog.dialog( 'open' );
        }
        
        return edit_dialog;

    } //end  _showDialog


    //public members
    let that = {

        /**
         * Gets the class name of the SvsEdit instance.
         * @memberof HSvsEdit
         * @instance
         * @function getClass
         * @returns {string} The class name "SvsEdit".
         */
        getClass: function () {return _className;},
        /**
         * Checks if the instance is of a specific class type.
         * @memberof HSvsEdit
         * @instance
         * @function isA
         * @param {string} strClass - The class name to compare against.
         * @returns {boolean} True if `strClass` is "SvsEdit", false otherwise.
         */
        isA: function (strClass) {return (strClass === _className);},
        /**
         * Gets the version of the SvsEdit module.
         * @memberof HSvsEdit
         * @instance
         * @function getVersion
         * @returns {string} The version number.
         */
        getVersion: function () {return _version;},

        /**
         * Removes the edit dialog from the DOM and performs necessary cleanup.
         * @memberof HSvsEdit
         * @instance
         * @function remove
         */
        remove: function () {
            //remove edit dialog from body
            if (edit_dialog) {
                edit_dialog.parent('.ui-dialog').off('mouseover mouseleave');
                edit_dialog.remove();
                edit_dialog = null;
            }
        },
        
        /**
         * Checks if the currently open edit dialog has been modified since it was loaded.
         * @memberof HSvsEdit
         * @instance
         * @function isModified
         * @returns {boolean} True if the dialog is open and modified, false otherwise.
         */
        isModified: function(){
            if(edit_dialog && edit_dialog.dialog('instance') && edit_dialog.dialog('isOpen')){

                return _isModified();    
               
            }else{
                return false;
            }
        },

        /**
         * Closes the edit dialog if it is currently open.
         * @memberof HSvsEdit
         * @instance
         * @function closeEditDialog
         */
        closeEditDialog: function () {
            if(edit_dialog && edit_dialog.dialog('instance')){  
                edit_dialog.dialog("close");    
            }
        },
        
        /**
         * Shows the dialog for creating or editing a saved filter, rule set, or faceted search.
         * This is the main public method to display and interact with the SvsEdit functionality.
         *
         * @memberof HSvsEdit
         * @instance
         * @function showSavedFilterEditDialog
         * @param {string} mode - The mode of operation:
         *                        'faceted' for faceted search configuration,
         *                        'rules' for rule-based search,
         *                        'saved' for standard saved search/filter.
         * @param {string|number} groupID - The group ID where the item will be saved or which it belongs to.
         * @param {?number} svsID - The ID of the saved search/visualization if editing an existing one; null for new.
         * @param {string|Object} [squery] - The search query string or query object for a new item,
         *                                 or when initially populating the editor.
         * @param {boolean} [is_short=false] - If true, displays a compact version of the dialog (applies mainly when adding).
         * @param {Object|string|jQuery|Element|Event} [position] - Position for the dialog, as per jQuery UI dialog `position` option.
         * @param {?function} [callback] - A callback function to be executed after a successful save operation.
         *                               The callback will receive `null` as the first argument (error) and the
         *                               `request` object (containing saved data) as the second argument.
         * @param {boolean} [is_modal=true] - Specifies whether the dialog should be modal.
         * @param {boolean} [is_h6style=false] - If true, applies H6 styling to the dialog.
         * @param {?function} [menu_locked] - A callback function for managing menu locking behavior when the dialog is open.
         * @param {boolean} [reset_svs_edit=true] - If true (default), forces re-initialization of the dialog's UI elements
         *                                       from the provided data. If false, may retain existing UI state if dialog is reused.
         * @returns {jQuery|undefined} The jQuery object representing the dialog element, or undefined if a
         *                             specialized wizard (like faceted search) is invoked directly.
         */
        showSavedFilterEditDialog: function( mode, groupID, svsID, squery, is_short, 
                    position, callback, is_modal, is_h6style, menu_locked, reset_svs_edit ) 
        {
            return _showDialog( mode, groupID, svsID, squery, is_short, position, 
                        callback, is_modal, is_h6style, menu_locked, reset_svs_edit );
        }
        
    }

    return that;  //returns object
}

