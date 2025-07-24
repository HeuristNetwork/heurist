/**
* @file manageDefGroups.js
* @brief Manages generic group entities.
* @fileOverview Provides a base UI widget for managing generic group structures within Heurist. This widget is typically extended by more specific group management widgets (e.g., for Detail Type Groups, Record Type Groups). It handles common functionalities like listing, creating, editing, deleting, and reordering groups.
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
 * @widget heurist.manageDefGroups
 * @brief Base widget for managing generic group entities.
 * @augments $.heurist.manageEntity
 * @property {string} [default_palette_class='ui-heurist-design'] Default palette class for the widget.
 * @property {boolean} [innerTitle=false] Whether to display an inner title within the widget.
 * @property {string} [layout_mode='short'] The layout mode for the widget.
 * @property {boolean} [use_cache=true] Whether to use caching for entity data.
 * @property {string} select_mode Determines selection behavior. If not 'manager', edit_mode is set to 'none' and width is adjusted.
 * @property {string} edit_mode Determines editing behavior. Can be 'inline' or 'popup'. If select_mode is not 'manager', this is set to 'none'.
 * @property {number} width Default width of the widget, adjusted based on select_mode and edit_mode.
 * @property {boolean} isFrontUI If true, adapts UI for front-end display, adjusting layout and adding specific controls.
 * @property {?function} onSelect Callback function executed when a group is selected, particularly relevant when select_mode is not 'manager'.
 * @property {?Array<number|string>} selection_on_init An array of record IDs to pre-select when the widget initializes.
 */
$.widget( "heurist.manageDefGroups", $.heurist.manageEntity, {
    
    _entityName: 'to be specified in descendant', // To be overridden by child widgets
    _entityPrefix: '', // To be overridden by child widgets, e.g., 'rtg' for Record Type Groups, 'dtg' for Detail Type Groups
    _title:'', // To be overridden by child widgets, used as the title in isFrontUI mode
    
    /**
     * @brief Initializes the widget.
     * @memberof heurist.manageDefGroups
     * @override
     * @description Sets default options, adjusts UI based on `select_mode` and `isFrontUI`,
     * and registers an event listener for `ON_STRUCTURE_CHANGE` to refresh data.
     */
    _init: function() {

        this.options.default_palette_class = 'ui-heurist-design';

        this.options.innerTitle = false;
        
        if(!this.options.layout_mode) this.options.layout_mode = 'short';
        this.options.use_cache = true;
        
        if(this.options.select_mode!='manager'){
            this.options.edit_mode = 'none';
            this.options.width = 300;
        }else if(this.options.edit_mode == 'inline') {
            this.options.width = 890;
        }
        
        this._super();
        
        if(this.options.select_mode!='manager'){
            //hide form 
            this.editForm.parent().hide();
            this.recordList.parent().css('width','100%');
        }

        if(this.options.isFrontUI){
            this.recordList.css('top','80px');  
        }else{
            this.recordList.css('top',0);  
        }        
        
        let that = this;

        //refresh list        
        $(window.hWin.document).on(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE, 
            function(e, data) { 
                if(!data || 
                   (data.source != that.uuid && data.type == that._entityPrefix))
                {
                    that._loadData();
                }
            });
        
    },
    
    /**
     * @brief Cleans up the widget upon destruction.
     * @memberof heurist.manageDefGroups
     * @override
     * @description Removes the `ON_STRUCTURE_CHANGE` event listener.
     */
    _destroy: function() {
       $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_STRUCTURE_CHANGE);
       this._super(); 
    },
    
    /**
     * @brief Placeholder for handling drop events when an item is moved to a group.
     * @memberof heurist.manageDefGroups
     * @param {number} type_ID The ID of the item being dropped.
     * @param {number} group_ID The ID of the group it's dropped onto.
     * @description This method is intended to be overridden by descendant widgets
     * to implement specific logic for when an item (e.g., a Record Type or Detail Type)
     * is dragged and dropped onto a group in the list.
     */
    _addOnDrop: function(type_ID, group_ID){
        //to be implemented in descendant 
    },
    
    /**
     * @brief Initializes the controls for the widget.
     * @memberof heurist.manageDefGroups
     * @override
     * @description Sets up the record list with sortable and droppable capabilities.
     * If `isFrontUI` is true, it configures toolbar buttons for adding groups and saving order.
     * Loads initial data.
     * @returns {boolean} False if the parent's `_initControls` fails, otherwise true.
     */
    _initControls: function() {

        if(!this._super()){
            return false;
        }
        
        if(this.options.edit_mode=='editonly'){
            this._initEditorOnly( $Db[this._entityPrefix]() );
            return;
        }

        let that = this;
        
        this.recordList.resultList({
            show_toolbar:false,
            sortable: true,
            empty_remark: 'Add new group',
            onSortStop: function(){
                that._onActionListener(null, 'save-order');
               
            },
            droppable: function(){   //change group for record type
                
                that.recordList.find('.recordDiv')  //.recordDiv, ,.recordDiv>.item
                    .droppable({
                        //accept: '.rt_draggable',
                        scope:  that._entityPrefix+'_change',  
                        hoverClass: 'ui-drag-drop',
                        drop: function( event, ui ){

                            let trg = $(event.target).hasClass('recordDiv')
                                        ?$(event.target)
                                        :$(event.target).parents('.recordDiv');
                                        
                            let type_ID = $(ui.draggable).parent().attr('recid');
                            let group_ID = trg.attr('recid');

                            that._addOnDrop(type_ID, group_ID);                            
                    }});
            },
            sortable_opts: {
                axis: 'y'
            }
        });
        

        if(this.options.isFrontUI){
            //specify add new/save order buttons above record list
            let btn_array = [
                {showLabel:true, icon:'ui-icon-plus',label:window.hWin.HR('Add'),
                      css:{'margin':'5px','float':'left',padding:'3px'}, class:'btnAddButton',
                      click: function() { that._onActionListener(null, 'add'); }},

                {label:window.hWin.HR('Save'),
                          css:{'margin-right':'0.5em','float':'left',display:'none'}, class:'btnApplyOrder',
                      click: function() { that._onActionListener(null, 'save-order'); }}
                      ];

            this._toolbar = this.searchForm;
            this.searchForm.css({'padding-top': '8px'}).empty();
            $(`<h4>${that._title}</h4>`).css({'margin':5}).appendTo(this.searchForm);
            this._defineActionButton2(btn_array[0], this.searchForm);
            this._defineActionButton2(btn_array[1], this.searchForm);
           
            
            this.searchForm.height(70);
        }
        
        that._loadData();

        return true;
    },    

    /**
     * @brief Loads or reloads the group data.
     * @memberof heurist.manageDefGroups
     * @description Fetches entity data for the `_entityName` and updates the record list.
     * If `selection_on_init` option was set, it attempts to select those records.
     */
    _loadData: function(){
        let that = this;
        window.hWin.HAPI4.EntityMgr.getEntityData(this._entityName, false,
            function(response){
                that.updateRecordList(null, {recordset:response});

                that.selectRecordInRecordset( that.options.selection_on_init );
                that.options.selection_on_init = null;
            });
        // OR    
        // this.updateRecordList(null, {recordset:$Db[this._entityPrefix]()});
        // this.selectRecordInRecordset();
    },

    /**
     * @brief Renders a single group item in the list.
     * @memberof heurist.manageDefGroups
     * @override
     * @param {HRecordSet} recordset The recordset containing the data.
     * @param {object} record The record object (group) to render.
     * @returns {string} HTML string representing the list item.
     * @description Displays the group name. If the name is 'Trash', a trash icon is shown.
     * Includes edit/delete buttons if not 'Trash' and in 'popup' edit mode.
     * Also includes a selection pointer icon.
     */
    _recordListItemRenderer: function(recordset, record){
        
        let recID   = recordset.fld(record, this._entityPrefix+'_ID');
        let recName = recordset.fld(record, this._entityPrefix+'_Name');
        
        let html = '<div class="recordDiv white-borderless" id="rd'+recID+'" recid="'+recID+'">'; // style="height:1.3em"
        if(this.options.select_mode=='select_multi'){
            html = html + '<div class="recordSelector"><input type="checkbox" /></div>';
        }
        
        if(recName=='Trash'){
            html = html + '<div style="display:table-cell;vertical-align: middle;"><span class="ui-icon ui-icon-trash"></span></div>';
        }
        
        html = html + 
            '<div class="item truncate" style="font-weight:bold;display:table-cell;width:150;max-width:150;padding:6px;">'
            +window.hWin.HEURIST4.util.htmlEscape(recName)+'</div>';

        if(recName!='Trash'){        
            if(this.options.edit_mode=='popup'){
                html = html
                + this._defineActionButton({key:'edit',label:'Edit', title:'', icon:'ui-icon-pencil', class:'rec_actions_button'},
                null,'icon_text','padding-top:9px');
            }

            html = html 
                + this._defineActionButton({key:'delete',label:'Remove', title:'', icon:'ui-icon-delete', class:'rec_actions_button'}, 
                    null,'icon_text');
        }
        
        html = html + '<div class="selection_pointer" style="display:table-cell">'
                    +'<span class="ui-icon ui-icon-carat-r"></span></div>';
        

        return html+'</div>';
        
    },

    /**
     * @brief Handles events after a group record is saved.
     * @memberof heurist.manageDefGroups
     * @override
     * @param {number} recID The ID of the saved group.
     * @param {object} fieldvalues The values of the saved group.
     * @description If in 'editonly' mode, selects the record and closes. Otherwise, calls parent's handler.
     * If it was an insert, triggers 'save-order' and selects the first record.
     * Triggers a refresh event for the entity type.
     */
    _afterSaveEventHandler: function( recID, fieldvalues ){
        
        if(this.options.edit_mode=='editonly'){
            
                this._selection = new HRecordSet();
                this._selection.addRecord(recID, fieldvalues);
                this._currentEditID = null;
                this._selectAndClose();
        }else{
                this._super( recID, fieldvalues );
                if(this.it_was_insert){
                    this._onActionListener(null, 'save-order');
                    this.selectRecordInRecordset(); //select first
                }
                
        }
    
        this._triggerRefresh(this._entityPrefix, recID);    
        
    },
    
    /**
     * @brief Handles events after a group record is deleted.
     * @memberof heurist.manageDefGroups
     * @override
     * @param {number} recID The ID of the deleted group.
     * @description Calls parent's handler, triggers a refresh event, and selects the first record in the list.
     * Note: Original method name might have a typo "EvenHandler" vs "EventHandler".
     */
    _afterDeleteEventHandler: function( recID ){
        this._super( recID );
        this._triggerRefresh(this._entityPrefix, recID);   
        //select first
        this.selectRecordInRecordset();
    },
    
    /**
     * @brief Deletes the current group record.
     * @memberof heurist.manageDefGroups
     * @override
     * @param {boolean} unconditionally If true, deletes without confirmation.
     * @description Prompts for confirmation before deleting, unless `unconditionally` is true.
     * It does not check for assigned fields here; that might be handled by DB constraints or specific implementations.
     */
    _deleteAndClose: function(unconditionally){
   
        if(unconditionally===true){
            this._super(); 
            
        }else{
            let that = this;
            window.hWin.HEURIST4.msg.showMsgDlg(
                'Are you sure you wish to delete this group?', function(){ that._deleteAndClose(true) }, 
                {title:'Warning',yes:'Proceed',no:'Cancel'},
                {default_palette_class:this.options.default_palette_class});        
        }
    },
    
    /**
     * @brief Handles actions triggered by events, such as button clicks.
     * @memberof heurist.manageDefGroups
     * @override
     * @param {Event} event The event object.
     * @param {object|string} action The action object (typically containing `action` and `recID`) or action string.
     * @description Extends the parent's `_onActionListener`. Handles 'save-order' to persist
     * the new order of groups and 'trash' to select the trash group if `onSelect` is configured.
     */
    _onActionListener: function(event, action){

        let isresolved = this._super(event, action);

        if(!isresolved){
            
            if(action=='save-order'){

                let recordset = this.getRecordSet();
                let that = this;
                window.hWin.HEURIST4.dbs.applyOrder(recordset, this._entityPrefix, function(res){
                    that._toolbar.find('.btnApplyOrder').hide();
                    that._triggerRefresh(this._entityPrefix);
                });
                
            }else if(action=='trash'){
                if(window.hWin.HEURIST4.util.isFunction(this.options.onSelect)){
                    let id = $Db.getTrashGroupId(this._entityPrefix);
                    this.options.onSelect.call( this, [id] );
                }
            }
            
        }
    }
  
});
