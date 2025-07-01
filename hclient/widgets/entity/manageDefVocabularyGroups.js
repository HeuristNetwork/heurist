/**
* @file manageDefVocabularyGroups.js
* @brief Manages Vocabulary Group entities.
* @fileOverview Provides a UI for managing Vocabulary Groups. This includes creating, listing, editing, deleting, and reordering groups, and managing their association with Vocabularies.
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
 * @widget heurist.manageDefVocabularyGroups
 * @brief Widget for managing Vocabulary Groups.
 * @extends $.heurist.manageDefGroups
 * @property {?object} reference_vocab_manger A reference to the `manageDefTerms` widget (in vocabulary mode),
 * used to update vocabulary group associations when a vocabulary is moved to a new group.
 * @property {number} [edit_width=550] Overrides the default edit form width from the parent widget.
 */
$.widget( "heurist.manageDefVocabularyGroups", $.heurist.manageDefGroups, {
    
    _entityName:'defVocabularyGroups',
    _entityPrefix: 'vcg',
    _title: 'Vocabularies Editor', // Used if isFrontUI is true
    
    /**
     * @brief Initializes the widget.
     * @memberof heurist.manageDefVocabularyGroups
     * @override
     * @description Calls the parent `_init` and then sets a specific `edit_width` for this widget.
     * Adds the entity name class to the element.
     */
    _init: function() {

        this._super();
        
        this.element.addClass(this._entityName); //to find all exisiting editors in application
        this.options.edit_width = 550;        
    },
    
    /**
     * @brief Initializes the controls for the widget.
     * @memberof heurist.manageDefVocabularyGroups
     * @override
     * @description Calls the parent `_initControls`. Then, customizes the search form area
     * by removing any existing h4 title and adding a new structure with "Vocabularies editor"
     * and "Groups" titles, and repositions the "Add" button. Adjusts layout CSS.
     * @returns {boolean} False if the parent's `_initControls` fails, otherwise true.
     */
    _initControls: function() {

        if(!this._super()){
            return false;
        }
        
        this.searchForm.find('h4').remove();

        $('<h3 style="margin:0;padding:0 8px;vertical-align: middle;width:100%;min-height: 32px; border-bottom: 1px solid gray; clear: both;">Vocabularies editor</h3>'
            +'<div class="action-buttons" style="height:40px;background:white;padding:3px 8px;">'
            +'<h4 style="display:inline-block;margin: 0 10px 0 0; vertical-align: middle;">Groups</h4></div>')
        .appendTo( this.searchForm );
        
        this.searchForm.find('.btnAddButton').css('float','none').appendTo(this.searchForm.find('.action-buttons'));
        //this._defineActionButton2(btn_array[0], this.searchForm.find('.action-buttons'));
        
        this.searchForm.css({padding:'6px 0 0 0'});
        this.recordList.css({ top:80});        
    },
    
    
    /**
     * @brief Handles the drop event when a vocabulary is moved to a new group.
     * @memberof heurist.manageDefVocabularyGroups
     * @override
     * @param {number} type_ID The ID of the vocabulary (trm_ID where trm_ParentTermID is 0) being moved.
     * @param {number} group_ID The ID of the target vocabulary group (vcg_ID).
     * @description This function is called when a vocabulary is dropped onto a group.
     * It calls the `changeVocabularyGroup` method of the referenced `manageDefTerms`
     * widget instance (`options.reference_vocab_manger`) to update the vocabulary's group association.
     */
    _addOnDrop: function(type_ID, group_ID){

        if(type_ID>0 && group_ID>0 && this.options.reference_vocab_manger){
            
            let params = {trm_ID:type_ID, trm_VocabularyGroupID:group_ID };
            
            this.options.reference_vocab_manger
                .manageDefTerms('changeVocabularyGroup', params);
        }
    }
});
