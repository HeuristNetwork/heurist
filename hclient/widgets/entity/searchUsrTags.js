/**
 * @file        searchUsrTags.js
 * @brief       Provides a search interface for User Tags.
 * @fileOverview This widget handles the search functionality for User Tags, allowing filtering by tag text and providing sorting options. It also includes a group filter that primarily affects UI display rather than the search query itself.
 * @project     Heurist academic knowledge management system
 *
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov <osmakov@gmail.com>
 * @author      Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since       4.0
 */



/**
 * @widget heurist.searchUsrTags
 * @brief Search widget for User Tags.
 * @extends $.heurist.searchEntity
 * @description This widget provides a user interface for searching user tags.
 *              It allows users to search by tag text and apply various sorting criteria.
 *              A user group filter is available, which triggers an "ongroupfilter" event
 *              for the parent manager to handle UI changes (e.g., accordion display) rather than
 *              directly filtering the tag search query itself.
 *
 * @listens heurist.searchUsrTags#ongroupfilter - Fired when the user group filter selection changes.
 *          Event data: `{string}` The selected group ID ('any', or a specific user group ID).
 * @listens heurist.searchUsrTags#onfilter - Inherited from `$.heurist.searchEntity`, triggered by `startSearch`.
 */
$.widget( "heurist.searchUsrTags", $.heurist.searchEntity, {

    /**
     * @brief Initializes the controls for the user tags search widget.
     * @override
     * @memberof heurist.searchUsrTags
     * @description Sets up the user group selection dropdown (`input_search_group`),
     *              radio buttons for sorting (by name, popularity, recent),
     *              and the main search input field. Helper text visibility is adjusted
     *              based on `options.select_mode`. Triggers an initial search or loads
     *              all data if `options.use_cache` is true.
     */
    _initControls: function() {
        this._super();
        
        this.input_search_group = this.element.find('#input_search_group');
        window.hWin.HEURIST4.ui.createUserGroupsSelect(this.input_search_group[0], null, 
            [{key:'any', title:window.hWin.HR('Any')},
             {key:window.hWin.HAPI4.currentUser['ugr_ID'], title:'Personal tags'}]);
        
        this.btn_search_start.css('float','right');   
        

        this.input_sort_name = this.element.find('#input_sort_name');
        this.input_sort_popular = this.element.find('#input_sort_popular');
        this.input_sort_recent =  this.element.find('#input_sort_recent');
        this._on(this.input_sort_name,  { change:this.startSearch });
        this._on(this.input_sort_popular,  { change:this.startSearch });
        this._on(this.input_sort_recent,  { change:this.startSearch });
        this._on(this.input_search_group,  { change:
            function(){
                this._trigger( "ongroupfilter", null, this.input_search_group.val());
            }
        });
        this._on( this.input_search, { keyup: this.startSearch });
        
        //hide all help divs except current mode
        let smode = this.options.select_mode; 
        this.element.find('.heurist-helper1 > span').hide();
        this.element.find('.heurist-helper1 > span.'+smode+',span.common_help').show();
        
        if(this.options.use_cache){
            this.startSearchInitial();            
        }else{
            this.startSearch();            
        }
    },  
    
    /**
     * @brief Initiates a search for user tags.
     * @override
     * @memberof heurist.searchUsrTags
     * @description Constructs a search request based on the tag text from `input_search`
     *              and the selected sort order (popularity, recent, or name).
     *              Note: The user group filter (`input_search_group`) does not directly add
     *              a condition to this search request; it triggers an "ongroupfilter" event instead.
     *              Triggers an "onfilter" event with the request, typically for client-side
     *              filtering as this widget often operates with `use_cache: true`.
     */
    startSearch: function(){
        
            let request = {}
        
            /* we don't filter by group - just hide acccordion
            if(this.input_search_group.val()!='any'){
                request['tag_UGrpID'] = this.input_search_group.val();    
            }
            */
            request['tag_Text'] = this.input_search.val();    
            
            if(this.input_sort_popular.is(':checked')){
                request['sort:tag_Usage'] = '-1';
            }else
            if(this.input_sort_recent.is(':checked')){
                request['sort:tag_Modified'] = '-1' 
            }else
            if(this.input_sort_name.is(':checked')){
                request['sort:tag_Text'] = '1' 
            }

            //if we use cache                
            this._trigger( "onfilter", null, request);
      
    },

});
