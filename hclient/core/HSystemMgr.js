/**
 * @file HSystemMgr.js
 * @brief Manages system-level interactions like user authentication, session management, and system information retrieval.
 * @fileOverview The HSystemMgr class provides methods for system-level operations. This includes user login,
 * logout, password reset, and credential verification. It handles fetching and saving user preferences,
 * managing user activity logs, and retrieving system information (like user details, database settings,
 * and structure definitions). It also includes functionalities for managing saved searches (though some
 * are marked for potential migration to EntityMgr), interacting with repositories, and performing version
 * checks for client and database software. Additionally, it provides utilities for file system operations
 * (listing/managing folders in HEURIST_FILESTORE_DIR), translation services via DeepL, and Matomo
 * analytics tracking integration.
 * 
 * @project     Heurist academic knowledge management system
 *
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 6.0
 */
 
/* global prepared_params */

/**
 * @class HSystemMgr
 * @classdesc System class responsible for server interactions related to user/group information,
 * credentials, saved searches, system info, and various other system-level operations.
 * Interacts primarily with server-side controllers like `usr_info.php` and `sys_structure.php`.
 */
class HSystemMgr {
    /** @private */
  hapi4;
    
  /**
   * Creates an instance of HSystemMgr.
   * @param {HAPI} hapi4 - An instance of the HAPI class.
   */
  constructor(hapi4) {
    this.hapi4 = hapi4; 
  }
  
  /**
   * Attempts to log in a user.
   *
   * @param {Object} request - The login request object.
   * @param {string} request.username - The username.
   * @param {string} request.password - The user's password.
   * @param {('public'|'shared'|'remember')} [request.session_type] - The type of session requested.
   * @param {function(Object): void} callback - Callback function that handles the server response.
   *        The response object typically contains user information (HUser) on success.
   * @returns {void}
   */
  login(request, callback) {

    if (!request) request = {};  
    request.a = 'login';  // Action parameter for the server
    
    window.hWin.HAPI4.callserver('usr_info', request, response => {
           if (response.status == window.hWin.ResponseStatus.OK) {
               this.matomoTrackLogin(); // Track successful login
           }

           if (window.hWin.HEURIST4.util.isFunction(callback)) {
               callback(response);
           }
         
           $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS);
       });
  }  
 
  /**
   * Initiates a password reset process for a user.
   *
   * @param {Object} request - The password reset request object.
   * @param {string} request.username - The username for whom to reset the password.
   * @param {function(Object): void} callback - Callback function to handle the server response.
   * @returns {void}
   */
  reset_password(request, callback) {
       if (request) request.a = 'reset_password';
       window.hWin.HAPI4.callserver('usr_info', request, callback); 
  } 
  
  /**
   * Logs out the current user.
   *
   * @param {function(Object): void} [callback] - Optional callback function to handle the server response.
   * @returns {void}
   */
  logout(callback) {
    window.hWin.HAPI4.callserver('usr_info', { a: 'logout' }, response => {
      if (response.status == window.hWin.ResponseStatus.OK) {
        window.hWin.HAPI4.setCurrentUser(null); // Clear current user locally
        
        this.matomoTrackLogout(); // Track logout
        
        // Trigger a global event indicating credentials have changed (user logged out)
        $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS);

        if (window.hWin.HEURIST4.util.isFunction(callback)) {
          callback(response);
        }
      } else {
        window.hWin.HEURIST4.msg.showMsgErr(response); // Show error message on failure
      }
    });
  } 
  
  /**
  * Verifies user credentials and permissions for an action.
  * This method performs several checks:
  * 1. Verifies credentials on the server-side and checks if they need an update.
  * 2. If credentials have changed (e.g., role update, DB settings change), it returns updated user and system info.
  * 3. If a specific `requiredLevel` of credentials/permissions is defined, it verifies if the current user meets these requirements.
  *
  * This method should be called before every major action or when opening popup dialogs that require specific permissions.
  * For internal client-side checks, `HAPI.is_admin()`, `HAPI.is_member()`, `HAPI.has_access()` can be used.
  *
  * @param {function(string): void} callback - Called with the `password_entered` if verification is successful (especially for password-protected actions).
  * @param {number|string} [requiredLevel=0] - The level of verification required.
  *   - `-1`: No verification needed (but password protection still applies if `password_protected` is set).
  *   - `0`: User must be logged in (default).
  *   - `groupid` (number): User must be an admin of the specified group ID.
  *   - `1`: User must be a DB admin (admin of group #1, typically "Database Managers").
  *   - `2`: User must be the DB owner.
  *   - String format `adminLevel;memberOfGroup` (e.g., "1;3"): User must meet `adminLevel` AND be a member of `memberOfGroup` group.
  * @param {string} [password_protected] - The name/key of a password-protected action. If set, this action requires an additional password,
  *                                       potentially a system administrator override password, regardless of `requiredLevel`.
  * @param {string} [password_entered] - The password entered by the user on the client-side for a password-protected action.
  * @param {('add'|'delete'|'add delete')} [requiredPermission] - Specific permissions required for the action (e.g., 'add', 'delete'). This is checked on server-side.
  * @param associationMembershipContext - if not empty need to validate membership, value is context be logged on heuristref(main server)                                  
  * @returns {void}
  */
  verify_credentials(callback, requiredLevel, password_protected, password_entered, requiredPermission, associationMembershipContext) {
  
      if(associationMembershipContext && 'nonmember'==window.hWin.HAPI4.sysinfo['associationMembershipStatus']){
          
        let $dlg = window.hWin.HEURIST4.msg.showMsgDlgUrl(
                      `${window.hWin.HAPI4.baseURL}?disclaimer=association_membership.html #content`,
                       null, 'Heurist Network Association', 
                       {enable_buttons_after:2000, closeOnEscape:false, noClose:true,
                       open: (event, ui) => { $dlg.find('#noteAboutFunction').show(); $(event.target).css({height: '44em', padding: '0em 2em'}); },
                       container: 'dlg-association-teaser'});
                       
        //call logger
        let request = {
                    db:  window.hWin.HAPI4.sysinfo.database_prefix + window.hWin.HAPI4.database,
                    host: window.location.hostname,
                    email: this.hapi4.currentUser['ugr_eMail'],
                    log: 1,
                    ctx: associationMembershipContext  // context
                };  

                //window.hWin.HAPI4.baseURL + 
        window.hWin.HEURIST4.util.sendRequest('https://heuristref.net/heurist/admin/utilities/checkMembershipApi.php',
                    request, null, ()=>{}, 'auto');
        
        return;
      }
      

      let requiredMembership = 0; // Default required membership group ID

      if (typeof requiredLevel === 'string' && requiredLevel.indexOf(';') > 0) {

          requiredLevel = requiredLevel.split(';');
          requiredMembership = requiredLevel[1];
          requiredLevel = requiredLevel[0];
          if (requiredLevel < 0) requiredLevel = 0;
      }

      requiredLevel = Number(requiredLevel);
      if (requiredLevel < 0) { //no verification required - everyone access

          //however need to check password protection
          if (window.hWin.HEURIST4.util.isempty(password_protected)) {
              //no password protection
              callback(password_entered);
              return;
          } else {
              if (window.hWin.HAPI4.sysinfo['pwd_' + password_protected]) { //system administrator password defined allowing system admin override for specific actions otherwise requiring ownership

                  //
                  window.hWin.HEURIST4.msg.showPrompt(
                      '<div style="padding:20px 0px">'
                      + 'Only the System Administrator (server manager) can access the Manage Databases function.<br>'
                      + 'This action requires a special system administrator password (NOT a normal login password).<br>'
                      + 'If you receive this message elsewhere the function is only available to the OWNER of the database (user #2)<br>'
                      + '</div><span style="display: inline-block;padding: 10px 0px;">Enter system administrator password:&nbsp;</span>',
                      (password_entered)=>{

                          let on_passwordcheck = 
                          (response)=>{
                              if (response.status == window.hWin.ResponseStatus.OK && response.data == 'ok') {
                                  callback(password_entered);
                              } else {
                                  window.hWin.HEURIST4.msg.showMsgFlash('Wrong password');
                              }
                          };

                          window.hWin.HAPI4.SystemMgr.action_password(
                              { action: password_protected, password: password_entered },
                              on_passwordcheck);

                      },
                      { title: 'Sysadmin override password required', yes: 'OK', no: 'Cancel' }, { password: true });

              } else {
                  window.hWin.HEURIST4.msg.showMsgDlg('This action is not allowed unless a special system administrator password is set - please consult system administrator');
              }
              return;
          }
      }      


      /**
      * Utility method to verify user locally based on membership and permissions.
      *  
      * @param {boolean} is_expired 
      */
      function __verify(is_expired) {

          if ((requiredMembership == 0 || window.hWin.HAPI4.is_member(requiredMembership))
              &&
              window.hWin.HAPI4.has_access(requiredLevel)) {
              //verification is accepted now check for password protection
              window.hWin.HAPI4.SystemMgr.verify_credentials(callback, -1, password_protected, password_entered);
              return;
          }

          let response = {};
          response.sysmsg = 0;
          response.status = window.hWin.ResponseStatus.REQUEST_DENIED;
          response.message = 'To perform this operation you have to be logged in (you may have been logged out due to lack of activity - if so, please reload the page)';

          if (requiredMembership > 0) {
              let sGrpName = '';
              if (window.hWin.HAPI4.sysinfo.db_usergroups?.[requiredMembership]) {
                  sGrpName = ' "' + window.hWin.HAPI4.sysinfo.db_usergroups[requiredMembership] + '"';
              }
              response.message += ' as member of group #' + requiredMembership + sGrpName;

          } else if (requiredLevel == window.hWin.HAPI4.sysinfo.db_managers_groupid) {
              response.message += ' as database administrator';// of group "Database Managers"' 
          } else if (requiredLevel == 2) {
              response.message += ' as database onwer';
          } else if (requiredLevel > 0) {
              let sGrpName = '';
              if (window.hWin.HAPI4.sysinfo.db_usergroups?.[requiredLevel]) {
                  sGrpName = ' "' + window.hWin.HAPI4.sysinfo.db_usergroups[requiredLevel] + '"';
              }
              response.message += ' as administrator of group #' + requiredLevel + sGrpName;
          } else if (requiredLevel == 0 && is_expired) {
              response.message = '';
          }

          if (response.message) {
              window.hWin.HEURIST4.msg.showMsgFlash(response.message, 2000);
          } else {
              //login expired
              window.hWin.HEURIST4.msg.showMsgErr(response, true);
          }
      }


      /**
      * Handles the server response for credential verification.
      * 
      * Adjust user's credentials based on verification, then triggers
      * window.hWin.HAPI4.Event.ON_CREDENTIALS
      */
      function __response_handler(response) {
          if (response.status == window.hWin.ResponseStatus.OK) {
              // Logic to update user credentials and trigger events

              if (response.data.sysinfo) {
                  window.hWin.HAPI4.sysinfo = response.data.sysinfo;
              }

              let is_expired = false;
              if (response.data.currentUser) {

                  let old_id = window.hWin.HAPI4.user_id();

                  window.hWin.HAPI4.setCurrentUser(response.data.currentUser);

                  is_expired = (old_id > 0 && window.hWin.HAPI4.user_id() == 0);

                  //trigger global event ON_CREDENTIALS
                  if (response.data.currentUser.ugr_ID > 0) {
                      $(window.hWin.document).trigger(window.hWin.HAPI4.Event.ON_CREDENTIALS);
                  }
              }

              //since currentUser is up-to-date - use client side method
              __verify(is_expired);

          } else {
              window.hWin.HEURIST4.msg.showMsgErr(response, true);
          }
      }  
      
      const VERIFY_LOCALLY = false;

      if (VERIFY_LOCALLY) {
          __verify();
      } else {
          window.hWin.HAPI4.callserver(
              'usr_info',
              { a: 'verify_credentials', permissions: requiredPermission },
              __response_handler   //, callback, password_protected, password_entered, requiredLevel
          );
      }      

  }
  
  /**
   * Performs an action on a repository via the `repoController`.
   * @param {Object} request - The request object for the repository action.
   * @param {function(Object): void} callback - Callback to handle the server response.
   * @returns {void}
   */
  repositoryAction(request, callback) {
      window.hWin.HAPI4.callserver('repoController', request, callback);
  }

  /**
   * Performs a database action (e.g., create, delete, register) via the relevant controller.
   * @param {Object} request - The request object for the database action.
   * @param {string} request.action - The specific database action (e.g., 'register', 'create').
   * @param {function(Object): void} callback - Callback to handle the server response.
   * @returns {void}
   */
  databaseAction(request, callback) {
      let controller = 'databaseController';
      let timeout = 600000; //10 minutes
      
      if(request.action=='register'){
          controller = 'indexController';
      }else if(request.action=='restore' || request.action=='clone' || request.action=='rename'){
          timeout = 1800000; //30 minutes
      }
      
      window.hWin.HAPI4.callserver(controller, request, callback, timeout); //10 or 30 minutes
  }

  /**
   * Executes a reporting action via the `ReportController`.
   * @param {Object} request - The request object for the report action.
   * @param {function(Object): void} callback - Callback to handle the server response.
   * @returns {void}
   */
  reportAction(request, callback) {
      //let controller = 'ReportController';
      request.controller = 'ReportController';
      window.hWin.HAPI4.callserver('index', request, callback, 'auto');
  }
  

  /**
  * Retrieves system information counts, such as total records, dashboard status, and workset count.
  * Updates `window.hWin.HAPI4.sysinfo` with this data.
  *
  * @param {function(): void} [callback] - Optional callback executed after counts are retrieved and processed.
  * @returns {void}
  */
  sys_info_count(callback) {
      window.hWin.HAPI4.callserver('usr_info', { a: 'sys_info_count' }, response => {
          if (response.status == window.hWin.ResponseStatus.OK) {
              window.hWin.HAPI4.sysinfo['db_total_records'] = response.data[0];
              window.hWin.HAPI4.sysinfo['db_has_active_dashboard'] = response.data[1];
              window.hWin.HAPI4.sysinfo['db_workset_count'] = response.data[2];
              if (window.hWin.HEURIST4.util.isFunction(callback)) callback();
          } else {
              window.hWin.HEURIST4.msg.showMsgErr(response);
          }
      });
  }  

  /**
  * Retrieves current user information and global database settings.
  * This is typically used during HAPI initialization (`hapi.init`) or when a force refresh of system info is needed.
  * Updates `window.hWin.HAPI4.currentUser` and `window.hWin.HAPI4.sysinfo`.
  *
  * @param {function(boolean): void} [callback] - Optional callback that receives a boolean indicating success.
  * @returns {void}
  */
  sys_info(callback) {
      let request = { a: 'sysinfo' };

      if (typeof prepared_params!=="undefined" && prepared_params?.guest_data) {
          request['is_guest'] = 1;
      }

      this.hapi4.callserver('usr_info', request, response => {
          let success = response.status == window.hWin.ResponseStatus.OK;
          if (success) {
              if (response.data.currentUser) {
                  window.hWin.HAPI4.setCurrentUser(response.data.currentUser);
              }
              if (response.data.sysinfo) {
                  window.hWin.HAPI4.sysinfo = response.data.sysinfo;
              }
          } else if(window.hWin.HEURIST4.msg) {
              window.hWin.HEURIST4.msg.showMsgErr(response);
          }
          if (callback) callback(success);
      });
  }

  /**
  * Saves user preferences to the server.
  *
  * @param {Object} request - The request object containing preferences to save.
  *                           It should include key-value pairs of preferences.
  * @param {function(Object): void} [callback] - Optional callback to handle the server response.
  * @returns {void}
  */
  save_prefs(request, callback) {
      if (request) request.a = 'save_prefs';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }  
  
  /**
  * Sets or clears the user's working subset of records.
  *
  * @param {Object} request - The request object.
  *                           Typically contains parameters to define or clear the working subset.
  * @param {function(Object): void} [callback] - Optional callback to handle the server response.
  * @returns {void}
  */
  user_wss(request, callback) {
      if (request) request.a = 'user_wss';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Saves user profile information to the database.
  *
  * @param {Object} request - The request object containing user profile data to save.
  * @param {function(Object): void} [callback] - Optional callback to handle the server response.
  * @returns {void}
  */
  user_save(request, callback) {
      if (request) request.a = 'usr_save';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Retrieves user profile information from the database.
  *
  * @param {Object} request - The request object, often specifying the user ID if not the current user.
  * @param {function(Object): void} callback - Callback to handle the server response containing user profile data.
  * @returns {void}
  */
  user_get(request, callback) {
      if (request) request.a = 'usr_get';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Retrieves full names for a list of user or group IDs.
  * It first attempts to resolve names from a local cache (`getUserNameLocal`)
  * and then queries the server for any unresolved IDs.
  *
  * @param {Object} request - The request object.
  * @param {string|Array<number|string>} request.UGrpID - A comma-separated string or an array of user/group IDs.
  * @param {*} [request.context] - Optional context to be passed back in the callback.
  * @param {function(Object): void} callback - Callback function. The `data` property of the response
  *        will contain an object mapping IDs to names: `{ id1: "Name1", id2: "Name2", ... }`.
  * @returns {void}
  */
  usr_names(request, callback) {
      let ugrp_ids_input = request.UGrpID;
      let resolvedNames = {}; // Stores names resolved locally or from server
      let idsToFetch = [];    // Stores IDs that need to be fetched from server

      // Normalize ugrp_ids_input to an array
      if (typeof ugrp_ids_input === 'number' || (typeof ugrp_ids_input === 'string' && !ugrp_ids_input.includes(','))) {
          ugrp_ids_input = [Number(ugrp_ids_input)];
      } else if (typeof ugrp_ids_input === 'string') {
          ugrp_ids_input = ugrp_ids_input.split(',').map(id => Number(id.trim()));
      } else if (!Array.isArray(ugrp_ids_input)) {
          ugrp_ids_input = []; // Fallback for invalid input
      }

      for (const id of ugrp_ids_input) {
          const numId = Number(id);
          if (isNaN(numId)) continue; // Skip invalid IDs

          let localName = this.getUserNameLocal(numId);
          if (localName) {
              resolvedNames[numId] = localName;
          } else {
              idsToFetch.push(numId);
          }
      }

      if (idsToFetch.length === 0) {
          // All names resolved locally
          if (window.hWin.HEURIST4.util.isFunction(callback)) {
              callback.call(this, { status: window.hWin.ResponseStatus.OK, data: resolvedNames, context: request.context });
          }
      } else {
          // Fetch remaining names from server
          let serverRequest = { ...request, a: 'usr_names', UGrpID: idsToFetch };
          window.hWin.HAPI4.callserver('usr_info', serverRequest, serverResponse => {
              if (serverResponse.status == window.hWin.ResponseStatus.OK && serverResponse.data) {
                  resolvedNames = { ...resolvedNames, ...serverResponse.data }; // Merge server results
                  if (window.hWin.HEURIST4.util.isFunction(callback)) {
                      callback.call(this, { status: window.hWin.ResponseStatus.OK, data: resolvedNames, context: serverResponse.context });
                  }
              } else {
                  // Handle error or partial success if needed
                  if (window.hWin.HEURIST4.util.isFunction(callback)) {
                     callback.call(this, { status: serverResponse.status, data: resolvedNames, message: serverResponse.message, context: serverResponse.context });
                  }
              }
          });
      }
  }

  /**
  * Retrieves a user or group name from the local cache (HAPI4.currentUser or HAPI4.sysinfo.db_usergroups).
  *
  * @param {number|string} ugrp_id - The user or group ID.
  * @returns {string|null} The name if found in local cache, otherwise null.
  */
  getUserNameLocal(ugrp_id) {
      let usr_ID = Number(ugrp_id);
      let sUserName = null;

      if (usr_ID === 0) { // ID 0 typically means "Everyone" or public
          sUserName = window.hWin.HR('Everyone'); // Assuming HR handles localization
      } else if (window.hWin.HAPI4.currentUser && usr_ID === window.hWin.HAPI4.currentUser['ugr_ID']) {
          sUserName = window.hWin.HAPI4.currentUser['ugr_FullName'];
      } else if (window.hWin.HAPI4.sysinfo && window.hWin.HAPI4.sysinfo.db_usergroups && window.hWin.HAPI4.sysinfo.db_usergroups[usr_ID]) {
          sUserName = window.hWin.HAPI4.sysinfo.db_usergroups[usr_ID];
      }

      return sUserName;
  }

  /**
  * Retrieves a detailed description of the groups the current user is a member of.
  *
  * @param {function(Object): void} callback - Callback to handle the server response.
  *        The response `data` typically contains an array or object describing the groups.
  * @returns {void}
  */
  mygroups(callback) {
      window.hWin.HAPI4.callserver('usr_info', { a: 'groups' }, callback);
  }

    /**
     * Logs user activity. This can be to Matomo (if configured) or to the Heurist server-side log.
     *
     * @param {string} activity - A string describing the activity, often underscore-separated (e.g., "db_create", "rec_edit").
     *                            Certain prefixes (like 'db', 'st', 'rec') or full action names (like 'VisitPage')
     *                            determine how the activity is categorized for Matomo.
     * @param {string|number} [supplementary_info] - Additional information related to the activity.
     *                                             For 'VisitPage', this might be the page identifier/URL part.
     *                                             For other actions, it could be a record ID or other relevant value.
     *                                             If numeric, it might be tracked as a value in Matomo.
     * @returns {void}
     */
    user_log(activity, supplementary_info) {

        const log_actions = ['VisitPage', 'editRec']; // Specific actions with special handling
        const log_prefix = ['db', 'st', 'prof', 'cms', 'imp', 'sync', 'exp', 'configure', 'rec', 'hlp', 'search']; // Prefixes for categorization
        const action_parts = activity.indexOf('_') > 0 ? activity.split('_') : [];

        if (
            log_actions.includes(activity) ||
            (action_parts.length > 0 && log_prefix.includes(action_parts[0].toLowerCase()))
        ) {
            let category = ''
            if (action_parts.length > 0) {
                category = action_parts[0];
                activity = '';
                for (let i = 1; i < action_parts.length; i++) {
                    action_parts[i] = action_parts[i].charAt(0).toUpperCase() + action_parts[i].slice(1);
                    activity = activity + action_parts[i];
                }
            }
            
            if(window._paq){
            
                //matomo
                if(activity=='VisitPage'){
                    
                    this.matomoTrackNewPage('web', supplementary_info);
                    
                }else if(activity!='editRec'){
                    if(category=='db'){
                        category='Database';
                    }else if(category=='st'){
                        category='Structure';
                    }else if(category=='prof'){
                        category='Profile';
                    }else if(category=='imp'){
                        category='Import';
                    }else if(category=='exp'){
                        category='Export';
                    }else if(category=='rec'){
                        category='Record';
                    }else if(category=='hlp'){
                        category='Help';
                    }
                    
                    category = category.charAt(0).toUpperCase() + category.slice(1);
                    
                    let value;
                    if(window.hWin.HEURIST4.util.isPositiveInt(supplementary_info)){
                        value = supplementary_info;
                    }
                    if(!activity){
                        activity = 'TBD';
                    }
                    
                    this.matomoTrackEventAction(category, activity, undefined, value);
                }
            
            }else{

                if(supplementary_info && activity === 'VisitPage'){
                    let [website, page] = supplementary_info.toString().split('/');
                    page = window.hWin.HEURIST4.util.isempty(page) ? website : page;
                    supplementary_info = {website: website, page: page};
                }

                const sessionID = Math.floor(Math.random() * 90000);
                this.prepareParameters('log', supplementary_info, 0, sessionID);

                let request = { a: 'usr_log', activity: (category+activity), session: sessionID, user: this.hapi4.user_id() };
                this.hapi4.callserver('usr_info', request);
            }
        }
    }

  /**
  * Initializes Matomo tracking with custom dimensions for the current user and page context.
  * This should be called once when the main Heurist interface loads or when context significantly changes.
  *
  * @param {('web'|'tpl'|'hml'|'view'|'edit'|'adm'|'startup')} pageType - The type of page or context being viewed.
  *        - `web`: Public website page.
  *        - `tpl`: Template view.
  *        - `hml`: Heurist Markup Language page.
  *        - `view`: Record view page.
  *        - `edit`: Record edit page.
  *        - `adm`: Administration interface page.
  *        - `startup`: Initial startup/login page.
  * @param {string} [value] - Supplementary value, typically used as a website ID if `pageType` is 'web'.
  * @returns {void}
  */
  matomoTrackInit(pageType, value){

      if(!window._paq || !this.hapi4){ // Ensure Matomo and HAPI are available
          return;
      }

      // Set custom dimensions for the current page view
      window._paq.push(['setCustomDimension', 1, this.hapi4.database ]);  // Dimension 1: Database Name
      window._paq.push(['setCustomDimension', 2, pageType ]);             // Dimension 2: Page Type
      window._paq.push(['setCustomDimension', 3, this.hapi4.getLocale() ]); // Dimension 3: Current Locale/Language
      window._paq.push(['setCustomDimension', 4, (pageType === 'web' && value) ? value : '' ]); // Dimension 4: Website ID (if applicable)

      // Set custom dimensions for the current visit
      const usrType = this.hapi4.getUserType(); // Retrieves user type (e.g., owner, admin, guest)
      if(usrType === 'visitor' || !this.hapi4.currentUser){ // If user is a visitor or not logged in
        window._paq.push(['resetUserId']); // Reset Matomo User ID
      } else {
        window._paq.push(['setUserId', this.hapi4.currentUser['ugr_eMail'] ]); // Set Matomo User ID to user's email
      }
      window._paq.push(['setCustomDimension', 5, usrType ]); // Dimension 5: User Type

      // Example: Configure tracking for downloads or outlinks (if needed)
      // _paq.push(['setDownloadClasses', "file-download"]);
      // _paq.push(['trackLink', 'https://example.com/file.pdf', 'download']);

      // Perform initial page view tracking for this context
      this.matomoTrackNewPage(pageType, value);
  }
  
  /**
  * Tracks a new page view in Matomo.
  * Use this when navigating to a new logical "page" within the application,
  * especially in Single Page Applications (SPAs).
  *
  * @param {('web'|'tpl'|'hml'|'view'|'edit'|'adm'|'startup')} pageType - The type of page being viewed.
  * @param {string} [value] - Supplementary information, often an ID or sub-identifier for the page (e.g., record ID for 'view', website ID for 'web').
  * @param {string} [title] - Optional custom title for the page view. If not provided, Matomo might use the document's current title.
  * @returns {void}
  */
  matomoTrackNewPage(pageType, value, title){
      
        if(!window._paq || !this.hapi4){
            return;
        }
        
        let pageURL;
        // Construct a logical URL for Matomo based on pageType and value
        if(pageType === 'adm'){ // Admin interface
            pageURL = '/?db=' + this.hapi4.database;
        } else if(pageType === 'startup'){ // Startup/login page
            pageURL = '/startup';
        } else { // Other page types, construct URL like /databaseName/pageType/value
            pageURL = '/' + this.hapi4.database + '/' + pageType;
            if(value){
                pageURL = pageURL + '/' + value;
            }
        }
      
        window._paq.push(['setCustomUrl', pageURL ]); // Set the custom URL for this page view
        if(title){
            window._paq.push(['setDocumentTitle', title]); // Set a custom document title if provided
        }
        window._paq.push(['trackPageView']); // Track the page view now
        // _paq.push(['enableLinkTracking']); // Optionally enable link tracking if not already globally enabled
  }

  /**
  * Tracks a custom event in Matomo.
  *
  * @param {string} category - The category of the event (e.g., 'Database', 'Record', 'UI Interaction').
  * @param {string} action - The specific action performed (e.g., 'Create', 'Delete', 'OpenTab').
  * @param {string} [name] - Optional name for the event (e.g., a label or identifier for the element interacted with).
  * @param {number} [value] - Optional numeric value associated with the event.
  * @returns {void}
  */
  matomoTrackEventAction(category, action, name, value){
        if(!window._paq){
            return;
        }

        let eventParams = ['trackEvent', category, action];
        if(name !== undefined) {
            eventParams.push(name);
            if(value !== undefined && typeof value === 'number' && !isNaN(value)){ // Ensure value is numeric if provided
                eventParams.push(value);
            }
        }
        window._paq.push(eventParams);      
  }

  /**
  * Performs Matomo tracking adjustments specifically for user logout.
  * This includes resetting the User ID and forcing a new visit for subsequent actions.
  * @returns {void}
  */
  matomoTrackLogout(){
      if(!window._paq){
          return;
      }

      window._paq.push(['resetUserId']); // Reset Matomo User ID as the user is now anonymous or different
      window._paq.push(['appendToTrackingUrl', 'new_visit=1']); // Force Matomo to start a new visit for page views after logout
      window._paq.push(['trackPageView']); // Track a page view to associate with the new (or anonymous) visit state
      window._paq.push(['appendToTrackingUrl', '']); // Clear the new_visit parameter for subsequent tracking calls
  }

  /**
   * Performs Matomo tracking adjustments specifically for user login.
   * Sets the User ID and relevant custom dimensions.
   * @returns {void}
   */
  matomoTrackLogin(){
        if(!window._paq || !this.hapi4 || !this.hapi4.currentUser){ // Ensure Matomo, HAPI, and currentUser are available
            return;
        }

      const usrType = this.hapi4.getUserType();
      if(usrType === 'visitor' || !this.hapi4.currentUser['ugr_eMail']){ // Should not happen if logged in, but good check
        window._paq.push(['resetUserId']);
      } else {
        window._paq.push(['setUserId', this.hapi4.currentUser['ugr_eMail'] ]); // Set User ID
      }
      window._paq.push(['setCustomDimension', 5, usrType ]); // Update User Type dimension

      window._paq.push(['trackPageView']); // Track a page view to associate with the logged-in user state
  }
  
  
  /**
  * Verifies special system passwords for password-protected actions.
  *
  * @param {Object} request - The request object.
  * @param {string} request.action - The name/key of the password-protected action.
  * @param {string} request.password - The password entered by the user.
  * @param {function(Object): void} callback - Callback to handle the server response.
  *        Response `data` is typically 'ok' on success.
  * @returns {void}
  */
  action_password(request, callback) {
      if (request) request.a = 'action_password';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Retrieves saved searches for the current user and their groups, or by specific IDs.
  *
  * @param {Object} [request={}] - The request object.
  * @param {number} [request.UGrpID] - Optional ID of a user/group to filter searches.
  *                                   If not provided, searches for the current user and their groups are typically returned.
  * @param {function(Object): void} callback - Callback to handle the server response.
  *        Response `data` contains the saved search definitions.
  * @returns {void}
  */
  ssearch_get(request, callback) {
      if (!request) request = {};
      request.a = 'svs_get';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Saves a Heurist query (saved search) to the database.
  *
  * @param {Object} request - The request object.
  * @param {number} [request.svs_ID] - ID of the saved search if updating an existing one. Not specified for new searches.
  * @param {string} request.svs_Name - The name for the saved search.
  * @param {string} request.svs_Query - The Heurist query string.
  * @param {number} request.svs_UGrpID - The user or group ID under which this search should be saved.
  * @param {function(Object): void} callback - Callback to handle the server response.
  * @returns {void}
  */
  ssearch_save(request, callback) {
      if (request) request.a = 'svs_save';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Duplicates an existing saved search.
  *
  * @param {Object} request - The request object.
  * @param {number} request.svs_ID - The ID of the saved search to duplicate.
  * @param {function(Object): void} callback - Callback to handle the server response.
  * @returns {void}
  */
  ssearch_copy(request, callback) {
      if (request) request.a = 'svs_copy';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Deletes one or more saved searches by their IDs.
  *
  * @param {Object} request - The request object.
  * @param {string} request.ids - A comma-separated string of saved search IDs to delete.
  * @param {function(Object): void} [callback] - Optional callback to handle server response.
  * @returns {void}
  */
  ssearch_delete(request, callback) {
      if (request) request.a = 'svs_delete';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Saves a nested hierarchy (tree structure) of saved searches.
  *
  * @param {Object} request - The request object.
  * @param {Object} request.data - A JSON representation of the search tree structure.
  * @param {function(Object): void} [callback] - Optional callback to handle server response.
  * @returns {void}
  */
  ssearch_savetree(request, callback) {
      if (request) request.a = 'svs_savetree';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Retrieves a nested hierarchy (tree structure) of saved searches.
  *
  * @param {Object} [request={}] - The request object.
  * @param {number} [request.UGrpID] - Optional: User/group ID whose search tree is to be retrieved.
  *                                   Defaults to the current user if not specified.
  * @param {function(Object): void} callback - Callback to handle the server response.
  *        Response `data` contains the search tree structure.
  * @returns {void}
  */
  ssearch_gettree(request, callback) {
      if (!request) request = {};
      request.a = 'svs_gettree';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }  

  /**
  * Retrieves database structure definitions (record types, detail types, terms).
  *
  * @param {Object} request - The request object specifying which definitions to retrieve.
  * @param {string} [request.terms] - Comma-separated list of term IDs, or 'all'.
  * @param {string} [request.rectypes] - Comma-separated list of record type IDs, or 'all'.
  * @param {string} [request.detailtypes] - Comma-separated list of detail type IDs, or 'all'.
  * @param {0|1|2|3} [request.mode] - Mode for retrieving record types:
  *   - `0`: Only names (default).
  *   - `1`: Only structure.
  *   - `2`: Both names and structure.
  *   - `3`: All details.
  * @param {function(Object): void} callback - Callback to handle server response containing definitions.
  * @returns {void}
  */
  get_defs(request, callback) {
      window.hWin.HAPI4.callserver('sys_structure', request, callback);
  }

  /**
  * Refreshes all entity data and database structure definitions from the server.
  * This is a wrapper for `HAPI.EntityMgr.refreshEntityData('force_all', ...)`.
  *
  * @param {boolean} [is_message=false] - Whether to show a success message to the user after refresh.
  * @param {Document} [document_context] - Unused parameter (kept for legacy compatibility if any).
  * @param {function(boolean): void} [callback] - Optional callback that receives a boolean indicating success of the refresh.
  * @returns {void}
  */
  get_defs_all(is_message, document_context, callback) {
      window.hWin.HEURIST4.msg.bringCoverallToFront(); // Show loading overlay

      window.hWin.HAPI4.EntityMgr.refreshEntityData('force_all', success => {
          window.hWin.HEURIST4.msg.sendCoverallToBack(); // Hide loading overlay

          if (success && is_message === true) {
              let $dlg = window.hWin.HEURIST4.msg.showMsgDlg('Database structure definitions refreshed.');
              if ($dlg && $dlg.parent('.ui-dialog').length) { // Ensure dialog exists before trying to position
                $dlg.parent('.ui-dialog').css({ top: 150, left: 150 });
              }
          }

          if (window.hWin.HEURIST4.util.isFunction(callback)) callback(success);
      });
  }

  /**
  * Resolves the MIME type for a given URL by querying the server.
  *
  * @param {string} url - The URL to check.
  * @param {function(Object): void} callback - Callback to handle the server response.
  *        Response `data` typically contains the MIME type string.
  * @returns {void}
  */
  get_url_content_type(url, callback) {
      let request = { a: 'get_url_content_type', url: url };
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Retrieves a list of files from specified server-side folders, filtered by extensions.
  *
  * @param {string|Array<string>} source - A single folder path or an array of folder paths to search on the server.
  * @param {string|Array<string>} exts - A single file extension or an array of extensions to filter by (e.g., "jpg", "pdf").
  * @param {function(Object): void} callback - Callback to handle the server response.
  *        Response `data` contains the list of found files.
  * @returns {void}
  */
  get_foldercontent(source, exts, callback) {
      let request = { a: 'foldercontent', source: source, exts: exts };
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }  

  /**
  * Checks if the current server and database context has access to ESTC (English Short Title Catalogue) lookups.
  *
  * @param {Object} [request] - Optional request parameters. If not provided, checks for the current database.
  * @param {string} [request.db] - Database name to check (if not current).
  * @param {function(Object): void} callback - Callback to handle the server response.
  *        Response `data` indicates if access is allowed.
  * @returns {void}
  */
  check_allow_estc(request, callback) {
      if (!request) {
          request = { a: 'check_allow_estc', db: window.hWin.HAPI4.database };
      } else if (!request.a) {
          request.a = 'check_allow_estc';
      }
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Checks if the current server has an alpha build, or specified version, setup/configured.
  *
  * @param {Object} [request={a: 'check_for_version'}] - Request object.
  * @param {function(Object): void} callback - Callback to handle server response.
  *        Response `data` indicates if an alpha build is set up.
  * @returns {void}
  */
  check_for_version(request, callback) {
      if (!request) {
          request = { a: 'check_for_version' };
      } else if (!request.a) {
          request.a = 'check_for_version';
      }
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Retrieves user notifications, if any.
  *
  * @param {Object} [request={a: 'get_user_notifications'}] - Request object.
  * @param {function(Object): void} callback - Callback to handle server response.
  *        Response `data` contains user notifications.
  * @returns {void}
  */
  get_user_notifications(request, callback){
      if(!request){
          request = { a: 'get_user_notifications' };
      } else if (!request.a) {
          request.a = 'get_user_notifications';
      }
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Retrieves custom formats for the TinyMCE rich text editor, configured for the Heurist instance.
  *
  * @param {Object} [request={a: 'get_tinymce_formats'}] - Request object.
  * @param {function(Object): void} callback - Callback to handle server response.
  *        Response `data` contains the TinyMCE formats configuration.
  * @returns {void}
  */
  get_tinymce_formats(request, callback){
      if(!request) request = {a: 'get_tinymce_formats'};
      else if (!request.a) request.a = 'get_tinymce_formats';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Uses DeepL (if configured) to translate a given string.
  *
  * @param {Object} request - Request object.
  * @param {string} request.text - The text to translate.
  * @param {string} request.target_lang - The target language code (e.g., 'EN', 'FR').
  * @param {string} [request.source_lang] - Optional source language code.
  * @param {function(Object): void} callback - Callback to handle server response.
  *        Response `data` contains the translated text.
  * @returns {void}
  */
  translate_string(request, callback){
      if(!request) { // Basic validation, ensure request object exists
          if(typeof callback === 'function') callback({status: window.hWin.ResponseStatus.INVALID_REQUEST, message: "Request object is missing."});
          return;
      }
      request.a = 'translate_string'; // Ensure action is set
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Checks if a list of provided databases are available on the current Heurist server.
  *
  * @param {Object} data - An object where keys are registered database IDs and values are database names.
  *                        Example: `{ "regID1": "dbName1", "regID2": "dbName2" }`
  * @param {function(Object): void} callback - Callback to handle the server response.
  *        Response `data` indicates availability for each database.
  * @returns {boolean} Returns `false` and shows an error if `data` is not provided. Otherwise, initiates server call.
  */
  check_for_databases(data, callback){

      if(!data || typeof data !== 'object' || Object.keys(data).length === 0){
          window.hWin.HEURIST4.msg.showMsgErr({
              message: 'The list of databases to be checked is missing or invalid.<br>'
              +'Please contact the Heurist team.',
              error_title: 'Missing or Invalid Database List',
              status: window.hWin.ResponseStatus.INVALID_REQUEST
          });
          return false;
      }

      let request = {
          a: 'check_for_databases', 
          data: JSON.stringify(data), 
          db: window.hWin.HAPI4.database // Current database context
      };

      window.hWin.HAPI4.callserver('usr_info', request, callback);
      return true; // Indicates request was made
  }

  /**
  * Calculates and returns the difference (in days, months, years) between two dates.
  *
  * @param {Object} data - Object containing the dates.
  * @param {string} data.early_date - The earlier date string (parsable by server).
  * @param {string} data.latest_date - The later date string (parsable by server).
  * @param {function(Object): void} callback - Callback to handle the server response.
  *        Response `data` contains the calculated time differences.
  * @returns {boolean} Returns `false` and shows error if dates are missing/invalid. Otherwise, initiates server call.
  */
  get_time_diffs(data, callback){

      if(!data || !data.early_date || !data.latest_date){
          window.hWin.HEURIST4.msg.showMsgErr({
              message: 'Both an earliest and latest date are required.',
              error_title: 'Missing Dates',
              status: window.hWin.ResponseStatus.INVALID_REQUEST
          });
          return false;
      }

      let request = {
          a: 'get_time_diffs',
          data: JSON.stringify(data), // Dates are passed as a JSON string
          db: window.hWin.HAPI4.database // Current database context
      };

      window.hWin.HAPI4.callserver('usr_info', request, callback);
      return true; // Indicates request was made
  }

  /**
  * Lists, renames, or deletes folders within the HEURIST_FILESTORE_DIR on the server.
  *
  * @param {Object} [request={}] - Request object.
  * @param {('list'|'rename'|'delete')} [request.operation='list'] - The operation to perform.
  * @param {string} [request.root_dir] - The directory to operate on, relative to HEURIST_FILESTORE_DIR.
  *                                      If not provided, defaults to HEURIST_FILESTORE_DIR itself.
  * @param {string} [request.old_name] - For 'rename', the current name of the folder.
  * @param {string} [request.new_name] - For 'rename', the new name for the folder.
  * @param {string} [request.folder_name] - For 'delete', the name of the folder to delete.
  * @param {function(Object): void} callback - Callback to handle server response.
  * @returns {void}
  */
  get_sysfolders(request, callback) {
      if (!request) request = {};
      if (!request.a) request.a = 'folders'; // Server-side action name
      if (!request.operation) request.operation = 'list'; // Default operation
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
   * Uploads a file to a Nakala repository (if configured).
   *
   * @param {Object} request - The request object, containing file details and Nakala target information.
   * @param {function(Object): void} callback - Callback to handle the server response.
   *        The response indicates success or failure of the upload.
   * @returns {void}
   */
  upload_to_nakala(request, callback){
      if (!request) {
          if(typeof callback === 'function') callback({status: window.hWin.ResponseStatus.INVALID_REQUEST, message: "Request object is missing."});
          return;
      }
      if (!request.a) request.a = 'upload_file_nakala';
      window.hWin.HAPI4.callserver('usr_info', request, callback);
  }

  /**
  * Checks for the presence of specified record type IDs (concept codes) in the current database.
  * If any are missing, it attempts to import them from a source database (defaulting to Heurist_Core_Definitions, ID #2).
  * Optionally shows a confirmation dialog before importing.
  *
  * @param {Array<string|number>} rty_IDs - An array of record type IDs (concept codes) to check.
  * @param {number} [databaseID=2] - The registration ID of the source database from which to import definitions if missing.
  *                                  Defaults to 2 (Heurist_Core_Definitions).
  * @param {string|false} [message] - An additional message to display in the confirmation dialog before import.
  *                                   If `false`, definitions are imported unconditionally without a dialog.
  *                                   If a string, it's shown to the user. If undefined/null, a default prompt may appear.
  * @param {function(): void} [callback] - A callback function executed after the check/import process is complete (regardless of user choice in dialog).
  * @param {boolean} [force_refresh=false] - If true, treats all provided `rty_IDs` as if they are missing,
  *                                         forcing an attempt to (re)import them or assign new concept codes.
  * @returns {boolean|0}
  *  - `true`: All specified record types are already present in the database.
  *  - `0`: At least one record type was initially missing. The import process was initiated (either directly or after user confirmation).
  *         The callback will be invoked after the import attempt.
  *  - `false`: The `rty_IDs` parameter was missing or invalid.
  */
  checkPresenceOfRectype(rty_IDs, databaseID, message, callback, force_refresh) {

      if (!rty_IDs || !Array.isArray(rty_IDs) && rty_IDs.length === 0) { // Ensure rty_IDs is a non-empty array
          if (window.hWin.HEURIST4.util.isFunction(callback)) {
              callback.call(this); // Call callback even if input is invalid, to maintain flow
          }
          return false;
      }

      // Normalize to array if single ID is passed (though JSDoc says Array)
      if (!Array.isArray(rty_IDs)) {
          rty_IDs = [String(rty_IDs)];
      }

      let missed_rty_IDs = [];
      if (force_refresh) {
          missed_rty_IDs = [...rty_IDs]; // Copy all IDs if forcing refresh
      } else {
          rty_IDs.forEach(rty_ID => {
              // Assuming $Db.getLocalID is globally available and correctly resolves concept codes to local IDs
              let local_id = typeof $Db !== 'undefined' ? $Db.getLocalID('rty', String(rty_ID)) : null;
              if (!(local_id > 0)) { // If not found or local_id is not positive
                  missed_rty_IDs.push(String(rty_ID));
              }
          });
      }

      if (missed_rty_IDs.length === 0) { // All record types are present
          if (window.hWin.HEURIST4.util.isFunction(callback)) {
              callback.call(this);
          }
          return true;
      }

      // Default source database ID (Heurist_Core_Definitions)
      const sourceDB_ID = (typeof databaseID === 'number' && databaseID > 0) ? databaseID : 2;

      if (message === false) { // Import unconditionally without dialog
          window.hWin.HAPI4.SystemMgr.import_definitions(sourceDB_ID, missed_rty_IDs, 'rectype', false, true, callback);
          return 0; // Indicates missing types and import initiated
      }

      // Prepare message for dialog
      const dialogMessage = (typeof message === 'string' ? message : window.hWin.HR('Some required record type definitions are missing.'))
          + '<br>'
          + window.hWin.HR('Click "Import" to get these definitions from the source database.');

      window.hWin.HEURIST4.msg.showMsgDlg(dialogMessage,
          { // Dialog buttons
              'Import': function () {
                  const $currentDialog = window.hWin.HEURIST4.msg.getMsgDlg();
                  if ($currentDialog) $currentDialog.dialog('close');

                  window.hWin.HEURIST4.msg.bringCoverallToFront();
                  window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Importing definitions...'), 10000);

                  window.hWin.HAPI4.SystemMgr.import_definitions(sourceDB_ID, missed_rty_IDs, 'rectype', false, true,
                      function (response) { // Callback for import_definitions
                          window.hWin.HEURIST4.msg.sendCoverallToBack();
                          const $flashDialog = window.hWin.HEURIST4.msg.getMsgFlashDlg();
                          if ($flashDialog && $flashDialog.dialog('instance')) $flashDialog.dialog('close');

                          if (response.status !== window.hWin.ResponseStatus.OK) {
                              window.hWin.HEURIST4.msg.showMsgErr(response);
                          }
                          if (window.hWin.HEURIST4.util.isFunction(callback)) callback.call(this); // Call original callback
                      });
              },
              'Skip': function () {
                  const $currentDialog = window.hWin.HEURIST4.msg.getMsgDlg();
                  if ($currentDialog) $currentDialog.dialog('close');
                  if (window.hWin.HEURIST4.util.isFunction(callback)) callback.call(this);
              },
              'Cancel': function () {
                  const $currentDialog = window.hWin.HEURIST4.msg.getMsgDlg();
                  if ($currentDialog) $currentDialog.dialog('close');
                  // Optionally, indicate cancellation to callback or handle differently
                  if (window.hWin.HEURIST4.util.isFunction(callback)) callback.call(this, true);
              }
          },
          window.hWin.HR('Required Definitions Missing'));

      return 0; // Indicates missing types and dialog shown / import process potentially initiated
  }


  /** 
  * Imports database definitions (record types, detail types, terms) from a source database.
  * After successful import, it refreshes local definitions and triggers `ON_STRUCTURE_CHANGE` event.
  *
  * @param {number} source_databaseID - The registration ID of the source database from which to import.
  * @param {Array<string|number>} definition_ids - An array of definition IDs (e.g., record type IDs, concept codes, term IDs) to import.
  * @param {('rectype'|'detailtype'|'term')} entity_type - The type of entity being imported.
  * @param {boolean} [is_rename_target=false] - If true, existing local definitions with the same ID will have their labels/names
  *                                             overwritten by those from the source database.
  * @param {boolean} [is_conservative=true] - If true (conservative mode), the import process might be more cautious about overwriting
  *                                           or may have specific behavior for handling conflicts (server-side logic).
  * @param {function(Object): void} [callback] - Optional callback to handle the server response after the import attempt.
  * @returns {void}
  */
  import_definitions(source_databaseID, definition_ids, entity_type, is_rename_target, is_conservative, callback) {
      
      let request = {
          a: 'import', // Server-side action might be different, this seems like a sub-parameter for sys_structure
          databaseID: source_databaseID,
          definitionID: definition_ids, // Server expects 'definitionID' for the list of IDs
          conservative: is_conservative ? 1 : 0,
          is_rename_target: is_rename_target ? 1 : 0,
          db: window.hWin.HAPI4.database, // Current database context
          import: entity_type // Specifies what kind of entity to import (e.g., 'rectype')
      };

      window.hWin.HAPI4.callserver('sys_structure', request, function (response) {
          if (response.status == window.hWin.ResponseStatus.OK) {
              // Refresh local definitions if import was successful and new definitions were returned
              if (response.defs) {
                  if (response.defs.sysinfo) {
                      window.hWin.HAPI4.sysinfo = { ...window.hWin.HAPI4.sysinfo, ...response.defs.sysinfo }; // Merge sysinfo
                  }

                  if (response.defs.entities) {
                      for (let entityName in response.defs.entities) {
                          // Assuming setEntityData correctly updates or replaces the specified entity's data
                          window.hWin.HAPI4.EntityMgr.setEntityData(entityName, response.defs.entities[entityName]);
                      }
                  }
              }
              // Trigger event to notify other parts of the application about structure change
              window.hWin.HAPI4.triggerEvent(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
          }

          if (window.hWin.HEURIST4.util.isFunction(callback)) {
              callback(response);
          }
      });
  }

  /** 
  * Performs client-side software version checks and database version checks.
  * 1. Compares cached client software version with server-provided version to detect outdated cache.
  *    If outdated, shows a message dialog forcing a cache clear (hard reload).
  * 2. Compares required database version (by client software) with the actual database version.
  *    If database is outdated, shows a message dialog prompting for database upgrade.
  * This is typically run in non-publish (development/staging) modes.
  *
  * @returns {boolean} `true` if a version mismatch forces an exit/reload (e.g. outdated client cache), `false` otherwise.
  * @todo Consider moving this to a system utility class or making it part of an initialization sequence.
  *       The `production=true` check should be more robust, perhaps via a global config.
  */
  versionCheck() {

      // Only run version checks if not in publish/production mode
      if (!window.hWin.HAPI4.isAdminInterface) {
          return false;
      }

      let version_in_cache = window.hWin.HAPI4.get_prefs_def('version_in_cache', null);
      let current_server_version = window.hWin.HAPI4.sysinfo ? window.hWin.HAPI4.sysinfo['version'] : null;
      let needs_client_reload = false;

      // 1. Check client software version (cache vs server)
      if (window.hWin.HAPI4.has_access() && current_server_version) {
          if (version_in_cache) {
              // versionCompare returns: -1 if v1 < v2, 0 if v1 == v2, 1 if v1 > v2.
              // -2 if format is different or error.
              // We are checking if cache (v1) is older than server (v2).
              if (window.hWin.HEURIST4.util.versionCompare(version_in_cache, current_server_version) < 0) {
                  needs_client_reload = true;
                  window.hWin.HEURIST4.msg.showMsgDlgUrl(
                      window.hWin.HAPI4.baseURL + 'hclient/widgets/cpanel/versionCheckMsg.html',
                      {} /* no buttons, effectively locks UI */,
                      'Client Version Mismatch', // Title for clarity
                      {
                          hideTitle: false, // Show title
                          closeOnEscape: false,
                          open: function (event, ui) {
                              let $dlg = window.hWin.HEURIST4.msg.getMsgDlg('dlg-heurist-updated');
                              $dlg.find('#version_cache').text(version_in_cache);
                              $dlg.find('#version_srv').text(current_server_version);
                          },
                          container: 'dlg-heurist-updated'
                      }
                  );
              }
          }
          // Update cached version if it's different from server's version
          if (version_in_cache !== current_server_version) {
              window.hWin.HAPI4.save_pref('version_in_cache', current_server_version);
          }
          if (needs_client_reload) return true; // Exit if client reload is forced

          // 2. Check Database version (required by software vs actual DB version)
          let required_db_version = window.hWin.HAPI4.sysinfo.db_version_req;
          let current_db_version = window.hWin.HAPI4.sysinfo.db_version;
          // Check if current DB version is older than required (-2 if current_db_version < required_db_version in this util's specific logic)
          if (window.hWin.HEURIST4.util.versionCompare(required_db_version, current_db_version) === -2) {
              window.hWin.HEURIST4.msg.showMsgDlgUrl(
                  window.hWin.HAPI4.baseURL + 'hclient/widgets/cpanel/versionDbCheckMsg.html',
                  {
                      'Upgrade Database': function () { // Clearer button text
                          top.location.href = (window.hWin.HAPI4.baseURL + 'admin/setup/dbupgrade/upgradeDatabase.php?db=' + window.hWin.HAPI4.database);
                      }
                  },
                  'Database Upgrade Required', // Title
                  {
                      hideTitle: false,
                      closeOnEscape: false,
                      open: function (event, ui) {
                          let $dlg = window.hWin.HEURIST4.msg.getMsgDlg();
                          $dlg.find('#version_db').text(current_db_version);
                          $dlg.find('#version_min_db').text(required_db_version);
                          $dlg.find('#version_srv').text(current_server_version); // Show current software version for context
                      }
                  }
              );
              return true; // Indicate that a DB upgrade prompt was shown, potentially blocking further action.
          }
      }
      return false; // No version issues requiring immediate action were found.
  }

  /**
  * Asynchronously loads HTML content from a specified URL into a target jQuery element.
  *
  * @async
  * @param {jQuery} target - The jQuery element where the HTML content will be loaded.
  * @param {string} url - The URL from which to fetch the HTML content.
  * @returns {Promise<void>} A promise that resolves when the content is loaded, or rejects on error.
  * @throws {Error} If the fetch operation fails (e.g., network error, 404).
  */  
  async loadHtmlContent(target, url){

        // let that = this; // 'that' is not used in this version of the function

        try {
            const response = await fetch(url);
            if (!response.ok) { // Check if response status is not OK (e.g., 404, 500)
                throw new Error(`HTTP error ${response.status}: ${response.statusText}`);
            }
            const htmlContent = await response.text();
            if (target && target.length > 0 && typeof target.html === 'function') { // Ensure target is a valid jQuery object with .html()
                target.html(htmlContent); // Use .html() to set content, assuming target is jQuery obj
            } else {
                console.error("loadHtmlContent: Target element is invalid or not a jQuery object.", target);
            }
        } catch (error) {
            console.error(`Failed to load HTML content from ${url}:`, error); // Log the error for debugging
            window.hWin.HEURIST4.msg.showMsgErr(`Failed to load content from ${url}: ${error.message}`);
        }
    }

    /**
     * 
     * @param {string} type service identifier, e.g. export, import, etc...
     * @param {object} data parameters to be prepared
     * @param {int} mode how to handle parameters: 0 - Complete replace, 1 - Merge + maintain existing, 2 - Merge + replace existing
     * @param {int} id prepared parameters session ID
     */
    async prepareParameters(type, data, mode = 2, id = null){

        let HAPI4 = this.hapi4;
        const CHUNK_SIZE = 2000;

        let _sendParams = async (key, value, curMode = mode) => {

            return new Promise((resolve) => {

                let request = { a: 'prepare_params', preparedID: id, preparedType: type, preparedMode: curMode, [key]: value };

                HAPI4.callserver('usr_info', request, (response) => {
                    resolve(response.data);
                });
            });
        };

        let chunkValue = async (key, value) => {

            let start = 0;

            while(true){

                if(value.length <= CHUNK_SIZE){
                    id = await _sendParams(key, value, start === 0 ? mode : 2);
                    break;
                }else if((start + CHUNK_SIZE) <= CHUNK_SIZE){
                    id = await _sendParams(key, value.substr(start, CHUNK_SIZE), 2);
                    break;
                }else{
                    id = await _sendParams(key, value.substr(start, CHUNK_SIZE), start === 0 ? mode : 2);
                    start += CHUNK_SIZE;
                }
            }
        };

        for(const key in data){

            if(!Object.hasOwn(data, key)){
                continue;
            }

            let value = data[key];
            const isArray = Array.isArray(value);
            const isObject = window.hWin.HEURIST4.util.isObject(value);
            if(window.hWin.HEURIST4.util.isFunction(value) || (isObject && !isArray && !$.isPlainObject(value))){ // skip functions and non-plain objects/arrays (i.e. object of class)
                continue;
            }
            if(isArray || isObject){ // stringify objects and arrays
                value = JSON.stringify(value);
            }else if(!Number.isNaN(value)){
                value = value.toString();
            }

            chunkValue(key, value);
        }
    }
  
}