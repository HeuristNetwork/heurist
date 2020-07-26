
/**
* svs_edit.js : functions to edit and save saved searches (filters)
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

function hSvsEdit(args) {
    var _className = "SvsEdit",
    _version   = "0.4",
    edit_dialog = null,
    callback_method;

    /**
    * Initialization
    */
    function _init(args) {
        //this.currentSearch = currentSearch;
    }

    /**
    * Assign values to UI input controls
    *
    * squery - need for new - otherwise it takes currentSearch
    * domain need for new
    *
    * return false if saved search and true if rules
    */
    function _fromDataToUI(svsID, squery, groupID, allowChangeGroupID){

        var $dlg = edit_dialog;
        if($dlg){
            $dlg.find('.messages').empty();

            var svs_id = $dlg.find('#svs_ID');
            var svs_name = $dlg.find('#svs_Name');
            var svs_query = $dlg.find('#svs_Query');
            var svs_ugrid = $dlg.find('#svs_UGrpID');
            var svs_rules = $dlg.find('#svs_Rules');
            var svs_rules_full = $dlg.find('#svs_Rules2'); //full format - hidden field
            var svs_rules_only = $dlg.find('#svs_RulesOnly');
            var svs_notes = $dlg.find('#svs_Notes');
            var svs_viewmode = $dlg.find('#svs_ViewMode');
            //svs_query.parent().show();
            //svs_ugrid.parent().show();

            var selObj = svs_ugrid.get(0);
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

            var isEdit = (parseInt(svsID)>0);
            var svs = null;
            if(isEdit){
                svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
            }

            if(isEdit && !window.hWin.HEURIST4.util.isnull(svs)){

                var request = window.hWin.HEURIST4.util.parseHeuristQuery(svs[_QUERY]);
                domain  = request.w;
                svs_ugrid.val(svs[_GRPID]==window.hWin.HAPI4.currentUser.ugr_ID ?domain:svs[_GRPID]);

                //ART 2018-02-26 
                //svs_ugrid.parent().hide();
                //svs_ugrid.attr('disabled', true);

                svs_id.val(svsID);
                svs_name.val(svs[_NAME]);
                svs_query.val( !window.hWin.HEURIST4.util.isempty(squery)
                                    ?squery  //overwrite (used in save fixed order)
                                    : ($.isArray(request.q)?JSON.stringify(request.q):request.q) );
                                    
                var crules = window.hWin.HEURIST4.util.cleanRules( request.rules );                                                        
                svs_rules.val( crules==null?'':JSON.stringify(crules) );
                svs_rules_full.val( crules==null?'':request.rules );
                
                var is_rules_only = (request.rulesonly>0 || request.rulesonly==true);
                svs_rules_only.prop('checked', is_rules_only);
                
                $dlg.find('#svs_RulesOnly1').prop('checked', (request.rulesonly!=2));
                $dlg.find('#svs_RulesOnly2').prop('checked', (request.rulesonly==2));
                
                svs_notes.val( request.notes );
                svs_viewmode.val( request.viewmode );


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

                //var domain = 'all';

                if(window.hWin.HEURIST4.util.isArray(squery)) { //this is RULES!!!
                    svs_rules.val(JSON.stringify(window.hWin.HEURIST4.util.cleanRules(squery)));
                    svs_rules_full.val(JSON.stringify(squery))
                    svs_query.val('');

                } else if( squery && (squery.q || squery.rules) ) {

                    svs_query.val( window.hWin.HEURIST4.util.isempty(squery)?'': ($.isArray(squery.q)?JSON.stringify(squery.q):squery.q) );
                    
                    var crules = window.hWin.HEURIST4.util.cleanRules( squery.rules );                                                        
                    svs_rules.val( crules==null?'':JSON.stringify(crules) );
                    var rules = window.hWin.HEURIST4.util.isArray(squery.rules)?JSON.stringify(squery.rules):squery.rules;
                    svs_rules_full.val( rules==null?'':rules );

                } else if(!window.hWin.HEURIST4.util.isempty(squery)){
                    svs_query.val( squery );
                } else {
                    svs_query.val( '' );
                }

                // TODO: remove this code if no longer needed
                //svs_ugrid.val('all');
                //fill with list of Workgroups in case non bookmark search
                /*var selObj = svs_ugrid.get(0);
                if(domain=="bookmark"){
                    svs_ugrid.empty();
                    window.hWin.HEURIST4.ui.addoption(selObj, 'bookmark', window.hWin.HR('My Bookmarks'));
                }else{
                    svs_ugrid.val(domain);
                }*/
                //svs_ugrid.parent().show();
                svs_ugrid.attr('disabled', !(allowChangeGroupID || window.hWin.HEURIST4.util.isempty(groupID)) );
            }

            svs_rules_only.on('change',function(e){
                $dlg.find('#divRulesOnly').css('display',$(e.target).is(':checked')?'block':'none');    
            });
            svs_rules_only.change();
                
            
            var isRules = window.hWin.HEURIST4.util.isempty(svs_query.val()) && !window.hWin.HEURIST4.util.isempty(svs_rules.val());

            if(isRules){ //ruleset only
                svs_query.parent().hide();
                //2018-03-17 svs_rules_only.parent().hide();
                return true;
            }else{
                svs_query.parent().show();
                //2018-03-17 svs_rules_only.parent().show();
                return false;
            }

        }
    }

    /**
    * Show faceted search wizard
    *
    * @param params
    */
    function _showSearchFacetedWizard ( params ){

        if($.isFunction($('body').search_faceted_wiz)){ //already loaded
            showSearchFacetedWizard(params);  //this function from search_faceted_wiz.js
        }else{
            $.getScript(window.hWin.HAPI4.baseURL+'hclient/widgets/search/search_faceted_wiz.js', 
                        function(){ showSearchFacetedWizard(params); } );
        }

    }

    //
    //
    //
    function _editRules(ele_rules, ele_rules_full, squery, groupID, dlg_options) {

       var that = this;

        var url = window.hWin.HAPI4.baseURL+ "hclient/widgets/search/ruleBuilderDialog.php?db=" + window.hWin.HAPI4.database;
        if(!window.hWin.HEURIST4.util.isnull(ele_rules_full)){
            url = url + '&rules=' + encodeURIComponent(ele_rules_full.val());
        }
        
        if(!dlg_options) dlg_options = {};
        dlg_options['closeOnEscape'] = true;
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
                                squery = window.hWin.HEURIST4.util.parseHeuristQuery(squery);
                             }
                             squery.rules = res.rules;
                             //squery = window.hWin.HEURIST4.util.composeHeuristQuery(params.q, params.w, res.rules, params.notes);

                            //mode, groupID, svsID, squery, callback
                            _showDialog('saved', groupID, null, squery ); //open new dialog
                        }else{
                            ele_rules_full.val( JSON.stringify(res.rules) ); //assign new rules
                            
                            var crules = window.hWin.HEURIST4.util.cleanRules( res.rules );                                                        
                            ele_rules.val( crules==null?'':JSON.stringify(crules) );

                        }
                    }
                }
        };

        window.hWin.HEURIST4.msg.showDialog(url, dlg_options);

    }

    
    function  _hasRules (query){
        var prms = Hul.parseHeuristQuery(query);
        if(Hul.isempty(prms.q)){
            return Hul.isempty(prms.rules) ?-1:2;
        }else {
            return Hul.isempty(prms.rules) ?0:1;
        }
    }
    
    /**
    * put your comment there...
    *
    * @param svsID
    * @param squery
    * @param mode - faceted, rules or saved
    * @param is_short - works only for addition (from save fixed order)
    * @param callback
    */
    function _showDialog( mode, groupID, svsID, squery, is_short, position, callback ){
        
        is_short = (!(svsID>0) && is_short===true);
        
        var is_h6style = (window.hWin.HAPI4.sysinfo['layout']=='H6Default');

        if(parseInt(svsID)>0){
            var svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
            if(window.hWin.HEURIST4.util.isnull(svs)){
                //verify that svsID is still in database
                window.hWin.HAPI4.SystemMgr.ssearch_get( {svsIDs: [svsID],
                                    UGrpID: window.hWin.HAPI4.currentUser.ugr_ID},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            
                            if(response.data && response.data[svsID]){
                                window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise edit for this saved search. '
                                +'It does not belong to your group.')+' Owner is user id '+response.data[svsID][_GRPID],
                                 null, "Error");
                            }else{
                                window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise edit for this saved search. '
                                    +'It appears it was removed. Reload page to update tree of saved searches'), null, "Error");
                            }
                        }
                    });
                
                return;
            }
            
            /*
            mode = 'faceted';
            if(!node.data.isfaceted){
                var qsearch = window.hWin.HAPI4.currentUser.usr_SavedSearch[node.key][_QUERY];
                var hasrules = that._hasRules(qsearch);
                mode = hasrules==2?'rules':'saved';
            }
            */
            
            var svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
            var qsearch = svs[_QUERY];
            var mode = 'faceted';
            try {
                facet_params = $.parseJSON(qsearch);
            }
            catch (err) {
                var hasrules = _hasRules(qsearch);
                mode = hasrules==2?'rules':'saved';
            }
        }
        
        //if not defined get last used
        var allowChangeGroupID = false;
        if(window.hWin.HEURIST4.util.isempty(groupID)){
              groupID = window.hWin.HAPI4.get_prefs('last_savedsearch_groupid');
              allowChangeGroupID = true;
        }
        
        if(callback){
            callback_method = callback;
        }

        if (mode == 'faceted'){

            var facet_params = {};
            if(svsID>0){
                var svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
                if(svs){
                    try {
                        facet_params = $.parseJSON(svs[_QUERY]);
                    }catch (err) {
                        // TODo something about the exception here
                        window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise edit for faceted search due to corrupted parameters. Please remove and re-create this search.'), null, "Error");
                        return;
                    }
                }
            }

            _showSearchFacetedWizard( {svsID:svsID, domain:groupID, params:facet_params, position: position, onsave: callback_method });
            //function(event, request){   that._updateAfterSave(request, 'faceted');

        }else if (mode == 'rules' && window.hWin.HEURIST4.util.isnull(svsID)){ //it happens for new rules only


            if(window.hWin.HEURIST4.util.isnull(squery)) squery = {};
             squery.q = ''; // from rule builder we always save pure query only
             
             var dlg_options = null;
             if(is_h6style){
                 dlg_options = {is_h6style:true, position:position, maximize:true};
             }
             
             _editRules(null, null, squery, groupID, dlg_options);

        }else if(null == edit_dialog){
            //create new dialog

            var $dlg = edit_dialog = $( "<div>" ).addClass('ui-heurist-bg-light').appendTo(  $('body') );

            //load edit dialogue
            $dlg.load(window.hWin.HAPI4.baseURL+"hclient/widgets/search/svs_edit.html?t="+(new Date().time), function(){

                //find all labels and apply localization
                $dlg.find('label').each(function(){
                    $(this).html(window.hWin.HR($(this).html()));
                })

                //$dlg.find("#svs_btnset").css({'width':'20px'}).position({my: "left top", at: "right+4 top", of: $dlg.find('#svs_Rules') });

                $dlg.find("#svs_Rules_edit")
                .button({icons: {primary: "ui-icon-pencil"}, text:false})
                .attr('title', window.hWin.HR('Edit RuleSet'))
                .css({'height':'16px', 'width':'16px'})
                .click(function( event ) {
                    //that.
                    
                    var dlg_options = is_h6style?{is_h6style:true}:null;
                    
                    _editRules( $dlg.find('#svs_Rules'), $dlg.find('#svs_Rules2'), '', groupID, dlg_options);
                });

                $dlg.find("#svs_Rules_clear")
                .button({icons: {primary: "ui-icon-close"}, text:false})
                .attr('title', window.hWin.HR('Clear RuleSet'))
                .css({'height':'16px', 'width':'16px'})
                .click(function( event ) {
                    $dlg.find('#svs_Rules').val('');
                    $dlg.find('#svs_Rules2').val('');
                });

                /* this button is moved to bottom panel
                $dlg.find("#svs_GetQuery").button({
                        label:'Get filter + rules as string',
                        title:'Gety query string for Mappable Query'})
                .click(__getFilterString);
                */

                var allFields = $dlg.find('input, textarea');

                //that.
                var isRules = _fromDataToUI(svsID, squery, groupID, allowChangeGroupID);

                function __doSave(need_check_same_name){   //save search

                    var message = $dlg.find('.messages');
                    var svs_id = $dlg.find('#svs_ID');
                    var svs_name = $dlg.find('#svs_Name');
                    var svs_query = $dlg.find('#svs_Query');
                    var svs_ugrid = $dlg.find('#svs_UGrpID');
                    var svs_rules = $dlg.find('#svs_Rules'); 
                    var svs_rules_full = $dlg.find('#svs_Rules2'); //hidden rules in full format
                    var svs_notes = $dlg.find('#svs_Notes');
                    var svs_viewmode = $dlg.find('#svs_ViewMode');
                    var svs_rules_only = $dlg.find('#svs_RulesOnly');
                    
                    allFields.removeClass( "ui-state-error" );
                    
                    svs_ugrid = svs_ugrid.val();
                    var domain = 'all';
                    if(svs_ugrid=="all" || svs_ugrid=="bookmark"){
                        domain = svs_ugrid;
                        svs_ugrid = window.hWin.HAPI4.currentUser.ugr_ID;
                    }

                    var bValid = window.hWin.HEURIST4.msg.checkLength( svs_name, "Name", null, 3, 64 );

                    if(bValid){
                        
                        //validate that name is unique within group
                        if(need_check_same_name!==false)
                        {
                            for (var id in window.hWin.HAPI4.currentUser.usr_SavedSearch){
                                var svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[id];
                                if(svs[_NAME]==svs_name.val() && svs[_GRPID]==svs_ugrid && id!=svs_id.val()){
                                    var $mdlg = window.hWin.HEURIST4.msg.showMsgDlg('Filter with such name already exists in group',
                                    [
                                      {text:'Replace existing', click: function(){ 
                                            svs_id.val(id);
                                            __doSave(false), 
                                            $mdlg.dialog( "close" );}},
                                      {text:'Cancel', click: function(){ $mdlg.dialog( "close" ); svs_name.focus() }}
                                    ]
                                    );
                                    return;
                                }
                            }
                        }

                        var bOk = isRules || window.hWin.HEURIST4.msg.checkLength( svs_query, "Query", null, 1 );
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

                        /*if(window.hWin.HEURIST4.util.isempty(svs_query.val()) && !window.hWin.HEURIST4.util.isempty(svs_rules.val())){   //PURE RuleSet
                            domain = 'rules';
                            svs_ugrid = window.hWin.HAPI4.currentUser.ugr_ID; //@todo!!!! it may by rule accessible by guest
                        }*/
                        
                        var rules_c = window.hWin.HEURIST4.util.cleanRules(svs_rules_full.val());  
                        if(rules_c!=null){ 
                            rules = svs_rules_full.val();
                        }else{
                            rules = null;
                        }
                        
                        var rules_only = 0;
                        if(svs_rules_only.is(':checked')){
                            rules_only = $dlg.find('#svs_RulesOnly1').is(':checked')?1:2;
                        }

                        
                        var request = {  //svs_ID: svsID, //?svs_ID:null,
                            svs_Name: svs_name.val(),
                            svs_Query: window.hWin.HEURIST4.util.composeHeuristQuery2({q:svs_query.val(), 
                                    w:domain, 
                                    rules:rules, 
                                    rulesonly:rules_only, 
                                    notes:svs_notes.val(),
                                    viewmode:svs_viewmode.val()  }, false),
                                    
                            svs_UGrpID: svs_ugrid,
                            domain:domain};

                        var isEdit = ( parseInt(svs_id.val()) > 0 );
                        if(isEdit){
                            request.svs_ID = svs_id.val();
                        }

                        //
                        window.hWin.HAPI4.SystemMgr.ssearch_save(request,
                            function(response){
                                if(response.status == window.hWin.ResponseStatus.OK){

                                    var svsID = response.data;

                                    if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){
                                        window.hWin.HAPI4.currentUser.usr_SavedSearch = {};
                                    }

                                    window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];
                                    
                                    window.hWin.HAPI4.save_pref('last_savedsearch_groupid', request.svs_UGrpID);

                                    $dlg.dialog( "close" );

                                    request.new_svs_ID = svsID;
                                    request.isNewSavedFilter = !isEdit;

                                    callback_method.call(that, null, request);
                                    //@todo that._updateAfterSave(request, 'saved');


                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response, true);
                                    //message.addClass( "ui-state-highlight" );
                                    //message.text(response.message);
                                }
                            }

                        );

                    }
                }

                //
                //  open dialog to copy filter+rules as json query
                //
                function __getFilterString(){
                    
                    var filter = $dlg.find('#svs_Query').val();
                    if(filter.trim()!=''){
                        
                        var req = {q:filter, rules:$dlg.find('#svs_Rules').val()
                                                , db:window.hWin.HAPI4.database};
                        
                        if($dlg.find('#svs_RulesOnly').is(':checked')){
                            req['rulesonly'] = 1;
                        }     
                        if($dlg.find('#svs_UGrpID')=='bookmark'){
                            req['w'] = 'b';
                        }     
                        
                        var res = Hul.hQueryStringify(req);
                        
                        var buttons = {};
                        buttons[window.hWin.HR('Copy')]  = function() {
                            
                            var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                            var target = $dlg.find('#dlg-prompt-value')[0];
                            target.focus();
                            target.setSelectionRange(0, target.value.length);
                            var succeed;
                            try {
                                succeed = document.execCommand("copy");
                                
                                $dlg.dialog( "close" );
                            } catch(e) {
                                succeed = false;
                                alert('Not supported by browser');
                            }                            
                            
                        }; 
                        buttons[window.hWin.HR('Close')]  = function() {
                            var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();            
                            $dlg.dialog( "close" );
                        };
                        
                        //var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                        window.hWin.HEURIST4.msg.showPrompt(
                        '<label>Edit and copy the string and paste into the Mappable Query filter field</label>'
                        + '<textarea id="dlg-prompt-value" class="text ui-corner-all" '
                        + ' style="min-width: 200px; margin-left:0.2em" rows="4" cols="50">'
                        +res
                        +'</textarea>',null,null,
                        {width:450, buttons:buttons,
                            my:'center bottom', at:'center bottom', of: $dlg}
                        );
                    }
                }
                
                
                allFields.on("keypress",function(event){
                    var code = (event.keyCode ? event.keyCode : event.which);
                    if (code == 13) {
                        window.hWin.HEURIST4.util.stopEvent(event);
                        __doSave(true);
                    }
                });
                
                $dlg.dialog({
                    autoOpen: false,
                    height: is_short?360:600,
                    width: 650,                                                                                               
                    modal: true,
                    resizable: false,
                    draggable: !is_h6style,
                    title: window.hWin.HR(isRules?'Edit RuleSet':'Save filter criteria'),
                    position: position,
                    buttons: [
                        {text:window.hWin.HR('Get filter + rules as string'), 
                            click: __getFilterString, css:{'margin-right':'60px'} },
                        {text:window.hWin.HR('Save'), 
                            id:'btnSave',
                            class:'ui-button-action', 
                            click: __doSave, css:{'margin-right':'10px'}},
                        {text:window.hWin.HR('Cancel'), click: function() {
                            $( this ).dialog( "close" );
                        }}
                    ],
                    close: function() {
                        allFields.val( "input, textarea" ).removeClass( "ui-state-error" );
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

            });
        }else{
            //show dialogue
            var isRules = _fromDataToUI(svsID, squery, groupID, allowChangeGroupID);
            edit_dialog.dialog("option",'title', window.hWin.HR(isRules?'Edit RuleSet':'Edit saved filter criteria'));

            edit_dialog.dialog("option",'height', is_short?360:600 );
            if(is_short){
                edit_dialog.find('.hide-if-short').hide();
            }else{
                edit_dialog.find('.hide-if-short').show();    
            }
            
            if(position!=null){
                edit_dialog.dialog( 'option', 'position', position );   
            }
            
            edit_dialog.dialog("open");
        }

    } //end  _showDialog


    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},

        remove: function () {
            //remove edit dialog from body
            edit_dialog.remove();
            edit_dialog = null;
        },

        showSavedFilterEditDialog: function( mode, groupID, svsID, squery, is_short, position, callback ) {
            _showDialog( mode, groupID, svsID, squery, is_short, position, callback );
        }

    }

    _init(args);
    return that;  //returns object
}

