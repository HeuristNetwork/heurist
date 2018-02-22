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
* @copyright   (C) 2005-2016 University of Sydney
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
    _guestUser = {ugr_ID:0, ugr_FullName:'Guest' };



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
        var installDir = window.hWin.location.pathname.replace(/(((\?|admin|applications|common|context_help|export|hapi|hclient|hserver|import|records|redirects|search|viewers|help|ext|external)\/.*)|(index.*|test.php))/, ""); // Upddate in 2 places this file and 6 other files if changed
        //TODO: top directories - admin|applications|common| ... are defined in SEVEN separate locations. Rationalise.
        that.baseURL = window.hWin.location.protocol + '//'+window.hWin.location.host + installDir;

        // TODO: This is actually a proto URL rather than a base URL. Rename.
        that.iconBaseURL = that.baseURL + 'hserver/dbaccess/rt_icon.php?db='+_database+'&id=';
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
        that.SystemMgr.sys_info( _oninit );

    }

    /*
    * internal function see hSystemMgr, hRecordMgr - ajax request to server
    *
    * @param action   - name of php script in hserver/controller foolder on server side
    * @param request - data to be sent to server side
    * @param callback - callback function, it obtains object with 2 properties:
    *       status - see ResponseStatus
    *       message - ajax response or error message
    */
    function _callserver(action, request, callback){

        if(!request.db){
            request.db = _database;
        }
        if(request.notes){
            request.notes = null; //unset to reduce traffic
        }


        //remove remark to debug 
        request.DBGSESSID='425944380594800002;d=1,p=0,c=07';

        var url = that.baseURL+"hserver/controller/"+action+".php"; //+(new Date().getTime());

        //window.hWin.HEURIST4.ajax.getJsonData(url, callback, request);

        //note jQuery ajax does not properly in the loop - success callback does not work often
        $.ajax({
            url: url,
            type: "POST",
            data: request,
            dataType: "json",
            cache: false,
            error: function( jqXHR, textStatus, errorThrown ) {

                err_message = (window.hWin.HEURIST4.util.isempty(jqXHR.responseText))
                                            ?'Error_Connection_Reset':jqXHR.responseText;
                                            
                var response = {status:window.hWin.HAPI4.ResponseStatus.UNKNOWN_ERROR, message: err_message}

                if(callback){
                    callback(response);
                }
                //message:'Error connecting server '+textStatus});
            },
            success: function( response, textStatus, jqXHR ){

                if(callback){
                    callback(response);
                }
                
                /*check response for special marker that forces to reload user and system info
                //after update sysIdentification, dbowner and user role
                if(response && 
                    (response.status == window.hWin.HAPI4.ResponseStatus.OK) && 
                     response.force_refresh_sys_info) {
                         that.SystemMgr.sys_info(function(success){
                             
                         });
                }*/
                
            },
            fail: function(  jqXHR, textStatus, errorThrown ){
                err_message = (window.hWin.HEURIST4.util.isempty(jqXHR.responseText))?'Error_Connection_Reset':jqXHR.responseText;
                var response = {status:window.hWin.HAPI4.ResponseStatus.UNKNOWN_ERROR, message: err_message}

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
            *  need to call this method before every major action or open popup dialog
            *  for internal actions use client side methods of hapi.is_admin, is_member, has_access
            */
            , verify_credentials: function(callback, requiredLevel){

                //check if login
                _callserver('usr_info', {a:'verify_credentials'},
                function(response){
                    
                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                    
                        if(response.data.sysinfo){
                            window.hWin.HAPI4.sysinfo = response.data.sysinfo;
                            window.hWin.HAPI4.baseURL = window.hWin.HAPI4.sysinfo['baseURL'];
                        }
                        if(response.data.currentUser) {
                            window.hWin.HAPI4.setCurrentUser(response.data.currentUser);   
                            //trigger global event ON_CREDENTIALS
                            $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS); 
                        }

                        //since currentUser is up-to-date - use client side method
                        requiredLevel = Number(requiredLevel);
                        
                        if(!(requiredLevel<0  //not verify if reqlevel <0
                            || window.hWin.HAPI4.has_access(requiredLevel))){ 

                            response.sysmsg = 0;
                            response.status = window.hWin.HAPI4.ResponseStatus.REQUEST_DENIED;
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
                        }
                    }
                    
                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                        callback();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response, true);
                       // window.hWin.HEURIST4.msg.showMsgErr(response, true); Login Page is already rendered no need to  display Error Message
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
                        var  success = (response.status == window.hWin.HAPI4.ResponseStatus.OK);
                        if(success){
                            
                            if(response.data.currentUser) {
                                window.hWin.HAPI4.setCurrentUser(response.data.currentUser);   
                            }
                            if(response.data.sysinfo){
                                window.hWin.HAPI4.sysinfo = response.data.sysinfo;
                                window.hWin.HAPI4.baseURL = window.hWin.HAPI4.sysinfo['baseURL'];
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

                //first try to take on client side
                var sUserName = null;
                var usr_ID = Number(request.UGrpID);
                
                if(usr_ID==0){
                    sUserName = window.hWin.HR('Everyone');
                }else if(usr_ID == window.hWin.HAPI4.currentUser['ugr_ID']){
                    sUserName = window.hWin.HAPI4.currentUser['ugr_FullName'];
                }else if( window.hWin.HAPI4.sysinfo.db_usergroups && window.hWin.HAPI4.sysinfo.db_usergroups[usr_ID]){
                    sUserName = window.hWin.HAPI4.sysinfo.db_usergroups[usr_ID];
                }
                
                if(sUserName){
                    var res = {};
                    res[usr_ID] = sUserName;
                    callback.call(this, {status:window.hWin.HAPI4.ResponseStatus.OK, data:res} );
                }else{
                    //search on server
                    if(request) request.a = 'usr_names';
                    _callserver('usr_info', request, callback);
                }
            }

            ,user_log: function(activity, suplementary){
                var request = {a:'usr_log', activity:activity, suplementary:suplementary};
                _callserver('usr_info', request);
            }

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
                    
                    if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                        
                        window.hWin.HEURIST4.rectypes = response.data.rectypes;
                        window.hWin.HEURIST4.terms = response.data.terms;
                        window.hWin.HEURIST4.detailtypes = response.data.detailtypes;
                        
                        if(top && top==window && top.HEURIST){
                            top.HEURIST.rectypes = response.data.rectypes;
                            top.HEURIST.terms = response.data.terms;
                            top.HEURIST.detailTypes = response.data.detailtypes;
                        }
                             
                        if(window.hWin.HEURIST && window.hWin.HEURIST.rectypes){
                            window.hWin.HEURIST.util.reloadStrcuture( is_message ); //relaod H3 structure
                        }else if (is_message==true) {
                            window.hWin.HEURIST4.msg.showMsgDlg('Database structure definitions in browser memory have been refreshed.<br>'+
                                'You may need to reload pages to see changes.');
                        }      
                        
                        if($.isFunction(callback)) callback.call();

                        $(document).trigger(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
                    }
                });

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
    *   add       - creates new temporary record
    *   save      - save record
    *   remove    - delete record
    *   duplicate
    *
    *   details   - batch edition of record details for many records
    *
    *   search
    *   minmax
    *   get
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
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
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
            getEntityConfig:function(entityName, callback){

                if(entity_configs[entityName]){
                    if($.isFunction(callback)){
                        callback(entity_configs[entityName]);
                    }
                    return entity_configs[entityName];
                }else{
                    _callserver('entityScrud', {a:'config', 'entity':entityName},
                       function(response){
                            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){

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

            //load entire entity data and store it in cache (applicable for entities with count < ~1500)
            getEntityData:function(entityName, force_reload, callback){

                if($.isEmptyObject(entity_data[entityName]) || force_reload==true){
                    _callserver('entityScrud', {a:'search', 'entity':entityName, 'details':'list'},
                       function(response){
                            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
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

            doRequest:function(request, callback){
                //todo - verify basic params
                request['request_id'] = window.hWin.HEURIST4.util.random();
                request['DBGSESSID'] = '424657986609500001;d=1,p=0,c=0';  //DEBUG parameter
                _callserver('entityScrud', request, callback);
            },

            //
            //
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
                                            if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                                                
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

        ResponseStatus: {
            INVALID_REQUEST: "invalid",    // The Request provided was invalid.
            NOT_FOUND: "notfound",         // The requested object not found.
            OK: "ok",                      // The response contains a valid Result.
            REQUEST_DENIED: "denied",      // The webpage is not allowed to use the service.
            DB_ERROR: "database",          // A request could not be processed due to a server database error. Most probably this is BUG. Contact developers
            UNKNOWN_ERROR: "unknown",      // A request could not be processed due to a server error. The request may succeed if you try again.
            SYSTEM_CONFIG: "syscfg", // System NON-fatal configuration. Contact system admin
            SYSTEM_FATAL: "system"           // System fatal configuration. Contact system admin
        },

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
            ON_STRUCTURE_CHANGE: 'ON_STRUCTURE_CHANGE'
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
        is_member: function(ug){
            return (ug==0 || that.currentUser['ugr_ID']==ug ||
                (that.currentUser['ugr_Groups'] && that.currentUser['ugr_Groups'][ug]));
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
                 search_detail_limit: 2000, 'help_on':'0', 
                 userCompetencyLevel: 2, //'beginner',
                 mapcluster_on: false};
            }
            if(window.hWin.HEURIST4.util.isempty(name)){
                return that.currentUser['ugr_Preferences'];
            }else{
                var res = that.currentUser['ugr_Preferences'][name];

                //take from old set
                if(window.hWin.HEURIST4.util.isnull(res) && window.hWin.HEURIST && window.hWin.HEURIST.displayPreferences){
                    res = window.hWin.HEURIST.displayPreferences[name];
                }

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
                        if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                            that.currentUser['ugr_Preferences'][name] = value;
                        }
                    }
                );
        },

        is_ui_normal: function(){
            return (window.hWin.HAPI4.get_prefs('layout_style')=='normal');
        },


        currentUser: _guestUser,
        sysinfo: {},

        // main result set that is filled in search_minimal - keeps all
        // purposes:
        // 1) to keep main set of records (original set) to apply RuleSet
        // 2) to get selected records by ids
        // 3) to pass result set into popup record action dialogs
        currentRecordset: null,


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

            return window.hWin.HAPI4.baseURL + 'hserver/utilities/fileGet.php'
                    +'?db='+ (database?database:window.hWin.HAPI4.database)
                    +'&entity='+entityName
                    +'&id='+recID
                    +'&version='+version
                    +'&def='+def;
        }

        //
        //
        //
        , parseCSV: function(request, callback){
            //if(request) request.a = 'svs_delete';
            //request['DBGSESSID']='425288446588500001;d=1,p=0,c=0';

            _callserver('fileParse', request, callback);
        }


    }

    _init(_db, _oninit); //, _currentUser);
    return that;  //returns object
}