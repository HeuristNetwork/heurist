
/**
* svs_edit.js : functions to edit and save saved searches (filters)
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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

    const _NAME = 0, _QUERY = 1, _GRPID = 2;

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
            var svs_rules_only = $dlg.find('#svs_RulesOnly');
            var svs_notes = $dlg.find('#svs_Notes');
            //svs_query.parent().show();
            //svs_ugrid.parent().show();


            var selObj = svs_ugrid.get(0);
            window.hWin.HEURIST4.ui.createUserGroupsSelect(selObj, null, 
                [{key:'bookmark', title:window.hWin.HR('My Bookmarks (private)')},
                    {key:'all', title:window.hWin.HR('My Filters (private)')}
                    //{key:0, title:window.hWin.HR('Searches for guests')}  removed 2016-02-18
                ],
                function(){
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
                svs_query.val( $.isArray(request.q)?JSON.stringify(request.q):request.q );
                svs_rules.val( request.rules );
                svs_rules_only.prop('checked', (request.rulesonly==1 || request.rulesonly==true));
                svs_notes.val( request.notes );


            }else{ //add new saved search
                isEdit = false;
                svsID = -1;

                svs_id.val('');
                svs_name.val('');
                svs_rules.val('');
                svs_rules_only.prop('checked', false);
                svs_notes.val('');
                svs_ugrid.parent().show();

                //var domain = 'all';

                if(window.hWin.HEURIST4.util.isArray(squery)) { //this is RULES!!!
                    svs_rules.val(JSON.stringify(squery));
                    svs_query.val('');

                } else if( squery && (squery.q || squery.rules) ) {

                    svs_query.val( window.hWin.HEURIST4.util.isempty(squery)?'': ($.isArray(squery.q)?JSON.stringify(squery.q):squery.q) );
                    svs_rules.val( window.hWin.HEURIST4.util.isArray(squery.rules)?JSON.stringify(squery.rules):squery.rules );

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

            var isRules = window.hWin.HEURIST4.util.isempty(svs_query.val()) && !window.hWin.HEURIST4.util.isempty(svs_rules.val());

            if(isRules){ //ruleset only
                svs_query.parent().hide();
                svs_rules_only.parent().hide();
                return true;
            }else{
                svs_query.parent().show();
                svs_rules_only.parent().show();
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
    function _editRules(ele_rules, squery, groupID) {

       var that = this;

        var url = window.hWin.HAPI4.baseURL+ "hclient/framecontent/ruleBuilderDialog.php?db=" + window.hWin.HAPI4.database;
        if(!window.hWin.HEURIST4.util.isnull(ele_rules)){
            url = url + '&rules=' + encodeURIComponent(ele_rules.val());
        }

        window.hWin.HEURIST4.msg.showDialog(url, { width:1200, height:600, title:'Ruleset Editor', callback:
            function(res){
                if(!window.hWin.HEURIST4.util.isempty(res)) {

                    if(res.mode == 'save') {
                        if(window.hWin.HEURIST4.util.isnull(ele_rules)){ //call from resultListMenu - create new rule

                             //replace rules
                             if(!window.hWin.HEURIST4.util.isObject(squery)){
                                squery = window.hWin.HEURIST4.util.parseHeuristQuery(squery);
                             }
                             squery.rules = res.rules;
                             //squery = window.hWin.HEURIST4.util.composeHeuristQuery(params.q, params.w, res.rules, params.notes);

                            //mode, groupID, svsID, squery, callback
                            _showDialog('saved', groupID, null, squery ); //open new dialog
                        }else{
                            ele_rules.val( JSON.stringify(res.rules) ); //assign new rules
                        }
                    }
                }
        }});


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
    * @param callback
    */
    function _showDialog( mode, groupID, svsID, squery, callback ){

        if(parseInt(svsID)>0){
            var svs = window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID];
            if(window.hWin.HEURIST4.util.isnull(svs)){
                //verify that svsID is still in database
                window.hWin.HAPI4.SystemMgr.ssearch_get( {svsIDs: [svsID],
                                    UGrpID: window.hWin.HAPI4.currentUser.ugr_ID},
                    function(response){
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            
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

            _showSearchFacetedWizard( {svsID:svsID, domain:groupID, params:facet_params, onsave: callback_method });
            //function(event, request){   that._updateAfterSave(request, 'faceted');

        }else if (mode == 'rules' && window.hWin.HEURIST4.util.isnull(svsID)){ //it happens for new rules only


            if(window.hWin.HEURIST4.util.isnull(squery)) squery = {};
             squery.q = ''; // from rule builder we always save pure query only
             _editRules(null, squery, groupID);

        }else if(null == edit_dialog){
            //create new dialog

            var $dlg = edit_dialog = $( "<div>" ).addClass('ui-heurist-bg-light').appendTo(  $('body') );

            //load edit dialogue
            $dlg.load(window.hWin.HAPI4.baseURL+"hclient/widgets/search/svs_edit.html?t="+(new Date().time), function(){

                //find all labels and apply localization
                $dlg.find('label').each(function(){
                    $(this).html(window.hWin.HR($(this).html()));
                })

                $dlg.find("#svs_btnset").css({'width':'20px'}).position({my: "left top", at: "right+4 top", of: $dlg.find('#svs_Rules') });

                $dlg.find("#svs_Rules_edit")
                .button({icons: {primary: "ui-icon-pencil"}, text:false})
                .attr('title', window.hWin.HR('Edit RuleSet'))
                .css({'height':'16px', 'width':'16px'})
                .click(function( event ) {
                    //that.
                    _editRules( $dlg.find('#svs_Rules'), '', groupID );
                });

                $dlg.find("#svs_Rules_clear")
                .button({icons: {primary: "ui-icon-close"}, text:false})
                .attr('title', window.hWin.HR('Clear RuleSet'))
                .css({'height':'16px', 'width':'16px'})
                .click(function( event ) {
                    $dlg.find('#svs_Rules').val('');
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

                function __doSave(){   //save search

                    var message = $dlg.find('.messages');
                    var svs_id = $dlg.find('#svs_ID');
                    var svs_name = $dlg.find('#svs_Name');
                    var svs_query = $dlg.find('#svs_Query');
                    var svs_ugrid = $dlg.find('#svs_UGrpID');
                    var svs_rules = $dlg.find('#svs_Rules');
                    var svs_rules_only = $dlg.find('#svs_RulesOnly');
                    var svs_notes = $dlg.find('#svs_Notes');

                    allFields.removeClass( "ui-state-error" );

                    var bValid = window.hWin.HEURIST4.msg.checkLength( svs_name, "Name", message, 3, 30 );

                    if(bValid){

                        var bOk = isRules || window.hWin.HEURIST4.msg.checkLength( svs_query, "Query", null, 1 );
                        if(!bOk) bOk = window.hWin.HEURIST4.msg.checkLength( svs_rules, "Rules", null, 1 );
                        if(!bOk){
                            message.text("Define query, rules or both.");
                            message.addClass( "ui-state-highlight" );
                            setTimeout(function() {
                                message.removeClass( "ui-state-highlight", 1500 );
                                }, 500 );
                            bValid = false;
                        }
                    }

                    if(bValid){

                        var svs_ugrid = svs_ugrid.val();

                        var domain = 'all';
                        if(svs_ugrid=="all" || svs_ugrid=="bookmark"){
                            domain = svs_ugrid;
                            svs_ugrid = window.hWin.HAPI4.currentUser.ugr_ID;
                            //if(domain!="all"){query_to_save.push('w='+domain);}
                        }

                        /*if(window.hWin.HEURIST4.util.isempty(svs_query.val()) && !window.hWin.HEURIST4.util.isempty(svs_rules.val())){   //PURE RuleSet
                            domain = 'rules';
                            svs_ugrid = window.hWin.HAPI4.currentUser.ugr_ID; //@todo!!!! it may by rule accessible by guest
                        }*/

                        var request = {  //svs_ID: svsID, //?svs_ID:null,
                            svs_Name: svs_name.val(),
                            svs_Query: window.hWin.HEURIST4.util.composeHeuristQuery(svs_query.val(), 
                                    domain, svs_rules.val(), svs_rules_only.is(':checked'), svs_notes.val(), false),
                                    
                            svs_UGrpID: svs_ugrid,
                            domain:domain};

                        var isEdit = ( parseInt(svs_id.val()) > 0 );
                        if(isEdit){
                            request.svs_ID = svs_id.val();
                        }

                        //
                        window.hWin.HAPI4.SystemMgr.ssearch_save(request,
                            function(response){
                                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

                                    var svsID = response.data;

                                    if(!window.hWin.HAPI4.currentUser.usr_SavedSearch){
                                        window.hWin.HAPI4.currentUser.usr_SavedSearch = {};
                                    }

                                    window.hWin.HAPI4.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];
                                    
                                    window.hWin.HAPI4.save_pref('last_savedsearch_groupid', request.svs_UGrpID);

                                    $dlg.dialog( "close" );

                                    request.new_svs_ID = svsID;

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

                
                function __getFilterString(){
                    var filter = $dlg.find('#svs_Query').val();
                    if(filter.trim()!=''){
                        var rules = $dlg.find('#svs_Rules').val();

                        var res = '';

                        try {
                            var r = $.parseJSON(filter);
                            if($.isArray(r) || $.isPlainObject(r)){
                                res = '{"q":'+filter;
                            }
                        }
                        catch (err) {
                        }
                        if(res==''){
                            //escape backslash to avoid errors
                            res = '{"q":"'+filter.split('"').join('\\\"')+'"';
                        }

                        if(rules!=''){
                            res = res + ',"rules":'+rules+'}';
                        } else{
                            res = res + '}';     
                        }
                        
                        
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
                        {options:{width:450, buttons:buttons},
                        my:'center bottom', at:'center bottom', of: $dlg}
                        );
                    }
                }
                
                
                allFields.on("keypress",function(event){
                    var code = (event.keyCode ? event.keyCode : event.which);
                    if (code == 13) {
                        window.hWin.HEURIST4.util.stopEvent(event);
                        __doSave();
                    }
                });


                $dlg.dialog({
                    autoOpen: false,
                    height: 600,
                    width: 650,                                                                                               
                    modal: true,
                    resizable: false,
                    title: window.hWin.HR(isRules?'Edit RuleSet':'Save filter criteria'),
                    buttons: [
                        {text:window.hWin.HR('Get filter + rules as string'), click: __getFilterString, css:{'margin-right':'60px'} },
                        {text:window.hWin.HR('Save'), click: __doSave, css:{'margin-right':'10px'}},
                        {text:window.hWin.HR('Cancel'), click: function() {
                            $( this ).dialog( "close" );
                        }}
                    ],
                    close: function() {
                        allFields.val( "input, textarea" ).removeClass( "ui-state-error" );
                    }
                });

                $dlg.dialog("open");
                $dlg.parent().addClass('ui-dialog-heurist');

            });
        }else{
            //show dialogue
            var isRules = _fromDataToUI(svsID, squery, groupID, allowChangeGroupID);
            edit_dialog.dialog("option",'title', window.hWin.HR(isRules?'Edit RuleSet':'Edit saved filter criteria'));
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

        show: function( mode, groupID, svsID, squery, callback ) {
            _showDialog( mode, groupID, svsID, squery, callback );
        }

    }

    _init(args);
    return that;  //returns object
}

