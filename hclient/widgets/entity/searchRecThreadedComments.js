/**
 * @file        searchRecThreadedComments.js
 * @brief       Provides a search interface for Threaded Comments on records.
 * @fileOverview This widget handles the search functionality for threaded comments, allowing users to find specific comments associated with records.
 * @project     Heurist academic knowledge management system
 * @package  hclient\widgets\entity
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       4.0
 */



/**
 * @widget heurist.searchRecThreadedComments
 * @brief Search widget for Threaded Comments.
 * @extends $.heurist.searchEntity
 * @description This widget provides a user interface for searching threaded comments.
 *              Users can search by comment text and specify sort order.
 *              It relies on options and events inherited from `$.heurist.searchEntity`.
 *              A `rec_ID` option can be provided by the instantiating manager to filter comments for a specific record,
 *              which would be typically handled by the manager by setting `initial_filter` on this search widget.
 */
$.widget( "heurist.searchRecThreadedComments", $.heurist.searchEntity, {

    /**
     * @brief Initializes the controls for the threaded comments search widget.
     * @override
     * @memberof heurist.searchRecThreadedComments
     * @description Sets up sort option inputs and triggers an initial search.
     */
    _initControls: function() {
        this._super();
        
        this.btn_search_start.css('float','right');   
        
        this.input_sort_rectitle = this.element.find('#input_sort_rectitle');
        this.input_sort_sdate = this.element.find('#input_sort_sdate');
        this._on(this.input_sort_rectitle,  { change:this.startSearch });
        this._on(this.input_sort_sdate,  { change:this.startSearch });
       
        
        this.startSearch();            
    },  
    
    /**
     * @brief Initiates a search for threaded comments.
     * @override
     * @memberof heurist.searchRecThreadedComments
     * @description Constructs a search request based on the comment text from `input_search`
     *              and the selected sort order (by record title or by modification date).
     *              If no search text is provided and no sort options are checked, it triggers
     *              an "onresult" event with an empty HRecordSet. Otherwise, it populates
     *              `this._search_request` and calls the parent's `startSearch` method.
     */
    startSearch: function(){
        
            let request = {}
        
            request['cmt_Text'] = this.input_search.val();    
            
            if(this.input_sort_rectitle.is(':checked')){
                request['sort:cmt_RecTitle'] = '1';
            }else
            if(this.input_sort_sdate.is(':checked')){
                request['sort:cmt_Modified'] = '-1' 
            }

            if($.isEmptyObject(request)){
                this._trigger( "onresult", null, {recordset:new HRecordSet()} );
            }else{
                request['details']   = 'list';
                this._search_request = request;
                this._super();
            }  
                     
    },

});
