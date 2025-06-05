/**
* HFilter - A widget that is container for filter dialog (includig faceted search)
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/
import '../HBase/HBaseView.js';

$.widget( 'heurist.HFilter', $.heurist.HBaseView, {

    // Default options
    options: {
        svsID: 0,  //Saved Filter ID  to display
        //svsQuery: null  heurist query
        
        searchDomain: null
    },
    
    _savedFilters: {},
    
    /**
     * Cleanup function. Removes generated elements and event listeners.
     */
    _destroy: function() {
        // remove generated elements
        this._super();
    },
    
    /**
     * Initializes controls and triggers rendering.
     */    
    _initControls:function(){
        this._super();
        this.show();  
    },
    
    /**
     * Clears the content inside the container.
     */
    clearContent: function(){
        
        if (!this._initCompleted) return;

        let viewDiv = this.getContainer();
        $(viewDiv).empty();        
    },

    /**
     * Displays the record viewer.
     * @param {number} recID - The record ID to display (optional).
     */
    show: function(svsID) {
        this._super();

        // If a new record ID is provided, update and render content
        if (svsID !== this.options.svsID) {
            if (svsID > 0) this.options.svsID = svsID;
            this.doSearchByID( svsID );
        }
    },
    
    /**
     * Renders the record content inside the container.
     */
    renderContent: function() {
        const selectedRecID = this.options.svsID;

        // Validate the record ID
        if (!this.$H.isPositiveInt(selectedRecID)) {
            this.clearContent();
            return;
        }

        let viewDiv = this.getContainer();
        let request;
        
        $(viewDiv).html('FILTER '+selectedRecID);

    },
                
    //
    //
    //
    doSearchByID: function(svsID){
    
        if(this._savedFilters[svsID])
        {
            this.doSearch( this._savedFilters[svsID] );
        }
        else{
            //not found - try to find
            let that = this;
            
            let request = {
                    'a'          : 'search',
                    'entity'     : 'usrSavedSearches',
                    'details'    : 'full',
                    'svs_ID'     : svsID
            };
            
            //window.hWin.HAPI4.SystemMgr.ssearch_get( { svsIDs:svsID },
            window.hWin.HAPI4.EntityMgr.doRequest(request,
                (response)=>{
                    if(response.status == window.hWin.ResponseStatus.OK){
                        if(response.data){
                            let resp = new HRecordSet( response.data );
                            that._savedFilters[svsID] = resp.fld(resp.getFirstRecord(), 'svs_Query');
                            that.doSearch( that._savedFilters[svsID] );
                        }else{
                            window.hWin.HEURIST4.msg.showMsgFlash('Saved filter not found ( ID: '+svsID+' )');    
                        }
                    }
            });
        }
    },
    
    //
    //
    //
    doSearch: function( qsearch ){

        if ( !qsearch ) retunr;

            let params = window.hWin.HEURIST4.query.parseHeuristQuery( qsearch );
            
            let context_on_exit = null;
            
            let qname = '';
            
            let s = window.hWin.HRJ('ui_name', params, this.options.language);
            if(!window.hWin.HEURIST4.util.isempty(s)){
                 qname = s;
            } 
            
            if(params.type<0){

                window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise search due to corrupted parameters. '
                +'Please remove and re-create this search.'), null, window.hWin.HR('Warning'));
                return;
            }            
            
            if(params.type==3){ //isfaceted
            
                if(params['version']==2){
                    window.hWin.HEURIST4.msg.showMsgErr({
                        message: "This faceted search is in an old format. "
                                + "Please delete it and add a new one (right click in the saved search list). "
                                + "We apologise for this inconvenience, but we have added many new features to the facet search "
                                + "function and it was not cost-effective to provide backward compatibility (given the relative "
                                + "ease of rebuilding searches and the new features now available).",
                        error_title: 'Out dated facet formatting'
                    });
                    return;
                }
                
                let that = this;

                //suplementary filter for faceted search
                //if(that.options.sup_filter){
                //    params.sup_filter = that.options.sup_filter;
                //}
                
                //options for faceted search
                let noptions = { 
                    query_name: qname, 
                    params: params, 
                    showclosebutton: true, //this.showclosebutton,
                    showresetbutton: true, //(this.options.showresetbutton!==false),
                    search_realm: this.options.searchDomain,
                    // search_page: this.options.search_page,
                    // language: this.options.language,
                    hide_no_value_facets: true,  //this.options.hide_no_value_facets
                    onclose: ()=>{
                        that.close();
                    }
                };

                let viewDiv = $(this.getContainer());
                
                if(viewDiv.search_faceted('instance')){
                    viewDiv.search_faceted('option', noptions ); //assign new parameters
                }else{
                    viewDiv.search_faceted( noptions );
                }

            }else {

                let request = params;

                request.rules = window.hWin.HEURIST4.query.cleanRules(request.rules);
                
                //query is not defenied, but rules are - this is pure RuleSet - apply it to current result set
                if(window.hWin.HEURIST4.util.isempty(request.q)&&!window.hWin.HEURIST4.util.isempty(request.rules)){

                    if(this.currentSearch){
                        this.currentSearch.rules = window.hWin.HEURIST4.util.cloneJSON(request.rules);
                    }
                    
                    if(request.rulesonly===true) request.rulesonly = 1;
                    
                    //target is required
                    if(! window.hWin.HAPI4.RecordSearch.doApplyRules( this, request.rules, 
                                        (request.rulesonly>0)?request.rulesonly:0, this.options.searchDomain ) ){
                        window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('RuleSets require an initial search result as a starting point.'),
                            3000, window.hWin.HR('Warning'), ele);
                    }else{
                        //window.hWin.HAPI4.SystemMgr.user_log('search_Record_applyrules');
                    }
                    
                }else if(window.hWin.HEURIST4.util.isempty(request.q)){

                    window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise this search due to corrupted parameters. '
                        +'Please redefine filter parameters.'), null, window.hWin.HR('Warning'));                    
                
                    return;    
                }else{
                    //additional params
                    request.detail = 'detail';
                    request.source = this.element.attr('id');
                    request.qname = qname;
                    request.search_realm = this.options.searchDomain;
                    //TBD request.search_page = this.options.search_page;
                    
                    //window.hWin.HAPI4.SystemMgr.user_log('search_Record_savedfilter');
                    
                    //get hapi and perform search
                    window.hWin.HAPI4.RecordSearch.doSearch( this, request );
                }
                
            }


    },

    

});
