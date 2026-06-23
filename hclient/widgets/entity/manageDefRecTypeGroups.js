/**
* @file manageDefRecTypeGroups.js
* @brief Manages Record Type Group entities.
* @fileOverview Provides a UI for managing Record Type Groups. This includes creating, listing, editing, deleting, and reordering groups, and managing their association with Record Types.
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
 * @widget heurist.manageDefRecTypeGroups
 * @brief Widget for managing Record Type Groups.
 * @augments $.heurist.manageDefGroups
 * @property {?object} reference_rt_manger A reference to the `manageDefRecTypes` widget,
 * used to update record type group associations when a record type is moved to a new group.
 */
$.widget( "heurist.manageDefRecTypeGroups", $.heurist.manageDefGroups, {
    
    _entityName:'defRecTypeGroups',
    _entityPrefix: 'rtg',
    _title: 'Record Type Groups',

    _checkingGroups: false,

    /**
     * @brief Initializes the controls for the widget.
     * @memberof heurist.manageDefRecTypeGroups
     * @override
     * @description This function calls the function to check whether group prioritisation has been setup
     * @returns {bool} False if groupings need to be updated, otherwise true
     */
    _initControls: function(){

        this._checkGroupOrders();

        if(!this._checkingGroups){
            return this._super();
        }

        return false;
    },

    /**
     * @brief Handles the drop event when a record type is moved to a new group.
     * @memberof heurist.manageDefRecTypeGroups
     * @override
     * @param {number} type_ID The ID of the record type (rty_ID) being moved.
     * @param {number} group_ID The ID of the target group (rtg_ID).
     * @description This function is called when a record type is dropped onto a group.
     * It updates the record type's group association (`rty_RecTypeGroupID`) and visibility status (`rty_ShowInLists`)
     * via the referenced `manageDefRecTypes` widget instance (`options.reference_rt_manger`).
     */
    _addOnDrop: function(type_ID, group_ID){

        if(type_ID>0 && group_ID>0 && this.options.reference_rt_manger){
            
            let params = {rty_ID:type_ID, rty_RecTypeGroupID:group_ID };
            
            let trash_id = $Db.getTrashGroupId(this._entityPrefix);
            //if source group is trash - change "show in list" to true
            if($Db.rty(type_ID,'rty_RecTypeGroupID') == trash_id){
                //from target
                params['rty_ShowInLists'] = 1;
            }else if(group_ID == trash_id){
                params['rty_ShowInLists'] = 0;
            }
        
            this.options.reference_rt_manger
                .manageDefRecTypes('changeRectypeGroup', params);
        }
    },

    /**
     * @brief Checks that the groups have been prioritised.
     * @memberof heurist.manageDefRecTypeGroups
     * @override
     * @description This function checks that the priority group handling has been setup,
     * or rather that there is at least one group with an order value higher than 099
     */
    _checkGroupOrders: function(){

        let that = this;

        if($Db.rtg().getSubSetByRequest({rtg_Order: '>099'}).length() > 0 || this._checkingGroups){
            return;
        }

        // need to setup the lower priority groups
        this._checkingGroups = true;

        let fields = [];
        let rtg_Order = $Db.rtg().getSubSetByRequest({'sort:rtg_Order': 1}).getOrder();

        if(rtg_Order.length < 3){ // set trash group to lower priority

            const trashID = $Db.getTrashGroupId('rtg');
            fields = [{rtg_ID: trashID, rtg_Order: '100'}];
        }else{

            let order = 99;
            for(let idx = 3; idx < rtg_Order.length; idx++){ // start at the 4th group, allow the first three groups to have higher priority
                ++order;
                fields.push({rtg_ID: rtg_Order[idx], rtg_Order: String(order)});
            }
        }

        let request = {
            a: 'save',
            entity: this._entityName,
            request_id: window.hWin.HEURIST4.util.random(),
            fields: fields
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            that._checkingGroups = false;
            that._triggerRefresh('rtg');
            that._initControls();
        });
    },

    /**
     * @brief Renders a single group item in the list.
     * @memberof heurist.manageDefRecTypeGroups
     * @override
     * @param {HRecordSet} recordset The recordset containing the data.
     * @param {object} record The record object (group) to render.
     * @returns {string} HTML string representing the list item.
     * @description This calls the parent version the setup the general DIV,
     * then replaces the standard 'white-borderless' class with 'grey-borderless' for lower priority groups
     */
    _recordListItemRenderer: function(recordset, record){

        let html = this._super(recordset, record);

        let order = recordset.fld(record, 'rtg_Order');

        if(Number.parseInt(order) >= 100){
            html = html.replace('white-borderless', 'grey-borderless');
        }

        return html;
    },

    /**
     * @brief Handles actions triggered by events, such as button clicks.
     * @memberof heurist.manageDefRecTypeGroups
     * @override
     * @param {Event} event The event object.
     * @param {object|string} action The action object (typically containing `action` and `recID`) or action string.
     * @description If the action is 'save-order' then special handling is required to maintain grou priority.
     */
    _onActionListener: function(event, action){

        let that = this;

        if(action !== 'save-order'){
            return this._super(event, action);
        }
        
        // Special handling, splitting groups into higher and lower priority
        let fields = [];
        let recordset = this.getRecordSet();
        let recOrder = recordset.getOrder();

        let idx = 0;
        for(let rtgID of recOrder){

            if(!window.hWin.HEURIST4.util.isPositiveInt(rtgID)){
                continue;
            }

            let record = recordset.getById(rtgID);
            let curOrder = recordset.fld(record, 'rtg_Order');

            let recDiv = this.recordList.find(`#rd${rtgID}`);
            if(recDiv.length === 1){

                // Jump to idx 100 for lower priority groups
                let lowerPriority = recDiv.prevAll('.grey-borderless.helpInfoDiv').length > 0;
                if(lowerPriority && idx < 100){
                    idx = 99;
                }

                // Update record div background to reflect priority
                if(lowerPriority){
                    recDiv.removeClass('white-borderless').addClass('grey-borderless');
                }else{
                    recDiv.removeClass('grey-borderless').addClass('white-borderless');
                }
            }

            // Check order placement has updated
            let newOrder = String(++idx).lpad(0, 3);
            if(curOrder != newOrder){

                recordset.setFld(record, 'rtg_Order', newOrder);
                fields.push({rtg_ID: rtgID, rtg_Order: newOrder});
            }
        }

        if(fields.length === 0){
            return;
        }

        // Save changes
        let request = {
            a: 'save',
            entity: this._entityName,
            request_id: window.hWin.HEURIST4.util.random(),
            fields: fields
        };

        window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            that._triggerRefresh('rtg');
        });
    },

    /**
     * @brief Adds extra help text to group result list.
     * @memberof heurist.manageDefRecTypeGroups
     * @override
     * @description
     */
    _onPageRender: function(){

        if(this.recordList.find('.helpInfoDiv').length > 0){ // help text is already added
            return;
        }

        // Add help text
        let $primaryHelp = $('<div>', {class: 'recordDiv white-borderless helpInfoDiv', tabindex: -1, style: 'padding: 0px 5px;'})
            .html(`<span class="heurist-helper3" style="text-align: left; font-size: 0.8em; display: block; margin: 0px; padding: 1em 5px; background: white;">
                Drag groups that you use into this area<br>Drag record types that you use into these groups
            </span>`).insertBefore(this.recordList.find('.recordDiv').first());

        let $secondaryHelp = $('<div>', {class: 'recordDiv grey-borderless helpInfoDiv', tabindex: -1, style: 'padding: 0px 5px;'})
            .html(`<span class="heurist-helper3" style="text-align: left; font-size: 0.8em; display: block; margin: 0px; padding: 1em 5px; background: lightgrey;">
                Drag groups into the white space above to prioritise them
            </span>`).insertBefore(this.recordList.find('.grey-borderless').first());

        // Prevent the help info from being sorted/moved
        this._on($primaryHelp, {
            'mousedown': (e) => { window.hWin.HEURIST4.util.stopEvent(e); e.stopPropagation(); }
        });
        this._on($secondaryHelp, {
            'mousedown': (e) => { window.hWin.HEURIST4.util.stopEvent(e); e.stopPropagation(); }
        });
    }

});
