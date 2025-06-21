/**
* dbVerify.js - popup dialog for database verification actions
*
* @package     Heurist academic knowledge management system
* @subpackage  hclient\widgets\database
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0 
*/

/**
 * jQuery UI Widget: heurist.dbVerify
 *
 * This widget provides an interface for performing database verification checks.
 * It extends `$.heurist.dbAction` and specializes it for verification tasks.
 * Users can select various checks to perform, and the widget displays the results.
 *
 * @namespace heurist.dbVerify
 * @augments $.heurist.dbAction
 *
 * @property {object} _verification_actions - An object mapping verification action keys (used in requests)
 *                                            to their display names and properties (e.g., `slow:1`).
 *                                            Example: `{ dup_terms:{name:'Invalid/Duplicate Terms'}, ... }`
 */
$.widget( "heurist.dbVerify", $.heurist.dbAction, {

    /**
     * @property {object} _verification_actions - Defines the available verification checks.
     * @property {string} _verification_actions.<key>.name - Display name for the check.
     * @property {number} [_verification_actions.<key>.slow] - If 1, indicates a slow check, potentially handled differently in UI.
     * @private
     */
    _verification_actions: {
            dup_terms:{name:'Invalid/Duplicate Terms'},
            field_type:{name:'Field Types'},
            default_values:{name:'Default Values'},
            defgroups:{name:'Definitions Groups'},
            title_mask:{name:'Title Masks'},

            owner_ref:{name:'Record Owner/Creator'},
            pointer_targets:{name:'Pointer Targets'},
            target_parent:{name:'Invalid Parents'},
            empty_fields:{name:'Empty Fields'},
            nonstandard_fields:{name:'Non-Standard Fields'},

            dateindex:{name:'Date Index'},
            multi_swf_values:{name:'Multiple Workflow Stages'},

            geo_values:{name:'Geo Values'},
            term_values:{name:'Term Values'},
            expected_terms:{name:'Expected Terms'},

            target_types:{name:'Target Types', slow:1},
            required_fields:{name:'Required Fields', slow:1},
            single_value:{name:'Single Value Fields', slow:1},
            relationship_cache:{name:'Relationship Cache', slow:1},
            date_values:{name:'Date Values', slow:1},
            fld_spacing:{name:'Spaces in Values', slow:1},
            invalid_chars:{name:'Invalid Characters', slow:1}
    },

    /**
     * Initializes controls specific to the dbVerify widget.
     * Calls `_initVerification` to set up the verification checklist and then calls the superclass method.
     * This method is called by `_init` (via `_super()`) after HTML content is loaded.
     * @memberof heurist.dbVerify
     * @private
     */
    _initControls:function(){
        this._initVerification();
        return this._super();
    },

    /**
     * Gathers selected verification actions and sends a request to the server to perform them.
     * Updates the progress display with the list of actions being performed.
     * @memberof heurist.dbVerify
     */
    doAction: function(){
        let request;
        let actions=[];
        let cont_steps = this._$('.progressbar_div > .loading > ol');
        cont_steps.empty(); // Clear previous steps

        // Collect selected verification actions
        this._$('.verify-actions:checked').each((i, item)=>{
            let action_key = item.value; // Corrected variable name from 'action' to 'action_key' to avoid conflict
            actions.push(action_key);
            $('<li>'+this._verification_actions[action_key].name+'</li>').appendTo(cont_steps);
        });

        // Add a terminate button to the progress display
        let btn_stop = $('<button class="ui-button-action" style="margin-top:10px">Terminate</button>').appendTo(cont_steps);
        btn_stop.button();
        this._on(btn_stop,{click:function(){
            let progress_url = window.hWin.HAPI4.baseURL + "hserv/controller/progress.php";
            let terminate_request = {terminate:1, t:(new Date()).getMilliseconds(), session:this._session_id}; // Renamed request to avoid conflict
            let that = this;
            window.hWin.HEURIST4.util.sendRequest(progress_url, terminate_request, null, function(response){
                that._session_id = 0; // Invalidate session ID on termination
                that._hideProgress();
            });
        }});

        request = {checks: actions.length==Object.keys(this._verification_actions).length?'all':actions.join(',')};
        this._sendRequest(request);
    },


    /**
     * Handles the server response after a verification action is completed.
     * Displays the results, including any termination messages.
     * Calls `_initVerificationResponse` to process and display the detailed verification report.
     * Remark: Method name has a typo "EvenHandler", should be "EventHandler".
     * @memberof heurist.dbVerify
     * @private
     * @param {object} response_data - The `data` part of the server response containing verification results.
     * @param {string|object} termination_message - A message or error object from the server about the action's outcome.
     */
    _afterActionEventHandler: function( response_data, termination_message ){
        this._$('.ent_wrapper').hide(); // Hide the form/selection part
        this._$("#div_result").show(); // Show the results area

        this._initVerificationResponse(response_data);

        if(termination_message){
            let error = window.hWin.HEURIST4.util.isObject(termination_message)
                        ? termination_message
                        : {message: termination_message};
            error['error_title'] = window.hWin.HEURIST4.util.isempty(error['error_title']) ? 'Verification terminated' : error['error_title'];
            window.hWin.HEURIST4.msg.showMsgErr(error);
        }
    },

    /**
     * Initializes the list of available verification actions in the UI.
     * Dynamically creates checkboxes for each action defined in `_verification_actions`.
     * Sets up event handlers for "Mark all" checkboxes and buttons for slow checks.
     * @memberof heurist.dbVerify
     * @private
     */
    _initVerification: function(){
        let cont1 = this._$('#actions'); // Container for regular checks
        let cont2 = this._$('#actions_slow'); // Container for slow checks

        for (const action_key in this._verification_actions){ // Iterate using action_key
           let is_slow = (this._verification_actions[action_key].slow==1);
           let cont = (is_slow)?cont2:cont1;
           $('<li><label><input type="checkbox" '+(is_slow?'data-slow="1"':'checked')+' class="verify-actions" value="'+action_key+'">'
                +this._verification_actions[action_key].name+'</label></li>').appendTo(cont);
        }

        // "Mark all" checkbox for regular actions
        this._on(this._$('input[data-mark-actions]'),{click:(event)=>{
            let is_checked = $(event.target).is(':checked');
            // Only affect non-slow checks with this "Mark all"
            this._$('input.verify-actions').filter(function() { return $(this).attr('data-slow') != "1"; }).prop('checked',is_checked);
        }});

        this._$("#div_result").css('overflow-y','auto'); // Ensure results area is scrollable

        if(window.hWin.HAPI4.sysinfo.db_total_records>100000){
            this._$('#notice_for_large_database').show();
        }

        // Buttons for initiating very slow checks (files, URLs) in a separate popup/dialog
        this._$('div.slow-checks-in-popup > button').button();
        this._on(this._$('div.slow-checks-in-popup > button'),{click:(event)=>{
                let type = $(event.currentTarget).attr('data-type'); // Use currentTarget
                if(type != 'files' && type != 'urls') { return; }

                let body = $(window.hWin.document).find('body');
                let screen_height = window && window.innerHeight && window.innerHeight > body.innerHeight() ?
                                    window.innerHeight : body.innerHeight();
                let opts = {height:screen_height*0.8, width:body.innerWidth()*0.8};

                window.hWin.HEURIST4.msg.showDialog(
                    `${window.hWin.HAPI4.baseURL}admin/verification/longOperationInit.php?type=${type}&db=${window.hWin.HAPI4.database}`
                    , opts);
        }});

        this._$('#btnVerifyURLs').button(); // Standard verify URLs button
        this._on(this._$('#btnVerifyURLs'),{click:(event)=>{
                window.hWin.HAPI4.actionHandler.executeActionById('menu-database-verifyURLs');
        }});
    },

    /**
     * Initializes and displays the response from a database verification action.
     * Formats the results into a tabbed interface, where each tab represents a verification check.
     * Sets up "Fix" buttons and "Mark all" / "Show selected/all" links within the results.
     * @memberof heurist.dbVerify
     * @private
     * @param {object} response - The data object received from the server, where keys are action names
     *                            and values are objects containing `status` and `message` (HTML report).
     */
    _initVerificationResponse: function(response){
            this._session_id = 0; // Reset session ID as verification is complete or reloaded

            let div_res = this._$("#div_result");
            let is_reload = false;

            if(response['reload']){ // Check if this is a reload of a single fix action
                is_reload = response['reload'];
                delete response['reload'];
            }

            if(is_reload){ // Update a single tab if it's a reload after a fix
                let action_key = Object.keys(response)[0]; // Assuming only one action result in reload
                let res = response[action_key];

                div_res.find('a[href="#'+action_key+'"]').parent() // Tab header
                    .css("background-color", res['status']?'#6AA84F':'#E60000'); // Green for OK, Red for issues
                div_res.find('#'+action_key).empty().append($(res['message'])); // Update tab content

                if (div_res.find('#linkbar').data('ui-tabs')) { // Check if tabs are initialized
                    div_res.find('#linkbar').tabs('refresh');
                }
            }else{ // Initial display of all verification results
                div_res.empty(); // Clear previous results
                let tabs = $('<div id="linkbar" style="margin:5px;"><ul id="links"></ul></div>').appendTo(div_res);
                let tab_header = div_res.find('#links');

                for (const [action_key, res] of Object.entries(response)) {
                    if (!this._verification_actions[action_key]) continue; // Skip if action_key is not in known list
                    $('<li style="background-color:'+(res['status']?'#6AA84F':'#E60000')+'"><a href="#'+action_key
                        +'" style="white-space:nowrap;padding-right:10px;color:black;">'
                        + this._verification_actions[action_key].name +'</a></li>')
                        .appendTo(tab_header);
                    $('<div id="'+action_key+'" style="top:110px;padding:5px !important">'+res['message']+'</div>').appendTo(tabs);
                }
                tabs.tabs(); // Initialize jQuery UI Tabs
            }

            // "FIX" button handler
            this._on(this._$('button[data-fix]').button(),{click:(event)=>{
                let action_to_fix = $(event.currentTarget).attr('data-fix'); // Use currentTarget
                let request_params = {checks: action_to_fix, fix:1, reload:1}; // Prepare request for fixing
                let marker = $(event.currentTarget).attr('data-selected');
                let sel_ids = [];

                if(marker){ // If specific record IDs can be selected for fixing
                    this._$('input[name="'+marker+'"]:checked').each((i,item)=>{
                        sel_ids.push(item.value);
                    });
                    if(sel_ids.length==0){
                        window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('Select one record at least'));
                        return;
                    }
                    request_params['recids'] = sel_ids.join(',');
                }

                let cont_steps = this._$('.progressbar_div > .loading > ol');
                cont_steps.empty(); // Clear progress steps
                $('<li>'+this._verification_actions[action_to_fix].name+'</li>').appendTo(cont_steps);
                this._sendRequest(request_params); // Send the fix request
            }});

            // "Mark all" checkbox for items within a result tab
            this._on(this._$('input[data-mark-all]'),{click:(event)=>{
                let ele = $(event.currentTarget); // Use currentTarget
                let name = ele.attr('data-mark-all');
                let is_checked = ele.is(':checked');
                this._$('input[name="'+name+'"]').prop('checked',is_checked);
            }});

            // "Show selected" link
            this._on(this._$('a[data-show-selected]'),{click:(event)=>{
                event.preventDefault(); // Prevent default link behavior
                let name = $(event.currentTarget).attr('data-show-selected');
                let sels = this._$('input[name="'+name+'"]:checked');
                let ids = [];
                sels.each((i,item)=>{ ids.push(item.value); });
                if(ids.length>0){
                    window.open( `${window.hWin.HAPI4.baseURL_pro}?db=${window.hWin.HAPI4.database}&w=all&q=ids:${ids.join(',')}`, '_blank' );
                }
                return false;
            }});

            // "Show All" link
            this._on(this._$('a[data-show-all]'),{click:(event)=>{
                event.preventDefault();
                let name = $(event.currentTarget).attr('data-show-all');
                let sels = this._$('input[name="'+name+'"]'); // All inputs for this category
                let ids = [];
                sels.each((i,item)=>{ ids.push(item.value); });
                if(ids.length>0){
                    window.open( `${window.hWin.HAPI4.baseURL_pro}?db=${window.hWin.HAPI4.database}&w=all&q=ids:${ids.join(',')}`, '_blank' );
                }
                return false;
            }});
    }


});