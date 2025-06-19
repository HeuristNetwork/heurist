/**
* @file manageDefDetailTypeGroups.js
* @brief Manages Detail Type Group entities.
* @fileOverview Provides a user interface for managing Detail Type Groups. This includes creating, listing, editing, and deleting groups, and managing their association with Detail Types.
* @package     Heurist academic knowledge management system
* @subpackage  hclient\widgets\entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov <osmakov@gmail.com>
* @author      Ian Johnson <ian.johnson.heurist@gmail.com>
* @since       4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * @class heurist.manageDefDetailTypeGroups
 * @brief Widget for managing Detail Type Groups.
 * @augments $.heurist.manageDefGroups
 * @property {?object} reference_dt_manger A reference to the manageDefDetailTypes widget, used to update detail type group associations.
 */
$.widget( "heurist.manageDefDetailTypeGroups", $.heurist.manageDefGroups, {
    
    _entityName:'defDetailTypeGroups',
    _entityPrefix: 'dtg',
    _title: 'Base Fields Groups',

    /**
     * @brief Handles the drop event when a detail type is moved to a new group.
     * @memberof heurist.manageDefDetailTypeGroups
     * @override
     * @param {number} type_ID The ID of the detail type being moved.
     * @param {number} group_ID The ID of the target group.
     * @description This function is called when a detail type is dropped onto a group.
     * It updates the detail type's group association and visibility status ('dty_ShowInLists')
     * via the referenced manageDefDetailTypes widget instance.
     */
    _addOnDrop: function(type_ID, group_ID){

        if(type_ID>0 && group_ID>0 && this.options.reference_dt_manger){
                
                let params = {dty_ID:type_ID, dty_DetailTypeGroupID:group_ID };
                
                let trash_id = $Db.getTrashGroupId(this._entityPrefix);
                //if source group is trash - change "show in list" to true
                if($Db.dty(type_ID,'dty_DetailTypeGroupID') == trash_id){
                    //from target
                    params['dty_ShowInLists'] = 1;
                }else if(group_ID == trash_id){
                    params['dty_ShowInLists'] = 0;
                }
            
                this.options.reference_dt_manger
                    .manageDefDetailTypes('changeDetailtypeGroup',params);
        }            
    }
    
});
