/**
* Main class for Heurist 
*   it stores major config info
*   local db definitions
*   and provides methods to call server side 
*
* Constructor:
* @param _db - database name, if omit it takes from url parameter
* @param _oninit - callback function, obtain parameter true if initialization is successeful
* @returns hAPI Object
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

/*

Properties:
    baseURL
    baseURL_pro
    iconBaseURL - url for record type icon (rty_ID to be added)
    database - current database name
    sysinfo
    is_publish_mode - false if Heurist is inited via main index.php and layout is not from the set of application (DH, EN, WebSearch)

Localization routines (assigned to window.hWin)

    HR  returns localized string
    HRA = localize all elements with class slocale for given element
    HRes = returns url or loads content for localized resource
    HRJ = returns localized value for json (options in widget)

LayoutMgr   hLayout object (@todo replace to new version from CMS)

Classes for server interaction

    SystemMgr - user credentials and system utilities
    RecordMgr - Records SCRUD actions    
    RecordSearch - wrapper for RecordMgr.search method
    EntityMgr - SCRUD for database defenitions and user/groups

*/
function hAPI(_db, _oninit, _baseURL) { //, _currentUser
    var _className = "HAPI",
    _version   = "0.4",
    _database = null, //same as public property  @toremove      
    
    _region = null, //current region
    _regional = null, //localization resources
    
    _guestUser = {ugr_ID:0, ugr_FullName:'Guest' },
    _listeners = [];
    _is_callserver_in_progress = false,
    
    _use_debug = false;

    /**
    * initialization of hAPI object
    *  1) define paths from top.location
    *  2) takes regional from  localization.js
    *
    * @param _db - database name, if omit it takes from url parameter
    * @param _oninit - callback function, obtain parameter true if initialization is successeful
    * @param _baseURL - defined for embed mode only when location of heurist client is differend from heurist server 
    *
    */
    function _init(_db, _oninit, _baseURL){ //, _currentUser) {
    
        //@todo - take  database from URL
        if(_db){
            _database = _db;
        }else{
            _database = window.hWin.HEURIST4.util.getUrlParameter('db');
        }

        //
        var installDir = window.hWin.location.pathname.replace(/(((\?|admin|applications|common|context_help|export|hapi|hclient|hsapi|import|startup|records|redirects|search|viewers|help|ext|external)\/.*)|(index.*|test.php))/, ""); // Upddate in 2 places this file and 6 other files if changed
        //TODO: top directories - admin|applications|common| ... are defined in 3 separate locations. Rationalise.
        that.installDir = installDir; //to detect development or production version 
        if(!_baseURL) _baseURL = window.hWin.location.protocol + '//'+window.hWin.location.host + installDir;
        that.baseURL = _baseURL;
        
        //detect production version
        if(installDir && !installDir.endsWith('/heurist/')){
            installDir = installDir.split('/');
            for (var i=installDir.length-1; i>=0; i--){
                if(installDir[i]!='') {
                    installDir[i] = 'heurist';    
                    break;   
                }
            }
            installDir = installDir.join('/');
            that.baseURL_pro = window.hWin.location.protocol + '//'+window.hWin.location.host + installDir;
        }else{
            that.baseURL_pro = _baseURL;
        }
        
        // @TODO: rename to rtyIconURL 
        that.iconBaseURL = that.baseURL + '?db='+_database+'&icon=';
        that.database = _database;

        // regional - global variable defined in localization.js
        if(!window.hWin.HR){
            window.hWin.HR = that.setLocale('en');
        }
        
        if(!$.isFunction(that.fancybox)){
            that.fancybox = $.fn.fancybox; //to call from iframes
        }    
        
        if(typeof hLayout !== 'undefined' && $.isFunction(hLayout)){
            that.LayoutMgr = new hLayout();
        }
        if(typeof hRecordSearch !== 'undefined' && $.isFunction(hRecordSearch)){
            that.RecordSearch = new hRecordSearch();
        }
        
        if(!window.onresize){
            that._delayOnResize = 0;
            function __trigger(){
                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_WINDOW_RESIZE);
            };
            window.onresize = function(){
                if(that._delayOnResize) clearTimeout(that._delayOnResize);
                that._delayOnResize = setTimeout(__trigger,1000);
            }
        }
        
        /*if(_currentUser){
        that.currentUser = _currentUser;
        }else{}*/
        // Get current user if logged in, and global database settings
        // see usr_info.php sysinfo method  and then system->getCurrentUserAndSysInfo
        if(that.database){
            that.SystemMgr.sys_info( function(success){
                if(success){
                    var lang = window.hWin.HEURIST4.util.getUrlParameter('lang');
                    if(lang){
                        //save in preferences
                        window.hWin.HAPI4.save_pref('layout_language', lang);
                    }else{
                        lang = window.hWin.HAPI4.get_prefs_def('layout_language', 'en');
                    }
                    window.hWin.HR = that.setLocale(lang);
                    window.hWin.HRA = that.HRA; //localize all elements with class slocale for given element
                    window.hWin.HRes = that.HRes; //returns url or content for localized resource (help, documentation)
                    window.hWin.HRJ = that.HRJ; // returns localized value for json (options in widget)
                }
                _oninit(success);
            } );
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

        //set d=0 and c=0 to disable debug  http://www.nusphere.com/kb/technicalfaq/faq_dbg_related.htm
        request.DBGSESSID= (_use_debug)?'425944380594800002;d=1,p=0,c=1':'425944380594800002;d=0,p=0,c=0';

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
/* DEPRECATED  */
            error: function( jqXHR, textStatus, errorThrown ) {
                
                _is_callserver_in_progress = false;
                                            
                var response;                     
                if(jqXHR.responseJSON && jqXHR.responseJSON.status){
                    response = jqXHR.responseJSON;
                }else{
                    var err_message = (window.hWin.HEURIST4.util.isempty(jqXHR.responseText))
                                    ?'Error_Connection_Reset':jqXHR.responseText;
                    response = {status:window.hWin.ResponseStatus.UNKNOWN_ERROR, 
                                        message: err_message, 
                                        request_code:request_code};
                }
                                            

                if($.isFunction(callback)){
                    callback(response);
                }
                //message:'Error connecting server '+textStatus});
            },
            success: function( response, textStatus, jqXHR ){

                _is_callserver_in_progress = false;
                
                if($.isFunction(callback)){
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

                if($.isFunction(callback)){
                    callback(response);
                }
            }
        });

    }

    
    function _triggerRecordUpdateEvent(response, callback){
        if(response && response.status == window.hWin.ResponseStatus.OK){
            if($Db) $Db.needUpdateRtyCount = 1;
            
            if(response.affectedRty){
                //clear record browse cache
                if(window.hWin.HEURIST4.browseRecordTargets){
                    var rtys = [];
                    if($.isArray(response.affectedRty)){
                        rtys = response.affectedRty;
                    }else if(typeof response.affectedRty==='string'){
                        rtys = response.affectedRty.split(',');
                    }else{
                        rtys = [response.affectedRty];
                    }
                    rtys.push('any');
                    $.each(rtys, function(i,id){
                        if(window.hWin.HEURIST4.browseRecordTargets[id]){
                            id = ''+id;
                            $.each(window.hWin.HEURIST4.browseRecordTargets[id], function(j,key){
                                if(window.hWin.HEURIST4.browseRecordCache && window.hWin.HEURIST4.browseRecordCache[key]){
                                    window.hWin.HEURIST4.browseRecordCache[key] = null;
                                    delete window.hWin.HEURIST4.browseRecordCache[key];                                   
                                }
                            });
                            window.hWin.HEURIST4.browseRecordTargets[id] = null;
                            delete window.hWin.HEURIST4.browseRecordTargets[id];                                   
                        }
                    });
                }
            }            
            window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_REC_UPDATE); //after save record     
        }
        if($.isFunction(callback)){
                callback(response);
        }
    }

    
    /**
    * System class that responsible for interaction with server in domains:
    *       user/groups information/credentials
    *       saved searches - @todo move to EntityMgr
    *       system info
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
    *                  (for details $system->getCurrentUserAndSysInfo) 
    *   sys_info_count - 
    *   save_prefs   - save user preferences  in session
    * 
    *   -----
    *           @todo move to EntityMgr
    *   ssearch_get  - get saved searches for current user and all usergroups where user is memeber, or by list of ids
    *   ssearch_save - save saved search in database
    *   ssearch_copy - duplicate
    *   ssearch_delete - delete saved searches by IDs
    *   ssearch_savetree - save saved search treeview data
    *   ssearch_gettree - get saved search treeview data
    *   ------ 
    *          @todo move to EntityMgr 
    *   user_get
    *   usr_names
    *   mygroups  - description of current user's Workgroups
    *   user_log  - activity log
    *   user_save
    *   user_wss  - working subset
    *   ------
    *   get_defs     - get the desired database structure definition (used in import defs only)
    *   get_defs_all - returns number of records in database, worksets and dashboard info (@todo remove)
    * 
    *   ------ 
    *   get_url_content_type - resolve mimetype for given url
    *   get_sysimages
    *   get_sysfolders
    *   checkPresenceOfRectype - check and download missed rectypes
    *   import_definitions
    *   versionCheck  - checks client software version and db version check (move)
    * 
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
                
                var requiredMembership = 0;
                
                if(typeof requiredLevel==='string' && requiredLevel.indexOf(';')>0){
                    
                    requiredLevel = requiredLevel.split(';');
                    requiredMembership = requiredLevel[1];
                    requiredLevel = requiredLevel[0];
                    if(requiredLevel<0) requiredLevel = 0;
                }

                requiredLevel = Number(requiredLevel);
                if(requiredLevel<0){ //no verification required - everyone access
                
                    //however need to check password protection
                    if(window.hWin.HEURIST4.util.isempty(password_protected)){
                            //no password protection
                            callback(password_entered);
                            return; 
                    }else{
                            if(window.hWin.HAPI4.sysinfo['pwd_'+password_protected]){ //system administrator password defined allowing system admin override for specific actions otherwise requiring ownership
                            
                            //
                                window.hWin.HEURIST4.msg.showPrompt(
                                '<div style="padding:20px 0px">'
                                +'Only an administrator (server manager) or the owner (for<br>'
                                +'actions on a single database) can carry out this action.<br>'
                                +'This action requires a special system administrator password (not a normal login password)'
                                +'</div><span style="display: inline-block;padding: 10px 0px;">Enter password:&nbsp;</span>',
                                    function(password_entered){
                                        
                                        window.hWin.HAPI4.SystemMgr.action_password({action:password_protected, password:password_entered},
                                            function(response){
                                                if(response.status == window.hWin.ResponseStatus.OK && response.data=='ok'){
                                                    callback(password_entered); 
                        //window.hWin.HAPI4.SystemMgr.verify_credentials(callback, requiredLevel, null,password_entered);
                                                }else{
                                                    window.hWin.HEURIST4.msg.showMsgFlash('Wrong password');
                                                }
                                            }
                                        );
                                        
                                    },
                                {title:'Sysadmin override password required',yes:'OK',no:'Cancel'}, {password:true});
                            
                            }else{
                                window.hWin.HEURIST4.msg.showMsgDlg('This action is not allowed unless a special system administrator password is set - please consult system administrator');
                            }
                            return;
                    }                
                }
                
                //verify locally
                function __verify( is_expired ){
                    
                        if( (requiredMembership==0 || window.hWin.HAPI4.is_member(requiredMembership))
                            && 
                             window.hWin.HAPI4.has_access(requiredLevel))
                        { 
                            //verification is accepted now check for password protection
                            window.hWin.HAPI4.SystemMgr.verify_credentials(callback, -1, password_protected, password_entered);
                        }else{
                            var response = {};
                            response.sysmsg = 0;
                            response.status = window.hWin.ResponseStatus.REQUEST_DENIED;
                            response.message = 'To perform this operation you have to be logged in (you may have been logged out due to lack of activity - if so, please reload the page)';
                            
                            if(requiredMembership>0){
                               var sGrpName = '';
                               if( window.hWin.HAPI4.sysinfo.db_usergroups 
                                    && window.hWin.HAPI4.sysinfo.db_usergroups[requiredMembership]){
                                    sGrpName = ' "'+window.hWin.HAPI4.sysinfo.db_usergroups[requiredMembership]+'"';
                               } 
                               response.message += ' as member of group #'+requiredMembership+sGrpName;
                               
                            }else if(requiredLevel==window.hWin.HAPI4.sysinfo.db_managers_groupid){
                               response.message += ' as database administrator';// of group "Database Managers"' 
                            }else if(requiredLevel==2){
                               response.message += ' as database onwer';
                            }else  if(requiredLevel>0){
                               var sGrpName = '';
                               if( window.hWin.HAPI4.sysinfo.db_usergroups && window.hWin.HAPI4.sysinfo.db_usergroups[requiredLevel]){
                                    sGrpName = ' "'+window.hWin.HAPI4.sysinfo.db_usergroups[requiredLevel]+'"';
                               } 
                               response.message += ' as administrator of group #'+requiredLevel+sGrpName;
                            }else if(requiredLevel==0 && is_expired){
                               response.message = ''; 
                            }
                            
                            if(response.message){
                                window.hWin.HEURIST4.msg.showMsgFlash(response.message, 2000);    
                            }else{
                                //login expired
                                window.hWin.HEURIST4.msg.showMsgErr(response, true);  
                            }
                            
                        }
                }
                
                function __response_handler(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){
                        if(response.data.sysinfo){
                            window.hWin.HAPI4.sysinfo = response.data.sysinfo;
//!!!!  assign baseURL window.hWin.HAPI4.baseURL = window.hWin.HAPI4.sysinfo['baseURL'];
                        }

                        var is_expired = false;
                        if(response.data.currentUser) {
                            
                            var old_id = window.hWin.HAPI4.user_id();
                            
                            window.hWin.HAPI4.setCurrentUser(response.data.currentUser);   
                            
                            is_expired = (old_id>0 && window.hWin.HAPI4.user_id()==0);
                            
                            //trigger global event ON_CREDENTIALS
                            if(response.data.currentUser.ugr_ID>0){
                                $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
                            }
                        }
                    
                        //since currentUser is up-to-date - use client side method
                        __verify( is_expired );
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response, true);
                    }
                }
                
                
                if(false){ //MODE1 verify locally only
                    __verify(); 
                
                }else{
                    //MODE2 verify via server each time
                    //check if login
                    _callserver('usr_info', {a:'verify_credentials'}, __response_handler);
                }
            }
            
            /**
            * Returns number of records in database, worksets and dashboard info
            */
            ,sys_info_count: function(callback){
                _callserver('usr_info', {a:'sys_info_count'}, 
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){
                            window.hWin.HAPI4.sysinfo['db_total_records'] = response.data[0];
                            window.hWin.HAPI4.sysinfo['db_has_active_dashboard'] = response.data[1];
                            window.hWin.HAPI4.sysinfo['db_workset_count'] = response.data[2];
                            if(callback) callback();
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response.message); 
                        }
                    });
            }
            
            /**
            * Get current user if logged in, and global database settings
            * used only in hapi.init and on force_refresh_sys_info
            * 
            * see $system->getCurrentUserAndSysInfo
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
            * Save user personal info/register new user
            * @param request - object - user info
            * @param callback
            */
            ,save_prefs: function(request, callback){
                if(request) request.a = 'save_prefs';
                _callserver('usr_info', request, callback);
            }
            
            
            /**
            * set/clear work subset
            */
            ,user_wss: function(request, callback){
                if(request) request.a = 'user_wss';
                _callserver('usr_info', request, callback);
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
                        }else{
                            callback.call(this, {status:context.status} );       
                        }
                    });
                }
            }

            /**
            * Returns detailed description of groups for current user
            *
            * response data - array of ugl_GroupID:[ugl_Role, ugr_Name, ugr_Description]
            */
            ,mygroups: function(callback){
                _callserver('usr_info', {a:'groups'}, callback);
            }
            
            
            //
            // activity logging
            //
            ,user_log: function(activity, suplementary){
               
                if(typeof gtag !== 'undefined' && $.isFunction(gtag)){ //google log function
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
                
                if(activity.indexOf('search')<0){
                
                    activity = activity.replace('_','');
                    
                    //our internal log function it is shelved for now. Since Jan 2019 we use Google Tags
                    var request = {a:'usr_log', activity:activity, suplementary:suplementary, user: window.hWin.HAPI4.user_id()};
                    //_callserver('usr_info', request);
                
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
            * Duplicte saved search
            */
            ,ssearch_copy: function(request, callback){
                if(request) request.a = 'svs_copy';
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
            * @todo - replace with EntityMgr.refreshEntityData
            *  Get the desired database structure definition in old format - used to get rectypes from REMOTE database ONLY
            * request
            *   terms, rectypes, detailtypes :  list of desired ids,  OR 'all'
            *   mode: applied for rectypes  0 only names (default), 1 only strucuture, 2 - both, 3 - all,   4 - for faceted search(with type names)
            *
            */
            ,get_defs: function(request, callback){
                _callserver('sys_structure', request, callback);
            }

            //
            // wrapper for EntityMgr.refreshEntityData - to be replaced
            //    
            ,get_defs_all: function(is_message, document, callback){
                
                window.hWin.HEURIST4.msg.bringCoverallToFront();
                
                var that = this;
                
                window.hWin.HAPI4.EntityMgr.refreshEntityData('all', function(success){

                    if(success){
                        
                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                        
                        if (is_message==true) {
                            $dlg = window.hWin.HEURIST4.msg.showMsgDlg('Database structure definitions in browser memory have been refreshed.<br>'+
                                'You may need to reload pages to see changes.');
                            $dlg.parent('.ui-dialog').css({top:150,left:150});    
                        }      
                        
                        window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
                        if($.isFunction(callback)) callback.call(that, true);
                        
                    }else{
                        window.hWin.HEURIST4.msg.sendCoverallToBack();
                        if($.isFunction(callback)) callback.call(that, false);
                    }
                
                });

            },
            
            //returns mimetype for given url
            get_url_content_type: function(url, callback){
                var request = {a:'get_url_content_type', url: url};
                _callserver('usr_info', request, callback);
            },

            //returns list of files for given folders
            get_sysimages: function(folders, callback){
                var request = {a:'sysimages', folders: folders};
                _callserver('usr_info', request, callback);
            },
            
            // check if cms creation is enabled
            check_allow_cms: function(request, callback){
                if(!request || !request.a){
                    request = {a:'check_allow_cms'};
                }
                _callserver('usr_info', request, callback);
            },

            // check if current server + db has access to ESTC lookups
            check_allow_estc: function(request, callback){
                if(!request){
                    request = {a:'check_allow_estc', db: window.hWin.HAPI4.database};
                }
                _callserver('usr_info', request, callback);
            },

            //manipulation with folders in database folder
            get_sysfolders: function(request, callback){
                if(!request) request = {};
                if(!request['a']) request['a'] = 'folders';
                if(!request['operation']) request['operation'] = 'list';
                _callserver('usr_info', request, callback);
            },
            
            // @todo - move
            // 1. verifies that given rty_IDs (concept codes) exist in this database
            // 2. If rectype is missed - download from given db_ID (registration ID)
            // 3. Show warning of info report
            //
            // rty_IDs - array of concept codes
            // databaseID - registratiion ID of source database. If it is not defined, it takes #2 by default
            // message - additional (context explanatory) message for final report, if false - without message
            // returns false - if rty_IDs is not defined
            //         true - all record types are in this database
            //
            checkPresenceOfRectype: function(rty_IDs, databaseID, message, callback, force_refresh){

                if(!rty_IDs){
                    if($.isFunction(callback)) callback.call();
                    return false;
                }else if(!$.isArray(rty_IDs)){
                    rty_IDs = [rty_IDs];
                }

                //check what rectypes are missed in this database                  
                var missed = [];
                
                if(force_refresh){
                    
                    missed = rty_IDs;
                    
                }else{                
                
                    for(var i=0; i<rty_IDs.length; i++){
                        var local_id = $Db.getLocalID('rty', rty_IDs[i]);
                        if(!(local_id>0)){
                            //not found
                            missed.push( rty_IDs[i] );
                        }
                    }
                }
                
                //all record types are in this database
                if(missed.length==0){
                    if($.isFunction(callback)) callback.call();
                    return true;
                }
                
                //by default we take Heurist_Core_Definitions id#2
                if(!(databaseID>0)) databaseID = 2;
            
                if(message==false){
                
                    window.hWin.HAPI4.SystemMgr.import_definitions(databaseID, missed, false, callback);
                    
                }else{
                    
                    var $dlg2 = window.hWin.HEURIST4.msg.showMsgDlg(message
                        + '<br>'
                        + window.hWin.HR('Click "Import" to get these definitions'),
                        {'Import':function(){
                            var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                            $dlg2.dialog('close');

                            window.hWin.HEURIST4.msg.bringCoverallToFront();
                            window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Import definitions'), 10000);

                            //import missed record types
                            window.hWin.HAPI4.SystemMgr.import_definitions(databaseID, missed, false,
                                function(response){    
                                    window.hWin.HEURIST4.msg.sendCoverallToBack(); 
                                    var $dlg2 = window.hWin.HEURIST4.msg.getMsgFlashDlg();
                                    if($dlg2.dialog('instance')) $dlg2.dialog('close');

                                    if(response.status == window.hWin.ResponseStatus.OK){
                                        if($.isFunction(callback)) callback.call();
                                    }else{
                                        window.hWin.HEURIST4.msg.showMsgErr(response);     
                                    }
                            });

                            },
                            'Cancel':function(){
                                var $dlg2 = window.hWin.HEURIST4.msg.getMsgDlg();
                                $dlg2.dialog('close');}
                        },
                        window.hWin.HR('Definitions required'));
                }
                return 0;
            },
            
            
            //
            // imports database defintions 
            // databaseID - source database 
            // definitionID - rectype (array) id or concept code to be imported
            //
            import_definitions: function(databaseID, definitionID, is_rename_target, callback){
             
                var request = {databaseID:databaseID, 
                    definitionID: definitionID, //array of id or concept codes
                    is_rename_target: is_rename_target?1:0,
                    db:window.hWin.HAPI4.database, import:'rectype'};
                    
                _callserver('sys_structure', request, function(response){
                    
                    if(response.status == window.hWin.ResponseStatus.OK){

                        //refresh local definitions
                        if(response.defs){
                            if(response.defs.sysinfo) window.hWin.HAPI4.sysinfo = response.defs.sysinfo; //constants
                            
                            if(response.defs.entities)
                            for(var entityName in response.defs.entities){
                                
                                //refresh local definitions
                                window.hWin.HAPI4.EntityMgr.setEntityData(entityName,
                                                                response.defs.entities);
                            }
                        }

                        window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
                    }
                    if($.isFunction(callback)){
                        callback(response);
                    }
                });
            },
            
            // @todo move 
            // 1. Checks client software version and 
            // 2. Checks Database version and runs update script
            //
            versionCheck: function(){

                //@todo define parameter in layout "production=true"
                if(!window.hWin.HAPI4.is_publish_mode){

                    var version_in_cache = window.hWin.HAPI4.get_prefs_def('version_in_cache', null); 
                    var need_exit = false;

                    //
                    // version of code to compare with server provided - to avoid caching issue
                    //
                    if(window.hWin.HAPI4.has_access() && window.hWin.HAPI4.sysinfo['version']){
                        if(version_in_cache){
                            need_exit = (window.hWin.HEURIST4.util.versionCompare(version_in_cache, 
                                window.hWin.HAPI4.sysinfo['version'])<0);   
                            if(need_exit){ // -1=older code in cache, -2=newer code in cache, +1=same code version in cache
                                // show lock popup that forces to clear cache
                                window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/versionCheckMsg.html',
                                    {}/* no buttons */,null,
                                    {hideTitle:true, closeOnEscape:false,
                                        open:function( event, ui ) {
                                            var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                            $dlg.find('#version_cache').text(version_in_cache);
                                            $dlg.find('#version_srv').text(window.hWin.HAPI4.sysinfo['version']);
                                }});
                            }
                        }
                        window.hWin.HAPI4.save_pref('version_in_cache', window.hWin.HAPI4.sysinfo['version']);
                        if(need_exit) return true;
                        
                        var res = window.hWin.HEURIST4.util.versionCompare(window.hWin.HAPI4.sysinfo.db_version_req, 
                            window.hWin.HAPI4.sysinfo.db_version);   
                        if(res==-2){ //-2= db_version_req newer
                            // show lock popup that forces to upgrade database
                            window.hWin.HEURIST4.msg.showMsgDlgUrl(window.hWin.HAPI4.baseURL+'hclient/widgets/dropdownmenus/versionDbCheckMsg.html',
                                {'Upgrade':function(){
                                    //console.log(window.hWin.HAPI4.baseURL+'admin/setup/dbupgrade/upgradeDatabase.php?db='+window.hWin.HAPI4.database);                                   
                                    top.location.href = (window.hWin.HAPI4.baseURL+'admin/setup/dbupgrade/upgradeDatabase.php?db='+window.hWin.HAPI4.database);
                                }},null,
                                {hideTitle:false, closeOnEscape:false,
                                    open:function( event, ui ) {
                                        var $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                                        $dlg.find('#version_db').text(window.hWin.HAPI4.sysinfo.db_version);
                                        $dlg.find('#version_min_db').text(window.hWin.HAPI4.sysinfo.db_version_req);
                                        $dlg.find('#version_srv').text(window.hWin.HAPI4.sysinfo['version']);
                            }});

                        }
                    }

                }  
                return false;                
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
    * see record_edit.php, record_batch.php, record_tags.php and record_search.php
    *
    * methods:
    *           for record_edit controller
    *   addRecord       - creates new temporary record
    *   saveRecord      - save record
    *   remove    - delete record
    *   duplicate
    *   access    - ownership and visibility
    *   increment
    *
    * 
    *           for record_batch controller
    *   details   - batch edition of record details for many records
    *
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
            addRecord: function(request, callback){
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
            ,saveRecord: function(request, callback){
                if(request) request.a = 's';
                
                window.hWin.HEURIST4.util.encodeRequest(request, ['details']);

                _callserver('record_edit', request, function(response){_triggerRecordUpdateEvent(response, callback);});
            }

            /**
            *  Batch Save Multiple Records (remove temporary flag if new record)
            * 
            * @param request a: batch_save
            * @param callback - response object of record ids
            */
            ,batchSaveRecords: function(request, callback){
                if(request) request.a = 'batch_save';

                _callserver('record_edit', request, function(response){_triggerRecordUpdateEvent(response, callback)});
            }

            ,duplicate: function(request, callback){
                if(request) request.a = 'duplicate';
                
                _callserver('record_edit', request, function(response){_triggerRecordUpdateEvent(response, callback);});
            }
            
            /**
            * ownership and visibility
            * ids, OwnerUGrpID, NonOwnerVisibility
            */
            ,access: function(request, callback){
                if(request) request.a = 'access';
                _callserver('record_edit', request, callback);
            }
            
            /**
            * Increment value for given detail field and returns it
            */
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
                
                _callserver('record_edit', request, function(response){_triggerRecordUpdateEvent(response, callback);});
            }

            /**
            * Batch edition/update of record details
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
                
                window.hWin.HEURIST4.util.encodeRequest(request, ['rVal','val']);
                
                _callserver('record_batch', request, function(response){_triggerRecordUpdateEvent(response, callback);});
            }
            
//@TODO - need to implement queue for record_search, otherwise sometimes we get conflict on simultaneous requests            
            
            /**
            * Search for records via global events
            * to search directly use RecordSearch
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

                if(!window.hWin.HAPI4.is_publish_mode && request['verify_credentials']!='ok'){
                    window.hWin.HAPI4.SystemMgr.verify_credentials(function(){
                        request['verify_credentials'] = 'ok';
                        that.search(request, callback);
                    }, 0); 
                    return;
                }
                
                if(request['verify_credentials']){
                    request['verify_credentials'] = null;
                    delete request['verify_credentials'];
                }

                if(!$.isFunction(callback)){   
                    // it happens only of calback function is not set
                    // remove all this stuff since callback function is always defined

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

                        }
                        if(!window.hWin.HEURIST4.util.isnull(document)){
                                document.trigger(window.hWin.HAPI4.Event.ON_REC_SEARCH_FINISH, {resultset:resdata}); //global app event
                        }
                    }
                }


                //if limit is not defined - get it from preferences
                //if(window.hWin.HEURIST4.util.isnull(request.limit)){
                //    request.limit = window.hWin.HAPI4.get_prefs('search_detail_limit'); //if needall is set it is ignored on server side
                //}
                
                window.hWin.HEURIST4.util.encodeRequest(request, ['q']);
                
                // start search
                _callserver('record_search', request, callback);    //standard search

            }
            
            //
            // prepare result in required format
            //
            ,search_new: function(request, callback){
                // start search
                //window.hWin.HEURIST4.util.encodeRequest(request, ['q']);
                
                _callserver('record_output', request, callback);    //standard search
            }

            //
            //
            //
            ,lookup_external_service: function(request, callback){
                // start search
                _callserver('record_lookup', request, callback);
            }
            
            //
            // load kml in geojson format
            // request
            //  recID - record id that has reference to kml file or has kml snippet
            //  simplify - reduce points
            //
            ,load_kml_as_geojson: function(request, callback){
                request['format'] = 'geojson';
                // start search
                _callserver('record_map_source', request, callback);
            }

            //
            // load kml in geojson format
            // request
            //  recID - record id that has reference to shp file
            //  simplify - reduce points
            //
            ,load_shp_as_geojson: function(request, callback){
                request['format'] = 'geojson'; //or wkt
                request['api'] = 0; //not api request
                // start search
                _callserver('record_shp', request, callback);
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
                if(request && !request.a) request.a = 'related';
                
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
                
                window.hWin.HEURIST4.util.encodeRequest(request, ['q','count_query']);
                
                _callserver('record_search', request, callback);
            }
			
            //
            // return the date intervals for the provided record type using the provided detail type
            //
            ,get_date_histogram_data: function(request, callback){
                if(request && !request.a) request.a = 'gethistogramdata';
                _callserver('record_search', request, callback);
            }

            //
            // return record ids after matching record detail fields, using the rectype ids provided
            //
            ,get_record_ids: function(request, callback){
                if(request && !request.a) request.a = 'getrecordids';
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
    *       User/groups information/credentials
    *       Database definitions - record structure, field types, terms
    *       @todo saved searches
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
                    _callserver('entityScrud', {a:'config', 'entity':entityName, 'locale':window.hWin.HAPI4.getLocale()},
                       function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){

                                entity_configs[response.data.entityName] = response.data;
                                
                                //find key and title fields
                                window.hWin.HAPI4.EntityMgr.resolveFields(response.data.entityName);

                                callback(entity_configs[response.data.entityName]);
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                       }

                    );
                }
            },
            
            //
            // reset entity data that forces reload from server on next request
            //
            clearEntityData: function(entityName){
                if(!$.isEmptyObject(entity_data[entityName])){
                    entity_data[entityName] = {};
                }
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
            //  find key and title fields
            //
            resolveFields: function(entityName){

                var entity_cfg = entity_configs[entityName];

                if(entity_cfg){

                    function __findFields(fields){
                        var idx;
                        for(idx in fields){
                            if(fields[idx].children){
                                __findFields(fields[idx].children);
                            }else{
                                if(fields[idx]['keyField']==true){
                                    entity_cfg.keyField = fields[idx]['dtID'];
                                }
                                if(fields[idx]['titleField']==true){
                                    entity_cfg.titleField = fields[idx]['dtID'];
                                }
                            }
                        }
                    }

                    __findFields( entity_cfg.fields );
                    
                }
            },
            
            //
            // 1) index   rty_ID[dty_ID] = rst_ID
            // 2) reverse links rty <- dty_ID - list of resource and relmarker fields that refers this rectype
            // 3) direct links  rty -> rty_IDs - linked to 
            // 4) reverse links rty <- rty_IDs - linked from
            //
            createRstIndex: function(){
                var rst_index = {};
                var rst_references = {}; //list of resource and relmarker fields that refers this rectype
                var rst_reverse = {};    //linked FROM rectypes
                var rst_direct = {};     //linked TO rectypes
                
                var recset = entity_data['defRecStructure'];
                recset.each2(function(rst_ID, record){
                    
                    //rstfield = recset.getRecord(rst_ID)
                    var rty_ID = record['rst_RecTypeID'];
                    var dty_ID = record['rst_DetailTypeID'];
                    
                    if(!rst_index[rty_ID]) rst_index[rty_ID] = {};
                    if(!rst_index[rty_ID][dty_ID]){
                        record['rst_ID'] = dty_ID;
                        rst_index[rty_ID][dty_ID] = record;  
                    } 

                    /*links
                    var dty_Type = $Db.dty(dty_ID, 'dty_Type');
                    if((dty_Type=='resource' || dty_Type=='relmarker') 
                        && record['rst_RequirementType']!='forbidden')
                    {
                        var ptr = $Db.dty(dty_ID, 'dty_PtrTargetRectypeIDs');
                        if(ptr){
                           ptr = ptr.split(',');
                           if(ptr.length>0){
                               //direct links
                               if(!rst_direct[rty_ID]) rst_direct[rty_ID] = [];  
                               rst_direct[rty_ID] = rst_direct[rty_ID].concat(ptr);
                               
                               for(var i=0; i<ptr.length; i++){
                                   var target_rty = ptr[i];
                                   if(rst_references[target_rty]){
                                       if(rst_references[target_rty].indexOf(dty_ID)<0){
                                           rst_references[target_rty].push(dty_ID);
                                       }
                                       if(rst_reverse[target_rty].indexOf(rty_ID)<0){
                                           rst_reverse[target_rty].push(rty_ID);
                                       }
                                   }else{
                                        rst_reverse[target_rty] = [rty_ID];
                                        rst_references[target_rty] = [dty_ID];
                                   }
                               }
                           }
                        }
                    }*/
                });
                
                //create separate recordset for every rectype
                for(var rty_ID in rst_index){
                    var _order = Object.keys(rst_index[rty_ID]);
                    rst_index[rty_ID] = new hRecordSet({
                        entityName: 'defRecStructure',
                        count: _order.length,
                        records: rst_index[rty_ID],
                        order: _order
                    });    
                }
                
                entity_data['rst_Index'] = rst_index;
                
                // see $Db.rst_links
                //entity_data['rst_Links'] = {direct:rst_direct, reverse:rst_reverse, refs:rst_references };
            },

            //
            // refresh several entity data at once
            // 
            refreshEntityData:function(entityNames, callback){

//var s_time = new Date().getTime() / 1000;
                
                 //'multi':1,   
                _callserver('entityScrud', {a:'structure', 'entity':entityNames, 'details':'full'},
                    function(response){
                        if(response.status == window.hWin.ResponseStatus.OK){

//var fin_time = new Date().getTime() / 1000;
//console.log('DEBUG refreshEntityData '+response.data+'  '+(fin_time-s_time));                    
                            
                            for(var entityName in response.data){
                                window.hWin.HAPI4.EntityMgr.setEntityData(entityName, response.data)
                            }

                            if($.isFunction(callback)) callback(this, true);  
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);
                        }
                    }

                );
            },
            
            
            //load entire entity data and store it in cache (applicable for entities with count < ~1500)
            // entityScrud.action = search
            //
            // entityName - name name or array of names or "all" for all db definitions
            //
            getEntityData:function(entityName, force_reload, callback){

                if($.isEmptyObject(entity_data[entityName]) || force_reload==true){

                    var det = 'list';
                    if(entityName=='defRecStructure'){
                        det = 'full';   
                    }

//var s_time = new Date().getTime() / 1000;
                    
                    _callserver('entityScrud', {a:'search', 'entity':entityName, 'details':det},
                       function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){

//var fin_time = new Date().getTime() / 1000;
//console.log('DEBUG getEntityData '+response.data.entityName+'  '+(fin_time-s_time));                    
                                
                                entity_data[response.data.entityName] = new hRecordSet(response.data);    
                                
                                if(response.data.entityName=='defRecStructure'){
                                    window.hWin.HAPI4.EntityMgr.createRstIndex();
                                }                                
                                
                                if($.isFunction(callback)){
                                        callback(entity_data[response.data.entityName]);  
                                } 
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                       }

                    );
                }else{
                    //take from cache
                    if($.isFunction(callback)){
                        callback(entity_data[entityName]);
                    }else{
                        //if user sure that data is already on client side
                        return entity_data[entityName];
                    }
                }
            },
            
            //
            // direct access (without check and request to server)
            //
            getEntityData2: function(entityName){
                return entity_data[entityName];     
            },

            //
            // data either recordset or response.data
            //
            setEntityData: function(entityName, data){
                
                if( window.hWin.HEURIST4.util.isRecordSet(data) ){
                    
                    entity_data[entityName] = data;    
                }else{
                    
                    entity_data[entityName] = new hRecordSet(data[entityName]);    

                    //build rst index
                    if(entityName=='defRecStructure'){
                        window.hWin.HAPI4.EntityMgr.createRstIndex();
                    }else if(entityName=='defTerms'){
//console.log('HEREEE!!!!!');                                   
                        entity_data['trm_Links'] = data[entityName]['trm_Links'];
                    }
                    
                    if(data[entityName]['config']){
                        entity_configs[entityName] = data[entityName]['config'];
                        //find key and title fields
                        window.hWin.HAPI4.EntityMgr.resolveFields(entityName);
                    }
                }
                
            },
            
            //
            // generic request for entityScrud
            //
            doRequest:function(request, callback){
                //todo - verify basic params
                request['request_id'] = window.hWin.HEURIST4.util.random();
                
                //set d and c=0 to disable debug  http://www.nusphere.com/kb/technicalfaq/faq_dbg_related.htm
                request.DBGSESSID= (_use_debug)?'425944380594800002;d=1,p=0,c=1':'425944380594800002;d=0,p=0,c=0';
                
                _callserver('entityScrud', request, callback);
            },

            //
            // retrieve title of entity by given ids
            // entityScrud.action = title
            //
            getTitlesByIds:function(entityName, recIDs, callback){
                
                var idx, display_value = [];
                if(entity_data[entityName]){
                   var ecfg = entity_configs[entityName];
                   if(!ecfg){
                          window.hWin.HAPI4.EntityMgr.getEntityConfig(entityName, function(){
                                window.hWin.HAPI4.EntityMgr.getTitlesByIds(entityName, recIDs, callback);
                          });
                          return;
                   }
                   
                   
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
        currentUser: _guestUser,
        is_publish_mode: true,
        sysinfo: {},

        Event: {
            /*LOGIN: "LOGIN",
            LOGOUT: "LOGOUT",*/
            ON_CREDENTIALS: 'ON_CREDENTIALS', //login, logout, change user role, sysinfo (change sysIdentification) 
            ON_REC_SEARCHSTART: "ON_REC_SEARCHSTART",
            ON_REC_SEARCH_FINISH: "ON_REC_SEARCH_FINISH",
            ON_CUSTOM_EVENT: "ON_CUSTOM_EVENT", //special event for custom link various widgets
            ON_REC_UPDATE: "ON_REC_UPDATE",
            ON_REC_SELECT: "ON_REC_SELECT",
            ON_REC_COLLECT: "ON_REC_COLLECT",
            ON_LAYOUT_RESIZE: "ON_LAYOUT_RESIZE",
            ON_WINDOW_RESIZE: "ON_WINDOW_RESIZE",
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
            
            var isChanged = (that.currentUser != user);
            
            if(user){
                that.currentUser = user;
            }else{
                that.currentUser = _guestUser;
            }

            if(false){ //disabled: verify credentials if user is idle
            
                if(that.currentUser['ugr_ID']>0){
                    
                    if(!window.hWin.HAPI4.is_publish_mode){

                        if(window.hWin.HEURIST4 && window.hWin.HEURIST4.ui)                        
                            window.hWin.HEURIST4.ui.onInactiveStart(5000, function(){  //300000 5 minutes 
                                //check that still logged in
                                window.hWin.HAPI4.SystemMgr.verify_credentials(function(){
                                    //ok we are still loggen in
                                    window.hWin.HEURIST4.ui.onInactiveReset(); //start again    
                                    }, 0);
                        });
                    }
                }else{
                    //terminate completely
                    window.hWin.HEURIST4.ui.onInactiveReset( true );
                }
            
            }
            
            if(window.hWin.HEURIST4.dbs && isChanged){
                window.hWin.HEURIST4.dbs.needUpdateRtyCount = 1;  
                window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_REC_UPDATE); //after save record 
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
        * Returns TRUE if currentUser satisfies to required level
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
                return (that.currentUser && that.currentUser['ugr_ID']>0); //just logged in
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
                 userFontSize: 12, //px
                 deriveMapLocation: true,
                 help_on: true, 
                 optfields: true,
                 mapcluster_on: true,
                 searchQueryInBrowser:true
                };
            }
            if(window.hWin.HEURIST4.util.isempty(name)){
                return that.currentUser['ugr_Preferences']; //returns all preferences
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
        
        removeEventListener:function( object, event_type ){
            for (var i=0; i<_listeners.length; i++){
                if(_listeners[i].event_type == event_type && _listeners[i].obj == object){
                   
                    _listeners.splice(i,1);
                    return;
                }
            }
        },

        user_id: function(){
                return that.currentUser['ugr_ID'];  
        },
        
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

        //assign it later since we may have different search managers - incremental, partial...
        RecordSearch: null, //class that responsible for search and incremental loading of result

        LayoutMgr: null,

        /*RecordMgr: function(){
        return /();
        }*/

        //
        // returns current locale - language code
        //
        getLocale: function(){
            return _region;
        },
        /**
        * Returns function to string resouce according to current region setting
        */
        setLocale: function( region ){
            
            if(!region) region = 'en'; //default
            
            if(_regional && _regional[region]){
                //already loaded - switch region
                _region = region;
                _regional = regional[_region];
            }else{
                $.getScript(that.baseURL+'hclient/core/localization'
                    +(region=='en'?'':('_'+region))+'.js', function() {
                    _region = region;
                    _regional = regional[_region];
                });
            }
            
            // function that returns string resouce according to current region setting
            return function(res){
                
                if(window.hWin.HEURIST4.util.isempty(res)){
                    return '';
                }
                var key = res.trim();
                if(key.indexOf('menu-')==0){
                    key = key.replaceAll('-','_');
                }
                
                if(_regional && _regional[key]){
                    return _regional[key];
                }else{
                    //if not found take from english version
                    if(_region!='en' && regional['en'] && regional['en'][key]){

                        return regional['en'][key];
                        
                    }else if(key.indexOf('menu_')==0){
                        
                        return '';
                    }else{
                        return res; //returns itself   
                    }
                }
            }
        },
        
        //
        //localize all elements with class slocale for given element
        //
        HRA: function(ele){
            if(ele){
                $.each($(ele).find('.slocale'),function(i,item){
                    var s = $(item).text();
                    //console.log(s+"=>"+window.hWin.HR(s))
                    $(item).html(window.hWin.HR(s));
                });
                
                $(ele).find('[slocale-title]').each(function(){
                    $(this).attr('title', window.hWin.HR($(this).attr('slocale-title')));
                });
            }
        },
        
        //
        // returns localized value for json (options in widget)
        //
        HRJ: function(name, options, lang){
            
            var def_value = options[name];
            
            var loc_value = options[name+':'+((lang && lang!='xx')?lang:_region)];
            
            return loc_value?loc_value:def_value;
        },
        
        //
        // returns url or loads content for localized resource (for example help, documentation or html snipper for form)
        // name - name of file (default html ext)
        // ele - target element
        //
        HRes: function(name, ele){
            
            //window.hWin.HAPI4.getLocale()
            var sURL = window.hWin.HAPI4.baseURL+'?lang='+_region+'&asset='+name;
            if(ele){
                ele.load(sURL);
            }else{
                return sURL;
            }
            
            /*default extension is html
            var ext = window.hWin.HEURIST4.util.getFileExtension(name);
            if(window.hWin.HEURIST4.util.isempty(ext)){
                name = name + '.html';
            }
            var sURL = '';
            if(_region && _region!='en'){
                sURL = sURL + _region + '/';
                
            }
            resultListEmptyMsg.html;
            */
            
        },


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
        getSelection: function(selection, needIds){

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

        //
        //  returns url for entity image
        // 
        // version = thumb, icon
        // def - if file not found return empty placeholder (0) or "add image" gif (1) 
        //       2 - default icon/thumb for entity
        //       3 - check existence
        //
        , getImageUrl: function(entityName, recID, version, def, database){
                
            return window.hWin.HAPI4.baseURL //redirected to + 'hsapi/controller/fileGet.php'
                    +'?db='+ (database?database:window.hWin.HAPI4.database)
                    +(entityName?('&entity='+entityName):'') //rty by default
                    +'&icon='+recID
                    +(version?('&version='+version):'')
                    +(def?('&def='+def):'');
        }

        //
        //
        //
        , checkImage: function(entityName, recID, version, callback){
            
            if(entityName=='Records'){

                var request = {
                        db: window.hWin.HAPI4.database,
                        file: recID,  //ulf_ID
                        mode: 'size' };
                
                window.hWin.HEURIST4.util.sendRequest(window.hWin.HAPI4.baseURL + 'hsapi/controller/file_download.php', 
                            request, null, callback);
                            
            }else{
                
                var checkURL = window.hWin.HAPI4.getImageUrl(entityName, recID, version, 'check');
                
                window.hWin.HEURIST4.util.sendRequest(checkURL, null, null, callback);
                        
            }
        }

        //
        //
        //
        , doImportAction: function(request, callback){
            _callserver('importController', request,  
            function(response){
                        _triggerRecordUpdateEvent(response, callback);
            });
        }

        
    
        /**
        * returns true if _callserver ajax request is in progress
        */
        ,is_callserver_in_progress: function (){
            return _is_callserver_in_progress;
        }
        

    }

    _init(_db, _oninit, _baseURL); //, _currentUser);
    return that;  //returns object
}