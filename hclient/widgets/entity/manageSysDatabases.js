/**
* @file manageSysDatabases.js
* @brief Manages System Database registrations and configurations.
* @fileOverview Provides a UI for administrators to register, configure, and manage databases accessible by the Heurist instance. This includes settings related to database connections, aliases, and status.
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
 * @widget heurist.manageSysDatabases
 * @brief Widget for managing System Database registrations.
 * @extends $.heurist.manageEntity
 * @description This widget provides an interface for administrators to view and manage
 * the list of databases registered with the Heurist instance.
 * It is primarily used for listing databases; direct editing capabilities via the
 * standard `manageEntity` form are disabled (`edit_mode: 'none'`).
 *
 * @property {string} default_palette_class Default CSS class for theming, set to 'ui-heurist-design'.
 * @property {number} width Default width of the widget, set to 800 pixels.
 * @property {number} height Default height of the widget, set to 600 pixels.
 * @property {string} edit_mode Set to 'none', indicating that the standard inline/popup editing form of `manageEntity` is not used. Management actions might be handled through custom actions or list item interactions.
 */
$.widget( "heurist.manageSysDatabases", $.heurist.manageEntity, {
    
    
    _entityName:'sysDatabases',
    
    /**
     * @brief Initializes the widget.
     * @override
     * @memberof heurist.manageSysDatabases
     * Sets default options for palette class, dimensions, and importantly,
     * sets `edit_mode` to 'none' as this widget is primarily for listing.
     */
    _init: function() {
  
        this.options.default_palette_class = 'ui-heurist-design';
        
        this.options.width = 800;
        this.options.height = 600;
        this.options.edit_mode = 'none';

        this._super();
    },

    _fullRecordset: null, // complete list of databases without filtering
    _email_filter: false, // using user email to filter
    
    //  
    /**
     * @brief Initializes the controls for the widget.
     * @override
     * @memberof heurist.manageSysDatabases
     * @returns {boolean} False if the parent `_initControls` fails, otherwise true.
     * Sets `use_cache` to true. Initializes the search form (`searchSysDatabases`)
     * and customizes the record list header. It also fetches and caches the full list
     * of databases on initialization since filtering is done client-side.
     */
    _initControls: function() {
        
        this.options.use_cache = true;
        
        if(!this._super()){
            return false;
        }
        
        if(this.searchForm && this.searchForm.length>0){
            this.searchForm.searchSysDatabases(this.options);   
            if(this.options.subtitle){
                this.recordList.css('top',80);
            }
        }
        
        this.recordList.resultList('option','rendererHeader',
                    function(){
       let sHeader = '<div style="width:60px"></div><div style="border-right:none">Db Name</div>';
        /*
                //+'<div style="width:3em">Ver</div>'
                +'<div style="width:3em">Reg#</div>'
                +'<div style="width:20em">Title</div>'
                +'<div style="width:5em">Role</div>'
                +'<div style="width:5em">Users</div>'; */
                
                return sHeader;
                    }
                );
        
        
        if(this.options.isdialog){
            window.hWin.HEURIST4.msg.bringCoverallToFront(this._as_dialog.parents('.ui-dialog')); 
        }
        

        //load at once everything to _cachedRecordset
        let that = this;
        function __onDataResponse(response){

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            that._cachedRecordset = response;
            that._fullRecordset = response;

            that.recordList.resultList('updateResultSet', response);
        };
            
        let entityData = window.hWin.HAPI4.EntityMgr.getEntityData2( this.options.entity.entityName );

        
        if($.isEmptyObject(entityData)){
        
            window.hWin.HAPI4.EntityMgr.doRequest(
                {a:'search', 'entity':this.options.entity.entityName, 'details':'ids'},
                       function(response){
                            if(response.status == window.hWin.ResponseStatus.OK){
                                entityData = new HRecordSet(response.data);
                                window.hWin.HAPI4.EntityMgr.setEntityData(
                                            that.options.entity.entityName,
                                            entityData);

                                __onDataResponse(entityData);
                            }else{
                                window.hWin.HEURIST4.msg.showMsgErr(response);
                            }
                       });
        
        }else{
            __onDataResponse(entityData);
        }
        
        /*
        window.hWin.HAPI4.EntityMgr.getEntityData(this.options.entity.entityName, false,
            function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                
                that._cachedRecordset = response;
                
                that.filterRecordList(null, {});
               
            });
        */    
            
        //and then filter locally    
        this._on( this.searchForm, {
                "searchsysdatabasesonfilter": this.filterRecordList
                });
                
        return true;
    },    
    
    //----------------------
    //
    /**
     * @brief Renders a single database item in the list.
     * @override
     * @memberof heurist.manageSysDatabases
     * @param {HRecordSet} recordset The recordset containing the item.
     * @param {object} record The specific record object for the item to render.
     * @returns {string} HTML string representing the database item.
     * Formats the display of a database, showing its name (`sys_Database`, with prefix removed)
     * and an icon.
     */
    _recordListItemRenderer: function(recordset, record){
        
        function fld(fldname){
            return window.hWin.HEURIST4.util.htmlEscape(recordset.fld(record, fldname));
        }
        function frm(value, col_width){
            let swidth = '';
            if(!window.hWin.HEURIST4.util.isempty(col_width)){
                swidth = ' style="width:'+col_width+'"';
            }
            //value = window.hWin.HEURIST4.util.htmlEscape(value);
            return '<div class="item" '+swidth+'>'+value+'</div>';  //title="'+val+'"
        }
        
        
        let recID   = fld('sys_Database');
        
        let dbName = fld('sys_dbName');
        if(dbName=='Please enter a DB name ...') dbName = '';
        
        let recTitle = recID; //remove prefix hdb_
        if(recTitle.indexOf(window.hWin.HAPI4.sysinfo.database_prefix)==0){
            recTitle = recTitle.substring(window.hWin.HAPI4.sysinfo.database_prefix.length);
        }
        recTitle= frm(recTitle, '40em');
        
       
        let rtIcon = window.hWin.HAPI4.getImageUrl(this._entityName, 0, 'icon');
        let recThumb = window.hWin.HAPI4.getImageUrl(this._entityName, recID, 'thumb');
        
        let html_thumb = '<div class="recTypeThumb" style="background-image: url(&quot;'+recThumb+'&quot;);opacity:1">'
        +'</div>';
        
        let html = '<div class="recordDiv" id="rd'+recID+'" recid="'+recID+'" data-value="'+ fld('sus_Role')+'">'
        + html_thumb
        + '<div class="recordSelector"><input type="checkbox" /></div>'
        + '<div class="recordIcons">' //recid="'+recID+'" bkmk_id="'+bkm_ID+'">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" style="background-image: url(&quot;'+rtIcon+'&quot;);">'
        + '</div>'
        + '<div class="recordTitle">'  // title="'+recTitleHint+'"
        +     recTitle 
        + '</div>';


        return html+'</div>';
        
    },

    /**
     * @brief Updates the record list with new data.
     * @override
     * @memberof heurist.manageSysDatabases
     * @param {Event} event The event object.
     * @param {object} data Data containing the recordset and request.
     * If `options.use_cache` is true, it updates `_cachedRecordset`.
     * Then calls the parent `resultList('updateResultSet')`.
     */
    updateRecordList: function( event, data ){
       
        if (data){
            if(this.options.use_cache){
                this._cachedRecordset = data.recordset;
                //there is n filter feature in this form - thus, show list directly
            }
            this.recordList.resultList('updateResultSet', data.recordset, data.request);
        }
    },
    
    /**
     * @brief Filters the displayed list of databases based on the request.
     * @override
     * @memberof heurist.manageSysDatabases
     * @param {Event} event The event object.
     * @param {object} request The filter request object, may contain `ugr_eMail`.
     * If the `ugr_eMail` filter has changed, it calls `filterByEmail`.
     * Otherwise, it applies other filters from the request to the `_cachedRecordset`
     * (or `_fullRecordset` if email filter was just cleared) and updates the list.
     * It can also exclude the current database if `options.except_current` is true.
     */
    filterRecordList: function(event, request){

        let filter_email = this._email_filter != request.ugr_eMail;

        if(filter_email){
            this._email_filter = request.ugr_eMail;
            this.filterByEmail(request);
        }else if(this.options.except_current === true){

            delete request.ugr_eMail;

            let subset = this._cachedRecordset.getSubSetByRequest(request, this.options.entity.fields);
            //except current
            subset = subset.getSubSetByRequest({'sys_Database': `!=${window.hWin.HAPI4.database}`}, 
                            this.options.entity.fields);
            //update
            this.recordList.resultList('updateResultSet', subset, request);   
        }else{
            this._super(event, request); 
        }
    },

    /**
     * @brief Filters the database list by user email.
     * @memberof heurist.manageSysDatabases
     * @param {object} filter The filter object, expected to contain `ugr_eMail`.
     * If `ugr_eMail` is empty, it resets the cache to the full list of databases and re-filters.
     * If `ugr_eMail` is provided, it makes a server request to fetch databases associated
     * with that email, updates `_cachedRecordset` with the response, and then re-filters the list.
     */
    filterByEmail: function(filter){

        let that = this;

        if(!this._email_filter){ // reset filter, use saved full list of databases
            this._cachedRecordset = this._fullRecordset;
            this.filterRecordList(null, filter);
            return;
        }

        let request = $.extend({}, {
            a: 'search',
            entity: this.options.entity.entityName,
            db: window.hWin.HAPI4.database,
            request_id: window.hWin.HEURIST4.util.random()
        }, filter);

        window.hWin.HEURIST4.msg.bringCoverallToFront();

        window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

            window.hWin.HEURIST4.msg.sendCoverallToBack();

            if(response.status !== window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            let recordset = new HRecordSet(response.data);

            that._cachedRecordset = recordset;

            that.filterRecordList(null, filter);
        })
    },

    /**
     * @brief Handles selection and closing of the dialog.
     * @override
     * @memberof heurist.manageSysDatabases
     * Stores the current email filter (`this._email_filter`) in `this._resultOnSelection`
     * before calling the parent's `_selectAndClose` method. This might be used by consuming
     * widgets to know the context of the selection.
     */
    _selectAndClose: function(){

        if(!this._resultOnSelection){
            this._resultOnSelection = {};
        }

        this._resultOnSelection.email = this._email_filter;

        this._super();
    }
    
});
