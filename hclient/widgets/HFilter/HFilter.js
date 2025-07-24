/**
* HFilter - widget for search form and execution of filters
* 
* HFilter generates a search form to define a search query. At the moment it uses the search_faceted widget.
* 
* As a descendant of HBaseView the search form can be presented in the provided container (inline div), float or modal popup or offcanvas(side slide panel).
* 
* This widget is integrated with HMenu and its properties can be defined along with its property editor. For standalone mode, the saved filter ID can be obtained from HMenu (via search group link ON_ACTION event) or defined as a widget property.
* 
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Artem Osmakov   <osmakov@gmail.com>
* @version     7.0
*/
import '../HBase/HBaseView.js';

$.widget( 'heurist.HFilter', $.heurist.HBaseView, {

    // Default options
    options: {
        svsID: 0,  //Saved Filter ID to display
        searchDomain: null // reference to entity HSearchDomains
    },

    currentSearch: null,    
    _savedFilters: {},  //cache
    
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
        this.doSearchByID(this.options.svsID);  
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
     * Gets saved filter parameters and calls doSearch
     * @param {number} svsID - Saved filter ID
     */
    doSearchByID: function(svsID){
        
        if(this._savedFilters[svsID])
        {
            this.doSearch( svsID, this._savedFilters[svsID] );
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
            
            //this.HAPI.SystemMgr.ssearch_get( { svsIDs:svsID },
            this.HAPI.EntityMgr.doRequest(request,
                (response)=>{
                    if(response.status == window.hWin.ResponseStatus.OK){
                        if(response.data){
                            let resp = new HRecordSet( response.data );
                            that._savedFilters[svsID] = resp.fld(resp.getFirstRecord(), 'svs_Query');
                            that.doSearch( svsID, that._savedFilters[svsID] );
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
    doSearch: function( svsID, qsearch ){

            if ( !qsearch ) return;

            let params = window.hWin.HEURIST4.query.parseHeuristQuery( qsearch );
            
            let qname = '';
            
            let s = window.hWin.HRJ('ui_name', params, this.options.language);
            if(!this.$H.isempty(s)){
                 qname = s;
            } 
            
            if(params.type<0){

                window.hWin.HEURIST4.msg.showMsgDlg(window.hWin.HR('Cannot initialise search due to corrupted parameters. '
                +'Please remove and re-create this search.'), null, window.hWin.HR('Warning'));
                return;
            }            
            
            if(params.type==3){ //isfaceted
            
                if(params['version']!=2){
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

                
                let viewDiv = $(this.getContainer());
                if(this.options.viewMode=='inline'){
                    viewDiv.css({'min-height':'300px',background:'white'}).show();
                }else{
                    this.show();    
                }
                
                if(this.options.svsID==svsID && viewDiv.search_faceted('instance')){
                    return;
                }
                
                this.options.svsID=svsID;
                //this.clearContent();
                
                //options for faceted search
                let noptions = { 
                    query_name: qname, 
                    params: params, 
                    showclosebutton: true, //this.showclosebutton,
                    showresetbutton: true, //(this.options.showresetbutton!==false),
                    search_realm: this.options.searchDomain,
                    // search_page: this.options.search_page,
                    // language: this.options.language,
                    is_publication: true,
                    hide_no_value_facets: true,  //this.options.hide_no_value_facets
                    onclose: ()=>{
                        if(that.options.viewMode=='inline'){
                            $(that.getContainer()).hide();
                        }else{
                            that.close();
                        }
                    }
                };
                
                if(viewDiv.search_faceted('instance')){
                    viewDiv.search_faceted('option', noptions ); //assign new parameters
                }else{
                    viewDiv.search_faceted( noptions );
                }

            }else {
                
                this.close();

                let request = params;

                request.rules = window.hWin.HEURIST4.query.cleanRules(request.rules);
                
                //query is not defenied, but rules are - this is pure RuleSet - apply it to current result set
                if(this.$H.isempty(request.q)&&!this.$H.isempty(request.rules)){

                    //TBR
                    //if(this.currentSearch){
                    //    this.currentSearch.rules = this.$H.cloneJSON(request.rules);
                    //}
                    
                    if(request.rulesonly===true) request.rulesonly = 1;
                    
                    //target is required
                    if(! this.HAPI.RecordSearch.doApplyRules( this, request.rules, 
                                        (request.rulesonly>0)?request.rulesonly:0, this.options.searchDomain ) ){
                        window.hWin.HEURIST4.msg.showMsgFlash(window.hWin.HR('RuleSets require an initial search result as a starting point.'),
                            3000, window.hWin.HR('Warning'));
                    }else{
                        //this.HAPI.SystemMgr.user_log('search_Record_applyrules');
                    }
                    
                }else if(this.$H.isempty(request.q)){

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
                    
                    //this.HAPI.SystemMgr.user_log('search_Record_savedfilter');
                    
                    //get hapi and perform search
                    this.HAPI.RecordSearch.doSearch( this, request );
                }
                
            }


    },

    

});
