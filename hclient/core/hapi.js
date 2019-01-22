/**
* Main class for Heurist API
*
* Constructor:
* @param _db - database name, if omit it takes from url parameter
* @param _oninit - callback function, obtain parameter true if initialization is successeful
* @returns hAPI Object
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

function hAPI(_db, _oninit) { //, _currentUser
    var _className = "HAPI",
    _version   = "0.4",
    _database = null, //same as public property  @toremove      
    _region = 'en',
    _regional = null, //localization resources
    _guestUser = {ugr_ID:0, ugr_FullName:'Guest' },
    _listeners = [];
    _is_callserver_in_progress = false;

    /**
    * initialization of hAPI object
    *  1) define paths from top.location
    *  2) takes regional from  localization.js
    *
    * @param _db - database name, if omit it takes from url parameter
    * @param _oninit - callback function, obtain parameter true if initialization is successeful
    *
    */
    function _init(_db, _oninit){ //, _currentUser) {
    
        //@todo - take  database from URL
        if(_db){
            _database = _db;
        }else{
            _database = window.hWin.HEURIST4.util.getUrlParameter('db');
        }

        //
        var installDir = window.hWin.location.pathname.replace(/(((\?|admin|applications|common|context_help|export|hapi|hclient|hsapi|import|records|redirects|search|viewers|help|ext|external)\/.*)|(index.*|test.php))/, ""); // Upddate in 2 places this file and 6 other files if changed
        //TODO: top directories - admin|applications|common| ... are defined in SEVEN separate locations. Rationalise.
        that.baseURL = window.hWin.location.protocol + '//'+window.hWin.location.host + installDir;

        // TODO: This is actually a proto URL rather than a base URL. Rename.
        that.iconBaseURL = that.baseURL + 'hsapi/dbaccess/rt_icon.php?db='+_database+'&id=';
        that.database = _database;

        //global variable defined in localization.js
        if(!(typeof regional === 'undefined')){
            _regional = regional;
            /*todo
            $.getScript(that.baseURL+'hclient/core/localization.js', function() {
            _regional = regional;
            });
            */
        }
        if(!window.hWin.HR){
            window.hWin.HR = that.setLocale('en');
        }
    

        if(typeof hLayout !== 'undefined' && $.isFunction(hLayout)){
            that.LayoutMgr = new hLayout();
        }
        if(typeof hSearchMinimal !== 'undefined' && $.isFunction(hSearchMinimal)){
            that.SearchMgr = new hSearchMinimal();
        }


        /*if(_currentUser){
        that.currentUser = _currentUser;
        }else{}*/
        // Get current user if logged in, and global database settings
        // see usr_info.php sysinfo method  and then system->getCurrentUserAndSysInfo
        if(that.database){
            that.SystemMgr.sys_info( _oninit );
        }else{
            if(_oninit){
                _oninit(false);
            }
        }

    }
    
    /*
    * internal function see hSystemMgr, hRecordMgr - ajax request to server
    *
    * @param action   - name of php script in hsapi/controller foolder on server side
    * @param request - data to be sent to server side
    * @param callback - callback function, it obtains object with 2 properties:
    *       status - see ResponseStatus
    *       message - ajax response or error message
    */
    function _callserver(action, request, callback){
        
        _is_callserver_in_progress = true;

        if(!request.db){
            request.db = _database;
        }
        if(request.notes){
            request.notes = null; //unset to reduce traffic
        }


        //remove remark to debug 
        //request.DBGSESSID='425944380594800002;d=1,p=0,c=07';
        //DBGSESSID=425944380594800002;d=1,p=0,c=07

        var url = that.baseURL+"hsapi/controller/"+action+".php"; //+(new Date().getTime());
        
        var request_code = {script:action, action:request.a};
        //note jQuery ajax does not properly in the loop - success callback does not work often
        $.ajax({
            url: url,
            type: "POST",
            data: request,
            dataType: "json",
            cache: false,
            xhrFields: {
                  withCredentials: true
            },            
            error: function( jqXHR, textStatus, errorThrown ) {
                
                _is_callserver_in_progress = false;

                err_message = (window.hWin.HEURIST4.util.isempty(jqXHR.responseText))
                                            ?'Error_Connection_Reset':jqXHR.responseText;
                                            
                var response = {status:window.hWin.ResponseStatus.UNKNOWN_ERROR, 
                                        message: err_message, 
                                        request_code:request_code};

                if(callback){
                    callback(response);
                }
                //message:'Error connecting server '+textStatus});
            },
            success: function( response, textStatus, jqXHR ){

                _is_callserver_in_progress = false;
                
                if(callback){
                    if($.isPlainObject(response)){
                        response.request_code = request_code;
                    }
                    callback(response);
                }
                
                /*check response for special marker that forces to reload user and system info
                //after update sysIdentification, dbowner and user role
                if(response && 
                    (response.status == window.hWin.ResponseStatus.OK) && 
                     response.force_refresh_sys_info) {
                         that.SystemMgr.sys_info(function(success){
                             
                         });
                }*/
                
            },
            fail: function(  jqXHR, textStatus, errorThrown ){
                
                _is_callserver_in_progress = false;
                
                err_message = (window.hWin.HEURIST4.util.isempty(jqXHR.responseText))?'Error_Connection_Reset':jqXHR.responseText;
                var response = {status:window.hWin.ResponseStatus.UNKNOWN_ERROR, 
                            message: err_message,
                            request_code: request_code}

                if(callback){
                    callback(response);
                }
            }
        });

    }

    // TODO: Remove, enable or explain
    /**
    * User class
    *
    * @returns {Object}
    function hUserMgr(){

    var _currentUser = null,
    _guestUser = {ugr_ID:0, ugr_FullName:'Guest'};

    var that = {

    // request {username: , password: }
    // response HUser object
    login: function(request, callback){
    if(request) request.a = 'login';
    _callserver('usr_info', request, callback);
    }

    ,logout: function(callback){
    _callserver('usr_info', {a:'logout'}, callback);
    }
    }
    }
    */


    /**
    * System class that responsible for interaction with server in domains:
    *       user/groups information/credentials
    *       database definitions - record structure, field types, terms
    *       saved searches
    *
    * see usr_info.php and sys_structure.php
    *
    * methods:
    *   login        - login and get current user info
    *   logout
    *   reset_password
    *   verify_credentials  - checks whether user is logged in and force_refresh_sys_info in case change roles or update db settings
    * 
    *   sys_info     - get current user info and database settings - used only in hapi.init and on force_refresh_sys_info
    *   save_prefs   - save user preferences  in session
    *   mygroups     - description of current user Workgroups
    *   ssearch_get  - get saved searches for current user and all usergroups where user is memeber, or by list of ids
    *   ssearch_save - save saved search in database
    *   ssearch_delete - delete saved searches by IDs
    *   ssearch_savetree - save saved search treeview data
    *   ssearch_gettree - get saved search treeview data
    *   get_defs     - get the desired database structure definition
    *   get_defs_all
    * 
    *   get_url_content_type - resolve mimetype for given url
    *
    * @returns {Object}
    */
    function hSystemMgr(){

        var that = {

            /**
            * Log in
            *
            * @param request - object {username: , password: }
            * @param callback - callback function with response parameter HUser object
            */
            login: function(request, callback){
                if(request) request.a = 'login';
                _callserver('usr_info', request, callback);
            }

            ,reset_password: function(request, callback){
                if(request) request.a = 'reset_password';
                _callserver('usr_info', request, callback);
            }

            /**
            * Log out
            */
            ,logout: function(callback){
                _callserver('usr_info', {a:'logout'}, callback);
            }

            /**
            *  1) it verifies crendentials on server side and checks if they will be upated
            *  2) in case they are changed it returns up-to-date user and sys info
            *  3) in case needed level of credentials is defined it verifies the permissions
            * 
            * requiredLevel - 
            *  -1 no verification
            *  0 logged (DEFAULT)
            *  groupid  - admin of group  
            *  1 - db admin (admin of group #1)
            *  2 - db owner
            * 
            * password_protected - name of password 
            * 
            * 
            *  need to call this method before every major action or open popup dialog
            *  for internal actions use client side methods of hapi.is_admin, is_member, has_access
            */
            , verify_credentials: function(callback, requiredLevel, password_protected, password_entered){

                if(!window.hWin.HEURIST4.util.isempty(password_protected)){
                    

                        if(window.hWin.HAPI4.sysinfo['pwd_'+password_protected]){ //password defined
                        
                            window.hWin.HEURIST4.msg.showPrompt('Enter password: ',
                                function(password_entered){
                                    
                                    window.hWin.HAPI4.SystemMgr.action_password({action:password_protected, password:password_entered},
                                        function(response){
                                            if(response.status == window.hWin.ResponseStatus.OK && response.data=='ok'){
                                                window.hWin.HAPI4.SystemMgr.verify_credentials(callback, requiredLevel, null, password_entered);                                        ;
                                            }else{
                                                window.hWin.HEURIST4.msg.showMsgFlash('Wrong password');
                                            }
                                        }
                                    );
                                    
                                },
                            'This action is password-protected', {password:true});
                        
                        }else{
                            window.hWin.HEURIST4.msg.showMsgDlg('This action is not allowed unless a challenge password is set - please consult system administrator');
                        }
                        return false;
                    
                }                
                
                
                requiredLevel = Number(requiredLevel);
                if(requiredLevel<0){ //no verification required - everyone access
                    callback(password_entered); 
                    return; 
                }
                
                function __verify(){
                    
                        if(window.hWin.HAPI4.has_access(requiredLevel)){ 
                              callback( password_entered );  
                        }else{
                            var response = {};
                            response.sysmsg = 0;
                            response.status = window.hWin.ResponseStatus.REQUEST_DENIED;
                            response.message = 'To perform this operation you have to be logged in';
                            
                            if(requiredLevel==window.hWin.HAPI4.sysinfo.db_managers_groupid){
                               response.message += ' as database administrator';// of group "Database Managers"' 
                            }else if(requiredLevel==2){
                               response.message += ' as database onwer';
                            }else  if(requiredLevel>0){
                               var sGrpName = '';
                               if( window.hWin.HAPI4.sysinfo.db_usergroups && window.hWin.HAPI4.sysinfo.db_usergroups[requiredLevel]){
                                    sGrpName = ' "'+window.hWin.HAPI4.sysinfo.db_usergroups[requiredLevel]+'"';
                               } 
                               response.message += ' as administrator of group #'+requiredLevel+sGrpName;
                            }
                            
                            window.hWin.HEURIST4.msg.showMsgErr(response, true);
                        }
                }
                
                function __response_handler(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        if(response.data.sysinfo){
                            window.hWin.HAPI4.sysinfo = response.data.sysinfo;
//!!!!  assign baseURL window.hWin.HAPI4.baseURL = window.hWin.HAPI4.sysinfo['baseURL'];
                        }
                        if(response.data.currentUser) {
                            window.hWin.HAPI4.setCurrentUser(response.data.currentUser);   
                            //trigger global event ON_CREDENTIALS
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
                        }
                    
                        //since currentUser is up-to-date - use client side method
                        __verify();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response, true);
                    }
                }
                
                //MODE1 verify locally only
                if(true){
                    __verify(); //verification on server temporarely disabled
                
                }else{
                    //MODE2 verify via server each time
                    //check if login
                    _callserver('usr_info', {a:'verify_credentials'}, __response_handler);
                }
            }
            
            ,sys_info_count: function(callback){
                _callserver('usr_info', {a:'sys_info_count'}, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            window.hWin.HAPI4.sysinfo['db_total_records'] = response.data[0];
                            window.hWin.HAPI4.sysinfo['db_has_active_dashboard'] = response.data[1];
                            if(callback) callback();
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response.message); 
                        }
                    });
            }

            /**
            * Get current user if logged in, and global database settings
            * used only in hapi.init and on force_refresh_sys_info
            */
            ,sys_info: function(callback){
                
                _callserver('usr_info', {a:'sysinfo'}, 
                    function(response){
                        var  success = (response.status == window.hWin.ResponseStatus.OK);
                        if(success){
                            
                            if(response.data.currentUser) {
                                window.hWin.HAPI4.setCurrentUser(response.data.currentUser);   
                            }
                            if(response.data.sysinfo){
                                window.hWin.HAPI4.sysinfo = response.data.sysinfo;
//!!!! assign baseURL window.hWin.HAPI4.baseURL = window.hWin.HAPI4.sysinfo['baseURL'];
                            }
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response.message);
                        }
                        if(callback){
                            callback(success);
                        }
                    }
                );
            }

            /**
            * Save user profile info in db
            * @param request
            * @param callback
            */
            ,user_save: function(request, callback){
                if(request) request.a = 'usr_save';
                _callserver('usr_info', request, callback);
            }

            /**
            * Get user profile info form db - used in Admin part only?
            * @param request
            * @param callback
            */
            ,user_get: function(request, callback){
                if(request) request.a = 'usr_get';
                _callserver('usr_info', request, callback);
            }
            
            //get user full names by id
            ,usr_names:function(request, callback){

                var ugrp_ids = request.UGrpID;
                if(ugrp_ids>=0){
                    ugrp_ids = [ugrp_ids];
                }else{                
                    ugrp_ids = (!$.isArray(ugrp_ids)?ugrp_ids.split(','):ugrp_ids);    
                }
                
                //first try to take on client side
                var sUserNames = {};
                request.UGrpID = [];
                
                for(var idx in ugrp_ids){
                
                    var usr_ID = Number(ugrp_ids[idx]);    
                    var sUserName = null;
                
                    if(usr_ID==0){
                        sUserName = window.hWin.HR('Everyone');
                    }else if(usr_ID == window.hWin.HAPI4.currentUser['ugr_ID']){
                        sUserName = window.hWin.HAPI4.currentUser['ugr_FullName'];
                    }else if( window.hWin.HAPI4.sysinfo.db_usergroups && window.hWin.HAPI4.sysinfo.db_usergroups[usr_ID]){
                        sUserName = window.hWin.HAPI4.sysinfo.db_usergroups[usr_ID];
                    }
                    if(sUserName){
                        sUserNames[usr_ID] = sUserName;
                    }else{
                        request.UGrpID.push(usr_ID);
                    }
                
                }
                
                
                if(request.UGrpID.length==0){ //all names are resolved on client side
                    callback.call(this, {status:window.hWin.ResponseStatus.OK, data:sUserNames} );
                }else{
                    //search on server
                    if(request) request.a = 'usr_names';
                    _callserver('usr_info', request, function(context){
                        if(context.status==window.hWin.ResponseStatus.OK){
                            
                            sUserNames = $.extend(sUserNames, context.data);
                            
                            callback.call(this, {status:window.hWin.ResponseStatus.OK, data:sUserNames} );       
                        }
                    });
                }
            }

            ,user_log: function(activity, suplementary){
                
                if($.isFunction(gtag)){
/*                    
Category
Action
Label (optional, but recommended) is the string that will appear as the event label.
Value (optional) is a non-negative integer that will appear as the event value.

gtag('event', <action>, {
  'event_category': <category>,
  'event_label': <label>,
  'value': <value>
});
*/                  
/*
[open]_structure_Terms  Structure - category, open - action, Terms - label
add_Record   Record - category   "add" action
open_Crosstabs
db_Register

actions:
open - default
add
imp =import
sync
upl =upload
verify
refresh
exp =export

short categories
db  =Database
st  =Structure
Rec =Rceord 
admin
hlp
prof =Profile


*/
                    var parts = activity.split('_'); 
                    //allowed actions
                    var actions = ['open','add','imp','sync','upl','verify','refresh','exp','search','delete','edit'];

                    var idx = 0;                    
                    var k = actions.indexOf(parts[0].toLowerCase());
                    var evt_action = 'open';
                    if(k>=0){
                        evt_action = actions[k];
                        idx++;
                    }
                    
                    //short names for cats
                    var categories = {'db':'database','st':'structure','rec':'record','hlp':'help','prof':'profile'};
                    
                    var evt_category = parts[idx].toLowerCase();
                    if(categories[evt_category]) evt_category = categories[evt_category];
                    idx++;
                    
                    var evt_label = (idx<parts.length)?parts[idx].toLowerCase():null;
                    
                    
                    gtag('event', evt_action, {'event_category': evt_category, 'event_label': evt_label});
                }
                
                if(activity.indexOf('search')<0 && activity.indexOf('edit')<0){
                
                    activity = activity.replace('_','');
                    
                    var request = {a:'usr_log', activity:activity, suplementary:suplementary};
                    _callserver('usr_info', request);
                
                }
            }

            //
            // verify special system passwords for some password-protection actions
            //
            ,action_password: function(request, callback){
                if(request) request.a = 'action_password';
                _callserver('usr_info', request, callback);
            }
            
            /**
            * Save user personal info/register new user
            * @param request - object - user info
            * @param callback
            */
            ,save_prefs: function(request, callback){
                if(request) request.a = 'save_prefs';
                _callserver('usr_info', request, callback);
            }
            
            /**
            * Returns detailed description of groupfs for current user
            *
            * response data - array of ugl_GroupID:[ugl_Role, ugr_Name, ugr_Description]
            */
            ,mygroups: function(callback){
                _callserver('usr_info', {a:'groups'}, callback);
            }

            /**
            *  Get saved searches for current user and all usergroups where user is memeber
            *
            * request
            *    UGrpID: group id -  if not defined returns all saved searches for current user
            *  response data - array of  svs_ID:[svs_Name, svs_Query, svs_UGrpID]
            */
            ,ssearch_get: function(request, callback){
                if(!request) request = {};

                request.a = 'svs_get';
                _callserver('usr_info', request, callback);
            }

            /**
            *  Save saved search in database
            *
            *  request - object
            *   svs_ID (not specified if ADD new search)
            *   svs_Name - name
            *   svs_Query - heurist query
            *   svs_UGrpID - user/group ID
            */
            ,ssearch_save: function(request, callback){
                if(request) request.a = 'svs_save';
                _callserver('usr_info', request, callback);
            }

            /**
            * Delete saved searches by IDs
            * request : {ids: list of records to be deleted}
            */
            ,ssearch_delete: function(request, callback){
                if(request) request.a = 'svs_delete';
                _callserver('usr_info', request, callback);
            }

            ,ssearch_savetree: function(request, callback){
                if(request) request.a = 'svs_savetree';
                _callserver('usr_info', request, callback);
            }

            ,ssearch_gettree: function(request, callback){
                if(request) request.a = 'svs_gettree';
                _callserver('usr_info', request, callback);
            }
            /**
            *  Get the desired database structure definition
            * request
            *   terms, rectypes, detailtypes :  list of desired ids,  OR 'all'
            *   mode: applied for rectypes  0 only names (default), 1 only strucuture, 2 - both, 3 - all,   4 - for faceted search(with type names)
            *
            */
            ,get_defs: function(request, callback){
                _callserver('sys_structure', request, callback);
            }

            ,get_defs_all: function(is_message, document, callback){
                
                window.hWin.HEURIST4.msg.bringCoverallToFront();

                this.get_defs({rectypes:'all', terms:'all', detailtypes:'all', mode:2}, function(response){
                    
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        
                        window.hWin.HEURIST4.rectypes = response.data.rectypes;
                        window.hWin.HEURIST4.terms = response.data.terms;
                        window.hWin.HEURIST4.detailtypes = response.data.detailtypes;
                        
                        if (is_message==true) {
                            $dlg = window.hWin.HEURIST4.msg.showMsgDlg('Database structure definitions in browser memory have been refreshed.<br>'+
                                'You may need to reload pages to see changes.');
                            $dlg.parent('.ui-dialog').css({top:150,left:150});    
                        }      
                        
                        if($.isFunction(callback)) callback.call();

                        window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
                    }
                });

            },
            
            get_url_content_type: function(url, callback){
                var request = {a:'get_url_content_type', url: url};
                _callserver('usr_info', request, callback);
            },

            get_sysimages: function(folders, callback){
                var request = {a:'sysimages', folders: folders};
                _callserver('usr_info', request, callback);
            }

            /*
            ,databases: function(request, callback){
            _callserver('sys_databases', request, callback);
            }*/
        }
        return that;
    }

    /**
    * System class that responsible for record's edit, search and tags
    *
    * see record_edit.php, record_details.php, record_tags.php and record_search.php
    *
    * methods:
    *           for record_edit controller
    *   add       - creates new temporary record
    *   save      - save record
    *   remove    - delete record
    *   duplicate
    *   access    - ownership and visibility
    *   increment
    *
    *           for record_details controller
    *   details   - batch edition of record details for many records
    *
    *           for record_search controller
    *   search
    *   minmax
    *   get_facets
    *   search_related
    *
    * @returns {Object}
    */
    function hRecordMgr(){

        
        
        var that = {

            /**
            * Creates temporary new record
            *
            * @param request a: a|add
            *      optional  rt - rectype, ro - owner,  rv - visibility
            * @param callback - response hRecordSet object
            */
            add: function(request, callback){
                if(request){
                    request.a = 'a';
                }else{
                    request = { a:'a' };
                }
                _callserver('record_edit', request, callback);
            }

            /**
            *  Save Record (remove temporary flag if new record)
            *
            * @param request a: s|save
            * @param callback - response hRecordSet object
            */
            ,save: function(request, callback){
                if(request) request.a = 's';
                _callserver('record_edit', request, callback);
            }
            
            ,duplicate: function(request, callback){
                if(request) request.a = 'duplicate';
                _callserver('record_edit', request, callback);
            }
            
            /**
            * ownership and visibility
            * ids, OwnerUGrpID, NonOwnerVisibility
            */
            ,access: function(request, callback){
                if(request) request.a = 'access';
                _callserver('record_edit', request, callback);
            }
            
            ,increment: function(rtyID, dtyID, callback){
                var request = {a:'increment', rtyID:rtyID, dtyID:dtyID};
                _callserver('record_edit', request, callback);
            }
            
            /**
            * Remove Record
            *
            * @param request a: d|delete
            *               ids: list of records to be deleted
            * @param callback
            */
            ,remove: function(request, callback){
                if(request) request.a = 'd';
                _callserver('record_edit', request, callback);
            }

            /**
            * Batch edition of record details
            *
            * @param request a: add,replace,delete
            *
            * recIDs - list of records IDS to be processed
            * rtyID - optional filter by record type
            * dtyID  - detail field to be added
            * for add: val, geo or ulfID
            * for replace: sVal - search value, rVal - replace value
            * for delete:  sVal - search value
            * tag 0|1  - add system tag to mark processed records
            *
            * @param callback
            */
            ,batch_details: function(request, callback){
                _callserver('record_details', request, callback);
            }
            
//@TODO - need to implement queue for record_search, otherwise sometimes we get conflict on simultaneous requests            
            
            /**
            * Search for records via global events
            * to search directly use SearchMgr
            *
            * request { }
            *  q - query string
            *  w - a|b - domain all or bookmarks
            *  f - none or cs list detail,map,structure,tags,relations,(backward)links,text,comments - details of output
            *  limit - limit
            *  o - offset
            *
            *  callback - callback function or  $document we have trigger the event
            */
            ,search: function(request, callback){


                if(!$.isFunction(callback)){   //@todo - remove all this stuff since it is implemented in SearchMgr

                    if(!request.increment || window.hWin.HEURIST4.util.isnull(request.id)){
                        request.id = window.hWin.HEURIST4.util.random();
                    }

                    var document = callback;
                    if(!window.hWin.HEURIST4.util.isnull(document) && !request.increment){
                        document.trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHSTART, [ request ]); //global app event
                    }

                    callback = function(response)
                    {
                        var resdata = null;
                        if(response.status == window.hWin.ResponseStatus.OK){
                            resdata = new hRecordSet(response.data);
                        }else{

                            window.hWin.HEURIST4.msg.showMsgErr(response);

                            if(!window.hWin.HEURIST4.util.isnull(document)){
                                document.trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, null); //global app event
                            }
                        }
                        if(!window.hWin.HEURIST4.util.isnull(document)){
                            document.trigger(window.hWin.HAPI4.Event.ON_REC_SEARCHRESULT, [ resdata ]);  //gloal app event
                        }
                    }
                }


                //if limit is not defined - get it from preferences
                //if(window.hWin.HEURIST4.util.isnull(request.limit)){
                //    request.limit = window.hWin.HAPI4.get_prefs('search_detail_limit'); //if needall is set it is ignored on server side
                //}

                // start search
                _callserver('record_search', request, callback);    //standard search

            }


            /**
            * Search relation within given set of records
            *
            * request { }
            *  ids - list of record ids
            *
            *  callback - callback function or  $document we have trigger the event
            */
            ,search_related: function(request, callback){
                if(request) request.a = 'related';
                _callserver('record_search', request, callback);    //standard search
            }


            // find min and max values for
            // rt - record type
            // dt - detailtyep
            ,minmax: function(request, callback){
                if(request) request.a = 'minmax';
                _callserver('record_search', request, callback);
            }

            // find ranges for faceted search
            //
            ,get_facets: function(request, callback){
                if(request && !request.a) request.a = 'getfacets';
                _callserver('record_search', request, callback);
            }

            //@TODO get full info for particular record
            ,get: function(request, callback){
                _callserver('record_get', request, callback);
            }
        }
        return that;
    }

    /**
    * System class that responsible for interaction with server in domains:
    *       user/groups information/credentials
    *       database definitions - record structure, field types, terms
    *       saved searches
    *
    * see entityScrud.php and db[TableName].php in dbaccess
    *
    * methods:
    *   config - loads entity config
    *   search
    *   save
    *   delete
    *
    * @returns {Object}
    */
    function hEntityMgr(){

        var entity_configs = {};
        var entity_data = {};

        var that = {

            //load entity configuration file
            // entityScrud.action = config
            // 
            getEntityConfig:function(entityName, callback){

                if(entity_configs[entityName]){
                    if($.isFunction(callback)){
                        callback(entity_configs[entityName]);
                    }
                    return entity_configs[entityName];
                }else{
                    _callserver('entityScrud', {a:'config', 'entity':entityName},
                       function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){

                                var entity_cfg = response.data;

                                //find key and title fields
                                var idx;
                                for(idx in entity_cfg.fields){
                                    if(entity_cfg.fields[idx]['keyField']==true){
                                        entity_cfg.keyField = entity_cfg.fields[idx]['dtID'];
                                    }
                                    if(entity_cfg.fields[idx]['titleField']==true){
                                        entity_cfg.titleField = entity_cfg.fields[idx]['dtID'];
                                    }
                                }
                                entity_configs[response.data.entityName] = entity_cfg;

                                callback(entity_cfg);
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                       }

                    );
                }
            },
            
            //
            // reset entity data that forces reload from server on next reequest
            //
            clearEntityData: function(entityName){
                if(!$.isEmptyObject(entity_data[entityName])){
                    entity_data[entityName] = {};
                }
            },

            
            //load entire entity data and store it in cache (applicable for entities with count < ~1500)
            // entityScrud.action = search
            //
            getEntityData:function(entityName, force_reload, callback){

                if($.isEmptyObject(entity_data[entityName]) || force_reload==true){
                    
                    _callserver('entityScrud', {a:'search', 'entity':entityName, 'details':'list'},
                       function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                entity_data[response.data.entityName] = new hRecordSet(response.data);
                                if($.isFunction(callback)) callback(entity_data[response.data.entityName]);
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                       }

                    );
                }else{
                    if($.isFunction(callback)){
                        callback(entity_data[entityName]);
                    }else{
                        //if user sure that data is already on client side
                        return entity_data[entityName];
                    }
                }
            },
            
            getEntityData2: function(entityName){
                return entity_data[entityName];     
            },

            setEntityData: function(entityName, data){
                entity_data[entityName] = data;
            },
            
            //
            // clear clinet side entity data for further refresh
            //
            emptyEntityData:function(entityName){
                if(entityName){
                    entity_data[entityName] = {};    
                }else{
                    entity_data = {};
                }
            },

            //
            // generic request for entityScrud
            //
            doRequest:function(request, callback){
                //todo - verify basic params
                request['request_id'] = window.hWin.HEURIST4.util.random();
                //request['DBGSESSID'] = '424657986609500001;d=1,p=0,c=0';  //DEBUG parameter
                _callserver('entityScrud', request, callback);
            },

            //
            // retrieve title of entity bt given id
            // entityScrud.action = title
            //
            getTitlesByIds:function(entityName, recIDs, callback){
                
                var idx, display_value = [];
                if(entity_data[entityName]){
                   var ecfg = entity_configs[entityName];
                   var edata = entity_data[entityName];
                   if(!$.isArray(recIDs)) recIDs = [recIDs];
                   for(idx in recIDs){
                        display_value.push(
                            edata.fld(edata.getById(recIDs[idx]), ecfg.titleField));
                   }
                   
                   callback.call(this, display_value);
                }else{
                    
                    
                        var request = {};
                        request['recID']  = recIDs;
                        request['a']          = 'title'; //action
                        request['entity']     = entityName;
                        request['request_id'] = window.hWin.HEURIST4.util.random();
                        
                        window.hWin.HAPI4.EntityMgr.doRequest(request, 
                                        function(response){
                                            if(response.status == window.hWin.ResponseStatus.OK){
                                                
                                                callback.call(this, response.data ); //array of titles
                                            }else{
                                                callback.call(this, recIDs);
                                            }
                                        }
                                    );
                                    
                }
            }

        }
        return that;
    }


    //public members
    var that = {

        baseURL: '', 
        iconBaseURL: '',
        database: '',

        Event: {
            /*LOGIN: "LOGIN",
            LOGOUT: "LOGOUT",*/
            ON_CREDENTIALS: 'ON_CREDENTIALS', //login, logout, change user role, sysinfo (change sysIdentification) 
            ON_REC_SEARCHSTART: "ON_REC_SEARCHSTART",
            ON_REC_SEARCH_FINISH: "ON_REC_SEARCH_FINISH",
            ON_REC_SEARCHRESULT: "ON_REC_SEARCHRESULT",
            //ON_REC_SEARCHTERMINATE: "ON_REC_SEARCHTERMINATE",
            ON_REC_PAGESET: "ON_REC_PAGESET",
            ON_REC_SELECT: "ON_REC_SELECT",
            ON_LAYOUT_RESIZE: "ON_LAYOUT_RESIZE",
            ON_SYSTEM_INITED: "ON_SYSTEM_INITED",
            ON_STRUCTURE_CHANGE: 'ON_STRUCTURE_CHANGE',
            ON_PREFERENCES_CHANGE: 'ON_PREFERENCES_CHANGE',
        },

        /**
        * Assign user after system initialization - obtained from server side by SystemMgr.sys_info
        *
        * @param user
        */
        setCurrentUser: function(user){
            if(user){
                that.currentUser = user;
            }else{
                that.currentUser = _guestUser;
            }
        },
        
        currentUserRemoveGroup: function(groupID, isfinal){

            if(window.hWin.HAPI4.currentUser['ugr_Groups'][groupID]){
                window.hWin.HAPI4.currentUser['ugr_Groups'][groupID] = null;
                delete window.hWin.HAPI4.currentUser['ugr_Groups'][groupID];
            }
            if(isfinal){
                window.hWin.HAPI4.sysinfo.db_usergroups[groupID] = null;
                delete window.hWin.HAPI4.sysinfo.db_usergroups[groupID];
            }
        },
        
        // is_admin, is_member, has_access - verify credentials on client side
        // they have to be used internally in widgets and loop operations to avoid server/network workload
        // However, before start any action or open widget popup need to call 
        // SystemMgr.verify_credentials
        
        /**
        * Returns true is current user is database admin (admin in group Database Managers)
        */
        is_admin: function(){
            return window.hWin.HAPI4.has_access(window.hWin.HAPI4.sysinfo.db_managers_groupid);
        },

        /**
        * Returns true if currentUser is member of given group ID or itself
        * @param ug
        */
        is_member: function(ugs){
            //return (ug==0 || that.currentUser['ugr_ID']==ug ||
            //    (that.currentUser['ugr_Groups'] && that.currentUser['ugr_Groups'][ug]));
                
                
            if(ugs==0 || ugs==null){
                return true;
            }
            
            if(ugs>0){
                ugs = [ugs];
            }else{
                ugs = $.isArray(ugs) ?ugs: ugs.split(',')
            }
            
            for (var idx in ugs){
                var ug = ugs[idx];
                if (ug==0 || that.currentUser['ugr_ID']==ug ||
                    (that.currentUser['ugr_Groups'] && that.currentUser['ugr_Groups'][ug])){
                    return true;   
                }
            }
            return false;        
                
        },

        /**
        * Returns IF currentUser satisfies to required level
        *
        * @param requiredLevel 
        * NaN or <1 - (DEFAULT) is logged in
        * 1 - db admin (admin of group 1 "Database managers")
        * 2 - db owner
        * n - admin of given group
        */
        has_access: function(requiredLevel){

            requiredLevel = Number(requiredLevel);
            
            if(isNaN(requiredLevel) || requiredLevel<1){
                return (that.currentUser && that.currentUser['ugr_ID']>0); 
            }
            
            return (requiredLevel==that.currentUser['ugr_ID'] ||   //iself 
                    2==that.currentUser['ugr_ID'] ||   //db owner
                    (that.currentUser['ugr_Groups'] && that.currentUser['ugr_Groups'][requiredLevel]=="admin")); //admin of given group
        },

        /**
        * Returns current user preferences
        *
        * @returns {Object}
        */
        get_prefs: function(name){
            if( !that.currentUser['ugr_Preferences'] ) {
                //preferences by default
                that.currentUser['ugr_Preferences'] = 
                {layout_language:'en',
                 layout_theme: 'heurist',
                 search_result_pagesize:100,
                 search_detail_limit: 2000,
                 userCompetencyLevel: 2, //'beginner',
                 deriveMapLocation: true,
                 help_on: true, 
                 optfields: true,
                 mapcluster_on: true,
                 searchQueryInBrowser:true
                };
            }
            if(window.hWin.HEURIST4.util.isempty(name)){
                return that.currentUser['ugr_Preferences'];
            }else{
                var res = that.currentUser['ugr_Preferences'][name];

                // TODO: redundancy: this duplicates same in System.php
                if('search_detail_limit'==name){
                    if(window.hWin.HEURIST4.util.isempty(res) || res<500 ) res = 500
                    else if(res>5000 ) res = 5000;
                }else if('search_result_pagesize'==name){
                    if(window.hWin.HEURIST4.util.isempty(res) || res<50 ) res = 100
                    else if(res>5000 ) res = 5000;
                }
                return res;
            }
        },
        
        get_prefs_def: function(name, defvalue){
               var res = window.hWin.HAPI4.get_prefs(name);
               if(window.hWin.HEURIST4.util.isempty(res)){
                   res = defvalue;
               }
               if(name=='userCompetencyLevel' && isNaN(Number(res))){
                    res = defvalue;
               }
               return res;
        },

        //
        // limit - to save limited list of ids - for example: last selected tags
        //
        save_pref: function(name, value, limit){
                //window.hWin.HAPI4.SystemMgr.save_prefs({'map_viewpoints': map_viewpoints});

                if($.isArray(value) && limit>0) {
                        value = value.slice(0,limit);

                        var cur_value = window.hWin.HAPI4.get_prefs(name);
                        cur_value = (cur_value?cur_value.split(','):null);
                        if(!$.isArray(cur_value)) cur_value = [];

                        $.each(value, function(i, item){
                            if($.inArray(item, cur_value) === -1) cur_value.unshift(item);
                        });
                        value = cur_value.slice(0, limit).join(',');
                }

                var request = {};
                request[name] = value;

                window.hWin.HAPI4.SystemMgr.save_prefs(request,
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            that.currentUser['ugr_Preferences'][name] = value;
                        }
                    }
                );
        },

        triggerEvent:function(eventType, data){
            $(window.hWin.document).trigger(eventType, data );

            //this is for listeners in other frames
            for (var i=0; i<_listeners.length; i++){
                if(_listeners[i].event_type == eventType){
                    _listeners[i].callback.call( _listeners[i].obj, data );
                }
            }
        },

        //to support event listeners in other frames
        addEventListener:function( object, event_type, callback){
            _listeners.push( {obj:object, event_type:event_type, callback:callback} );
        },
        
        is_ui_normal: function(){
            return (window.hWin.HAPI4.get_prefs('layout_style')=='normal');
        },


        user_id: function(){
                return that.currentUser['ugr_ID'];  
        },
        
        currentUser: _guestUser,
        sysinfo: {},

        // main result set that is filled in search_minimal - keeps all
        // purposes:
        // 1) to keep main set of records (original set) to apply RuleSet
        // 2) to get selected records by ids
        // 3) to pass result set into popup record action dialogs
        currentRecordset: null,
        
        currentRecordsetSelection:[],  //selected record ids - main assignment in lister of resultListMenu


        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},


        //UserMgr: new hUserMgr(),

        SystemMgr: new hSystemMgr(),

        /*SystemMgr: function(){
        return hSystemMgr();
        },*/

        RecordMgr: new hRecordMgr(),

        EntityMgr: new hEntityMgr(),

        //@todo - assign it later since we may have different search managers - incremental, partial...
        //SearchMgr: new hSearchIncremental(), //class that responsible for search and incremental loading of result
        SearchMgr: null, //class that responsible for search and incremental loading of result

        LayoutMgr: null,

        /*RecordMgr: function(){
        return /();
        }*/

        /**
        * Returns function to string resouce according to current region setting
        */
        setLocale: function( region ){
            if(_regional && _regional[region]){
                _region = region;
            }
            // function that returns string resouce according to current region setting
            return function key(res){
                if(_regional && _regional[_region] && _regional[_region][res]){
                    return _regional[_region][res];
                }else{
                    return res;
                }
            }
        }

        /**
        *  @todo need to rewrite since it works with global currentRecordset
        * 
        *   Returns subset of currentRecordset or array of its ids
        *   @param selection :
        *           all - returns all records of currentRecordset
        *           array of ids
        *           recordset
        *           @todo array of recordtype
        *
        *   @param needIds if it is true  it returns array of record ids
        */
        , getSelection: function(selection, needIds){

                if (selection == "all") {
                    if(this.currentRecordset){
                        selection = needIds ?this.currentRecordset.getIds() :this.currentRecordset;
                    }else{
                        return null;
                    }
                }
                if( selection ) {
                    if( (typeof selection.isA == "function") && selection.isA("hRecordSet") ){
                        if(selection.length()>0){
                            return (needIds) ?selection.getIds():selection; //array of record ids
                        }
                    }else{  //selection is array of ids
                            return (needIds) ?selection
                                        :((that.currentRecordset)?that.currentRecordset.getSubSetByIds(selection):null);
                    }
                }
                return null;
        }

        , getImageUrl: function(entityName, recID, version, def, database){
                
            //if file not found return empty gif (0) or add image gif (1) or default icon/thumb for entity (2)
            if(!(def>=0||def<3)) def = 2;

            return window.hWin.HAPI4.baseURL + 'hsapi/utilities/fileGet.php'
                    +'?db='+ (database?database:window.hWin.HAPI4.database)
                    +'&entity='+entityName
                    +'&id='+recID
                    +'&version='+version
                    +'&def='+def;
        }

        , checkImageUrl: function(entityName, recID, version, callback){

            var request = {
                    db:window.hWin.HAPI4.database,
                    entity: entityName,
                    id:recID,
                    version:version,
                    def: 'check'};
            
            window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL + 'hsapi/utilities/fileGet.php', 
                        request, null, callback);
        }

        //
        //
        //
        , doImportAction: function(request, callback){
            //if(request) request.a = 'svs_delete';
            //request['DBGSESSID']='425288446588500001;d=1,p=0,c=0';

            _callserver('importController', request, callback);
        }

        
    
        /**
        * returns true if _callserver ajax request is in progress
        */
        ,is_callserver_in_progress: function (){
            return _is_callserver_in_progress;
        }
        

    }

    _init(_db, _oninit); //, _currentUser);
    return that;  //returns object
}