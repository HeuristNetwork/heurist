/**
 * @file        searchUsrReminders.js
 * @brief       Provides a search interface for User Reminders.
 * @fileOverview This widget handles the search functionality for User Reminders, allowing filtering by message text and reminder type (Workgroup, User, Email), and sorting options.
 * @package     Heurist academic knowledge management system
 * @subpackage  hclient\widgets\entity
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       4.0
 */



/**
 * @widget heurist.searchUsrReminders
 * @brief Search widget for User Reminders.
 * @extends $.heurist.searchEntity
 * @description This widget provides a user interface for searching user reminders.
 *              It allows users to filter reminders by their type (Workgroup, User, Email),
 *              search by message content, and apply various sorting criteria.
 */
$.widget( "heurist.searchUsrReminders", $.heurist.searchEntity, {

    /**
     * @brief Initializes the controls for the user reminders search widget.
     * @override
     * @memberof heurist.searchUsrReminders
     * @description Sets up the search input field, a dropdown to filter by reminder group/type
     *              (`input_search_group`), and radio buttons for sorting by record title,
     *              start date, or recent modification. Triggers an initial search.
     */
    _initControls: function() {
        this._super();
        
        this.btn_search_start.css('float','right');   
        
        this.input_search_group = this.element.find('#input_search_group');
        this.input_sort_rectitle = this.element.find('#input_sort_rectitle');
        this.input_sort_sdate = this.element.find('#input_sort_sdate');
        this.input_sort_recent =  this.element.find('#input_sort_recent');
        this._on(this.input_sort_rectitle,  { change:this.startSearch });
        this._on(this.input_sort_sdate,  { change:this.startSearch });
        this._on(this.input_sort_recent,  { change:this.startSearch });
        this._on(this.input_search_group,  { change:this.startSearch });
       
        
        this.startSearch();            
    },  
    
    /**
     * @brief Initiates a search for user reminders.
     * @override
     * @memberof heurist.searchUsrReminders
     * @description Constructs a search request based on the selected reminder type
     *              (Workgroup, User, Email from `input_search_group`), message text from `input_search`,
     *              and the chosen sort order. If no search criteria are provided, it triggers
     *              an "onresult" event with an empty HRecordSet. Otherwise, it populates
     *              `this._search_request` and calls the parent's `startSearch` method.
     */
    startSearch: function(){
        
            let request = {}
        
            let val = this.input_search_group.val();
            if(val!='any'){
                if(val=='Workgroup') request['rem_ToWorkgroupID'] = '-NULL'; // Assumes -NULL means 'is not null' or similar logic server-side
                else if(val=='User') request['rem_ToUserID'] = '-NULL';
                else if(val=='Email') request['rem_ToEmail'] = '-NULL';
            }
            
            request['rem_Message'] = this.input_search.val();    
            
            if(this.input_sort_rectitle.is(':checked')){
                request['sort:rem_RecTitle'] = '1';
            }else
            if(this.input_sort_recent.is(':checked')){
                request['sort:rem_Modified'] = '-1' 
            }else
            if(this.input_sort_sdate.is(':checked')){
                request['sort:rem_StartDate'] = '-1' 
            }

            if($.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new HRecordSet()} );
            }else{
                
                this._search_request = request;
                this._super();
            }  
                     
    },

});
