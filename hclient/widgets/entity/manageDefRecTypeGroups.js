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
    }

});
