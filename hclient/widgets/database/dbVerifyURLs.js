/**
* @file dbVerifyURLs.js
* @brief Verifies URLs in record headers and fields
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
 * @class dbVerifyURLs
 * @augments dbAction
 * @memberof Widgets.Admin
 * @description This widget handles the verification of URLs within database records (both in record headers and specific fields).
 * It allows users to start a new verification process, continue a previous one, or resume an interrupted session.
 * Results, including problematic URLs, can be displayed and exported.
 *
 * @property {boolean} prevSessionExists - Flag indicating if a previous, potentially incomplete,
 *                                         URL verification session exists for the current database.
 */
$.widget( "heurist.dbVerifyURLs", $.heurist.dbAction, {

    // Server-side action name. New implementation avoids MySQL REGEXP extraction.
    // If the widget is instantiated with the legacy actionName ("verifyurls"), we transparently
    // switch to the new action.
    options: {
        // Default action used by databaseController.php
        // (Your revised server-side implementation should be wired to this action.)
        actionName: "verifyurls"
    },

    prevSessionExists: false,
    autoContinueProcess: false,
    autoContinueInterval: null,

    /**
     * @function _initControls
     * @description Initializes controls for the URL verification widget.
     * Checks for a previous verification session and then calls the superclass's `_initControls`.
     * This method is called by `_init` (via `_super()`) after HTML content is loaded.
     * @memberof Widgets.Admin.dbVerifyURLs
     * @private
     */
    _initControls:function(){
        // Backward compatibility: old dialogs may still instantiate with actionName="verifyurls"
        this._checkPreviousSession();
        this._setupAutoStartControls();
        return this._super();
    },

    /**
     * @function _setupAutoStartControls
     * @description Checks with the server if a previous URL verification session exists for the current database.
     * Updates the UI to show options for continuing, restarting, or viewing previous results based on the server response.
     * If a session is currently in progress, it will resume showing progress for that session.
     * @memberof Widgets.Admin.dbVerifyURLs
     * @private
     */
    _setupAutoStartControls: function(){

        let $checkbox = this._$('#midProcessAutoContinue');

        this._on($checkbox, {
            change: () => {
                this.autoContinueProcess = $checkbox.prop('checked');
                if(this.autoContinueProcess){
                    this._startAutoStartInterval();
                }else{
                    clearInterval(this.autoContinueInterval);
                    this.autoContinueInterval = null;
                }
            }
        });
    },

    /**
     * @function _startAutoStartInterval
     * @description Starts the interval countdown to automatically starting the next set of record checks.
     * The countdown lasts 5 seconds starting when the previous set finishes
     * @memberof Widgets.Admin.dbVerifyURLs
     * @private
     */
    _startAutoStartInterval: function(){

        if(!this.autoContinueProcess){
            this._$('#autoContinueMsg').hide();
            return;
        }

        // Start interval
        let count = 5;
        this.autoContinueInterval = setInterval(() => {

            count--;

            if(count === 0 || !this.autoContinueInterval){

                this._$('#autoContinueCountdown').text(5);

                if(this.autoContinueInterval !== null){

                    clearInterval(this.autoContinueInterval);
                    this.autoContinueInterval = null;

                    this._$('#autoContinueCountdown').text('Stopped');

                    this.doAction();
                }

                return;
            }

            this._$('#autoContinueCountdown').text(count);
        }, 1000);
        
        this._$('#autoContinueCountdown').text(count);
        this._$('#autoContinueMsg').show();
    },

    /**
     * @function _checkPreviousSession
     * @description Checks with the server if a previous URL verification session exists for the current database.
     * Updates the UI to show options for continuing, restarting, or viewing previous results based on the server response.
     * If a session is currently in progress, it will resume showing progress for that session.
     * @memberof Widgets.Admin.dbVerifyURLs
     * @private
     */
    _checkPreviousSession: function(){
        this._hideProgress(); // Ensure any previous progress display is hidden

        let request = {
            action: this.options.actionName, // Should resolve to 'verifyurls'
            db: window.hWin.HAPI4.database,
            checksession: 1 // Flag to tell server to check for existing session
        };
        let that = this;

        window.hWin.HAPI4.SystemMgr.databaseAction( request,  function(response){
                if (response.status == window.hWin.ResponseStatus.OK) {
                    if(response.data.session_id > 0){ // A session is currently in progress
                        that._session_id = response.data.session_id;
                        // Resume showing progress for the ongoing session.
                        // The onComplete callback is set to _checkPreviousSession to re-evaluate after this progress display finishes or is interrupted.
                        that._showProgress( that._session_id, false, 1000, function() { that._checkPreviousSession(); } );
                    }else if(response.data.total_checked > 0){ // A previous session exists and is complete or was interrupted
                        that._$('#prevSessionExist').show();
                        that._$('#prevSessionNotExist').hide();
                        that._$('span.total_checked').text(response.data.total_checked);
                        that._$('span.total_bad').text(response.data.total_bad);

                        let btnCSV = that._$('.btnCSV').button();
                        that._on(btnCSV, {click:that._getPreviousSessionAsCSV});

                        if(response.data.total_bad==0){ btnCSV.hide(); }
                        else{ btnCSV.show(); }

                        let formats = response.data.formats;
                        if(!window.hWin.HEURIST4.util.isempty(formats)){
                            that._$('#checkRecHeaders').prop('checked', formats == 'all' || Array.isArray(formats) && formats.includes('recheaders'));
                            that._$('#checkTextFields').prop('checked', formats == 'all' || Array.isArray(formats) && formats.includes('textfields'));
                            that._$('#checkExternalFiles').prop('checked', formats == 'all' || Array.isArray(formats) && formats.includes('externalfiles'));
                        }

                        that.prevSessionExists = true;
                    }else{ // No previous session data found
                        that._$('#prevSessionExist').hide();
                        that._$('#prevSessionNotExist').show();
                        that.prevSessionExists = false;
                    }
                } else {
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });
    },

    /**
     * @function _getPreviousSessionAsCSV
     * @description Initiates a download of the previous URL verification session's results as a CSV file.
     * Opens a new window/tab pointing to the server-side script that generates the CSV.
     * @memberof Widgets.Admin.dbVerifyURLs
     * @private
     */
    _getPreviousSessionAsCSV: function(){
        let req = {
            'action' : this.options.actionName, // 'verifyurls'
            'getsession': 1, // Flag to get session data
            'db': window.hWin.HAPI4.database
        };
        let url = window.hWin.HAPI4.baseURL + 'hserv/controller/databaseController.php?';
        window.open(url+$.param(req), '_blank'); // Open in new tab
    },

    /**
     * @function doAction
     * @description Gathers parameters (limit, mode) and starts the URL verification process by calling `_sendRequest`.
     * The 'mode' determines if it's a new scan, a continuation, or a re-check of bad URLs.
     * @memberof Widgets.Admin.dbVerifyURLs
     */
    doAction: function(){

        let limit = this._$('#selCheckURLsLimit').val();

        let format = [];
        if(this._$('#checkRecHeaders').is(':checked')){
            this._$('#report_record').show();
            format.push('recheaders');
        }else{
            this._$('#report_record').hide();
        }
        if(this._$('#checkTextFields').is(':checked')){
            this._$('#report_text').show();
            format.push('textfields');
        }else{
            this._$('#report_text').hide();
        }
        if(this._$('#checkExternalFiles').is(':checked')){
            this._$('#report_file').show();
            format.push('externalfiles');
        }else{
            this._$('#report_file').hide();
        }

        format = format.length > 0 && format.length != 3 ? format : 'all';

        this.autoContinueProcess = this._$('#optAutoStart').prop('checked');
        this._$('#midProcessAutoContinue').prop('checked', this.autoContinueProcess);

        // Mode: 0 = Continue, 1 = Recheck bad URLs then continue, 2 = Start from scratch
        const mode = this.prevSessionExists ? this._$('input[name="mode"]:checked').val() : 2;

        let request = {limit: limit, verbose: 0, mode: mode, format: format};
        this._sendRequest(request);
    },

    /**
     * @function _showProgress
     * @description Overrides the baseAction `_showProgress` to use a more specific progress display method
     * from `window.hWin.HEURIST4.msg.showProgress`.
     * @memberof Widgets.Admin.dbVerifyURLs
     * @private
     * @param {number} session_id - The session ID for the current action.
     * @param {boolean} is_autohide - Whether the progress should hide automatically on completion (not directly used by `msg.showProgress`).
     * @param {number} t_interval - Polling interval (passed to `msg.showProgress`).
     * @param {Function} [onComplete] - Callback function when progress display completes or is stopped.
     */
    _showProgress: function ( session_id, is_autohide, t_interval, onComplete ){
        this._$('.ent_wrapper').hide();
        let progress_div = this._$('.progressbar_div').show();
        // Uses a global/centralized progress display mechanism
        window.hWin.HEURIST4.msg.showProgress({container: progress_div,
                        session_id: session_id, interval:t_interval || 2000, onComplete:onComplete});
    },


    /**
     * @function _hideProgress
     * @description Hides the progress indicator and restores the main form/header view.
     * Uses `window.hWin.HEURIST4.msg.hideProgress`.
     * @memberof Widgets.Admin.dbVerifyURLs
     * @private
     */
    _hideProgress: function (){
        window.hWin.HEURIST4.msg.hideProgress(); // Uses global mechanism
        this._$('.ent_wrapper').hide();
        this._$('#div_header').show(); // Show initial configuration part of the dialog
    },



    /**
     * @function _afterActionEventHandler
     * @description Handles the server response after a URL verification action (or part of it) is completed.
     * Updates the UI with statistics (total checked, bad URLs, processed counts per type)
     * and provides links to view problematic records. Manages UI state based on whether
     * the verification process is fully finished.
     * @memberof Widgets.Admin.dbVerifyURLs
     * @private
     * @param {object} response_data - The `data` part of the server response.
     * @param {string|object} termination_message - Message or error object if the process was terminated or encountered an issue.
     */
    _afterActionEventHandler: function( response_data, termination_message ){

        this._$('.ent_wrapper').hide();
        let div_res = this._$("#div_result").show(); // Show the results display area

        if(response_data.output){ // Server might send a summary HTML block
            div_res.find('#session_summary').html(response_data.output);
        }

        const isFinished = response_data.session_checked == 0; // Server indicates 0 when fully complete
        let that = this;

        const types = ['record','text','file']; // URL types to report on
        types.forEach(function(key) {
            that._$(`span.session_processed_${key}`).text(response_data[`session_processed_${key}`] || 0);
            const total_bad = response_data[`${key}_bad`] || 0;
            let ele_total_bad = that._$(`span.${key}_bad`);
            ele_total_bad.text(total_bad);
            ele_total_bad.css('color','red');
            if(total_bad > 0 && response_data[key]){ // If bad URLs of this type exist and IDs are provided
               const ids = Object.keys(response_data[key]).join(',');
               const url = `${window.hWin.HAPI4.baseURL}?db=${window.hWin.HAPI4.database}&q=ids:${ids}`;
               that._$(`span.links_${key}`).html(`<a href="${url}" target="_blank" style="padding-left:10px;font-size:0.8em">show records as search  <span class="ui-icon ui-icon-linkext"></span></a>`);
            }else if(isFinished && total_bad === 0){
                ele_total_bad.text('OK').css('color','green');
                that._$(`span.links_${key}`).empty(); // Clear any previous link
            } else {
                 that._$(`span.links_${key}`).empty(); // Clear link if no bad URLs or not finished
            }
        });

        this._$('span.total_checked').text(response_data.total_checked || 0);
        this._$('span.total_bad').text(isFinished && response_data.total_bad==0 ? 'OK' : (response_data.total_bad || 0));
        this._$('span.total_bad').css('color',isFinished && response_data.total_bad==0 ? 'green':'red');

        if(isFinished){
            this._$('#all_urls_verified').show();
            if(response_data.total_bad==0){ this._$('#all_urls_ok').show(); }
            else { this._$('#all_urls_ok').hide(); }
            this._$('button.ui-button-action').hide(); // Hide "Start/Continue" button

            this._$('#autoContinueDetails').hide();
        }else{
            this._$('#all_urls_verified').hide();
            this._$('button.ui-button-action').show(); // Show "Start/Continue" button

            this._$('#autoContinueDetails').show();
            this._startAutoStartInterval();
        }

        if(response_data.total_bad==0){
            div_res.find('button.btnCSV').hide();
        }else{
            div_res.find('button.btnCSV').button().show(); // Ensure button() is called if it was hidden
            this._on(div_res.find('.btnCSV'), {click:this._getPreviousSessionAsCSV}); // Rebind, or ensure it's bound once
        }

        this.prevSessionExists = (response_data.total_checked || 0) > 0; // Allow "Continue checking" to resume by default

        
        // After any run, default the radio selection to "continue" for the next click.
        if(this.prevSessionExists){
            this._$('input[name="mode"][value="0"]').prop('checked', true);
        }

        if(termination_message){
            let message_text = window.hWin.HEURIST4.util.isObject(termination_message)
                        ? termination_message.message
                        : termination_message;
            $(`<h3>`).text(message_text).appendTo(div_res.find('#session_summary'));
        }
    }
});