/**
* Main class for Heurist API
* 
* Constructor:
* @param _db - database name, if omit it takes from url parameter
* @param _oninit - callback function, obtain parameter true if initialization is successeful
* @returns hAPI Object
*/
function hAPI(_db, _oninit) { //, _currentUser
     var _className = "HAPI",
         _version   = "0.4",
         _database = null, //same as public property  @toremove
         _region = 'en',
         _regional = null, //localization resources
         _guestUser = {ugr_ID:0, ugr_FullName:'Guest'};



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
            _database = top.HEURIST.util.getUrlParameter('db');
        }

        // does not work
        var installDir = top.location.pathname.replace(/(((\?|admin|common|js|php)\/.*)|(index.*))/, "");
        that.basePath = top.location.protocol + '//'+top.location.host + installDir;
        that.iconBaseURL = top.location.protocol + '//'+top.location.host+'/HEURIST_FILESTORE/'+_database+'/rectype-icons/';
        that.database = _database;

        //global variable defined in localization.js
        if(!(typeof regional === 'undefined')){ 
            _regional = regional;
            /*todo
            $.getScript(that.basePath+'/js/localization.js', function() {
                  _regional = regional;
             });
             */
        }

        /*if(_currentUser){
            that.currentUser = _currentUser;
        }else{}*/
        
        // Get current user if logged in, and global database settings
        that.SystemMgr.sys_info(
                function(response){
                    var  success = (response.status == top.HAPI.ResponseStatus.OK);
                    if(success){
                        that.setCurrentUser(response.data.currentUser);
                        that.registration_allowed = (response.registration_allowed==1);
                    }else{
                        top.HEURIST.util.showMsgErr(response.message);
                    }
                    if(_oninit){
                        _oninit(success);
                    }
                }
        );
        
    }

    /*
    * internal function see hSystemMgr, hRecordMgr - ajax request to server
    * 
    * @param action   - name of php script in php/api foolder on server side
    * @param request - data to be sent to server side
    * @param callback - callback function, it obtains object with 2 properties: 
    *       status - see ResponseStatus
    *       message - ajax response or error message
    */
    function _callserver(action, request, callback){

                 if(!request.db){
                    request.db = _database;
                 }

                 var url = that.basePath+"php/api/"+action+".php";

                 $.ajax({
                         url: url,
                         type: "POST",
                         data: request,
                         dataType: "json",
                         cache: false,
                         error: function(jqXHR, textStatus, errorThrown ) {
                            callback({status:top.HAPI.ResponseStatus.UNKNOWN_ERROR,
                                message: jqXHR.responseText });
                                //message:'Error connecting server '+textStatus});
                         },
                         success: function (response) {
                             callback(response);
                         }
                     });

    }

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
    *   sys_info     - get current user info and database settings
    *   save_prefs   - save user preferences  in session
    *   mygroups     - description of current user groups
    *   ssearch_get  - get saved searched for current user and all usergroups where user is memeber
    *   ssearch_save - save saved search in database
    *   ssearch_delete - delete saved searches by IDs
    *   get_defs     - get the desired database structure definition
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

            /**
            * Log out
            */
            ,logout: function(callback){
                 _callserver('usr_info', {a:'logout'}, callback);
            }

            /**
            * Get current user if logged in, and global database settings
            */
            ,sys_info: function(callback){
                 _callserver('usr_info', {a:'sysinfo'}, callback);
            }

            /**
            * Save user preferences in session
            * @param request - object with preperties - user preferences
            * @param callback  
            */
            ,save_prefs: function(request, callback){
                if(request) request.a = 'save_prefs';
                 _callserver('usr_info', request, callback);
            }

            /**
            * Returns detailed description of current user groups
            * 
            * response data - array of ugl_GroupID:[ugl_Role, ugr_Name, ugr_Description]
            */
            ,mygroups: function(callback){
                 _callserver('usr_info', {a:'groups'}, callback);
            }

            /**
            *  Get saved searched for current user and all usergroups where user is memeber
            * 
            *  response data - array of  svs_ID:[svs_Name, svs_Query, svs_UGrpID]
            */
            ,ssearch_get: function(callback){
                 _callserver('usr_info', {a:'svs_get'}, callback);
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


            /**
            *  Get the desired database structure definition 
            * request
            *   terms, rectypes, detailtypes :  list of desired ids,  OR 'all'
            *   mode: applied for rectypes  0 only names (default), 1 only strucuture, 2 - both
            * 
            */
            ,get_defs: function(request, callback){
                 _callserver('sys_structure', request, callback);
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
    * see record_edit.php, record_tags.php and record_search.php
    * 
    * methods:
    *   add       - creates new temporary record
    *   save      - save record
    *   remove    - delete record
    *   
    *   search
    *   minmax
    *   get
    * 
    *   tag_save
    *   tag_delete
    *   tag_get
    *   tag_set
    * 
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
            *  Save Record (remove temporary falg if new record)
            * 
            * @param request a: s|save
            * @param callback - response hRecordSet object 
            */
            ,save: function(request, callback){
                if(request) request.a = 's';
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
            * Search for records
            * 
            * request { }
            *  q - query string
            *  w - a|b - domain all or bookmarks
            *  f - none or cs list detail,map,structure,tags,relations,(backward)links,text,comments - details of output
            *  l - limit
            * o - offset
            */
            ,search: function(request, callback){
                 _callserver('record_search', request, callback);
            }

            // find min and max values for
            // rt - record type
            // dt - detailtyep
            ,minmax: function(request, callback){
                 if(request) request.a = 'minmax';
                 _callserver('record_search', request, callback);
            }


            //@TODO get full info for particular record
            ,get: function(request, callback){
                 _callserver('record_get', request, callback);
            }

            // add/save tag
            // request  a: save
            //          tag_ID (not specified if ADD)
            //          tag_Text
            //          tag_Description
            //          tag_UGrpID
            ,tag_save: function(request, callback){
                if(request) request.a = 'save';
                 _callserver('record_tags', request, callback);
            }
            // remove tag
            // request  a: delete
            //          ids - list of tag ids to be deleted
            ,tag_delete: function(request, callback){
                if(request) request.a = 'delete';
                 _callserver('record_tags', request, callback);
            }
            // get list of tags for sepcified userid
            // request  a: search
            //          UGrpID
            ,tag_get: function(request, callback){
                 if(request) request.a = 'search';
                 _callserver('record_tags', request, callback);
            }
            // assign/remove list of tags for sepcified list of records
            // request  a: assign_remove
            //          assign: cs list of tag ids to be assigned
            //          remove: tags to be removed from records
            //          recs: cs list of record ids
            //          UGrpID
            ,tag_set: function(request, callback){
                 if(request) request.a = 'set';
                 _callserver('record_tags', request, callback);
            }


        }
        return that;
    }

    /**
    * Localization
    * 
    * Returns string resouce according to current region setting
    */
    function _key(res){
        if(_regional && _regional[_region] && _regional[_region][res]){
            return _regional[_region][res];
        }else{
            return res;
        }
    }

    //public members
    var that = {

        basePath: '',
        iconBaseURL: '',
        database: '',

        ResponseStatus: {
            INVALID_REQUEST: "invalid",    // The Request provided was invalid.
            NOT_FOUND: "notfound",         // The requested object not found.
            OK: "ok",                      // The response contains a valid Result.
            REQUEST_DENIED: "denied",      // The webpage is not allowed to use the service.
            DB_ERROR: "database",          // A request could not be processed due to a server database error. Most probably this is BUG. Contact developers
            UNKNOWN_ERROR: "unknown",      // A request could not be processed due to a server error. The request may succeed if you try again.
            SYSTEM_FATAL: "system"         // System fatal configuration. Contact system admin
        },

        Event: {
            LOGIN: "LOGIN",
            LOGOUT: "LOGOUT",
            ON_REC_SEARCHSTART: "ON_REC_SEARCHSTART",
            ON_REC_SEARCHRESULT: "ON_REC_SEARCHRESULT",
            ON_REC_PAGESET: "ON_REC_PAGESET",
            ON_REC_SELECT: "ON_REC_SELECT"
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
        
        /**
        * Returns true if currentUser ID > 0 (not guest)
        */
        is_logged: function(){
            return that.currentUser['ugr_ID']>0;
        },
        
        /**
        * Returns true is current user is database owner
        */
        is_admin: function(){
            return that.currentUser['ugr_Admin'];
        },
        
        /**
        * Returns true if currentUser is member of given group ID or itself  (similar on server is_admin2)
        * @param ug
        */
        is_member: function(ug){
            return (ug==0 || that.currentUser['ugr_ID']==ug ||
                (that.currentUser['ugr_Groups'] && that.currentUser['ugr_Groups'][ug]));
        },
        
        /**
        * Return userGroup ID if currentUser is database owner or admin of given group
        * 
        * @param ugrID - userGroup ID
        */
        has_access: function(ugrID){

            if(!ugrID){
                ugrID = that.currentUser['ugr_ID'];
            }
            if(ugrID==that.currentUser['ugr_ID'] || that.is_admin() ||
                (that.currentUser['ugr_Groups'] && that.currentUser['ugr_Groups'][ugrID]=="admin"))
            {
                return ugrID;
            }else{
                return -1;
            }
        },
        
        /**
        * Returns current user preferences
        * 
        * @returns {Object}
        */
        get_prefs: function(){
            if( that.currentUser['ugr_Preferences'] ) {
                return that.currentUser['ugr_Preferences'];
            }else{
                return {layout_language:'en', layout_theme:'base'};
            }
        },

        currentUser: _guestUser,
        registration_allowed: false,

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},


        //UserMgr: new hUserMgr(),

        SystemMgr: new hSystemMgr(),

        /*SystemMgr: function(){
            return hSystemMgr();
        },*/

        RecordMgr: new hRecordMgr(),

        /*RecordMgr: function(){
            return hRecordMgr();
        }*/

        /**
        * Returns function to string resouce according to current region setting
        */
        setLocale: function( region ){
                if(_regional && _regional[region]){
                        _region = region;
                }
                return _key;  //_key is function
        }
    }

    _init(_db, _oninit); //, _currentUser);
    return that;  //returns object
}