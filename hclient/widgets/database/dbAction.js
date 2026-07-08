/**
* @file dbAction.js
* @brief Dialog for performing various database-level actions
* @fileOverview
*
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0 
*/

/**
 * @class dbAction
 * @augments baseAction
 * @memberof Widgets.Admin
 * @description This widget provides a dialog or inline interface for performing various database-level actions
 * such as creating, renaming, cloning, deleting, clearing, restoring, or registering a database.
 * It handles user input for action parameters, sends requests to the server,
 * and displays progress and final results/reports.
 *
 * @property {object} options - Configuration options for the widget.
 *
 * @property {number} _progressInterval - Interval ID for progress polling.
 * @property {number} _session_id - Unique session ID for the current action, used for progress tracking.
 * @property {?jQuery} _select_file_dlg - jQuery object for the file selection dialog (used in 'restore' action).
 */
$.widget( "heurist.dbAction", $.heurist.baseAction, {

    /**
    * @memberof Widgets.Admin.dbAction
    * @type {object} Extends {@link baseAction.options}.
    * 
    * @property {string} actionName - The name of the database action to perform (e.g., 'dbCreate', 'dbClone').
    *                                        This is transformed internally (e.g., 'dbCreate' becomes 'create').
    * @property {string} [options.default_palette_class='ui-heurist-admin'] - Default CSS class for theming.
    * @property {string} [options.path='widgets/database/'] - Path to HTML template files for actions.
    * @property {string} [options.entered_password=''] - Pre-filled password, if any (e.g., for actions requiring re-authentication).
    */
    options: {
        actionName: '',
        default_palette_class: 'ui-heurist-admin',
        path: 'widgets/database/',
        entered_password: ''
    },

    _progressInterval:0,
    _session_id:0,
    _select_file_dlg:null,

    _alreadyCheckedMetadata: true,

    /**
     * @function _init
     * @description Initializes the widget. Sets up HTML content path based on `actionName` and calls the superclass `_init`.
     * Transforms `actionName` (e.g., 'dbCreate' to 'create').
     * This method is called by jQuery UI when the widget is created.
     * @memberof Widgets.Admin.dbAction
     * @private
     */
    _init: function() {
        if(this.options.htmlContent=='' && this.options.actionName){
            this.options.htmlContent = this.options.actionName+'.html';
        }

        // Example: dbCreate => create
        if (this.options.actionName.startsWith('db')) {
            this.options.actionName = this.options.actionName.slice(2).toLowerCase();
        } else {
            this.options.actionName = this.options.actionName.toLowerCase();
        }

        this._super();
    },

    /**
     * @function _initControls
     * @description Initializes controls within the widget's HTML structure after it has been loaded.
     * Sets up buttons, event handlers, and pre-fills form elements based on the specific action.
     * This method is called by `_init` (via `_super()`) after HTML content is loaded.
     * @memberof Widgets.Admin.dbAction
     * @private
     * @returns {void}
     */
    _initControls:function(){

        this._$('button').button();
        this._on(this._$('button.ui-button-action'),{click:this.doAction});

        this._$('span.dbprefix').text(window.hWin.HAPI4.sysinfo.database_prefix);

        if(this.options.actionName=='create' &&
            window.hWin.HAPI4.sysinfo['pwd_DatabaseCreation'])
        {
            this._$('#div_need_password').show();
        }
        else if(this.options.actionName=='clone')
        {
            if(!window.hWin.HEURIST4.util.isPositiveInt(window.hWin.HAPI4.sysinfo.db_registeredid) && window.hWin.HAPI4.sysinfo.sysadmin_email !== window.hWin.HAPI4?.currentUser.ugr_eMail){
                this._checkNewDefinitions();
            }

            if(window.hWin.HAPI4.sysinfo.db_total_records<50000){
                this._$('.large-db').hide();
            }else{
                this._on(this._$('#nodata'), {click:()=>{
                    if(this._$('#nodata').is(':checked')){
                        this._$('.large-db').hide();
                    }else{
                        this._$('.large-db').show();
                    }
                }});
            }

        }else if(this.options.actionName=='restore')
        {
            this._on(this._$('#btnSelectZip'),{click:this._selectArchive});

        }else if(this.options.actionName=='register')
        {
            if(window.hWin.HAPI4.sysinfo.database_hostname){
                this._$('#div_header').hide();
                this._$('#div_block').show();
                return;
            }
            this._$('#div_block').hide();
            

            let that = this;
            window.hWin.HAPI4.EntityMgr.getEntityData('sysIdentification', false, function(response){
                if(!window.hWin.HEURIST4.util.isempty(response)){
                    let record = response.getFirstRecord();
                    let description = response.fld(record, 'sys_dbDescription');
                    // cache Display name and Rights statement - sent alongside the long
                    // description when registering, see Ian's metadata field mapping
                    that._sysDbDisplayName = response.fld(record, 'sys_dbName');
                    that._sysDbRights = response.fld(record, 'sys_dbRights');
                    that._$('.dbDescription').text(description);
                    that._$('#dbTitle').val(description).trigger('keyup');
                }});

            if(window.hWin.HAPI4.sysinfo['db_registeredid'] > 0){
                this._$('.dbDescription').text('');
                this._$('span.dbId').text(window.hWin.HAPI4.sysinfo['db_registeredid']);
                this._$('a.dbLink').attr('href',
                    window.hWin.HAPI4.sysinfo['referenceServerURL']
                        +'?fmt=edit&recID='+window.hWin.HAPI4.sysinfo['db_registeredid']
                        +'&db='+window.hWin.HAPI4.sysinfo.referenceServerIndexDatabase);

                this._$('.ent_wrapper').hide();
                this._$("#div_result").show();

            }else{
                this._$('#serverURL').val(window.hWin.HAPI4.baseURL_pro+'?db='+window.hWin.HAPI4.database);
                this._$('#dbTitle').val('');

                if(window.hWin.HAPI4.user_id()!=2){ // User ID 2 is dbowner
                    this._$('#div_need_password').show();
                }else{
                    this._$('#div_need_password').hide();
                }

                this._on(this._$('#dbTitle'),{keyup:
                        function ( event ){
                            let len = $(event.target).val().length;
                            let ele = this._$('#cntChars').text(len);
                            ele.parent().css('color',(len<40)?'red':'#6A7C99'); // Visual feedback for length
                        }
                });
            }

            if(window.hWin.HAPI4.sysinfo['db_registeredid'] <= 0 && !this.options.isdialog && $('.ui-menu6').length > 0){
                // For new registers, ensure the user has clicked the metadata link before closing

                $('.ui-menu6').slidersMenu('manageSwitchHandler', 'admin', 'dbRegister', () => {

                    if(!this._alreadyCheckedMetadata){

                        window.hWin.HEURIST4.msg.showMsgFlash('Opening metadata editor...', 3000);

                        setTimeout(() => {
                            this._$('a.dbLink')[0].click();
                        }, 3000);

                        return false;
                    }

                    $('.ui-menu6').slidersMenu('manageSwitchHandler', 'remove', 'dbRegister');
                    return true;
                });
            }

            this._on(this._$('a.dbLink'), {
                click: () => {
                    this._alreadyCheckedMetadata = true;
                }
            });
        }

        // User and database name inputs
        let ele = this._$('#uname');
        if(ele.length > 0 && ele.val()==''){ // Check ele.length
            ele.val(window.hWin.HAPI4.currentUser.ugr_Name.replace(/[^a-zA-Z0-9$_]/g,'').slice(0,5));
            
            const rawName = window.hWin.HAPI4.currentUser.ugr_Name || '';

            // Keep only allowed chars
            let dbName = rawName.replace(/[^a-zA-Z0-9]/g, '');

            // If non-latin → empty after replace
            if (!dbName) {
                dbName = 'user';
            }

            // Ensure exactly 5 characters
            if (dbName.length < 5) {
                dbName = dbName.padEnd(5, '0'); // user0, ab120, etc.
            }

            ele.val(dbName.slice(0, 5).toLowerCase());            
        }
        this._on(this._$('#newdblink'),{click:this.closeDialog}); // Assuming closeDialog is from baseAction
        this._$('span.dbname').text(window.hWin.HAPI4.database);

        this._$('#dbname').trigger('focus');

        // Prevent non-alphanumeric characters in text inputs
        this._on(this._$('input[type=text]'),{keypress:window.hWin.HEURIST4.ui.preventNonAlphaNumeric});

        return this._super(); // Call baseAction's _initControls
    },

    /**
     * @function _destroy
     * @description Custom widget-specific cleanup. Called when the widget is destroyed.
     * @memberof Widgets.Admin.dbAction
     * @private
     */
    _destroy: function() {
        // this._super(); // Call if baseAction has a _destroy method
        if (this._select_file_dlg && typeof this._select_file_dlg.remove === 'function') {
            this._select_file_dlg.remove(); // Clean up file dialog if created
            this._select_file_dlg = null;
        }
        if (this._progressInterval) {
            clearInterval(this._progressInterval);
            this._progressInterval = null;
        }
    },

    /**
     * @function _getActionButtons
     * @description Defines the buttons to be displayed in the dialog (if this widget is used in a dialog context).
     * Typically overridden by `baseAction` or its parent.
     * @memberof Widgets.Admin.dbAction
     * @private
     * @returns {Array<object>|null} An array of button definition objects, or null if handled by base.
     */
    _getActionButtons: function(){
        return this._super(); // Delegates to baseAction
    },

    /**
     * @function doAction
     * @description Performs the database action based on `options.actionName`.
     * Gathers required parameters from the form, validates them, and calls `_sendRequest`.
     * @memberof Widgets.Admin.dbAction
     */
    doAction: function(){
        let request = {}; // Initialize with an empty object

        if(this.options.actionName=='create'
            || this.options.actionName=='rename'
            || this.options.actionName=='clone')
        {
           const dbname = this._$('#dbname').val().trim();
           if(dbname==''){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define name of database'));
                return;
           }
           let ele = this._$('#uname');
           request = {uname : (ele.length>0?ele.val().trim():''), // Use current user if uname field not present/empty
                      dbname: dbname};

           if(this.options.actionName=='clone'){
                if(this._$('#nodata').is(':checked')){
                    request['nodata'] = 1;
                }
                const pwd = this._$('#pwd').val().trim();
                if(pwd!=''){ request['pwd'] = pwd; }
           }else if(this.options.actionName=='create' && window.hWin.HAPI4.sysinfo['pwd_DatabaseCreation']){
                const pwd = this._$('#create_pwd').val().trim();
                if(pwd==''){
                    window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define password'));
                    return;
                }
                request['create_pwd'] = pwd;
           }
        }else if(this.options.actionName=='clear' || this.options.actionName=='delete'){
           let chpwd = this._$('#db-password').val().trim();
           if(chpwd==''){
               window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define challenge word'));
               return;
           }
           request = { chpwd: chpwd };
           if(this.options.entered_password){ request['pwd'] = this.options.entered_password; }
        }else if(this.options.actionName=='restore'){
           const dbname = this._$('#dbname').val().trim();
           if(dbname==''){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define name of database'));
                return;
           }
           let dbarchive_file = this._$('#selectedZip').text();
           if(dbarchive_file==''){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define name of source zip archive'));
                return;
           }
           let dbarchive_folder = this._$('input[name=selArchiveFolder]:checked').val();
           request = {file: dbarchive_file, folder: dbarchive_folder, dbname: dbname, pwd: this.options.entered_password};
        }else if(this.options.actionName=='register'){
            let description = this._$('#dbTitle').val().trim();
            if(description.length<40){
                window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define informative description (min 40 characters)'));
                return;
            }
           request = {dbReg: window.hWin.HAPI4.database,
                      dbTitle: description,
                      // Heurist_Reference_Index field mapping (per Ian Johnson's metadata strategy):
                      //  Display name (Design > Properties)           -> Database title (concept 2-1)
                      //  Database rights statement (Design > Properties) -> concept 2-311
                      //  This long description (>=40 chars, above)    -> concept 2-12
                      dbDisplayName: this._sysDbDisplayName || '',
                      dbRights: this._sysDbRights || '',
                      dbVer: window.hWin.HAPI4.sysinfo['db_version'],
                      serverURL: this._$('#serverURL').val() };
           if(window.hWin.HAPI4.user_id()!=2){ // Not superadmin
                const pwd = this._$('#pwd').val().trim();
                if(pwd==''){
                    window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Define password'));
                    return;
                }
                request['pwd'] = pwd;
           }
        }

        if(this.options.actionName=='delete' || this.options.actionName=='rename'){
           if(!this._$('#db-archive').is(':checked')){
                request['noarchive'] = 1;
                this._$('.archive').hide();
           }else{
                this._$('.archive').show();
           }
        }
        this._sendRequest(request);
    },

    /**
     * @function _sendRequest
     * @description Sends the prepared request to the server via `HAPI4.SystemMgr.databaseAction`.
     * Shows progress indicator before sending and hides it after completion or error.
     * Calls `_afterActionEventHandler` on successful completion.
     * @memberof Widgets.Admin.dbAction
     * @private
     * @param {object} request - The request object to send to the server.
     */
    _sendRequest: function(request) {
        this._session_id = window.hWin.HEURIST4.util.random(); // Unique ID for this action attempt

        request['action'] = this.options.actionName;
        request['db'] = window.hWin.HAPI4.database;
        request['locale'] = window.hWin.HAPI4.getLocale();
        request['session'] = this._session_id;

        this._showProgress( this._session_id, false, (this.options.actionName=='register')?0:1000 );
        let that = this;
        window.hWin.HAPI4.SystemMgr.databaseAction( request,  function(response){
                that._hideProgress();
                if (response.status == window.hWin.ResponseStatus.OK) {
                    that._afterActionEventHandler(response.data, response.message);
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                    that._$('.ent_wrapper').hide(); // Hide form fields
                    that._$('#div_header').show();  // Show initial page/header again
                }
        });
    },

    /**
     * @function _checkNewDefinitions
     * @description For 'clone' action, checks if the source database contains new definitions
     * that might require a password for cloning. Shows a warning if necessary.
     * @memberof Widgets.Admin.dbAction
     * @private
     */
    _checkNewDefinitions: function(){
        let that = this;
        let request = {action: 'check_newdefs', db: window.hWin.HAPI4.database};
        window.hWin.HAPI4.SystemMgr.databaseAction( request,  function(response){
                if (response.status == window.hWin.ResponseStatus.OK) {
                    if(response.data != ''){ // Non-empty response.data indicates new definitions found
                        that._$('#div_need_password').show(); // Show password input field
                        that._$('#new_defs_warning').text(response.data); // Display warning message
                    }
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });
    },

    /**
     * @function _selectArchive
     * @description For 'restore' action, opens a file selection dialog to choose a database archive (.zip or .bz2).
     * Populates the selected filename and suggests a database name based on the archive name.
     * @memberof Widgets.Admin.dbAction
     * @private
     */
    _selectArchive: function(){
        let that = this;
        let src_folder = this._$('input[name=selArchiveFolder]:checked').val(); // e.g., 'local', 'server'

        let extensions = 'zip,bz2';
        extensions = src_folder == 5 ? 'folders' : extensions;

        if(!this._select_file_dlg){ // Initialize dialog on first use
            this._select_file_dlg = $('<div>').hide().appendTo( this.element ); // Create hidden div for dialog

            this._select_file_dlg.selectFile({
               keep_dialogue: true, // Keep dialog instance for reuse
               showFilter: true,
               source: src_folder,
               extensions: extensions,
               title: window.HR('Select database archive'),
               onselect:function(res){
                    if(res && res.filename){
                        that._$('#selectedZip').text(res.filename);
                        that._$('#db-restore-name').show();

                        // Suggest database name based on archive filename
                        if(that._$('#dbname').val().trim()==''){
                            let sname = res.filename;
                            if(sname.indexOf(window.hWin.HAPI4.sysinfo.database_prefix)==0){
                                sname = sname.substring(window.hWin.HAPI4.sysinfo.database_prefix.length);
                            }
                            if(sname.indexOf('.')>0){ sname = sname.substring(0,sname.indexOf('.')); }
                            if(sname.length>60){ sname = sname.substring(0,59); } // Max length constraint
                            that._$('#dbname').val(sname);
                        }
                    }else{ // No file selected or dialog cancelled
                        that._$('#selectedZip').text('');
                        that._$('#db-restore-name').hide();
                    }
               }});
        }else{ // Reuse existing dialog instance
            this._select_file_dlg.selectFile('option', { // Update options for existing dialog
               source: src_folder,
               extensions: extensions
            });
            this._select_file_dlg.selectFile('open'); // Reopen the dialog
        }
    },


    /**
     * @function _afterActionEventHandler
     * @description Handles the server response after a database action is successfully completed.
     * Displays results, warnings, and appropriate messages or redirects based on the action performed.
     * @memberof Widgets.Admin.dbAction
     * @private
     * @param {object} response_data - The `data` part of the server response.
     * @param {string} termination_message - A general message from the server about the action's outcome.
     */
    _afterActionEventHandler: function( response_data, termination_message ){
        this._$('.ent_wrapper').hide(); // Hide form inputs
        let div_res = this._$("#div_result").show(); // Show result display area

        if(response_data && response_data.newdbname){
            this._$('#newdbname').text(response_data.newdbname);
        }

        if(this.options.actionName=='delete'){
            window.hWin.HEURIST4.msg.showMsgDlg(div_res.html(),
               null, window.hWin.HR('Database deleted'), {
                    width:700, height:'auto',
                    close: function(){ window.hWin.document.location = window.hWin.HAPI4.baseURL; } // Redirect to home
               });
        }else if(this.options.actionName=='rename') {
            if(response_data.warning){
                div_res.find('.warning').html(window.hWin.HEURIST4.util.stripTags(response_data.warning,'p,br'));
            }
            window.hWin.HEURIST4.msg.showMsgDlg(div_res.html(),
               null, window.hWin.HR('Database renamed'), {
                    width:700, height:'auto',
                    close: function(){ window.hWin.document.location = response_data.newdblink; } // Redirect to new DB link
               });
        }else if(this.options.actionName=='register'){

            this._$('.dbDescription').text(response_data.dbTitle);
            this._$('span.dbId').text(response_data.dbID);

            this._$('a.dbLink').attr('href',
                `${window.hWin.HAPI4.sysinfo['referenceServerURL']}?fmt=edit&recID=${response_data.dbID}&db=${window.hWin.HAPI4.sysinfo.referenceServerIndexDatabase}`);

            let content = `${div_res.html()}<br>The metadata editing form will open automatically when you close this popup.`;
            window.hWin.HEURIST4.msg.showMsgDlg(div_res.html(),
               null, window.hWin.HR('Database registered'), {
                    width:700, height:'auto', default_palette_class: 'ui-heurist-design', dialogId: 'registered-db',
                    close: () => {
                        if(!this._alreadyCheckedMetadata){
                            this._$('a.dbLink')[0].click();
                        }
                        window.hWin.document.location.reload(); // Reload current page
                    }
               });

            this._alreadyCheckedMetadata = false;
        }else{ // For create, clone, restore
            this._$('#newusername').text(response_data.newusername);
            this._$('#newdblink').attr('href',response_data.newdblink).text(response_data.newdblink);
            if(response_data.warnings && response_data.warnings.length>0){
                this._$('#div_warnings').html(response_data.warnings.join('<br><br>')).show();
                this._$('#div_login_info').hide(); // Hide login info if there are warnings
            }
        }

        if(this.options.actionName!='clear'){ // Clear/reset DB list cache unless action was 'clear'
            window.hWin.HAPI4.EntityMgr.emptyEntityData('sysDatabases');
        }
    },

    /**
     * @function _showProgress
     * @description Shows a progress indicator for long-running database actions.
     * Displays a loading animation and periodically polls the server for progress updates.
     * Updates a list of steps or a percentage based on server response.
     * @memberof Widgets.Admin.dbAction
     * @private
     * @param {number} session_id - The session ID for the current action, used to fetch progress.
     * @param {boolean} is_autohide - If true, hides progress automatically when server reports termination.
     * @param {number} t_interval - Polling interval in milliseconds. If <= 900, only shows basic loading.
     * @param {Function} [onComplete] - Optional callback when progress is hidden (not currently used).
     */
    _showProgress: function ( session_id, is_autohide, t_interval, onComplete ){
        if(!window.hWin.HEURIST4.util.isPositiveInt(session_id)) { // Validate session_id
             this._hideProgress();
             return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront(null, {opacity: '0.3'}, window.hWin.HR(this.options.title || 'Processing...'));
        $('body').css('cursor','progress'); // Change cursor to indicate processing

        let that = this;
        this._$('.ent_wrapper').hide(); // Hide form elements
        let progress_div = this._$('.progressbar_div').show(); // Show progress display area
        let div_loading = progress_div.find('.loading').show();
        let all_li = div_loading.find('li'); // List items representing steps

        if(all_li.length>0){ // If there are predefined steps in HTML
            all_li.css('color','lightgray'); // Dim all steps initially
            $(all_li[0]).css('color','black'); // Highlight first step
            // Add processing animation to the first step
            $('<span class="processing"> <span class="ui-icon ui-icon-loading-status-balls"></span>  <span class="percentage">processing...</span></span>').appendTo( $(all_li[0]) );
        }

        let currStep = 0; // Tracks the current step number reported by server

        if(t_interval > 900){ // Only poll if interval is meaningful
            let progress_url = window.hWin.HAPI4.baseURL + "hserv/controller/progress.php";
            if (this._progressInterval) clearInterval(this._progressInterval); // Clear any existing interval

            this._progressInterval = setInterval(function(){
                let request = {t:Date.now(), session:session_id}; // Add timestamp for cache busting
                window.hWin.HEURIST4.util.sendRequest(progress_url, request, null, function(response){
                    if(response && response.status==window.hWin.ResponseStatus.UNKNOWN_ERROR){ // Error condition
                        that._hideProgress();
                    }else if(response=='terminate' && is_autohide){ // Server signals termination
                        that._hideProgress();
                    }else if(response && currStep!=response){ // New progress update
                        currStep = response;
                        let percentage = 0, newStepVal = 0; // Renamed newStep to newStepVal
                        if(typeof response === 'string' && response.indexOf(',')>0){
                            [newStepVal, percentage] = response.split(',');
                        }else{
                            newStepVal = response;
                        }

                        if(window.hWin.HEURIST4.util.isNumber(newStepVal)){ // Numerical step update
                            newStepVal = parseInt(newStepVal);
                            if(all_li.length > 0) { // Update predefined list items
                                if(newStepVal>0){
                                    let arr = all_li.slice(0,newStepVal);
                                    arr.css('color','black');
                                    arr.find('span.processing').remove();
                                }
                                if(newStepVal<all_li.length){
                                    let current_li = $(all_li[newStepVal]);
                                    let percentage_text = 'processing...';
                                    if(percentage>0){
                                        if(percentage>100) percentage = 100;
                                        percentage_text = percentage+'%';
                                    }
                                    let ele_percentage = current_li.find('span.percentage');
                                    if(ele_percentage.length==0){
                                        $('<span class="processing"> <span class="ui-icon ui-icon-loading-status-balls"></span> <span class="percentage">'
                                            +percentage_text+'</span></span>').appendTo( current_li );
                                        current_li.css('color','black');
                                    }else{
                                        ele_percentage.text(percentage_text);
                                    }
                                }
                            }
                        }else{ // Textual step update (append to list)
                            let cont = div_loading.find('ol');
                            if (cont.length === 0) { // Create OL if not present
                                cont = $('<ol>').appendTo(div_loading);
                            }
                            let li_ele = cont.find('li').filter(function() { return $(this).text() === newStepVal; });
                            if(li_ele.length==0){
                                $('<li>').text(newStepVal).appendTo(cont);
                            }
                        }
                    }
                },'text'); // Expect plain text response
            }, t_interval);
        } else if (all_li.length === 0) { // No predefined steps and no polling, show basic loading
             div_loading.html('<span class="processing"> <span class="ui-icon ui-icon-loading-status-balls"></span> processing...</span>');
        }
    },

    /**
     * @function _hideProgress
     * @description Hides the progress indicator and restores the UI.
     * Clears the progress polling interval and resets the cursor.
     * @memberof Widgets.Admin.dbAction
     * @private
     */
    _hideProgress: function (){
        $('body').css('cursor','auto');
        window.hWin.HEURIST4.msg.sendCoverallToBack(true); // Remove overlay
        if(this._progressInterval!=null){
            clearInterval(this._progressInterval);
            this._progressInterval = null;
        }
        // Show the main header/form part, hide progress bar part.
        // This assumes a structure where .ent_wrapper contains the form and .progressbar_div the progress.
        // If action was successful, _afterActionEventHandler would have already hidden .ent_wrapper and shown #div_result.
        // If action failed, _sendRequest would hide .ent_wrapper and show #div_header.
        // This function mainly ensures the progressbar_div itself is hidden if it was shown.
        this._$('.progressbar_div').hide();
        if (!this._$('#div_result').is(':visible')) { // If result is not shown, show the header/form
             this._$('#div_header').show();
        }
    }
});