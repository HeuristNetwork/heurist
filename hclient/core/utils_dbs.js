/**
 * @file utils_dbs.js
 * @brief Utility functions for Heurist database structure definitions and metadata.
 * @fileOverview This file provides a collection of utility functions, primarily under the
 * `window.hWin.HEURIST4.dbs` namespace (aliased as `$Db`), for working with Heurist database
 * definitions and metadata. These include functions for accessing and manipulating terms,
 * vocabularies, record types, detail types, record type structures (fields), and workflow rules.
 * Key functionalities involve retrieving definition properties, resolving local and concept IDs,
 * navigating term hierarchies, managing term references, interpreting entry masks, and handling
 * record type links. It also includes helpers for fetching record counts and managing 'Trash' group IDs.
 * @package Heurist academic knowledge management system
 * @subpackage hclient\core
 * @link https://HeuristNetwork.org
 * @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author Artem Osmakov <osmakov@gmail.com>
 * @author Ian Johnson <ian.johnson.heurist@gmail.com>
 * @since 4.0
 */
 
/* global ActiveXObject,Temporal,TDate */

if (!window.hWin.HEURIST4){
    window.hWin.HEURIST4 = {};
}

//init only once
if (!window.hWin.HEURIST4.dbs) 
{
/**
 * @namespace HEURIST4.dbs
 * @description Provides utility functions for interacting with and managing database definitions
 * (Record Types, Detail Types, Terms, etc.) and their structures within Heurist.
 * It includes helpers for retrieving definition properties, navigating hierarchies,
 * and performing specific operations related to database metadata.
 * 
Selectors:

TERMS

getInverseTermById  - (used in record edit for relmarker fields)

getTermValue - Returns Label and Termcode in brackets (optionally) (used in EN and faceted search)

getTermByCode - returns term by code in given vocab (used in lookup geonames only)

getTermByLabel - returns term ID in vocabulary by label (in record edit for search and duplication check)

getTermVocab - returns vocabulary for given term - real vocabulary (not by reference)

trm_InVocab - returns true if term belongs to vocabulary (including by reference)

isTermByReference - return false if given term belongs to vocabulary, otherwise returns level of reference

getColorFromTermValue - Returns hex color by label or code for term by id (for googlemaps only)

    trm_TreeData  - returns hierarchy for given vocabulary as a flat array, recordset or tree data
    trm_HasChildren - is given term has children
    trm_getVocabs - get all vocabularies OR for given domain
    trm_getAllVocabs - get all vocab where given term presents directly or by reference
    trm_RemoveLinks - remove all entries of term from trm_Links

    
WORKFLOW STAGES

getSwfByRectype - returns rules for recordtype and current user 

RECTYPES
   

createRectypeStructureTree
getLinkedRecordTypes  -  FIX in search_faceted.js

hasFields - returns true if rectype has a field in its structure
rstField - Returns rectype header or details field values


    getLocalID
    getConceptID

getTrashGroupId

getHierarchyTitles - returns list of rt and dt titles for linked hierachy rt:dt:rt:dt
                    (in faceted search and linked geo places) 
                    
 * 
 * 
 * Alias: `$Db`
 */
window.hWin.HEURIST4.dbs = {
    
    /**
     * @memberof HEURIST4.dbs
     * @property {Object<string, string>} baseFieldType - Mapping of base field type codes to their human-readable names.
     * @property {string} baseFieldType.enum - 'Terms list'
     * @property {string} baseFieldType.float - 'Numeric'
     * @property {string} baseFieldType.date - 'Date / temporal'
     * @property {string} baseFieldType.file - 'File'
     * @property {string} baseFieldType.geo - 'Geospatial'
     * @property {string} baseFieldType.freetext - 'Text (single line)'
     * @property {string} baseFieldType.blocktext - 'Memo (multi-line)'
     * @property {string} baseFieldType.resource - 'Record pointer'
     * @property {string} baseFieldType.relmarker - 'Relationship marker'
     * @property {string} baseFieldType.separator - 'Heading (no data)'
     * @property {string} baseFieldType.relationtype - 'Relationship type' (legacy)
     * @property {string} baseFieldType.integer - 'Numeric - integer' (legacy)
     * @property {string} baseFieldType.year - 'Year (no mm-dd)' (legacy)
     * @property {string} baseFieldType.boolean - 'Boolean (T/F)' (legacy)
     */
    baseFieldType: {
            enum: 'Terms list',
            float: 'Numeric',
            date: 'Date / temporal',
            file: 'File',
            geo: 'Geospatial',
            freetext: 'Text (single line)',
            blocktext: 'Memo (multi-line)',
            resource: 'Record pointer',
            relmarker: 'Relationship marker',
            separator: 'Heading (no data)',
            //calculated: 'Calculated',
            // Note=> the following types are no longer deinable but may be required for backward compatibility
            relationtype: 'Relationship type',
            integer: 'Numeric - integer',
            year: 'Year (no mm-dd)',
            boolean: 'Boolean (T/F)'},
    
    /**
     * @memberof HEURIST4.dbs
     * @property {number} needUpdateRtyCount - Flag indicating if record type counts need updating. -1 initial, 0 means update scheduled/done.
     */
    needUpdateRtyCount: -1,
    
    /**
     * @memberof HEURIST4.dbs
     * @property {number} rtg_trash_id - Cached ID of the 'Trash' record type group.
     */
    rtg_trash_id: 0,
    /**
     * @memberof HEURIST4.dbs
     * @property {number} dtg_trash_id - Cached ID of the 'Trash' detail type group.
     */
    dtg_trash_id: 0,
    /**
     * @memberof HEURIST4.dbs
     * @property {number} vcg_trash_id - Cached ID of the 'Trash' vocabulary group.
     */
    vcg_trash_id: 0,
    
    /**
     * @memberof HEURIST4.dbs
     * @property {boolean} vocabs_already_synched - Flag to track if vocabularies have been synced after an import by mapping.
     */
    vocabs_already_synched: false,

    /** 
     * Returns the real vocabulary ID for a given term ID, traversing up the parent hierarchy if necessary.
     * This function identifies the root vocabulary a term belongs to, even if it's part of a nested structure
     * or referenced from another vocabulary.
     * @function getTermVocab
     * @memberof HEURIST4.dbs
     * @param {number} trm_ID - The ID of the term.
     * @returns {number} The ID of the real vocabulary this term belongs to.
    */
    getTermVocab: function(trm_ID){
        let trm_ParentTermID;
        do{
            trm_ParentTermID = $Db.trm(trm_ID, 'trm_ParentTermID');
            if(trm_ParentTermID>0){
                trm_ID = trm_ParentTermID;
            }else{
                break;
            }
        }while (trm_ParentTermID>0);
        
        return trm_ID;        
    },

    /** 
     * Checks if a term belongs to a vocabulary by reference.
     * It determines if the term is directly part of the vocabulary or if it's included
     * through a linking mechanism from another vocabulary.
     * @function isTermByReference
     * @memberof HEURIST4.dbs
     * @param {number} vocab_id - The ID of the vocabulary to check against.
     * @param {number} trm_ID - The ID of the term.
     * @returns {false|number} `false` if the term directly belongs to the vocabulary (not by reference).
     * Otherwise, returns a number representing the level of reference:
     *  - `0`: First level of reference (parent is a vocabulary or a "real" term in that vocab).
     *  - `1+`: Higher levels of reference (parent is also a term by reference).
    */
    isTermByReference: function(vocab_id, trm_ID){
        
        let real_vocab_id = $Db.getTermVocab(trm_ID);
        
        if(real_vocab_id==vocab_id){
            return false; //this is not reference
        }
        
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 

        /**
         * @function __checkParents
         * @private
         * @description Internal helper to recursively check parentage for term reference.
         * @param {number} recID - The current record ID (vocabulary or term) being checked.
         * @param {number} lvl - The current reference level.
         * @returns {number|false} The reference level if the term is found as a child (by reference), otherwise `false`.
         */
        
        function __checkParents(recID, lvl){
            
            let children = t_idx[recID]; //array of children ids trm_Links (including references)    
            if(children){
                let k = window.hWin.HEURIST4.util.findArrayIndex(trm_ID, children);
                
                if(k>=0){
                    return lvl;
                }
                
                for(k=0;k<children.length;k++){
                    
                    let real_parent_id = $Db.trm(children[k], 'trm_ParentTermID');
                    let lvl2 = lvl + (lvl>0 || (real_parent_id>0 && real_parent_id!=recID))?1:0;
                    
                    let res = __checkParents(children[k], lvl2);
                    if(res!==false) return res;
                }
            }
            return false;
        }    
        
        return __checkParents(vocab_id, 0);
    },
    

    /**
     * Returns the label of a term, optionally including its code in parentheses.
     * If the term is not found, it returns a "not found" message.
     * @function getTermValue
     * @memberof HEURIST4.dbs
     * @param {number} termID - The ID of the term.
     * @param {boolean} [withcode=false] - If true, appends the term code (if any) in parentheses to the label.
     * @returns {string} The term's label, optionally with its code, or a "not found" message.
     */
    getTermValue: function(termID, withcode){
        
        let term = $Db.trm(termID);
        let termName, termCode='';

        if(term){
            termName = term['trm_Label'];
            termCode = term['trm_Code'];
            if(window.hWin.HEURIST4.util.isempty(termCode)){
                termCode = '';
            }else{
                termCode = " ("+termCode+")";
            }
        } else {
            termName = 'not found term#'+termID;
        }

        return termName+(withcode ?termCode :'');
    },
    
    /**
     * Retrieves the inverse term ID for a given term ID.
     * This is primarily used in record editing for relationship marker fields.
     * - If the term is not found, returns an empty string.
     * - If an inverse term (`trm_InverseTermID`) is defined and greater than 0, returns the inverse term ID.
     * - Otherwise, returns the original `termID`.
     * @function getInverseTermById
     * @memberof HEURIST4.dbs
     * @param {number} termID - The ID of the term.
     * @returns {string|number} The inverse term ID, the original term ID, or an empty string if not found.
     */
    
    getInverseTermById: function(termID){
        let term = $Db.trm(termID);
        if(term){
            let invTermID = term['trm_InverseTermID'];
            if(invTermID>0) return invTermID;
            return termID;
        }
        return '';
    },
    
    /**
     * Attempts to determine a hex color value from a term's code or label.
     * This function is primarily used for visualizations, e.g., in Google Maps integration (recordset.toTimemap).
     * - If the term has a `trm_Code` that is a valid hex color, it's returned.
     * - If `trm_Code` is empty, it checks if the lowercase `trm_Label` matches a predefined color name.
     *   If a match is found, the corresponding hex color is returned.
     * - Otherwise, an empty string is returned.
     * @function getColorFromTermValue
     * @memberof HEURIST4.dbs
     * @param {number} termID - The ID of the term.
     * @returns {string} A hex color string (e.g., "#FF0000") or an empty string if no color can be determined.
     */
    getColorFromTermValue: function(termID){

        let termName, termCode='';

        if(termID>0){
            let term = $Db.trm(termID);
            if(term){

                termName = term['trm_Label'];
                termCode = term['trm_Code'];
                if(window.hWin.HEURIST4.util.isempty(termCode)){
                    let cnames = window.hWin.HEURIST4.ui.getColorArr('names');
                    let idx = window.hWin.HEURIST4.util.findArrayIndex(termName.toLowerCase(),cnames);
                    if(idx>=0){
                        cnames = window.hWin.HEURIST4.ui.getColorArr('hexs');
                        termCode = '#'+cnames[idx]; 
                    }
                }
            }
        }

        return termCode;
    },

    //========================================================================
    

    /**
     * Legacy wrapper for `createRectypeStructureTree_new`.
     * Returns rectype structure as treeview data. This method is often faster on the client side
     * than equivalent server-side methods. It's used for treeviews in import structure,
     * faceted search wizard, and potentially other areas like smarty editor and title mask editor.
     *
     * @function createRectypeStructureTree
     * @memberof HEURIST4.dbs
     * @deprecated Use {@link HEURIST4.dbs.createRectypeStructureTree_new} directly.
     * @param {Object} [db_structure] - Database structure object. Currently not directly used by `createRectypeStructureTree_new` via this wrapper.
     * @param {number} $mode - Controls the tree generation logic:
     *  - `3`: For record title mask editor. No reverse links. Enum fields include id, label, code, internal id. Max depth calculated.
     *  - `4`: Finds reverse links and relations.
     *  - `5`: For filter builder. Lazy treeview with reverse links.
     *  - `6`: For import structure, export CSV. Lazy tree without reverse links.
     *  - `7`: For Smarty editor. Lazy tree without reverse links, with relationship stub. Enum fields include id, label, code, internal id.
     *  - `8`: For faceted search wizard. Similar to mode 5 but excludes "record exists" options.
     * @param {(string|string[])} rectypeids - A comma-separated string or an array of record type IDs to include in the tree.
     * @param {(string|string[])} fieldtypes - Array or comma-separated string of field types to include. Special values:
     *  - `'all'`: Include all field types.
     *  - `'header'`: Include only title and modified fields from the header.
     *  - `'header_ext'`: Include all header fields.
     *  - `'parent_link'`: Include the `DT_PARENT_ENTITY` field (link to parent record).
     *  If 'header' is present, 'title' and 'modified' are automatically added.
     * @param {string} [parentcode] - Prefix for generated node codes.
     * @param {(number|string)} [field_order=0] - Field ordering:
     *  - `0` or `null`: Record structure order.
     *  - `1`: Alphabetic order.
     * @returns {Array} An array of tree node objects suitable for a treeview component (e.g., Fancytree).
     *                  For `mode=3` and a single `rectypeid`, returns the `children` array directly.
     */
    createRectypeStructureTree: function( db_structure, $mode, rectypeids, fieldtypes, parentcode, field_order ) {
        
        let options = {db_structure:db_structure, mode: $mode, rectypeids:rectypeids, fieldtypes:fieldtypes, parentcode:parentcode, field_order:field_order};
    
        return window.hWin.HEURIST4.dbs.createRectypeStructureTree_new( options );
    },

    /**
     * Generates a tree structure representing record types and their fields.
     * This is the core implementation for creating rectype structure trees used in various
     * parts of the Heurist interface (e.g., import, faceted search, editors).
     *
     * @function createRectypeStructureTree_new
     * @memberof HEURIST4.dbs
     * @param {Object} options - Configuration options for tree generation.
     * @param {Object} [options.db_structure] - Database structure. (Not directly used in current logic but passed by wrapper).
     * @param {number} options.mode - Controls tree generation (see {@link HEURIST4.dbs.createRectypeStructureTree} for $mode details).
     * @param {(string|string[])} options.rectypeids - Record type IDs.
     * @param {(string|string[])} options.fieldtypes - Field types to include.
     * @param {string} [options.parentcode] - Prefix for node codes.
     * @param {(number|string)} [options.field_order=0] - Field ordering.
     * @param {string} [options.enum_mode] - If 'expanded' (auto-set for mode 3 or 7), enum fields will have sub-nodes for 'Term', 'Code', etc.
     * @returns {Array} An array of tree node objects. For `mode=3` and single `rectypeid`, returns `children` array.
     */
    createRectypeStructureTree_new: function( options )
    {
        let $mode = options.mode,
            rectypeids = options.rectypeids,
            fieldtypes = options.fieldtypes,
            parentcode = options.parentcode,
            field_order = options.field_order,
            enum_mode = options.enum_mode; 
        
        if($mode==3 || $mode==7){
            enum_mode = 'expanded'
        }
        
        
        const DT_PARENT_ENTITY  = window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY'];
        
        let rst_links = $Db.rst_links();
        
        let _separator = ($mode==3)?'..':':';
        
        let recTypesWithParentLink = [];
        
        let max_allowed_depth = 2;
        
        //-------------------- internal functions    

    /**
     * @function __getRecordTypeTree
     * @private
     * @description Recursively builds a tree node for a given record type.
     * This is an internal helper function for `createRectypeStructureTree_new`.
     * @param {(number|string)} $recTypeId - The ID of the record type, or 'Relationship' for the generic relationship type.
     * @param {number} $recursion_depth - Current depth in the recursion, used to limit nesting.
     * @param {number} $mode - The generation mode (passed from `createRectypeStructureTree_new`).
     * @param {string[]} $fieldtypes - Array of field types to include.
     * @param {number[]} [$pointer_fields=null] - Array of pointer field IDs already processed to avoid infinite recursion.
     * @param {boolean} [$is_parent_relmarker=false] - True if the parent context is a relationship marker.
     * @param {boolean} [is_multi_constrained=false] - True if the context involves multiple constraints.
     * @returns {Object|null} A tree node object for the record type, or null if not applicable.
     * The node object typically includes `key`, `title`, `type`, `conceptCode`, `rtyID_local`, `code`, and `children` properties.
     */    
        

    function __getRecordTypeTree($recTypeId, $recursion_depth, $mode, $fieldtypes, $pointer_fields, $is_parent_relmarker, is_multi_constrained){
            
            let $res = {};
            let $children = [];
            let $dtl_fields = [];
            
            //add default fields - RECORD TYPE HEADER
            if($mode==3){

                $children.push({key:'rec_ID',title:'Record ID', code:'Record ID'});
                $children.push({key:'rec_RecTypeID', title:'Record TypeID', code:'Record TypeID'});
                $children.push({key:'rec_TypeName', title:'Record TypeName', code:'Record TypeName'});
                $children.push({key:'rec_Modified', title:"Record Modified", code:'Record Modified'});

                $children = [
                    {title:'<span style="font-style:italic">metadata</span>', folder:true, is_generic_fields:true, children:$children}];

                if($recursion_depth>0){ // keep record title separate from generic fields
                    $children.unshift({key:'rec_Title', type:'freetext', title:'Constructed title', code:'Record Title'});
                }
            }else
            if($recursion_depth==0 && $fieldtypes.length>0){    
                
                //include record header fields
                let all_header_fields = $fieldtypes.indexOf('header_ext')>=0;
                if($fieldtypes.indexOf('header')>=0){
                    $fieldtypes.push('title');
                    $fieldtypes.push('modified');
                }  
                
                let recTitle_item = null;
                
                if(all_header_fields || $fieldtypes.indexOf('ID')>=0 || $fieldtypes.indexOf('rec_ID')>=0){
                    $children.push({key:'rec_ID', type:'integer',
                        title:('ID'+($mode!=7?' <span style="font-size:0.7em">(Integer)</span>':'')), 
                        code:($recTypeId+_separator+'ids'), name:'Record ID'});
                }

                if(all_header_fields || $fieldtypes.indexOf('title')>=0 || $fieldtypes.indexOf('rec_Title')>=0){
                   
                    recTitle_item = {key:'rec_Title', type:'freetext',
                        title:('Title'+($mode!=7?' <span style="font-size:0.7em">(Constructed Text)</span>':'')), 
                        code:($recTypeId+_separator+'title'), name:'Record title'};
                }
                
                if(all_header_fields || $fieldtypes.indexOf('typeid')>=0 || $fieldtypes.indexOf('rec_RecTypeID')>=0){
                    $children.push({key:'rec_RecTypeID', 
                        title:('Record TypeID'+($mode!=7?' <span style="font-size:0.7em">(Integer)</span>':'')), 
                        code:$recTypeId+_separator+'typeid', name: 'Record type ID'});
                }
                if(all_header_fields || $fieldtypes.indexOf('typename')>=0 || $fieldtypes.indexOf('rec_TypeName')>=0){
                    $children.push({key:'rec_TypeName', 
                        title:('Record TypeName'+($mode!=7?' <span style="font-size:0.7em">(Text)</span>':'')), 
                        code:$recTypeId+_separator+'typename', name: 'Record type'});
                }
                
                if(all_header_fields || $fieldtypes.indexOf('added')>=0 || $fieldtypes.indexOf('rec_Added')>=0){
                    $children.push({key:'rec_Modified', type:'date',
                        title:('Added'+($mode!=7?' <span style="font-size:0.7em">(Date)</span>':'')), 
                        code:($recTypeId+_separator+'added'), name:'Date added'});
                }
                if(all_header_fields || $fieldtypes.indexOf('modified')>=0 || $fieldtypes.indexOf('rec_Modified')>=0){
                    $children.push({key:'rec_Modified', type:'date',
                        title:('Modified'+($mode!=7?' <span style="font-size:0.7em">(Date)</span>':'')), 
                        code:($recTypeId+_separator+'modified'), name:'Date modified'});
                }
                if(all_header_fields || $fieldtypes.indexOf('addedby')>=0 || $fieldtypes.indexOf('rec_AddedBy')>=0){
                    $children.push({key:'rec_AddedBy', type:'enum',
                        title:('Creator'+($mode!=7?' <span style="font-size:0.7em">(User)</span>':'')), 
                        code:($recTypeId+_separator+'addedby'), name:'Creator (user)'});
                }
                if(all_header_fields || $fieldtypes.indexOf('url')>=0 || $fieldtypes.indexOf('rec_URL')>=0){
                    $children.push({key:'rec_URL', type:'freetext',
                        title:('URL'+($mode!=7?' <span style="font-size:0.7em">(Text)</span>':'')), 
                        code:($recTypeId+_separator+'url'), name:'Record URL'});
                }
                
                if(all_header_fields || $fieldtypes.indexOf('notes')>=0 || $fieldtypes.indexOf('rec_ScratchPad')>=0){
                    $children.push({key:'rec_ScratchPad', type:'freetext',
                        title:('Notes'+($mode!=7?' <span style="font-size:0.7em">(Text)</span>':'')), 
                        code:($recTypeId+_separator+'notes'), name:'Record Notes'});
                }
                if(all_header_fields || $fieldtypes.indexOf('owner')>=0 || $fieldtypes.indexOf('rec_OwnerUGrpID')>=0){
                    $children.push({key:'rec_OwnerUGrpID', type:'enum',
                        title:('Owner'+($mode!=7?' <span style="font-size:0.7em">(User or Group)</span>':'')), 
                        code:($recTypeId+_separator+'owner'), name:'Record Owner'});
                }
                if(all_header_fields || $fieldtypes.indexOf('visibility')>=0 || $fieldtypes.indexOf('rec_NonOwnerVisibility')>=0){
                    $children.push({key:'rec_NonOwnerVisibility', type:'enum',
                        title:('Visibility'+($mode!=7?' <span style="font-size:0.7em">(Terms)</span>':'')), 
                        code:($recTypeId+_separator+'access'), name:'Record Visibility'});
                }

                if(all_header_fields || $fieldtypes.indexOf('tags')>=0 || $fieldtypes.indexOf('rec_Tags')>=0){
                    $children.push({key:'rec_Tags', type:'terms',
                        title:('Tags'+($mode!=7?' <span style="font-size:0.7em">(Terms)</span>':'')), 
                        code:($recTypeId+_separator+'tag'), name:'Record Tags'});
                }
                
                if(all_header_fields || $mode == 7){
                    let $grouped = [];
                    
                    if($is_parent_relmarker){
                        let rt_id = window.hWin.HAPI4.sysinfo['dbconst']['RT_RELATION'];
                        let dc = window.hWin.HAPI4.sysinfo['dbconst'];
                        
                        let $rl_children = [];
                        
                        let $details = $Db.rst(rt_id);
                        $details.each2(function($dtID, $dtValue){
                            
                            let $dt_type = $Db.dty($dtID,'dty_Type');
                            if( $dtValue['rst_RequirementType']=='forbidden' ||
                                $dt_type == 'separator' ||
                                $dtID == dc['DT_TARGET_RESOURCE'] ||
                                $dtID == dc['DT_PRIMARY_RESOURCE'] 
                                ){
                                return;    
                            }
                            
                            if($dtID == dc['DT_RELATION_TYPE']){
                                $dt_type = 'reltype';
                            }    
                            
                            let titleR = $dtValue['rst_DisplayName'];
                            if(titleR.indexOf('Relationship ')<0){
                                titleR = 'Relationship '+titleR;
                            }
                                
                            $rl_children.push({type:$dt_type,
                                title: titleR, 
                                code:(rt_id+_separator+'r.'+$dtID), name:$dtValue['rst_DisplayName']});
                            
                        });

                        if($mode == 5){ // for search builder only
                            const rty_Name = $Db.rty($recTypeId, 'rty_Name');
                            $grouped.push(
                                {title: `${rty_Name} relationship records`, code: `${$recTypeId}:exists`, key: 'exists', type: 'freetext', name: rty_Name}
                            );
                        }

                        $grouped.push(
                            {title:'<span style="font-style:italic">Relationship Fields</span>', folder:true, 
                                        is_generic_fields:true, children:$rl_children});
                            
                    }else if($mode==5 && $recTypeId>0){ //for search builder
                        
                        const rty_Name = $Db.rty($recTypeId, 'rty_Name');

                        $grouped.push( {code:`${$recTypeId}:exists`,
                            key: 'exists',
                            name: rty_Name,
                            title: `${rty_Name} records`,
                            type: 'freetext'} );
                    }

                    if(recTitle_item){
                        $grouped.push( recTitle_item );
                    }
                    
                    $grouped.push(
                        {title:'<span style="font-style:italic">metadata</span>', folder:true, is_generic_fields:true, children:$children});
                    
                    $children = $grouped;
                }
            }

            if($recTypeId>0 && $Db.rty($recTypeId,'rty_Name')){//---------------

                const rty_Name = $Db.rty($recTypeId,'rty_Name');
            
                $res['key'] = $recTypeId;
                $res['title'] = rty_Name;
                $res['type'] = 'rectype';
                
                $res['conceptCode'] = $Db.getConceptID('rty', $recTypeId);
                $res['rtyID_local'] = $recTypeId; //$Db.getLocalID('rty', $rt_conceptcode); //for import structure
                
                if(($mode<5 || $recursion_depth==0)){


                    let $details = $Db.rst($recTypeId);
                    
                    //
                    if($fieldtypes.indexOf('parent_link')>=0 && !$Db.rst($recTypeId,DT_PARENT_ENTITY)){
                        
                        //find all parent record types that refers to this record type
                        let $parent_Rts = rst_links.parents[$recTypeId];
                        
                        if($parent_Rts && $parent_Rts.length>0){
                        
                            //create fake rectype structure field
                            let $ffr = {};
                            $ffr['rst_DisplayName'] = 'Parent entity';
                            $ffr['rst_PtrFilteredIDs'] = $parent_Rts.join(',');
                           
                            $ffr['rst_DisplayHelpText'] = 'Reverse pointer to parent record';
                            $ffr['rst_RequirementType'] = 'optional';
                            $ffr['rst_DisplayOrder'] = '0'; // place at top
                                  
                            $details.addRecord(DT_PARENT_ENTITY, $ffr)
                            
                            recTypesWithParentLink.push($recTypeId);
                        }
                    }
                    
                    let $children_links = [];
                    let $new_pointer_fields = [];
                    
                    // add details --------------------------------
                    if($details){
                        
                    //count number of relmarkers and define allowed max depth for rectitle mask tree
                    if($recursion_depth==0 && ($mode==3 || $mode==4)){
                        let cnt_pointers = 0;
                        $details.each2(function($dtID, $dtValue){
                            if($dtValue['rst_RequirementType']!='forbidden'){
                                let $dt_type = $Db.dty($dtID,'dty_Type');
                                if($dt_type=='relmarker'){
                                      cnt_pointers++;
                                }
                            }
                        });
                        max_allowed_depth = (cnt_pointers>10)?2:3;
                    }

                    $details.each2(function($dtID, $dtValue){
                        //@TODO forbidden for import????
                        if($dtValue['rst_RequirementType']!='forbidden'){

                            let $dt_type = $Db.dty($dtID,'dty_Type');
                            
                            if($dt_type=='resource' || $dt_type=='relmarker'){ //title mask w/o relations
                                    $new_pointer_fields.push( $dtID );
                            }

                            let $res_dt = __getDetailSection($recTypeId, $dtID, $recursion_depth, $mode, 
                                                                    $fieldtypes, null, $new_pointer_fields);
                            if($res_dt){
                                
                                if($res_dt['type']=='resource' || $res_dt['type']=='relmarker'){

                                    if($mode==3 && $res_dt['constraint'] && $res_dt['constraint']>1){ 
                                        //for rectitle mask do not create additional level for  multiconstrained link

                                        let separate_meta_fields = $res_dt['constraint'] > 1;
                                        for (let i=0; i<$res_dt['constraint']; i++){
                                            $res_dt['children'][i]['code'] = '{'+$res_dt['children'][i]['title'] +'}'; // change code

                                            if(separate_meta_fields){
                                                // remove constructed title and metadata, keep fields node
                                               

                                                // move fields out of sub-heading
                                                let fields = $res_dt['children'][i]['children'].pop();
                                                $res_dt['children'][i]['children'] = fields['children'];
                                            }
                                        }


                                        if(separate_meta_fields){ // if more than one rectype, place constrcuted title and metadata outside

                                            let meta_fields = [
                                                {key:'rec_ID',title:'Record ID', code:'Record ID'}, {key:'rec_RecTypeID', title:'Record TypeID', code:'Record TypeID'},
                                                {key:'rec_TypeName', title:'Record TypeName', code:'Record TypeName'}, {key:'rec_Modified', title:"Record Modified", code:'Record Modified'}
                                            ];
                                            let meta_title = '<span style="font-style:italic">metadata</span>';

                                            $res_dt['children'].unshift(
                                                {key:'rec_Title', type:'freetext', title:'Constructed title', code:'Record Title'}, {title:meta_title, folder:true, is_generic_fields:true, children:meta_fields}
                                            );
                                        }
                                    }

                                    $children_links.push($res_dt);
                                }else{
                                    
                                    if($res_dt['type']=='enum' && $mode==3){
                                        $res_dt['title'] = "<span class='ui-icon ui-icon-menu' style='margin-right:2px;'>&nbsp;</span>" + $res_dt['title'];
                                    }
                                    
                                    $dtl_fields.push($res_dt);
                                }
                            }
                        }
                    });//for details

                    }
                    
                    //add record pointer and relation at the end of result array
                    $dtl_fields = $dtl_fields.concat($children_links);

                    $dtl_fields.sort(function(a,b){
                        if(field_order == 1){
                            let nameA = a['name'].toLocaleUpperCase();
                            let nameB = b['name'].toLocaleUpperCase();
                            return nameA.localeCompare(nameB);
                        }else{
                            return (a['display_order']<b['display_order'])?-1:1;
                        }
                    });

                    if($fieldtypes.indexOf('anyfield')>=0){ //for filter builder 
                        $dtl_fields.unshift({key:'anyfield', type:'freetext',
                        title:"<span style='font-size:0.9em;font-style:italic;'>ANY</span>", 
                        code:($recTypeId+_separator+'anyfield'), name:'Any field'});    
                    }

                    //--------------------------------------------
                    //find all reverse links and relations
                    if( ($mode==4 && $recursion_depth<2) || (($mode==5 || $mode==8) && $recursion_depth==0) )
                    {
                        let rev_fields = {};
                        let reverse_fields = rst_links.reverse[$recTypeId]; //all:, dty_ID:[rty_ID,...]
                        let twice_only = 0;
                        while(twice_only<2){

                            for (let $dtID in reverse_fields) {
                                if($dtID>0 && 
                                    ( $pointer_fields==null ||    // to avoid recursion
                                        (Array.isArray($pointer_fields) &&   
                                        window.hWin.HEURIST4.util.findArrayIndex($dtID, $pointer_fields)<0) ) )
                                {
                                    rev_fields[$dtID] = reverse_fields[$dtID];
                                }
                            }
                            reverse_fields = rst_links.rel_reverse[$recTypeId]; //all:, dty_ID:[rty_ID,...]
                            twice_only++;
                        }
                        
                        for (let $dtID in rev_fields) {
                                let $rtyIDs = rev_fields[$dtID];
                                for(let i=0; i<$rtyIDs.length; i++)  {
                                    const $res_dt = __getDetailSection($rtyIDs[i], $dtID, $recursion_depth, $mode, $fieldtypes, $recTypeId, null);
                     
                                    if($res_dt){
                                        $dtl_fields.push( $res_dt );
                                    }
                                }
                        }//for
                    }
                    
                    
                }
                
                if($mode==7 && $recursion_depth==0 && !parentcode){
                    $dtl_fields.push(__getRecordTypeTree('Relationship', 0, $mode, $fieldtypes, null));
                }   

            }
            else if($recTypeId=='Relationship') { //----------------------------

                $res['title'] = 'Relationship';
                $res['type'] = 'relationship';
                $res['code'] = 'Relationship';

                //add specific Relationship fields
                $children.push({code:'recRelationType', title:'Relation Type'});
                $children.push({code:'recRelationNotes', title:'Relation Notes'});
                $children.push({code:'recRelationStartDate', title:'Relation StartDate'});
                $children.push({code:'recRelationEndDate', title:'Relation EndDate'});

                if($mode == 7){

                    let skip = [
                        window.hWin.HAPI4.sysinfo.dbconst.DT_PRIMARY_RESOURCE, window.hWin.HAPI4.sysinfo.dbconst.DT_TARGET_RESOURCE,
                        window.hWin.HAPI4.sysinfo.dbconst.DT_RELATION_TYPE, window.hWin.HAPI4.sysinfo.dbconst.DT_SHORT_SUMMARY,
                        window.hWin.HAPI4.sysinfo.dbconst.DT_START_DATE, window.hWin.HAPI4.sysinfo.dbconst.DT_END_DATE
                    ];
                    $Db.rst(window.hWin.HAPI4.sysinfo.dbconst.RT_RELATION).each2((dty_ID, rst_Fields) => {

                        if(skip.indexOf(dty_ID) >= 0){
                            return;
                        }

                        $children.push({code: dty_ID, title: `Relation ${rst_Fields.rst_DisplayName}`})
                    });
                }

                $res['children'] = $children;
                
            }else if($mode==5 || $mode==6 || $mode==8) //----------------------------------- for query builder and facet search tree
            {
                //record type is array - add common fields only
                
                $res['title'] = 'Any record type';
                $res['type'] = 'rectype';
                /* disabled
                if(false && $mode==5 && $recursion_depth==0 && $recTypeId && $recTypeId.indexOf(',')>0){ //for faceted search
                    $res['key'] = $recTypeId;
                    $res['type'] = 'rectype';
                    
                    var recTypes = $recTypeId.split(',');
                    
                    $res['title'] = $Db.rty( recTypes[0],'rty_Name');
                    
                    var  $details = $Db.rst(recTypes[0]); 
                     
                    var $children_links = [];
                    var $new_pointer_fields = [];

                    $details.each2(function($dtID, $dtValue){
                        
                        if($dtValue['rst_RequirementType']!='forbidden'){

                            var $dt_type = $Db.dty($dtID,'dty_Type');
                            if($dt_type=='resource' || $dt_type=='relmarker'){
                                    $new_pointer_fields.push( $dtID );
                            }
                            
                            $res_dt = __getDetailSection(recTypes[0], $dtID, $recursion_depth, $mode, 
                                                                    $fieldtypes, null, $new_pointer_fields);
                            if($res_dt){
                                
                                var codes = $res_dt['code'].split(_separator);
                                codes[0] = $recTypeId;
                                $res_dt['code'] = codes.join(_separator);
                                
                                if($res_dt['type']=='resource' || $res_dt['type']=='relmarker'){
                                    $children_links.push($res_dt);
                                }else{
                                    $children.push($res_dt);
                                }
                            }
                        }
                    });//for details
                    
                    //sort bt rst_DisplayOrder
                    $children.sort(function(a,b){
                        return (a['display_order']<b['display_order'])?-1:1;
                    });
                    
                    //add record pointer and relation at the end of result array
                    $children = $children.concat($children_links);                    
                    
                }*/
                
            }

            if($dtl_fields.length > 0){
                if($children.length==0 && $mode==6){
                    //no header fields - avoid 
                    $children = $dtl_fields;
                }else{
                    $children.push({title: 'fields', folder: true, expanded:(!parentcode), is_rec_fields: true, children: $dtl_fields});    
                }
            }

            if($mode<5 || $recursion_depth==0){
                $res['children'] = $children;
            }

            return $res;
    } //__getRecordTypeTree

    /*
    $dtValue - record type structure definition
    returns display name  or if enum array
    $mode - 3 all, 4, 5 for treeview (5 lazy) , 6 - for import csv(dependencies)
    */

    /**
     * @function __getDetailSection
     * @private
     * @description Builds a tree node for a specific detail field within a record type structure.
     * This is an internal helper function for `__getRecordTypeTree`.
     * @param {number} $recTypeId - The ID of the parent record type.
     * @param {number} $dtID  - The ID of the detail type (field).
     * @param {number} $recursion_depth - Current recursion depth.
     * @param {number} $mode - The generation mode.
     * @param {string[]} $fieldtypes - Array of allowed field types.
     * @param {number} [$reverseRecTypeId=null] - If this is a reverse link, the ID of the target record type.
     * @param {number[]} [$pointer_fields=null] - Pointer fields already processed.
     * @returns {Object|null} A tree node object for the detail field, or `null` if the field should not be included.
     * The node object includes properties like `key`, `title`, `type`, `code`, `name`, `display_order`, `conceptCode`, `dtyID_local`.
     * For pointer types (`resource`, `relmarker`), it can also include `children`, `lazy`, `rt_ids`, `constraint`, `isreverse`, `isparent`.
     */
    function __getDetailSection($recTypeId, $dtID, $recursion_depth, $mode, $fieldtypes, $reverseRecTypeId, $pointer_fields){

        let $res = null;

        let $dtValue = $Db.rst($recTypeId, $dtID);

        let $detailType = $Db.dty($dtID,'dty_Type');
        
        if(($mode==7) && $detailType=='relmarker'){ //$mode==3 || 
            return null;   
        }
        
        let $dt_label   = $dtValue['rst_DisplayName'];
        let $dt_title   = $dtValue['rst_DisplayName'];
        let $dt_conceptcode   = $Db.getConceptID('dty', $dtID);
        let $dt_display_order = $dtValue['rst_DisplayOrder'];
        
        let $pointerRecTypeId = ($dtID==DT_PARENT_ENTITY)?$dtValue['rst_PtrFilteredIDs']:$Db.dty($dtID,'dty_PtrTargetRectypeIDs');
        if(window.hWin.HEURIST4.util.isnull($pointerRecTypeId)) $pointerRecTypeId = '';
        
        let $pref = "";
        
        if ($fieldtypes.indexOf('all')>=0   //($mode==3) || 
            || window.hWin.HEURIST4.util.findArrayIndex($detailType, $fieldtypes)>=0) //$fieldtypes - allowed types
        {

        switch ($detailType) {
            case 'separator':
                $res = {};
                $res['checkbox'] = false;
                if($dt_label == '-'){
                    $dt_title = '<span style="display: inline-block; width: 150px;"><hr></span>'; //replace empty header w/ line
                }else{
                    $dt_title = '<span style="font-weight:bold">' + $dt_title + '</span>';
                }
                break;
            case 'enum':
            case 'relationtype':

                $res = {};
                
                if(enum_mode=='expanded'){
                    $res['children'] = [
                        {key:'term',title: 'Term',code: 'term'}, //label
                        {key:'code',title: 'Code',code: 'code'},       
                        {key:'conceptid',title: 'Concept ID',code: 'conceptid'},       
                        {key:'desc',title: 'Description',code: 'desc'},       
                        {key:'internalid',title: 'Internal ID',code: 'internalid'}
                    ];
                    
                    //title mask (3)
                    if($mode==3){

                        $res['children'][2]['title'] = 'Con-ID';
                        $res['children'][4]['title'] = 'Int-ID';

                        $res['children'].splice(3,1); // remove description option
                    }
                }
                
                
                break;

            case 'resource': // link to another record type
            case 'relmarker':
            
                
                if ($mode==4 || $mode==3){ //record titlemask
                   //max_allowed_depth = 3; calculated
                }else if ($mode==5 || $mode==6 || $mode==7 || $mode==8) //make it 1 for lazy load
                   max_allowed_depth = 1; 
                                                                
                if($recursion_depth<max_allowed_depth){
                    
                    if($reverseRecTypeId!=null){
                            $res = __getRecordTypeTree($recTypeId, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                            if($res){
                                $res['rt_ids'] = $recTypeId; //list of rectype - constraint
                               
                                $pref = ($detailType=="resource")?"lf":"rf";

                                $dt_title = "<span>&lt;&lt; <span style='font-weight:bold'>" 
                                        + $Db.rty($recTypeId, 'rty_Name') + "</span> . " + $dt_title + '</span>';
                                
                                if($mode==5 || $mode==6 || $mode==8){
                                    $res['lazy'] = true;
                                }

                                let parents = $Db.rst_links().parents[$reverseRecTypeId];
                                if(parents && parents.includes($recTypeId) !== false){
                                    $res['isparent'] = 1;
                                    $res['rst_DisplayOrder'] = '0'; // place at top
                                }

                                $res['isreverse'] = 1;
                            }
                    }
                    else{

                            $pref = ($detailType=="resource")?"lt":"rt";

                            let $is_required = ($dtValue['rst_RequirementType']=='required');
                            let $rectype_ids = $pointerRecTypeId.split(",");
                             
                            if($mode==4 || $mode==5 || $mode==6 || $mode==8){
                                
                                let $type_name = $Db.baseFieldType[$detailType];
                                
                                $dt_title = ' <span'+($mode!=5 && $mode!=8?' style="font-style:italic"':'')
                                    +'>' + $dt_title 
                                    +'</span> <span style="font-size:0.7em">(' + $type_name + ')</span>';
                            }else{
                                $dt_title = ' <span style="font-style:italic">' + $dt_title + '</span>';
                            }

                            $res = {};                            
                            
                            if($pointerRecTypeId=="" || $rectype_ids.length==0){ //unconstrainded
                                                    //
                               
                                if($mode==5 || $mode==8){
                                    $res['rt_ids'] = '';
                                    $res['lazy'] = true;
                                }else{
                                    $res = __getRecordTypeTree( null, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                                }

                            }else{ //constrained pointer

                                if($rectype_ids.length>1){
                                    $res['rt_ids'] = $pointerRecTypeId; //list of rectype - constraint
                                    $res['constraint'] = $rectype_ids.length;
                                    if($mode<5) $res['children'] = [];
                                }
                                if($mode==5 || $mode==6 || $mode==7 || $mode==8){ 
                                    $res['rt_ids'] = $pointerRecTypeId;
                                    $res['lazy'] = true;
                                    
                                }else{
                                
                                    for (let k in $rectype_ids){
                                        const $rtID = $rectype_ids[k];
                                        const $rt_res = __getRecordTypeTree($rtID, $recursion_depth+1, $mode, $fieldtypes, $pointer_fields);
                                        if($rectype_ids.length==1){//exact one rectype constraint
                                            //avoid redundant level in tree
                                            $res = $rt_res;
                                            $res['constraint'] = 1;
                                            $res['rt_ids'] = $pointerRecTypeId; //list of rectype - constraint
                                        }else if($rt_res!=null){
                                            
                                            $res['children'].push($rt_res);
                                            $res['constraint'] = $rectype_ids.length;
                                            
                                        }else{
                                            $res['constraint'] = null;
                                            $res['children'].push({
                                                title:'Unconstrained pointer; constrain to record type to see field', 
                                                code:null});
                                        }
                                    }
                                
                                }
                            
                            }
                            
                            $res['required'] = $is_required;
                    }
                }

                break;

            default:
                    $res = {};
        }//end switch
        }

        if($res!=null){

            if(window.hWin.HEURIST4.util.isnull($res['code'])){
              
              if($mode==3){
                  $res['code'] = $dt_label;
              }else{
                  $res['code'] = (($reverseRecTypeId!=null)?$reverseRecTypeId:$recTypeId)+_separator+$pref+$dtID;  //(($reverseRecTypeId!=null)?$reverseRecTypeId:$recTypeId)  
              }  
                
            } 
            $res['key'] = "f:"+$dtID;
            if($mode==4 || $mode==5 || $mode==6 || $mode==8){
                    
                let $stype = ($detailType=='resource' || $detailType=='relmarker' || $detailType=='separator')?'':$Db.baseFieldType[$detailType];
                if($reverseRecTypeId!=null){
                   
                    $res['isreverse'] = 1;
                }
                if($stype!=''){
                    $stype = " <span style='font-size:0.7em'>(" + $stype + ")</span>";   
                }
                
                $res['title'] = $dt_title + $stype;
                //$res['code'] = 
            }else{
                $res['title'] = $dt_title;    
            }
            $res['type'] = $detailType;
            $res['name'] = $dt_label;
            
            $res['display_order'] = $dt_display_order;
            
            $res['conceptCode'] = $dt_conceptcode;
            $res['dtyID_local'] = $dtID; //$Db.getLocalID('dty', $dt_conceptcode); for import
        }            
        return $res;
        
        
    }
    
    
    /**
     * @function __assignCodes
     * @private
     * @description Recursively assigns hierarchical codes to tree nodes.
     * It prepends the parent node's code to its children's codes.
     * This is an internal helper function for `createRectypeStructureTree_new`.
     * @param {Object} $def - The tree node definition object, expected to have a `code` and `children` property.
     * @returns {Object} The modified tree node definition with updated codes for itself and its children.
     */
    function __assignCodes($def){
        
        for(let $idx in $def['children']){
            const $det = $def['children'][$idx];
            if(!window.hWin.HEURIST4.util.isnull($def['code'])){

                if(!window.hWin.HEURIST4.util.isnull($det['code'])){
                    $def['children'][$idx]['code'] = $def['code'] + _separator + $det['code']; 
                }else{
                    $def['children'][$idx]['code'] = $def['code'];    
                }
            }
            if(Array.isArray($det['children'])){
                   $def['children'][$idx] = __assignCodes($def['children'][$idx]);
            }
        }
        return $def;
    }
    //========================= end internal 

        if(fieldtypes==null){
            fieldtypes = ['integer','date','freetext','year','float','enum','resource','relmarker','relationtype','separator'];
        }else if(!Array.isArray(fieldtypes) && fieldtypes!='all'){
            fieldtypes = fieldtypes.split(',');
        }

        let res = [];

        rectypeids = (!Array.isArray(rectypeids)?rectypeids.split(','):rectypeids);    

        let is_multi_constrained = parentcode?rectypeids?.length:0;
        let pointer_field_id = null;
            
        let is_parent_relmarker = false;
        if(parentcode!=null){
            let codes = parentcode.split(_separator);
            if(codes.length>0){
                let lastcode = codes[codes.length-1];
                is_parent_relmarker = (lastcode.indexOf('rt')==0 || lastcode.indexOf('rf')==0);
                
                if(lastcode.indexOf('lt')==0 && is_multi_constrained==1){
                   pointer_field_id =  lastcode.substr(2); 
                }else{
                   is_multi_constrained = 0;
                }
            }
        }
        
        //create hierarchy tree 
        for (let k=0; k<rectypeids.length; k++) {
            let rectypeID = rectypeids[k];
            
            let def = __getRecordTypeTree(rectypeID, 0, $mode, fieldtypes, null, is_parent_relmarker, is_multi_constrained);
            
                if(def!==null) {
                    if(parentcode!=null){
                        
                        /*if(pointer_field_id && def['code']==''){
                            //special case: search existance or count for single constrained pointer
                            let codes = parentcode.split(_separator);
                            codes[codes.length-1] = pointer_field_id; 
                            def['code'] = codes.join(_separator);
                        }else   */
                        if(def['code']){
                            def['code'] = parentcode+_separator+def['code'];
                        }else{
                            def['code'] = parentcode;
                        }
                    }
                    //asign codes
                    if(Array.isArray(def['children'])){
                       
                        def = __assignCodes(def);
                        res.push( def );
                    }                    
                }
        }

        for (let i=0; i<recTypesWithParentLink.length; i++){
            let $details = $Db.rst(recTypesWithParentLink[i]);
            $details.removeRecord(DT_PARENT_ENTITY); //remove fake parent link    
        }
        
        if(rectypeids.length==1 && $mode==3){
            res = res[0]['children'];            
        }

        return res;
    },
    
    
    /**
     * @todo This function uses an old database structure format and needs to be rewritten.
     * Retrieves an array of record type IDs that are linked as resources for a given record type.
     * Used in `search_faceted.js`.
     *
     * @function getLinkedRecordTypes
     * @memberof HEURIST4.dbs
     * @deprecated Uses an outdated structure. Prefer {@link HEURIST4.dbs.getLinkedRecordTypes_cache}.
     * @param {number} $rt_ID - The ID of the source record type.
     * @param {Object} [db_structure=window.hWin.HEURIST4] - The database structure object to use. Defaults to the global Heurist structure.
     * @param {boolean} [need_separate=false] - If true, returns an object with `linkedto` and `relatedto` arrays.
     *                                       Otherwise, returns a single array of all linked/related record type IDs.
     * @returns {Array<number>|{linkedto: Array<number>, relatedto: Array<number>}} An array of record type IDs,
     *          or an object separating them by link type if `need_separate` is true.
     */
    getLinkedRecordTypes: function ($rt_ID, db_structure, need_separate){
        
        if(!db_structure){
            db_structure = window.hWin.HEURIST4;
        }
        
        let $dbs_rtStructs = db_structure.rectypes;
        //find all DIREreverse links (pointers and relation that point to selected rt_ID)
        let $alldetails = $dbs_rtStructs['typedefs'];
        let $fi_type = $alldetails['dtFieldNamesToIndex']['dty_Type'];
        let $fi_rectypes = $alldetails['dtFieldNamesToIndex']['rst_PtrFilteredIDs'];
        
        let $arr_rectypes = [];
        let res = {'linkedto':[],'relatedto':[]};
        
        let $details = $dbs_rtStructs['typedefs'][$rt_ID]['dtFields'];
        if($details) {
            for (let $dtID in $details) {
                
                let $dtValue = $details[$dtID];
        
                if(($dtValue[$fi_type]=='resource' || $dtValue[$fi_type]=='relmarker')){

                        //find constraints
                        let $constraints = $dtValue[$fi_rectypes];
                        if(!window.hWin.HEURIST4.util.isempty($constraints)){
                            $constraints = $constraints.split(",");
                            //verify record type exists
                            if($constraints.length>0){
                                for (let i=0; i<$constraints.length; i++) {
                                    let $recTypeId = $constraints[i];
                                    if( !$arr_rectypes[$recTypeId] && 
                                        $dbs_rtStructs['typedefs'][$recTypeId]){
                                            
                                            $arr_rectypes.push( $recTypeId );
                                            
                                            if(need_separate){
                                                let t1 = ($dtValue[$fi_type]=='resource')?'linkedto':'relatedto';
                                                res[t1].push( $recTypeId );
                                            }
                                    }
                                }                            
                            } 
                        }
                }
            }
        }
        
        return  need_separate ?res :$arr_rectypes;
        
    },

    /**
     * Retrieves a list of record type IDs that are linked or related to/from a given record type, using cached link information.
     *
     * @function getLinkedRecordTypes_cache
     * @memberof HEURIST4.dbs
     * @param {number} rty_ID - The ID of the record type.
     * @param {boolean} [need_separate=false] - If true, returns an object separating IDs by link direction and type
     *                                       (e.g., `linkedto`, `relatedto`, `linkedfrom`, `relatedfrom`).
     *                                       If false, returns a single array of unique record type IDs.
     * @param {string} [direction='to'] - Specifies the direction of links to retrieve:
     *  - `'to'`: Links pointing from `rty_ID` to other record types.
     *  - `'from'`: Links pointing from other record types to `rty_ID`.
     *  - `'both'`: Both 'to' and 'from' links.
     * @returns {Array<number>|Object} An array of unique record type IDs, or an object with arrays grouped by
     *                                 link direction/type if `need_separate` is true.
     *                                 Example for `need_separate=true, direction='both'`:
     *                                 `{ linkedto: [], relatedto: [], linkedfrom: [], relatedfrom: [] }`
     */
    getLinkedRecordTypes_cache: function(rty_ID, need_separate, direction = 'to'){

        let combined = {};
        if(direction != 'from'){
            combined['linkedto'] = [];
            combined['relatedto'] = [];
        }
        if(direction != 'to'){
            combined['linkedfrom'] = [];
            combined['relatedfrom'] = [];
        }

        let rectypes = [];

        let links = $Db.rst_links();

        if(direction != 'from'){ // get 'to' record types

            if(Object.keys(links.direct).length > 0 && links.direct[rty_ID]){
                combined['linkedto'] = links.direct[rty_ID].all;
                rectypes.push(...links.direct[rty_ID].all);
            }
            if(Object.keys(links.rel_direct).length > 0 && links.rel_direct[rty_ID]){
                combined['relatedto'] = links.rel_direct[rty_ID].all;
                rectypes.push(...links.rel_direct[rty_ID].all);
            }
        }
        if(direction != 'to'){ // get 'from' record types

            if(Object.keys(links.reverse).length > 0 && links.reverse[rty_ID]){
                combined['linkedfrom'] = links.reverse[rty_ID].all;
                rectypes.push(...links.reverse[rty_ID].all);
            }
            if(Object.keys(links.rel_reverse).length > 0 && links.rel_reverse[rty_ID]){
                combined['relatedfrom'] = links.rel_reverse[rty_ID].all;
                rectypes.push(...links.rel_reverse[rty_ID].all);
            }
        }

        rectypes = [...new Set(rectypes)]; // remove dups from array version

        return need_separate ? combined : rectypes;
    },

    /**
     * Checks if a record type has at least one field of a specific base field type in its structure.
     *
     * @function hasFields
     * @memberof HEURIST4.dbs
     * @param {number} rty_ID - The ID of the record type to check.
     * @param {string} fieldtype - The base field type to look for (e.g., 'freetext', 'resource').
     * @param {Object} [db_structure] - Legacy parameter, not currently used.
     * @returns {boolean} `true` if the record type has at least one field of the specified type, `false` otherwise.
     */
    hasFields: function( rty_ID, fieldtype, db_structure ){
        
        let is_exist = false;
        
        $Db.rst(rty_ID).each(function(dty_ID, record){
            if($Db.dty(dty_ID,'dty_Type')==fieldtype){
                is_exist = true;
                return false;
            }
        });
        
        return is_exist;
    },

    //--------------------------------------------------------------------------
    
    /*
    shortcuts for working wit db definitions
    
    $Db = window.hWin.HEURIST4.dbs
    
    rty,dty,rst,rtg,dtg,trm,swf = dbdef(entityName,....)  access HEntityMgr.entity_data[entityName]
    
    set(entityName, id, field, newvalue)    
        id - localcode or concept code. For rst this are 2 params rtyID, dtyID
        field - field name. If empty returns entire record
        newvalue - assign value of field
    
    */
    
    /**
     * Shortcut to get or set a field value for a Record Type Group definition.
     * Calls {@link HEURIST4.dbs.getset} with entityName 'defRecTypeGroups'.
     * @function rtg
     * @memberof HEURIST4.dbs
     * @param {number} rec_ID - The ID of the Record Type Group.
     * @param {string} [fieldName] - The name of the field to get. If undefined and `newValue` is also undefined, returns the entire record object.
     * @param {*} [newValue] - If provided, sets the value of `fieldName` to this value.
     * @returns {*|Object|null} If getting a value, returns the field value or the record object. If setting a value, returns `null`.
     */
    rtg: function(rec_ID, fieldName, newValue){
        return $Db.getset('defRecTypeGroups', rec_ID, fieldName, newValue);        
    },

    /**
     * Shortcut to get or set a field value for a Detail Type Group definition.
     * Calls {@link HEURIST4.dbs.getset} with entityName 'defDetailTypeGroups'.
     * @function dtg
     * @memberof HEURIST4.dbs
     * @param {number} rec_ID - The ID of the Detail Type Group.
     * @param {string} [fieldName] - The name of the field to get. If undefined and `newValue` is also undefined, returns the entire record object.
     * @param {*} [newValue] - If provided, sets the value of `fieldName` to this value.
     * @returns {*|Object|null} If getting a value, returns the field value or the record object. If setting a value, returns `null`.
     */
    dtg: function(rec_ID, fieldName, newValue){
        return $Db.getset('defDetailTypeGroups', rec_ID, fieldName, newValue);        
    },

    /**
     * Shortcut to get or set a field value for a Vocabulary Group definition.
     * Calls {@link HEURIST4.dbs.getset} with entityName 'defVocabularyGroups'.
     * @function vcg
     * @memberof HEURIST4.dbs
     * @param {number} rec_ID - The ID of the Vocabulary Group.
     * @param {string} [fieldName] - The name of the field to get. If undefined and `newValue` is also undefined, returns the entire record object.
     * @param {*} [newValue] - If provided, sets the value of `fieldName` to this value.
     * @returns {*|Object|null} If getting a value, returns the field value or the record object. If setting a value, returns `null`.
     */
    vcg: function(rec_ID, fieldName, newValue){
        return $Db.getset('defVocabularyGroups', rec_ID, fieldName, newValue);        
    },
    
    /**
     * Shortcut to get or set a field value for a Record Type definition.
     * Calls {@link HEURIST4.dbs.getset} with entityName 'defRecTypes'.
     * @function rty
     * @memberof HEURIST4.dbs
     * @param {number} rec_ID - The ID of the Record Type.
     * @param {string} [fieldName] - The name of the field to get. If undefined and `newValue` is also undefined, returns the entire record object.
     * @param {*} [newValue] - If provided, sets the value of `fieldName` to this value.
     * @returns {*|Object|null} If getting a value, returns the field value or the record object. If setting a value, returns `null`.
     */
    rty: function(rec_ID, fieldName, newValue){
        return $Db.getset('defRecTypes', rec_ID, fieldName, newValue);        
    },

    /**
     * Shortcut to get or set a field value for a Detail Type definition.
     * Calls {@link HEURIST4.dbs.getset} with entityName 'defDetailTypes'.
     * @function dty
     * @memberof HEURIST4.dbs
     * @param {number} rec_ID - The ID of the Detail Type.
     * @param {string} [fieldName] - The name of the field to get. If undefined and `newValue` is also undefined, returns the entire record object.
     * @param {*} [newValue] - If provided, sets the value of `fieldName` to this value.
     * @returns {*|Object|null} If getting a value, returns the field value or the record object. If setting a value, returns `null`.
     */
    dty: function(rec_ID, fieldName, newValue){
        return $Db.getset('defDetailTypes', rec_ID, fieldName, newValue);        
    },

    /**
     * Shortcut to get or set a field value for a Term definition.
     * Calls {@link HEURIST4.dbs.getset} with entityName 'defTerms'.
     * @function trm
     * @memberof HEURIST4.dbs
     * @param {number} rec_ID - The ID of the Term.
     * @param {string} [fieldName] - The name of the field to get. If undefined and `newValue` is also undefined, returns the entire record object.
     * @param {*} [newValue] - If provided, sets the value of `fieldName` to this value.
     * @returns {*|Object|null} If getting a value, returns the field value or the record object. If setting a value, returns `null`.
     */
    trm: function(rec_ID, fieldName, newValue){
        return $Db.getset('defTerms', rec_ID, fieldName, newValue);        
    },

    /**
     * Shortcut to get or set a field value for a System Workflow Rule.
     * Calls {@link HEURIST4.dbs.getset} with entityName 'sysWorkflowRules'.
     * @function swf
     * @memberof HEURIST4.dbs
     * @param {number} rec_ID - The ID of the Workflow Rule.
     * @param {string} [fieldName] - The name of the field to get. If undefined and `newValue` is also undefined, returns the entire record object.
     * @param {*} [newValue] - If provided, sets the value of `fieldName` to this value.
     * @returns {*|Object|null} If getting a value, returns the field value or the record object. If setting a value, returns `null`.
     */
    swf: function(rec_ID, fieldName, newValue){
        return $Db.getset('sysWorkflowRules', rec_ID, fieldName, newValue);        
    },
    
    
    /**
     * Retrieves the cached index of record type structures (`rst_Index`).
     * The index is an object where keys are record type IDs, and values are HRecordSet instances
     * representing the structure (fields) of that record type.
     * @function rst_idx2
     * @memberof HEURIST4.dbs
     * @returns {Object<string, HRecordSet>|null} The cached record type structure index, or null if not available.
     */
    rst_idx2: function(){
        return window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index');
    },
    
    
    /**
     * Analyzes the database structure to build a comprehensive map of links between record types.
     * This includes direct links (resource pointers), relationship links (relmarkers),
     * their reverse counterparts, and parent-child relationships based on `rst_CreateChildIfRecPtr`.
     * Forbidden fields are ignored in this analysis.
     *
     * @function rst_links
     * @memberof HEURIST4.dbs
     * @returns {Object} An object containing different views of record type links:
     * @returns {Object<string, {all: string[], dty_ID?: string[]}>} return.direct - Direct resource links from a source rty_ID.
     *          `rty_ID` (string): The source record type ID.
     *          `all` (string[]): Array of all target record type IDs linked directly.
     *          `dty_ID` (string[]): Optional, if specific detail types (fields) are involved, this maps dty_ID to target rty_IDs.
     * @returns {Object<string, {all: string[], dty_ID?: string[]}>} return.reverse - Reverse resource links to a target rty_ID.
     *          Structure similar to `direct`, but represents links pointing *to* the key rty_ID.
     * @returns {Object<string, string[]>} return.parents - Parent-child relationships.
     *          `child_rty_ID` (string): The child record type ID.
     *          `value` (string[]): Array of parent record type IDs.
     * @returns {Object<string, {all: string[], dty_ID?: string[]}>} return.rel_direct - Direct relationship links (relmarkers).
     *          Structure similar to `direct`.
     * @returns {Object<string, {all: string[], dty_ID?: string[]}>} return.rel_reverse - Reverse relationship links (relmarkers).
     *          Structure similar to `reverse`.
     */
    rst_links: function(){

        let rst_reverse_parent = {};  //linked FROM rectypes as a child (list of parent rectypes)
        let rst_reverse = {};    //linked FROM rectypes
        let rst_direct = {};     //linked TO rectypes

        let rst_rel_reverse = {};    //linked FROM rectypes
        let rst_rel_direct = {};     //linked TO rectypes

      
        let is_parent = false;
        let all_structs = $Db.rst_idx2();
        for (let rty_ID in all_structs){
            let recset = all_structs[rty_ID];
            recset.each2(function(dty_ID, record){

                //links
                let dty_Type = $Db.dty(dty_ID, 'dty_Type');
                if((dty_Type=='resource' || dty_Type=='relmarker') 
                    && record['rst_RequirementType']!='forbidden')
                {
                    is_parent = false;
                    
                    let ptr = $Db.dty(dty_ID, 'dty_PtrTargetRectypeIDs');
                    if(ptr) ptr = ptr.split(',');
                    if(ptr && ptr.length>0){
                        
                            let direct;
                            let reverse;
                    
                            if(dty_Type=='resource'){
                                //LINK
                                is_parent = (record['rst_CreateChildIfRecPtr']==1);
                                
                                direct = rst_direct;
                                reverse = rst_reverse;
                            }else{
                                //RELATION
                                direct = rst_rel_direct;
                                reverse = rst_rel_reverse;
                            }      
                            
                            
                            if(!direct[rty_ID]) direct[rty_ID] = {all:[]};  
                            direct[rty_ID][dty_ID] = ptr;

                            for(let i=0; i<ptr.length; i++){
                                
                                let target_rty = ptr[i];
                                
                                //all rectypes that is referenced FROM rty_ID
                                if(direct[rty_ID].all.indexOf(target_rty)<0){
                                    direct[rty_ID].all.push(target_rty);   
                                }    
                                
                                // reverse links
                                if(!reverse[target_rty]) reverse[target_rty] = {all:[]};  

                                //all rectypes that refer TO rty_ID
                                if(reverse[target_rty].all.indexOf(rty_ID)<0){
                                    reverse[target_rty].all.push(rty_ID);        
                                }    
                                if(is_parent){
                                    if(!rst_reverse_parent[target_rty]) rst_reverse_parent[target_rty] = [];
                                    if(rst_reverse_parent[target_rty].indexOf(rty_ID)<0){
                                            rst_reverse_parent[target_rty].push(rty_ID);
                                    }
                                }
                                
                                if(!reverse[target_rty][dty_ID]) reverse[target_rty][dty_ID] = [];
                                reverse[target_rty][dty_ID].push(rty_ID)

                            }//for constraints
                    }
                }                

            });
        }
        
        return {
            parents: rst_reverse_parent,
            reverse: rst_reverse,
            direct: rst_direct,

            rel_reverse: rst_rel_reverse,
            rel_direct: rst_rel_direct
        };
        
    },


    /**
     * Retrieves links based on base field definitions (Detail Types), disregarding their specific usage in record type structures.
     * It maps target record type IDs to an array of detail type IDs that point to them.
     *
     * @function rst_links_base
     * @memberof HEURIST4.dbs
     * @returns {Object<string, number[]>} An object where keys are target record type IDs (as strings)
     *          and values are arrays of detail type IDs (`dty_ID`) that are defined to point to that record type.
     *          Example: `{"123": [45, 67], "124": [45]}` means dty 45 and 67 can point to rty 123, and dty 45 can point to rty 124.
     */
    rst_links_base: function(){
        
        let links = {};
        
        $Db.dty().each2(function(dty_ID, record){
            
                let dty_Type = record['dty_Type'];
                if(dty_Type=='resource' || dty_Type=='relmarker') 
                {
                    let ptr = record['dty_PtrTargetRectypeIDs'];
                    if(ptr) ptr = ptr.split(',');
                    if(ptr && ptr.length>0){
                        for(let i=0; i<ptr.length; i++){
                            if(!links[ptr[i]]) links[ptr[i]] = [];
                            
                            links[ptr[i]].push(dty_ID);       
                        }
                    }
                }
        });

        return links;
    },    
    

    /**
     * Finds all record type IDs where a given detail type (field) is used.
     *
     * @function rst_usage
     * @memberof HEURIST4.dbs
     * @param {number} dty_ID - The ID of the detail type (field) to check.
     * @returns {string[]} An array of record type IDs (as strings) that include the specified detail type in their structure.
     */
    rst_usage: function(dty_ID){
       
        let usage = [];
        let all_structs = $Db.rst_idx2();
        for (let rty_ID in all_structs){
            if(all_structs[rty_ID].getById(dty_ID)){
                usage.push(rty_ID);
            }
        }
        return usage;
    },
    

    /**
     * Accessor for record type structure (rst) definitions.
     * Allows getting a specific field value from a structure definition, the entire definition object,
     * or the HRecordSet for the structure of a given record type.
     * If `newValue` is provided, it attempts to set the field value.
     *
     * @function rst
     * @memberof HEURIST4.dbs
     * @param {number} rec_ID - The Record Type ID (rty_ID) whose structure is being accessed.
     * @param {number} [dty_ID] - The Detail Type ID (field ID) within the record type's structure.
     *                          If `dty_ID` is 0 or not provided, returns the HRecordSet for the entire structure of `rec_ID`.
     * @param {string} [fieldName] - The specific field name within the structure definition to get/set.
     *                             If `dty_ID` is provided but `fieldName` is not, returns the entire definition object for that field.
     * @param {*} [newValue] - If provided, sets the value of `fieldName` for the specified `dty_ID` in `rec_ID`'s structure.
     * @returns {*|Object|HRecordSet|null}
     *          - If setting: `null`.
     *          - If getting `fieldName`: The field's value.
     *          - If getting `dty_ID` without `fieldName`: The structure definition object for that field.
     *          - If getting `rec_ID` without `dty_ID`: The HRecordSet for the record type's structure.
     *          - `null` if `rec_ID` is not found in the structure index.
     */
    rst: function(rec_ID, dty_ID, fieldName, newValue){
        
            //direct access (without check and reload)
            let rectype_structure = window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index');
            
            if(rectype_structure && rectype_structure[rec_ID]){
                if(dty_ID>0){
                    return $Db.getset(rectype_structure[rec_ID], dty_ID, fieldName, newValue);                
                }else{
                    return rectype_structure[rec_ID]; // Returns the HRecordSet for the rty_ID
                }
            }
        return null
        
    },
    
    /**
     * Generic getter/setter for entities managed by HAPI4.EntityMgr or direct HRecordSet instances.
     * If `newValue` is undefined, it acts as a getter. Otherwise, it acts as a setter.
     *
     * @function getset
     * @memberof HEURIST4.dbs
     * @param {string|HRecordSet} entityName - The name of the entity (e.g., 'defRecTypes') or an HRecordSet instance.
     * @param {number} rec_ID - The ID of the record/definition to access.
     * @param {string} [fieldName] - The name of the field to get or set. If getting and `fieldName` is undefined, returns the entire record object.
     * @param {*} [newValue] - The value to set if in setter mode.
     * @returns {*|Object|null} If getting, returns the field value or record object. If setting, returns `null`.
     */
    getset: function(entityName, rec_ID, fieldName, newValue){
        if(typeof newValue == 'undefined'){
            return $Db.get(entityName, rec_ID, fieldName);        
        }else{
            $Db.set(entityName, rec_ID, fieldName, newValue);        
            return null;
        }
    },
    
     
    /**
     * Generic getter for entities managed by HAPI4.EntityMgr or direct HRecordSet instances.
     * Assumes database definitions are always available on the client side.
     *
     * @function get
     * @memberof HEURIST4.dbs
     * @param {string|HRecordSet} entityName - The name of the entity (e.g., 'defRecTypes') or an HRecordSet instance.
     * @param {number} [rec_ID] - The ID of the record/definition. If not provided, returns the entire HRecordSet for `entityName`.
     * @param {string} [fieldName] - The name of the field to retrieve. If provided `rec_ID` but not `fieldName`, returns the entire record object for `rec_ID`.
     * @returns {*|Object|HRecordSet|null}
     *          - The HRecordSet if `rec_ID` is not provided.
     *          - The record object (JSON) if `rec_ID` is provided but `fieldName` is not.
     *          - The field value if both `rec_ID` and `fieldName` are provided.
     *          - `null` or the HRecordSet itself if `rec_ID` is invalid or not found (behavior depends on HRecordSet).
     */
    get: function (entityName, rec_ID, fieldName){
        //it is assumed that db definitions ara always exists on client side
        let recset =  window.hWin.HEURIST4.util.isRecordSet(entityName)?entityName
                        :window.hWin.HAPI4.EntityMgr.getEntityData(entityName); 
        
        if(recset && rec_ID>0){
            
            if(fieldName){
                return recset.fld(rec_ID, fieldName);
            }else{
                return recset.getRecord(rec_ID); //returns JSON {fieldname:value,....}
            }
            
        }else{
            return recset;
        }
        
    },

    

    /**
     * Generic setter for entities managed by HAPI4.EntityMgr or direct HRecordSet instances.
     * Assigns a value to a specific field or overwrites an entire record.
     *
     * @function set
     * @memberof HEURIST4.dbs
     * @param {string|HRecordSet} entityName - The name of the entity (e.g., 'defRecTypes') or an HRecordSet instance.
     * @param {number} rec_ID - The ID of the record/definition to modify. Must be greater than 0.
     * @param {string} [fieldName] - The name of the field to set. If not provided, `newValue` is assumed to be an object
     *                             representing the entire record, and `rec_ID` will be updated with this object using `addRecord`.
     * @param {*} newValue - The value to set for the field, or the record object if `fieldName` is not provided.
     */
    set: function (entityName, rec_ID, fieldName, newValue){

        if(rec_ID>0){
        
            let recset =  window.hWin.HEURIST4.util.isRecordSet(entityName)
                            ?entityName
                            :window.hWin.HAPI4.EntityMgr.getEntityData(entityName); 
            
            if(fieldName){
                recset.setFldById(rec_ID, fieldName, newValue);
            }else{
                recset.addRecord(rec_ID, newValue);
            }
            
        }
    },
    
/*    
    //
    //
    //
    rst_set: function(rty_ID, dty_ID, fieldName, value){
        
        var dfname = $Db.rst_to_dtyField( fieldName );
        
        if(dfname){
            $Db.dty( dty_ID, dfname, value );
        }else{
        
            var recset = window.hWin.HAPI4.EntityMgr.getEntityData('defRecStructure');
            var details = window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index'); 
            if(details[rty_ID]){
                var rst_ID = details[rty_ID][dty_ID];    
                if(rst_ID>0){
                    recset.setFldById(rst_ID, fieldName, newValue);
                }else{
                    //add new basefield
                    rst_ID = recset.addRecord3({fieldName: newValue});
                    details[rty_ID][dty_ID] = rst_ID;
                }
            }
        }
    },
    //  
    // special behavior for defRecStructure
    // it returns value for given field or entire recstrucure field
    //    
    rst_idx: function(rty_ID, dty_ID, fieldName){
        
        var recset = window.hWin.HAPI4.EntityMgr.getEntityData('defRecStructure'); 
        
        if(rty_ID>0){
            
            //rty_ID:{dty_ID:rstID, ..... }
            var details = window.hWin.HAPI4.EntityMgr.getEntityData2('rst_Index');
            
            if(!details || !details[rty_ID]){
                return null;
            }else if(dty_ID>0){
                var rst_ID = details[rty_ID][dty_ID];
                
                if(!(rst_ID>0)){
                    return null;
                }else if(fieldName){
                    
                    //for backward capability
                    var dfname = $Db.rst_to_dtyField( fieldName );
                    if(dfname){
                        return $Db.dty(dty_ID, dfname);
                    }else{
                        return recset.fld(rst_ID, fieldName);        
                    }
                    
                }else{
                    return recset.getRecord(rst_ID); //json for paticular detail
                }
            }else{
                return details[rty_ID]; //array of dty_ID:rst_ID
            }
            
        }else{
            return recset;
        }
        //create group
        
        //return $Db.getset('defRecStructure', rec_ID, fieldName, newValue);        
    },
*/    

    /**
     * Finds the local ID for an entity (RecordType, DetailType, Term) given its concept code.
     * A concept code can be in the format 'DBRegistryID-LocalIDInThatDB' or just a local ID if it's from the current DB.
     *
     * @function getLocalID
     * @memberof HEURIST4.dbs
     * @param {string} entity - The entity prefix: 'rty' for RecordTypes, 'dty' for DetailTypes, 'trm' for Terms.
     * @param {string|number} concept_code - The concept code (e.g., "123-45") or a local ID.
     * @returns {number} The local ID if found, otherwise 0.
     */
    getLocalID: function(entity, concept_code){

        let findID = 0;
        let codes = null;
        
        if(typeof concept_code == 'string' && concept_code.indexOf('-')>0)
        {
            codes = concept_code.split('-');
            if(codes.length==2 && 
                (parseInt(codes[0])==0 || codes[0]==window.hWin.HAPI4.sysinfo['db_registeredid']) )
            {
                findID = codes[1];
            }
        }else if(parseInt(concept_code)>0){
            findID = concept_code;    
        }
        
        if(findID>0 && $Db[entity](findID)){
            return findID; 
        }
        
        if(codes && codes.length==2){
        
            let f_dbid = entity+'_OriginatingDBID';
            let f_id = entity+'_IDInOriginatingDB';
            
            let recset = $Db[entity]();
            recset.each2( function(id, record){
                if(record[f_dbid]==codes[0] && record[f_id]==codes[1]){
                    findID = id;
                    return false;
                }
            });
            
        }
        
        return findID;
    },
    
    /**
     * Generates a concept code for a given local entity ID.
     * If the entity has an originating DB ID, the format is 'OriginatingDBID-IDInOriginatingDB'.
     * If the current database is registered, format is 'CurrentRegisteredDBID-LocalID'.
     * Otherwise, format is '0000-LocalID'.
     * If `is_ui` is true and the DB is not registered, wraps the '0000-LocalID' in a span with a title explaining concept IDs.
     *
     * @function getConceptID
     * @memberof HEURIST4.dbs
     * @param {string} entity - The entity prefix: 'rty', 'dty', 'trm'.
     * @param {number} local_id - The local ID of the entity.
     * @param {boolean} [is_ui=false] - If true and the database is not registered, formats the output for UI display with an explanatory tooltip.
     * @returns {string} The generated concept code, or an empty string if the local entity is not found.
     */
    getConceptID: function(entity, local_id, is_ui){
        
        let rec = $Db[entity](local_id);
        if(rec!=null){
            let dbid = rec[entity+'_OriginatingDBID'];
            let id = rec[entity+'_IDInOriginatingDB'];
            if(parseInt(dbid)>0 && parseInt(id)>0){
                return dbid+'-'+id;
            }else if( window.hWin.HAPI4.sysinfo['db_registeredid']>0 ){
                return window.hWin.HAPI4.sysinfo['db_registeredid']+'-'+local_id;
            }else{
                if(is_ui===true){
                  return '<span '
                    +'title="Concept IDs are attributed when a database is registered with the '
                    +'Heurist Reference Index using Design > Setup > Register. In the meantime only local codes are defined.">'
                    +'0000-'+local_id+'</span>';
                }else{
                    return '0000-'+local_id;    
                }
                
                
            }
        }else{
            return '';
        }
    
    },

    /**
     * Finds a term ID within a given vocabulary by its code.
     *
     * @function getTermByCode
     * @memberof HEURIST4.dbs
     * @param {number} vocab_id - The ID of the vocabulary to search within.
     * @param {string} code - The term code (`trm_Code`) to search for.
     * @returns {number|null} The term ID if found, otherwise `null`.
     */
    getTermByCode: function(vocab_id, code){

        let _terms = $Db.trm_TreeData(vocab_id, 'set');
        
        for(let i=0; i<_terms.length; i++){
            if($Db.trm(_terms[i],'trm_Code')==code){
                return _terms[i];
            }
        }
        return null;
    },

    /**
     * Finds a term ID within a given vocabulary by its label (case-insensitive).
     *
     * @function getTermByLabel
     * @memberof HEURIST4.dbs
     * @param {number} vocab_id - The ID of the vocabulary to search within.
     * @param {string} label - The term label (`trm_Label`) to search for.
     * @returns {number|null} The term ID if found, otherwise `null`.
     */
    getTermByLabel: function(vocab_id, label){

        let _terms = $Db.trm_TreeData(vocab_id, 'set');
        
        label = label.toLowerCase();
        
        for(let i=0; i<_terms.length; i++){
            if($Db.trm(_terms[i],'trm_Label').toLowerCase()==label){
                return _terms[i];
            }
        }
        return null;
    },
    
    /**
     * Checks if a term has an associated icon.
     *
     * @function trmHasIcon
     * @memberof HEURIST4.dbs
     * @param {number} term_id - The ID of the term to check.
     * @returns {boolean} `true` if the term has an icon, `false` otherwise.
     *                    (Note: Contains a temporary condition `window.hWin.HEURIST4.util.isempty(ids)` which might always return true if `trm_Icons` is empty initially).
     */
    trmHasIcon: function(term_id){
        let ids = window.hWin.HAPI4.EntityMgr.getEntityData2('trm_Icons');
        return window.hWin.HEURIST4.util.isempty(ids)   //temp - remove later
            || window.hWin.HEURIST4.util.findArrayIndex(term_id, ids)>=0; //ids.indexOf(term_id)>=0;
    },

    
    /**
     * Checks if a term belongs to a specific vocabulary, including by reference.
     *
     * @function trm_InVocab
     * @memberof HEURIST4.dbs
     * @param {number} vocab_id - The ID of the vocabulary.
     * @param {number} term_id - The ID of the term.
     * @returns {boolean} `true` if the term is in the vocabulary (directly or by reference), `false` otherwise.
     */
    trm_InVocab: function(vocab_id, term_id){
        
        let all_terms = $Db.trm_TreeData(vocab_id, 'set');
        
        return (window.hWin.HEURIST4.util.findArrayIndex(term_id, all_terms)>=0);
    },
    
    /**
     * Comparison function for sorting term IDs.
     * Sorts primarily by 'trm_OrderInBranch' (numeric, nulls/invalid first).
     * If 'trm_OrderInBranch' is the same or not set for both, sorts by 'trm_Label' (case-insensitive).
     *
     * @function trm_SortingById
     * @memberof HEURIST4.dbs
     * @param {number} a - The ID of the first term.
     * @param {number} b - The ID of the second term.
     * @returns {number} A negative value if `a` should come before `b`,
     *                   a positive value if `a` should come after `b`,
     *                   or `0` if they are considered equal in sorting order.
     */
    trm_SortingById: function(a, b){

        let a_name = $Db.trm(a,'trm_Label').toLocaleUpperCase();
        let b_name = $Db.trm(b,'trm_Label').toLocaleUpperCase();
        let a_order = parseInt($Db.trm(a,'trm_OrderInBranch'), 10);
        let b_order = parseInt($Db.trm(b,'trm_OrderInBranch'), 10);

        a_order = (!a_order || a_order < 1 || isNaN(a_order)) ? null : a_order;
        b_order = (!b_order || b_order < 1 || isNaN(b_order)) ? null : b_order;

        if(a_order == null && b_order == null){ // alphabetic
            return a_name.localeCompare(b_name);
        }else if(a_order == null || b_order == null){ // null is first
            return a_order == null;
        }else{ // branch order
            return (a_order - b_order);
        }
    },

    /**
     * Generates hierarchical data for a given vocabulary or a set of vocabularies (e.g., domain "relation").
     * It uses the `trm_Links` cache (parent:[children] mapping) to build the hierarchy.
     *
     * @function trm_TreeData
     * @memberof HEURIST4.dbs
     * @param {number|string} vocab_id - The ID of the vocabulary, or a special string like "relation" to get all vocabularies of that domain.
     * @param {string|number} [mode='flat'] - The format of the output:
     *  - `'flat'` or `0`: Returns an HRecordSet of terms with `trm_Parents` field populated (comma-separated parent IDs).
     *  - `'tree'` or `1`: Returns an array of tree node objects suitable for Fancytree (includes `title`, `key`, `children`, `folder`).
     *  - `'select'` or `2`: Returns a flat array of objects for a selector/dropdown (includes `title`, `key`, `code`, `depth`, `is_vocab`).
     *  - `'set'` or `3`: Returns a flat array of term IDs.
     *  - `'labels'` or `4`: Returns a flat array of term labels in lowercase.
     * @param {boolean} [without_refs=false] - If `true`, excludes terms that are included by reference.
     * @param {string} [language=''] - Language code (e.g., 'FRA') to retrieve translated term labels. If empty or 'ENG'/'ALL', uses default labels.
     * @returns {HRecordSet|Array<Object>|Array<number>|Array<string>} The hierarchical data in the specified format.
     */
    trm_TreeData: function(vocab_id, mode, without_refs = false, language = ''){
        
        let recset = window.hWin.HAPI4.EntityMgr.getEntityData('defTerms');
        //parent:[children]
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let trm_ids = []; // Used to accumulate results for 'select', 'set', 'labels' modes or initial IDs for 'flat' mode.
        let res = {}; // Root node for 'tree' mode or initial result for 'relation' vocab_id.
        let translated_labels = null; // Not currently used as per logic, but intended for translations.
        
        if(window.hWin.HEURIST4.util.isNumber(mode)){
            if(mode==1) mode='tree'
            else if(mode==2) mode='select'
            else if(mode==3) mode='set'
            else if(mode==4) mode='labels'
            else mode='flat';
        }
        
        /**
         * @function __addChilds
         * @private
         * @description Internal recursive helper to build term hierarchy.
         * @param {number} currentRecID - The ID of the current term/vocabulary being processed.
         * @param {number|string} currentLvlParents - Current parent level for 'select' mode, or comma-separated parent IDs for 'flat' mode.
         * @param {boolean} include_vocab_in_select - For 'select' mode, whether to add the vocabulary itself as an item.
         * @returns {Object} A node object for 'tree' mode. Modifies `trm_ids` for other modes.
         */
        function __addChilds(currentRecID, currentLvlParents, include_vocab_in_select){
        
            let label = $Db.trm_getLabel(currentRecID, language);

            let node = {title: label, key: currentRecID};
            
            if(mode === 'select' && include_vocab_in_select && currentLvlParents===0){
                node.is_vocab = true; // Mark the node if it's a vocabulary root in select mode
                trm_ids.push({title: label, 
                                is_vocab: true,
                                key: currentRecID, depth:currentLvlParents});
            }

            let children = t_idx[currentRecID]; //array of children ids trm_Links (including references)
            
            if(children && children.length>0){
                
                if(without_refs===true){
                    //remove terms by reference
                    let real_children = [];
                    $.each(children, function(i,id){
                        if(recset.fld(id,'trm_ParentTermID')==currentRecID) real_children.push(id);
                    });
                    children = real_children;
                }

                //sort children by name
               
                children.sort($Db.trm_SortingById);
                
                if(mode=='tree'){
                    let child_nodes = [];  
                    for(let i=0; i<children.length;i++){  
                        child_nodes.push( __addChilds(children[i], 0, false) ); // lvl_parents and include_vocab not strictly needed for tree children here
                    }
                    node['children'] = child_nodes;
                    node['folder'] = true;

                }else if(mode=='select'){
                    for(let i=0; i<children.length;i++){ 
                        let childID = children[i];
                        let childLabel = translated_labels ? translated_labels[childID] : recset.fld(childID, 'trm_Label');

                        trm_ids.push({title: childLabel,
                                      code: recset.fld(childID, 'trm_Code'),
                                      key: childID,
                                      depth: currentLvlParents}); // currentLvlParents should be incremented by caller if used for depth
                        __addChilds(childID, currentLvlParents+1, false); // Recursive call for children
                    }

                }else if(mode=='set' || mode=='labels'){
                    for(let i=0; i<children.length;i++){  
                        let childID = children[i];
                        let childLabel = translated_labels ? translated_labels[childID] : recset.fld(childID, 'trm_Label');

                        trm_ids.push(mode=='labels'?childLabel.toLowerCase()
                                                   :childID);
                        __addChilds(childID, 0, false); // lvl_parents and include_vocab not strictly needed here
                    }
                    
                }else{ // 'flat' mode: gather ids and set trm_Parents
                    let parentPathArray = currentLvlParents ? String(currentLvlParents).split(',') : [];
                    parentPathArray.push(currentRecID);
                    let newParentPath = parentPathArray.join(',');

                    for(let i=0; i<children.length;i++){  
                        let childID = children[i];
                        if (trm_ids.indexOf(childID) === -1) { // Avoid processing duplicates if structure allows
                           trm_ids.push(childID);
                        }

                        recset.setFldById(childID, 'trm_Parents', newParentPath);
                        __addChilds(childID, newParentPath, false);
                    }
                }
            }
            
            return node;
        }
        
        if(vocab_id=='relation'){
            //find all vocabulary with domain "relation"
            res = {'children':[]};
            let vocab_ids = $Db.trm_getVocabs('relation');
            for (let i=0; i<vocab_ids.length; i++){
                let trm_ID = vocab_ids[i];
                res['children'].push( __addChilds(trm_ID, 0, true) );
            }
            
        }else{
            res = __addChilds(vocab_id, 0, false);
        }
        
        if(mode=='tree'){
            return res['children'];
        }else if(mode=='select'){
            return trm_ids;
        }else if(mode=='set' || mode=='labels'){
            return trm_ids;
        }else{
            return recset.getSubSetByIds(trm_ids);
        }
        
    },
    
    /**
     * Checks if a term is a direct child of a parent term/vocabulary.
     *
     * @function trm_IsChild
     * @memberof HEURIST4.dbs
     * @param {number} parent_id - The ID of the parent term or vocabulary.
     * @param {number} trm_id - The ID of the term to check.
     * @returns {boolean} `true` if `trm_id` is a direct child of `parent_id`, `false` otherwise.
     */
    trm_IsChild: function(parent_id, trm_id)
    {
        
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let children = t_idx[parent_id] ?t_idx[parent_id]:[];

        if(trm_id>0){
            return (window.hWin.HEURIST4.util.findArrayIndex(trm_id, children)>=0);
        }
        
        return false;
        
    },

    /**
     * Checks if a parent term/vocabulary has a direct child with a specific label (case-insensitive).
     *
     * @function trm_HasChildWithLabel
     * @memberof HEURIST4.dbs
     * @param {number} parent_id - The ID of the parent term or vocabulary.
     * @param {string} trm_label - The label to search for among direct children.
     * @param {number} [ignored_trm_id=null] - A term ID to ignore during the check (e.g., when checking for duplicates before saving a term).
     * @returns {boolean} `true` if a direct child with the given label exists (and is not ignored), `false` otherwise.
     */
    trm_HasChildWithLabel: function(parent_id, trm_label, ignored_trm_id = null){

        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let children = t_idx[parent_id] ?t_idx[parent_id]:[];
        
        if(!window.hWin.HEURIST4.util.isempty(trm_label))
        {
           let recset = window.hWin.HAPI4.EntityMgr.getEntityData('defTerms');        
           trm_label = trm_label.toLowerCase();
           
           for(let i=0; i<children.length;i++){  
                let recID = children[i];
                let check_id = ignored_trm_id != recID;
                if(check_id && recset.fld(recID, 'trm_Label').toLowerCase()==trm_label)
                {
                   return true; 
                }
           }
        }
        
        return false;
    },
    
    /**
     * Checks if a parent term/vocabulary has a direct child with a specific code.
     *
     * @function trm_HasChildWithCode
     * @memberof HEURIST4.dbs
     * @param {number} parent_id - The ID of the parent term or vocabulary.
     * @param {string} trm_code - The code (`trm_Code`) to search for among direct children.
     * @param {number} [ignored_trm_id=null] - A term ID to ignore during the check.
     * @returns {boolean} `true` if a direct child with the given code exists (and is not ignored), `false` otherwise.
     */
    trm_HasChildWithCode: function(parent_id, trm_code, ignored_trm_id = null){

        const t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        const children = t_idx[parent_id] ?t_idx[parent_id]:[];

        if(!window.hWin.HEURIST4.util.isempty(trm_code)){

           let recset = window.hWin.HAPI4.EntityMgr.getEntityData('defTerms');
           
           for(let i=0; i<children.length;i++){  
                let recID = children[i];
                let check_id = ignored_trm_id != recID;
                if(check_id && recset.fld(recID, 'trm_Code')==trm_code){
                   return true; 
                }
           }
        }
        
        return false;
    },
    
    /**
     * Checks if a given term has any children (including references) in the `trm_Links` hierarchy.
     *
     * @function trm_HasChildren
     * @memberof HEURIST4.dbs
     * @param {number} trm_id - The ID of the term to check.
     * @returns {boolean} `true` if the term has children, `false` otherwise.
     */
    trm_HasChildren: function(trm_id){
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let children = t_idx[trm_id];
        return (children && children.length>0);
    },

    /**
     * Updates the parentage of children terms in the local `defTerms` cache and `trm_Links` index.
     * Moves children from `old_parent_id` to `new_parent_id`.
     * This is typically a client-side adjustment after a server-side change.
     *
     * @function trm_ChangeChildren
     * @memberof HEURIST4.dbs
     * @param {number} old_parent_id - The ID of the old parent term/vocabulary.
     * @param {number} new_parent_id - The ID of the new parent term/vocabulary.
     */
    trm_ChangeChildren: function(old_parent_id, new_parent_id){
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let children = t_idx[old_parent_id];
        
        if((children && children.length>0)){
            
            $.each(children,function(i,trm_id){
                 if($Db.trm(trm_id,'trm_ParentTermID')==old_parent_id){
                     $Db.trm(trm_id,'trm_ParentTermID',new_parent_id); // Update actual term definition
                 }
            });
            
            let target_children = t_idx[new_parent_id];
            if(target_children && target_children.length>0){
                t_idx[new_parent_id] = target_children.concat(children)
            }else{
                t_idx[new_parent_id] = children;
            }
            // It might be necessary to remove children from old_parent_id in t_idx as well
            // delete t_idx[old_parent_id]; or t_idx[old_parent_id] = [];
        }
    },
    
    
    /**
     * Retrieves all vocabularies, optionally filtered by a specific domain.
     * Vocabularies are identified as terms with no `trm_ParentTermID`.
     *
     * @function trm_getVocabs
     * @memberof HEURIST4.dbs
     * @param {string} [domain] - If provided, only vocabularies belonging to this domain (e.g., "relation") are returned.
     * @returns {number[]} An array of vocabulary IDs (which are also term IDs).
     */
    trm_getVocabs: function(domain){

        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); // Used to get keys, but could also iterate defTerms
        let res = [];
        let parents = Object.keys(t_idx); // These are parent_IDs from trm_Links, not necessarily all vocabs
                                          // A more robust way might be to iterate all terms and check ParentTermID and domain.
                                          // However, current logic iterates keys of trm_Links (which are terms that have children).
        $Db.trm().each2(function(trm_ID, termRec){ // Iterate all terms instead
            if(!(termRec.trm_ParentTermID > 0)){ // It's a vocabulary
                 if(!domain || termRec.trm_Domain == domain) {
                    res.push(trm_ID);
                 }
            }
        });
        
        return res;
    },
    
    /**
     * Recursively finds all vocabularies to which a given term belongs, including by reference.
     *
     * @function trm_getAllVocabs
     * @memberof HEURIST4.dbs
     * @param {number} trm_id - The ID of the term.
     * @returns {number[]} An array of vocabulary IDs that the term is part of, directly or indirectly.
     */
    trm_getAllVocabs: function(trm_id){
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        
        let res = [];
        let parents = Object.keys(t_idx); // Iterate through all terms that are parents in trm_Links
        for (let i=0; i<parents.length; i++){
            let parent_ID = parents[i];
            if (t_idx[parent_ID]) { // Ensure parent_ID actually has children list in t_idx
                let k = window.hWin.HEURIST4.util.findArrayIndex(trm_id, t_idx[parent_ID]);
                if(k>=0){ // If trm_id is a child of parent_ID
                    let trm_ParentTermID = $Db.trm(parent_ID, 'trm_ParentTermID');
                    if(trm_ParentTermID>0){ // If parent_ID is itself a term (not a vocab root)
                        res = res.concat($Db.trm_getAllVocabs(parent_ID)); // Recurse upwards
                    }else{ // parent_ID is a vocabulary
                        if (res.indexOf(parent_ID) === -1) { // Avoid duplicates
                           res.push( parent_ID );
                        }
                    }
                }
            }
        }
        // Also consider the direct vocabulary of the term itself if it's a root of its own real vocabulary
        let directVocab = $Db.getTermVocab(trm_id);
        if ($Db.trm(directVocab, 'trm_ParentTermID') == 0 && res.indexOf(directVocab) === -1) {
            // If the term's "real" vocab is a root vocab and not already found.
            // This case handles terms that might not be explicitly linked under other vocabs in trm_Links
            // but are part of their own vocabulary structure.
            // However, the logic primarily relies on trm_Links, so this might be redundant or cover edge cases.
        }
        return [...new Set(res)]; // Ensure uniqueness
    },

    /**
     * Creates an object mapping term IDs to their translated (or default) labels for a given vocabulary or list of term IDs.
     * If a translation for the specified language is not available, the term's original label is used.
     *
     * @function trm_getTranslatedLabels
     * @memberof HEURIST4.dbs
     * @param {number|number[]} vocab_id - The ID of a vocabulary (to get all its terms) or an array of term IDs.
     * @param {string} language - The language code (e.g., 'FRA') for translation.
     * @returns {Object<number, string>} An object where keys are term IDs and values are their corresponding labels.
     */
    trm_getTranslatedLabels: function(vocab_id, language){

        let term_ids = [];
        if(!Array.isArray(vocab_id)){
            term_ids = $Db.trm_TreeData(vocab_id, 'set');
            if(term_ids.length == 0){ // vocab id is term id(s)
                term_ids = [vocab_id];
            }else if(term_ids.indexOf(vocab_id) == -1){ // add vocab id, if missing
                term_ids.push(vocab_id);
            }
        }else{
            term_ids = vocab_id;
        }

        let translated_list = {};
        for(const id of term_ids){
            translated_list[id] = $Db.trm_getLabel(id, language);
        }

        return translated_list;
    },

    /**
     * Retrieves the label for a given term ID, optionally translated into a specified language.
     * If the language is not provided, or is 'ENG'/'ALL', or if no translation exists,
     * the term's default `trm_Label` is returned.
     *
     * @function trm_getLabel
     * @memberof HEURIST4.dbs
     * @param {number} term_id - The ID of the term.
     * @param {string} [language=null] - The 3-letter language code (e.g., 'FRA') for the desired translation.
     * @returns {string} The (translated) label of the term, or its default label if no translation is found or language is not specified. Returns `trm_Label` from `$Db.trm` as fallback.
     */
    trm_getLabel: function(term_id, language = null){


        if(!window.hWin.HEURIST4.util.isempty(language)){
            language = window.hWin.HAPI4.getLangCode3(language);    
            if(language!='ENG' && language!='ALL'){
                let translations = window.hWin.HAPI4.EntityMgr.getEntityData2('trm_Translation');

                if(translations){   
                    let rec = translations.getSubSetByRequest({trn_LanguageCode: language, 
                        trn_Source: 'trm_Label', 
                        trn_Code: term_id}).getFirstRecord();
                    if(rec && Object.keys(rec).length > 0){
                        return translations.fld(rec, 'trn_Translation');
                    }
                }
            }
        }

        return $Db.trm(term_id, 'trm_Label');
    },
    
    /**
     * Removes all mentions of a specific term ID from the `trm_Links` hierarchy cache.
     * This involves removing the term if it's a parent key, and removing it from any child arrays it might be in.
     *
     * @function trm_RemoveLinks
     * @memberof HEURIST4.dbs
     * @param {number} trm_id - The ID of the term to remove from the links structure.
     */
    trm_RemoveLinks: function(trm_id){
        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        let parents = Object.keys(t_idx);
        let i = 0;
        while(i<parents.length){
            if(parents[i]==trm_id){ // If the term itself is a parent key
                delete t_idx[parents[i]]; // Remove its entry (and all its children from this link perspective)
                // Re-evaluate parents array as its keys might have changed if parents[i] was string representation of number
                parents = Object.keys(t_idx);
                // No increment for i here, as the array length changed and current index needs re-evaluation
            }else{
                if (t_idx[parents[i]]) { // Check if parent still exists (could be deleted in previous iteration)
                    let k = window.hWin.HEURIST4.util.findArrayIndex(trm_id, t_idx[parents[i]]);
                    if(k>=0){
                        t_idx[parents[i]].splice(k,1);
                    }
                }
                i = i +1;
            }
        }
    },
    
    /**
     * Manages term references by adding or removing terms from vocabularies/parent terms.
     * This function makes a server request to perform the action and then updates the client-side
     * `trm_Links` cache via `changeParentInIndex` upon success.
     * It includes checks to prevent adding terms that already exist in the target vocabulary
     * and handles potential server-side blocks (e.g., term in use).
     *
     * @function setTermReferences
     * @memberof HEURIST4.dbs
     * @param {number[]} term_IDs - Array of term IDs to be moved or referenced.
     * @param {number} new_vocab_id - Target vocabulary ID. If > 0, terms are added/moved here.
     * @param {number} new_parent_id - Target parent term ID within `new_vocab_id`. If 0, `new_vocab_id` is used as parent.
     * @param {number} old_vocab_id - Source vocabulary ID (if removing/moving from).
     * @param {number} old_parent_id - Source parent term ID. If 0, `old_vocab_id` is used as parent.
     * @param {function} [callback] - A callback function to execute after the server response and client-side update.
     */
    setTermReferences: function(term_IDs, new_vocab_id, new_parent_id, old_vocab_id, old_parent_id, callback){

        let default_palette_class = 'ui-heurist-design';
        
        if(new_vocab_id>0){
            
            if(!(new_parent_id>0)) new_parent_id = new_vocab_id;

            let trm_ids = $Db.trm_TreeData(new_vocab_id, 'set'); //all terms in target vocab

            let all_children = [];
            let is_exists = 0;
            for(let i=0; i<term_IDs.length; i++){
                if(window.hWin.HEURIST4.util.findArrayIndex(term_IDs[i], trm_ids)>=0){
                    is_exists = term_IDs[i]; // Selected term itself is already in target
                    break;
                }
                // Check if any child of the selected terms is already in the target vocabulary
                let children_of_term_i = $Db.trm_TreeData(term_IDs[i], 'set');
                for(let j=0; j<children_of_term_i.length; j++){
                    if(window.hWin.HEURIST4.util.findArrayIndex(children_of_term_i[j], trm_ids)>=0){
                        is_exists = children_of_term_i[j]; // A child of selected term is in target
                        break;
                    }
                    if(all_children.indexOf(children_of_term_i[j])<0) all_children.push(children_of_term_i[j]);
                }
                if (is_exists) break;
            }
            
            //some of selected terms are already in this vocabulary
            if(is_exists>0){
                window.hWin.HEURIST4.msg.showMsgDlg('Term <b>'+$Db.trm(is_exists,'trm_Label')
                    +'</b> (or one of its children) is already in vocabulary <b>'+$Db.trm(new_vocab_id,'trm_Label')+'</b>',
                    null, {title:'Terms'},
                    {default_palette_class:default_palette_class});                        
                return;
            }

            //exclude all child terms - they will be added via their parent
            let i=0;
            while(i<term_IDs.length){
                if(all_children.indexOf(term_IDs[i])<0){ // If term_ID[i] is not a child of another term_ID in the list
                    i++;
                }else{
                    term_IDs.splice(i,1); // Remove it, as its parent will bring it along
                } 
            }
        }
        if(old_vocab_id>0){
            if(!(old_parent_id>0)) old_parent_id = old_vocab_id;
        }

        let request = {
            'a'          : 'action',
            'reference'  : 1,
            'entity'     : 'defTerms',
            'request_id' : window.hWin.HEURIST4.util.random(),
            'old_VocabID': old_vocab_id,  
            'old_ParentTermID': old_parent_id,  
            'new_VocabID': new_vocab_id,  
            'new_ParentTermID': new_parent_id,  
            'trm_ID': term_IDs                   
        };

        window.hWin.HEURIST4.msg.bringCoverallToFront();                                             

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                window.hWin.HEURIST4.msg.sendCoverallToBack();

                if(response.status == window.hWin.ResponseStatus.OK){

                    $Db.changeParentInIndex(new_parent_id, term_IDs, old_parent_id);

                    if(window.hWin.HEURIST4.util.isFunction(callback)){
                            callback.call();
                    }

                }else{
                    if(response.status == window.hWin.ResponseStatus.ACTION_BLOCKED){
                        
                        let sMsg;
                        if(response.sysmsg && response.sysmsg.reccount){
                            
                            let s = '';
                            $.each(response.sysmsg['fields'],function(i,dty_ID){
                               s = s + $Db.dty(dty_ID,'dty_Name'); 
                            });
                              
                            sMsg = '<p>Sorry, we cannot '+(new_parent_id>0?'move':'delete')
                            + ' this term because it (or its children) is already in use in fields '
                            + ' ( '+s+' ) which reference this vocabulary</p> '
                            + ' <p><a href="'+window.hWin.HAPI4.baseURL+'?db='
                            + window.hWin.HAPI4.database+'&q=ids:' + response.sysmsg['records'].join(',') + '&nometadatadisplay=true'
                            + '" target="_blank">Show '+response.sysmsg['reccount']+' records</a> which use this term (or its descendants).</p>';
                        }else{
                            sMsg = response.message;
                        }
                        
                        window.hWin.HEURIST4.msg.showMsgDlg(sMsg, 
                            null, {title:'Term by Reference'},
                            {default_palette_class:default_palette_class});                        
                        
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);                            
                    }
                }
        });   

    },

    /**
     * Updates the client-side `trm_Links` cache after a server-side action has changed term parentage.
     * If `new_parent_id` is > 0, it adds the `term_ID` (or array of `term_ID`s) to the children of `new_parent_id`.
     * If `old_parent_id` is > 0, it removes `term_ID` from the children of `old_parent_id`.
     *
     * @function changeParentInIndex
     * @memberof HEURIST4.dbs
     * @param {number} new_parent_id - The ID of the new parent term/vocabulary. If 0 or less, no addition is made.
     * @param {number|number[]} term_ID - The ID or array of IDs of the term(s) whose parentage changed.
     * @param {number} old_parent_id - The ID of the old parent term/vocabulary. If 0 or less, no removal is attempted from an old parent.
     */
    changeParentInIndex: function(new_parent_id, term_ID, old_parent_id){

        if(new_parent_id==old_parent_id) return;

        let t_idx = window.hWin.HAPI4.EntityMgr.getEntityData('trm_Links'); 
        if(new_parent_id>0){
            if(!t_idx[new_parent_id]) t_idx[new_parent_id] = []; 
            if(Array.isArray(term_ID)){
               

                for(let i=0; i<term_ID.length; i++)
                    if(window.hWin.HEURIST4.util.findArrayIndex(term_ID[i], t_idx[new_parent_id])<0){
                        t_idx[new_parent_id].push( term_ID[i] );    
                }

            }else if(window.hWin.HEURIST4.util.findArrayIndex(term_ID, t_idx[new_parent_id])<0)
            {
                t_idx[new_parent_id].push(term_ID);
            }

        }
        if(old_parent_id>0 && t_idx[old_parent_id]){ // Ensure old_parent_id exists in t_idx
            // If term_ID is an array, this part needs to iterate through term_ID to remove each one
            if(Array.isArray(term_ID)) {
                for(let i=0; i<term_ID.length; i++) {
                    let k = window.hWin.HEURIST4.util.findArrayIndex(term_ID[i], t_idx[old_parent_id]);
                    if(k>=0){
                        t_idx[old_parent_id].splice(k,1);
                    }
                }
            } else {
                let k = window.hWin.HEURIST4.util.findArrayIndex(term_ID, t_idx[old_parent_id]);
                if(k>=0){
                    t_idx[old_parent_id].splice(k,1);
                }
            }
        }

    },    
    
    
        
    //--------------------------------------------------------------------------
    /**
     * Applies a new order to items in a recordset and saves this order to the server.
     * It updates a specified order field (e.g., `vcg_Order`) for each record in the recordset
     * based on its current position in the recordset's internal order.
     *
     * @function applyOrder
     * @memberof HEURIST4.dbs
     * @param {HRecordSet} recordset - The HRecordSet instance containing the items to reorder.
     *                                 Its `entityName` property is used in the save request.
     *                                 Its `getOrder()` method provides the current sequence of record IDs.
     * @param {string} prefix - The prefix for the ID field (e.g., 'vcg' for `vcg_ID`) and the order field (e.g., 'vcg' for `vcg_Order`).
     * @param {function} [callback] - A callback function to execute after the server response.
     *                                It's called on success or if no changes were needed.
     */
    applyOrder: function(recordset, prefix, callback){

        let entityName = recordset.entityName;
        let fieldId    = prefix+'_ID'; 
        let fieldOrder = prefix+'_Order';
        
        //assign new value for vcg_Order and save on server side
        let rec_order = recordset.getOrder();
        let idx = 0, len = rec_order.length;
        let fields = [];
        for(; (idx<len); idx++) {
            let record = recordset.getById(rec_order[idx]);
            let oldval = recordset.fld(record, fieldOrder);
            let newval = String(idx+1).lpad(0,3); // New order is 1-based, padded to 3 digits
            if(oldval!=newval){
                recordset.setFld(record, fieldOrder, newval);        
                let fld = {};
                fld[fieldId] = rec_order[idx];
                fld[fieldOrder] = newval;
                fields.push(fld);
            }
        }
        if(fields.length>0){

            let request = {
                'a'          : 'save',
                'entity'     : entityName,
                'request_id' : window.hWin.HEURIST4.util.random(),
                'fields'     : fields                     
            };

            window.hWin.HAPI4.EntityMgr.doRequest(request, 
                function(response){
                    if(response.status == window.hWin.ResponseStatus.OK){
                        if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
                    }else{
                        window.hWin.HEURIST4.msg.showMsgErr(response);
                    }
            });

        }else{
            if(window.hWin.HEURIST4.util.isFunction(callback)) callback.call();
        }
    },
    
    /**
     * Fetches record counts for each record type and updates the `rty_RecCount` field
     * in the local `defRecTypes` cache.
     * Sets `needUpdateRtyCount` to 0 upon initiating the request.
     *
     * @function get_record_counts
     * @memberof HEURIST4.dbs
     * @param {function} [callback] - A callback function to execute after the counts have been fetched and applied.
     */
    get_record_counts: function( callback )
    {
    
        $Db.needUpdateRtyCount = 0; 
        
        let request = {
                'a'       : 'counts',
                'entity'  : 'defRecTypes',
                'mode'    : 'record_count',
                //'rty_ID'  : // Can be used to get count for specific rty_ID, but not used here
                'ugr_ID'  : window.hWin.HAPI4.user_id() // Counts are user-specific (permissions)
                };
                             
        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){

                if(response.status == window.hWin.ResponseStatus.OK){
                    
                    $Db.rty().each(function(rty_ID,rec){
                        let cnt = response.data[rty_ID]
                        if(!(cnt>0)) cnt = 0;
                        $Db.rty(rty_ID, 'rty_RecCount', cnt);
                    });
                    
                    if(window.hWin.HEURIST4.util.isFunction(callback)){
                        callback.call();
                    }
        
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });
        
    },
    
    /**
     * Retrieves the ID of the 'Trash' group for a given entity type (e.g., 'rtg', 'dtg', 'vcg').
     * It caches the ID locally after the first lookup.
     *
     * @function getTrashGroupId
     * @memberof HEURIST4.dbs
     * @param {string} entity - The entity prefix for the group type (e.g., 'rtg' for Record Type Groups).
     *                          It expects a corresponding accessor like `$Db.rtg()` to exist.
     * @returns {number} The ID of the 'Trash' group for that entity type, or 0 if not found or on error.
     */
    getTrashGroupId: function(entity){
        
        if(!(this[entity+'_trash_id']>0)){ // Check local cache first
            let name_field = entity+'_Name'; // e.g., rtg_Name
            let that = this; // To access 'this' (HEURIST4.dbs) inside 'each2'
            $Db[entity]().each2(function(id, record){ // e.g., $Db.rtg().each2(...)
                if(record[name_field]=='Trash'){
                    that[entity+'_trash_id'] = id; // Cache it
                    return false; // Stop iteration
                }
            });
        }
        return this[entity+'_trash_id'];
    },
    
    /**
     * Parses a hierarchical code string (e.g., "rtid:dtid:rtid:dtid") into a human-readable format.
     * Used in facet and query builders to display the path of a selected field.
     * If a record type or field in the path is not found, it may mark the facet for removal.
     *
     * @function parseHierarchyCode
     * @memberof HEURIST4.dbs
     * @param {string} codes - The colon-separated hierarchical code string.
     * @param {number} [top_rty_ID] - The top-level record type ID, used if the first `rtid` in `codes` is 'any'.
     * @returns {false|{harchy: string[], harchy_fields: string[]}}
     *          `false` if a component in the hierarchy is not found (signaling potential removal of a facet).
     *          Otherwise, an object:
     *          - `harchy` (string[]): Array of strings representing the full path with HTML bolding for record types
     *                                and separators ('.', '>', '<').
     *          - `harchy_fields` (string[]): Array of field display names in the hierarchy.
     */
    parseHierarchyCode: function(codes, top_rty_ID){

        codes = codes.split(':');

        let removeFacet = false;
        let harchy = [];
        let harchy_fields = []; //for facet.title - only field names (w/o rectype)
        let j = 0;
        while(j<codes.length){
            let rtid = codes[j];
            let dtid = codes[j+1];

            if(rtid.indexOf(',')>0){ //take first from list of rty_IDs
                rtid = rtid.split(',')[0];
            }
            
            if(rtid!=''){
                if(rtid=='any'){
                    harchy.push('');    
                    if(top_rty_ID>0) rtid = top_rty_ID; // Use top_rty_ID for context if 'any'
                    
                }else if($Db.rty(rtid)==null){
                    //record type was removed - remove facet
                    removeFacet = true;
                    break;
                }else{
                    harchy.push('<b>'+$Db.rty(rtid,'rty_Name')+'</b>');    
                }
            }

            let rec_header = null;
            // Check for special header field codes
            if(dtid=='title'){
                rec_header = 'Constructed record title';
            }else if(dtid=='ids'){
                rec_header = "IDs"; 
            }else if(dtid=='typeid'){
                rec_header = "type ID"; 
            }else if(dtid=='typename'){
                rec_header = "type name"; 
            }else if(dtid=='added'){
                rec_header = "Added"; 
            }else if(dtid=='modified'){
                rec_header = "Modified"; 
            }else if(dtid=='addedby'){
                rec_header = "Record author"; 
            }else if(dtid=='url'){
                rec_header = "URL"; 
            }else if(dtid=='notes'){
                rec_header = "Notes"; 
            }else if(dtid=='owner'){
                rec_header = "Owner"; 
            }else if(dtid=='access'){
                rec_header = "Visibility"; 
            }else if(dtid=='tag'){
                rec_header = "Tags"; 
            }else if(dtid=='anyfield'){
                rec_header = "Any field"; 
            }else if(dtid=='exists'){
                rec_header = `${$Db.rty(rtid, 'rty_Name')} records`;
            }
            
            if( rec_header ){ // If it's a recognized header field
            
                    harchy.push(' . '+rec_header);
                    harchy_fields.push(rec_header);
                
            }else
            if(dtid){ // If dtid is present and not a header field
                
                if(dtid.indexOf('r.')==0){ // Relationship field prefix
                    dtid = dtid.substr(2);
                }

                let linktype = dtid.substr(0,2); // Check for link type prefixes (lt, rt, lf, rf)
                if(isNaN(Number(linktype))){ // If prefix is not a number, it's a link type
                    dtid = dtid.substr(2); // Actual dty_ID

                    if(dtid>0){


                        if(linktype=='lt' || linktype=='rt'){ // Linked To or Related To (direct)

                            const sFieldName = (rtid=='any') // If rtid is 'any', get dty_Name, else get rst_DisplayName
                                            ?$Db.dty(dtid, 'dty_Name')
                                            :$Db.rst(rtid, dtid, 'rst_DisplayName');

                            if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                                //field was removed - remove facet
                                removeFacet = true;
                                break;
                            }

                            harchy.push(' . '+sFieldName+' > ');
                            harchy_fields.push(sFieldName);
                        }else{ // Linked From or Related From (reverse)
                            let from_rtid = codes[j+2]; // The rtid from which this link originates

                            const sFieldName = $Db.rst(from_rtid, dtid, 'rst_DisplayName');

                            if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                                //field was removed - remove facet
                                removeFacet = true;
                                break;
                            }

                            harchy.push(' &lt '+sFieldName+' . ');
                            // harchy_fields might not push here for reverse links, depends on desired display
                        }

                    }//dtid>0

                }else{ // Normal field (not a link type with prefix)

                    const sFieldName = (rtid=='any')
                                ?$Db.dty(dtid, 'dty_Name')
                                :$Db.rst(rtid, dtid, 'rst_DisplayName');

                    if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                        //field was removed - remove facet
                        removeFacet = true;
                        break;
                    }

                    harchy.push(' . '+sFieldName);
                    harchy_fields.push(sFieldName);
                }
            }
            j = j+2;
        }//while codes        



        return removeFacet? false :{harchy:harchy, harchy_fields:harchy_fields};
    },
    
    /**
     * Retrieves workflow rules (`sysWorkflowRules`) applicable to a given record type and user.
     * Filters rules based on `swf_RecTypeID` and user restrictions in `swf_StageRestrictedTo`.
     *
     * @function getSwfByRectype
     * @memberof HEURIST4.dbs
     * @param {number} _rty_ID - The ID of the record type.
     * @param {number} _usr_ID - The ID of the user. If 0 or less, user restrictions are not checked.
     * @returns {Object[]} An array of workflow rule objects (records from `sysWorkflowRules`) that apply.
     */
    getSwfByRectype: function(_rty_ID, _usr_ID){
        
        let res = [];
        
        $Db.swf().each2(function(id, record){
            
            let rty_ID = record['swf_RecTypeID'];
            if(rty_ID == _rty_ID) 
            {
                let is_allowed = true;
                if(_usr_ID>0 && record['swf_StageRestrictedTo']){
                    //check restriction
                    let grps = record['swf_StageRestrictedTo'].split(',');
                    if(grps.indexOf(''+_usr_ID)<0){ // User/group not in allowed list
                        is_allowed = false;
                    }
                }
                if(is_allowed){
                    res.push(record);    
                }
            }
        });
        
        return res;
    },
    
    
    /**
     * Initiates the process for directly editing a calculated field's formula.
     * 1. Fetches the calculated function definition (`defCalcFunctions`).
     * 2. Finds all record types that use this calculated function in their structure (`defRecStructure`).
     * 3. Opens a report editor dialog (`widgets/report/reportEditor`) pre-filled with the formula
     *    and contextual information (affected record types).
     * 4. On closing the editor, if changes were made:
     *    a. Saves the updated formula back to `defCalcFunctions`.
     *    b. If successful and there are affected record types, opens a long operation dialog
     *       (`admin/verification/longOperationInit.php?type=calcfields`) to re-calculate affected fields.
     *
     * @function editCalculatedField
     * @memberof HEURIST4.dbs
     * @param {number} cfn_ID - The ID of the calculated function definition (`cfn_ID`) to edit.
     * @param {function} [main_callback] - A callback function to be passed as `afterclose` to the long operation dialog.
     */
    editCalculatedField: function(cfn_ID, main_callback){

        if(!(cfn_ID>0)) return;

        let request = {};
        request['cfn_ID']  = cfn_ID;
        request['a']          = 'search'; //action
        request['entity']     = 'defCalcFunctions';
        request['details']    = 'full';
        request['request_id'] = window.hWin.HEURIST4.util.random();

        window.hWin.HAPI4.EntityMgr.doRequest(request, 
            function(response){
                if(response.status == window.hWin.ResponseStatus.OK){
                    let recset = new HRecordSet(response.data);
                    if(recset.length()>0){

                        let cfn_record = recset.getFirstRecord();
                        let cfn_Content = recset.fld(cfn_record, 'cfn_FunctionSpecification');

                        //find affected record types
                        //finds all fields with rst_CalcFunctionID = cfn_ID
                        let request_struct = {}; // Renamed to avoid conflict with outer 'request'
                        request_struct['rst_CalcFunctionID']  = cfn_ID;
                        request_struct['a']          = 'search'; //action
                        request_struct['entity']     = 'defRecStructure';
                        request_struct['details']    = 'rectype'; // We need rst_RecTypeID from the results
                        request_struct['request_id'] = window.hWin.HEURIST4.util.random();
                        window.hWin.HAPI4.EntityMgr.doRequest(request_struct,
                            function(response_struct){ // Renamed to avoid conflict
                                if(response_struct.status == window.hWin.ResponseStatus.OK){

                                    let rectypes = null;
                                    let recset_struct = new HRecordSet(response_struct.data); // Renamed
                                    if(recset_struct.length()>0){
                                        rectypes = [];
                                        recset_struct.each2(function(id, rec){
                                            rectypes.push(rec['rst_RecTypeID']);
                                        });
                                    }
                                    
                                    let popup_dialog_options = {path: 'widgets/report/', 
                                                default_palette_class: 'ui-heurist-design',
                                                title: 'Edit calculation field',
                                                keep_instance:false, 
                                                
                                                isCalcFieldTemplate: true,
                                                is_snippet_editor: true, 
                                                rty_ID:rectypes, // Pass affected record types to editor
                                                rec_ID:0, // No specific record context for editing the formula itself
                                                template_body:cfn_Content,
                                                
                                                onClose: function(context){ // `context` is the new formula from editor
                                                    if(!context) return;

                                                    //save new formula
                                                    let request_save = { // Renamed
                                                        'a'          : 'save',
                                                        'entity'     : 'defCalcFunctions',
                                                        'request_id' : window.hWin.HEURIST4.util.random(),
                                                        'fields'     : {cfn_ID:cfn_ID, cfn_FunctionSpecification:context}
                                                    };
                                                    window.hWin.HAPI4.EntityMgr.doRequest(request_save,
                                                        function(response_save){ // Renamed
                                                            if(response_save.status == window.hWin.ResponseStatus.OK){
                                                                //update caclulated fields
                                                                if(rectypes && rectypes.length>0){

                                                                    let sURL = window.hWin.HAPI4.baseURL + 'admin/verification/longOperationInit.php?type=calcfields&db='
                                                                    +window.hWin.HAPI4.database+"&recTypeIDs="+rectypes.join(',');

                                                                    window.hWin.HEURIST4.msg.showDialog(sURL, {

                                                                        "close-on-blur": false,
                                                                        "no-resize": true,
                                                                        height: 400,
                                                                        width: 550,
                                                                        afterclose: main_callback
                                                                    });                                                            

                                                                } else if (window.hWin.HEURIST4.util.isFunction(main_callback)) {
                                                                    // If no rectypes affected but save was OK, still call callback if provided
                                                                    main_callback.call();
                                                                }
                                                            }else{
                                                                window.hWin.HEURIST4.msg.showMsgErr(response_save);
                                                            }
                                                    });
                                                }
                                    };
                                    window.hWin.HEURIST4.ui.showRecordActionDialog('reportEditor', popup_dialog_options);

                                }else{
                                    window.hWin.HEURIST4.msg.showMsgErr(response_struct);
                                }
                            }
                        );

                    }                            
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }
        });           
    },
    
    /**
     * Parses a hierarchical code string (e.g., "rtid:dtid:rtid:dtid") and returns display titles for the components.
     * Used in faceted search and for displaying linked geo places.
     * Similar to `parseHierarchyCode` but focuses on generating titles.
     *
     * @function getHierarchyTitles
     * @memberof HEURIST4.dbs
     * @param {string} codes - The colon-separated hierarchical code string.
     * @returns {false|{harchy: string[], harchy_fields: string[]}}
     *          `false` if a component in the hierarchy is not found.
     *          Otherwise, an object:
     *          - `harchy` (string[]): Array of strings representing the full path with HTML bolding for record types
     *                                and separators ('.', '>', '<').
     *          - `harchy_fields` (string[]): Array of field display names in the hierarchy.
     */
    getHierarchyTitles: function( codes ){
      
        let removeFacet = false;
        let harchy = [];
        let harchy_fields = []; //for facet.title
        codes = codes.split(':');
        let j = 0;
        while(j<codes.length){
            let rtid = codes[j];
            let dtid = codes[j+1];
            
            if(rtid.indexOf(',')>0){ // Take the first rtid if it's a list
                rtid = rtid.split(',')[0];
            }
            
            if($Db.rty(rtid)==null){
                //record type was removed - remove facet
                removeFacet = true;
                break;
            }
            
            harchy.push('<b>'+$Db.rty(rtid,'rty_Name')+'</b>');
            
            // Handle special header field codes
            if(j==0 && dtid=='title'){ // Only for the top-level item
               harchy_fields.push('Constructed record title');
            }else
            if(dtid=='modified'){
               harchy_fields.push("Modified"); 
            }else if(dtid=='added'){
               harchy_fields.push("Added"); 
            }else if(dtid=='ids'){
               harchy_fields.push("Record ID"); 
            }else if(dtid=='typeid' || dtid=='t'){ // 't' is a shorthand for typeid
               harchy_fields.push("Type ID"); 
            }else if(dtid=='typename'){ //record type name rty_Name
               harchy_fields.push("Type Name"); 
            }else if(dtid=='addedby'){
               harchy_fields.push("Creator"); 
            }else if(dtid=='owner'){
               harchy_fields.push("Record Owner"); 
            }else if(dtid=='access'){
               harchy_fields.push("Record Visibility"); 
            }else if(dtid=='notes'){
               harchy_fields.push("Notes"); 
            }else if(dtid=='url'){
               harchy_fields.push("URL"); 
            }else if(dtid=='tag'){
               harchy_fields.push("Tags"); 
            }
            // End of header field specific section for harchy_fields
            
            if(dtid.indexOf('r.')==0){ // Relationship field prefix
                dtid = dtid.substr(2);
            }
            
            let linktype = dtid.substr(0,2);  // Check for link type prefixes
            if(isNaN(Number(linktype))){ // If prefix is not a number, it's a link type
                dtid = dtid.substr(2); // Actual dty_ID
                
                if(dtid>0){
                
                    
                if(linktype=='lt' || linktype=='rt'){ // Linked To or Related To (direct)
                    
                    const sFieldName = $Db.rst(rtid, dtid, 'rst_DisplayName');
                    
                    if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                        removeFacet = true;
                        break;
                    }
                    
                    harchy.push(' . '+sFieldName+' &gt; '); // Add field name and separator to main hierarchy path
                    harchy_fields.push(sFieldName); // Add field name to field list
                }else{ // Linked From or Related From (reverse)
                    const from_rtid = codes[j+2]; // The rtid from which this link originates

                    const sFieldName = $Db.rst(from_rtid, dtid, 'rst_DisplayName');
                    
                    if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                        removeFacet = true;
                        break;
                    }
                    
                    harchy.push(' &lt; '+sFieldName+' . ');
                    harchy_fields.push(sFieldName); // Add field name for reverse links too
                }
                
                }//dtid>0
                
            }else{ // Normal field
                // This 'else' block is reached if dtid was not a header field code AND
                // it didn't have a non-numeric two-letter prefix (lt, rt, lf, rf).
                // So, dtid here is expected to be a direct field dty_ID.
                const sFieldName = $Db.rst(rtid, dtid, 'rst_DisplayName');
                
                if(window.hWin.HEURIST4.util.isempty(sFieldName)){
                    // This can happen if dtid was a header code not caught above, or an invalid field.
                    // For example, if dtid was 'title' but j > 0.
                    // Or if it's a simple field that was removed.
                    if (! (j==0 && dtid=='title') && !['modified', 'added', 'ids', 'typeid', 't', 'typename', 'addedby', 'owner', 'access', 'notes', 'url', 'tag'].includes(dtid) ) {
                         // Only mark for removal if it's not a known header type that might have been pushed to harchy_fields already
                        removeFacet = true;
                        break;
                    }
                } else {
                     harchy.push(' . '+sFieldName);
                     harchy_fields.push(sFieldName);
                }
            }
            j = j+2;
        }//while codes
       
        if(removeFacet){
            return false;
        }else{
            return {harchy:harchy, harchy_fields:harchy_fields};
        }         

    },

    /**
     * Retrieves a structured list of base fields (detail types) and their specific instances
     * (record type structure fields) across one or more record types.
     *
     * @function getBaseFieldInstances
     * @memberof HEURIST4.dbs
     * @param {number|number[]|string} rty_IDs - A single record type ID, an array of record type IDs, or 'all' to process all record types.
     * @param {number} [mode=0] - Output format mode:
     *  - `0`: Flat data array. Each element is `[dty_id, dty_label, [rst_label1, rst_label2, ...], show_in_lists_flag]`.
     *           `show_in_lists_flag` is true if `list_all_fields` is false and `dty_ShowInLists` is 0.
     *  - `1`: Array for dropdowns. Objects with `key` (dty_id), `title` (dty_label or rst_label), `depth` (for rst instances), `hidden` (show_in_lists_flag).
     *  - `2`: (Commented as "needs testing") Intended for Fancytree nodes with `title`, `key`, `code`, `children`.
     * @param {string|string[]} [allowed_types='all'] - Field types to include (e.g., 'freetext', ['resource', 'enum']). 'all' includes all types.
     * @param {number|number[]} [ignored_dty_id=[]] - Detail type ID(s) to ignore.
     * @param {boolean} [list_all_fields=true] - If `false`, the `dty_ShowInLists` property is considered for the `show_in_lists_flag`/`hidden` property.
     * @returns {Array} An array structured according to the specified `mode`. Returns an empty array if `rty_IDs` is invalid or empty.
     */
    getBaseFieldInstances: function(rty_IDs, mode = 0, allowed_types = 'all', ignored_dty_id = [], list_all_fields = true){

        let fields = [];

        if(!rty_IDs || rty_IDs == 'all'){ // get all ids
            rty_IDs = $Db.rty().getIds();
        }

        if(!Array.isArray(rty_IDs) && rty_IDs > 0){
            rty_IDs = [ rty_IDs ];
        }
        if(!Array.isArray(ignored_dty_id) && ignored_dty_id > 0){
            ignored_dty_id = [ ignored_dty_id ];
        }

        if(!rty_IDs || !window.hWin.HEURIST4.util.isArrayNotEmpty(rty_IDs)){
            return [];
        }

        let last_idx = 0; // current index count
        let arr_idx = {}; // id to array idx
        for(const rty_id of rty_IDs){ // Get base fields and instances for each rectype

        
            const rty_name = $Db.rty(rty_id, 'rty_Name');

            const recset = $Db.rst(rty_id);

            if(window.hWin.HEURIST4.util.isempty(recset)) { continue; }

            recset.each2(function(dty_id, details){

                if(dty_id == ignored_dty_id || ignored_dty_id.indexOf(dty_id) >= 0){
                    return;
                }

                const dty = $Db.dty(dty_id);
                const dty_name = dty['dty_Name'];

                if(allowed_types != 'all' && allowed_types.indexOf(dty['dty_Type']) < 0){
                    return;
                }

                if(!Object.hasOwn(arr_idx, dty_id)) {
                    // show_in_lists_flag / hidden: true if it should be hidden/not shown in lists
                    let list_fld = !list_all_fields && $Db.dty(dty_id, 'dty_ShowInLists') == 0;
                    arr_idx[dty_id] = last_idx;
                    last_idx ++;
                    fields.push( [ dty_id, dty_name, [], list_fld ] );
                }

                const dty_idx = arr_idx[dty_id];
                const rst_name = rty_name + "." + details["rst_DisplayName"]; // Instance name: RtyName.RstDisplayName

                fields[dty_idx][2].push(rst_name);
            });
        }

        // sort base field names
        fields.sort((arr1, arr2) => {
            let a = arr1[1].toLocaleUpperCase(); // Sort by dty_label (base field name)
            let b = arr2[1].toLocaleUpperCase();
            return a.localeCompare(b);
        });

        let processed_fields = [];

        for(const field of fields){ // sort rst field names + additional processing for different modes

            field[2].sort((a, b) => { // Sort instance names alphabetically
                a = a.toLocaleUpperCase();
                b = b.toLocaleUpperCase();
                return a.localeCompare(b);
            });

            const dty_id = field[0];
            const dty_title = field[1];
            const rst_titles = field[2];
            const show_in_list_flag = field[3]; // This flag is true if it should be hidden

            if(mode == 1){ // For dropdowns
               
                processed_fields.push({key: dty_id, title: dty_title, hidden: show_in_list_flag});

                for(const rst_title of rst_titles){
                    processed_fields.push({key: dty_id, title: rst_title, depth: 1, hidden: show_in_list_flag});
                }
            }
            /*  needs testing
            else if(false && mode == 2){ // For Fancytree (example)

                let node = {
                    'title': dty_title,
                    'key': dty_id,
                    'code': dty_id, // Could be dty_id or another code
                    'children': []
                    // 'hidden': show_in_list_flag // If tree supports hiding nodes
                };

                let sub_node_template = { // Template for children
                    'key': dty_id, // Child key might be composite like dty_id + '_' + rst_title or just dty_id
                    'code': dty_id // Child code
                    // 'hidden': show_in_list_flag
                };

                for(const rst_title of rst_titles){
                    let sub_node = {...sub_node_template};
                    sub_node['title'] = rst_title;
                    node['children'].push(sub_node);
                }

                processed_fields.push(node);
            }
            */
        }

        if(mode == 0 || mode == 2){ // Mode 0 returns the 'fields' array directly; Mode 2 would too if implemented
            return fields;
        }else{ // Mode 1 returns 'processed_fields'
            return processed_fields;
        }
    },

    /**
     * Finds detail type IDs (fields) that are common to all specified record types.
     *
     * @function getSharedFields
     * @memberof HEURIST4.dbs
     * @param {number|number[]} rty_IDs - A single record type ID or an array of record type IDs. If falsy, returns empty array.
     * @param {number|number[]} [ignored_dty_id=[]] - An array of detail type IDs to exclude from the result.
     * @returns {number[]} An array of detail type IDs that are present in all given record types (and not ignored).
     */
    getSharedFields: function(rty_IDs, ignored_dty_id = []){

        let dty_IDs = [];

        if(!rty_IDs){ // get all ids
            return dty_IDs;
        }

        if(!Array.isArray(rty_IDs) && rty_IDs > 0){
            rty_IDs = [ rty_IDs ];
        }
        if(!Array.isArray(ignored_dty_id) && ignored_dty_id > 0){
            ignored_dty_id = [ ignored_dty_id ];
        }

        if(!rty_IDs || !window.hWin.HEURIST4.util.isArrayNotEmpty(rty_IDs)){
            return dty_IDs;
        }

        for(const rty_ID of rty_IDs){

            let fields_for_rty = $Db.rst(rty_ID).getIds(); // Get all dty_IDs for this rty_ID
            if(dty_IDs.length == 0){ // First record type, so all its fields are potential shared fields
                dty_IDs = fields_for_rty;
                continue;
            }
            // Intersect current dty_IDs with fields_for_rty
            dty_IDs = dty_IDs.filter(fld_id => fields_for_rty.includes(fld_id));
        }

        if(dty_IDs.length > 0 && ignored_dty_id.length > 0){
            dty_IDs = dty_IDs.filter(fld_id => !ignored_dty_id.includes(fld_id));
        }

        return dty_IDs;

    },

    /**
      * Interprets a Heurist entry mask string (e.g., from `rst_EntryMask`) into a human-readable description
      * and a version with a placeholder for the value.
      * Mask format example: `Prefix $a10(1,5) Suffix`
      * - `$` introduces the mask pattern.
      * - `a`: alphabetic, `d`: decimal, `i`: integer, `m`: mixed alphanumeric, `n`: numeric.
      * - Optional number after type: length constraint (e.g., `a10` for max 10 chars for alpha; decimal places for `d`, `n`).
      * - Optional `(min,max)`: numeric range.
      *
      * @function rst_InterpretEntryMask
      * @memberof HEURIST4.dbs
      * @param {string} mask - The entry mask string.
      * @returns {[string, string]} An array where:
      *          - Index 0: Human-readable description of the mask (e.g., "a string with a maximum of 10 characters, 1 to 5").
      *          - Index 1: The original mask with the pattern part replaced by `&lt;value&gt;` (e.g., "Prefix &lt;value&gt; Suffix").
      *          Returns `['', '']` if the mask does not contain a valid pattern.
      */
    rst_InterpretEntryMask: function(mask){

        /**
         * @function getHelpText
         * @private
         * @description Generates human-readable help text for a given mask pattern.
         * @param {string} type - Single character mask type ('a', 'd', 'i', 'm', 'n').
         * @param {number} length - Length constraint from the mask.
         * @param {string[]} range - Two-element array [min, max] for numeric range.
         * @returns {string} Human-readable description.
         */
        function getHelpText(type, length, range){

            let help_text = '';

            switch(type){

                case 'a':
                    help_text = 'a string';
                    break;

                case 'd':
                    help_text = `a decimal`;
                    break;

                case 'i':
                    help_text = `an integer`;
                    break;

                case 'm':
                    help_text = `a mixed (alphanumeric) value`;
                    break;

                case 'n':
                    help_text = `a numeric value`;
                    break;

                default:
                    break;
            }
            
            if(range?.length == 2){ // If range is specified
                
                switch(type){

                    case 'd':
                    case 'i':
                    case 'n':
                        help_text += ` from ${range[0]} to ${range[1]}`; // Corrected "is" to "from"
                        break;

                    default:
                        break;
                }
            }

            if(length > 0){ // If length constraint is specified

                switch(type){

                    case 'a':
                        help_text += ` with a maximum of ${length} characters`;
                        break;

                    case 'd':
                    case 'n':
                        help_text += `, rounded to ${length} decimal places`;
                        break;

                    case 'i': // For integers, length can mean max number of digits
                         help_text += ` with a maximum of ${length} digits`;
                         break;

                    default:
                        break;
                }
            }

            return help_text;
        }
        
        // Regex captures: $1:type, $2:length (optional), $3:range (optional, e.g., (min,max))
        let matches = mask.match(/\$([adimn])(\d+)?(\(\d+,\d+\))\$/);
        let rtn = ['', ''];

        if(!matches){
            return rtn;
        }

        // Extract length: $2 might be length or start of range if $2 is like "(".
        // If $2 is digits, it's length. If $2 starts with '(', it's range and length is 0.
        let length = 0;
        let rangeStr = null;

        if (matches[2] && /^\d+$/.test(matches[2])) { // If $2 is purely digits
            length = Number.parseInt(matches[2]);
            if (matches[3] && matches[3].startsWith('(')) { // And $3 is the range
                rangeStr = matches[3];
            }
        } else if (matches[2] && matches[2].startsWith('(')) { // If $2 is the range
            rangeStr = matches[2];
            // length remains 0
        }
        // This logic might need refinement if $3 can exist without $2 being purely digits.
        // Current regex: (\d)* means $2 can be empty. (\(\d,?\d*\))* means $3 can be empty.

        let range = null;
        if (rangeStr) {
            range = rangeStr.replace(/[()]/g, '').split(','); // remove parentheses and split
            if (range.length === 1 && rangeStr.includes(',')) { // e.g. "(,5)" or "(1,)"
                 if (rangeStr.startsWith('(,')) range.unshift(''); else range.push('');
            }
            if (range.length === 1 && !rangeStr.includes(',')) { // e.g. "(5)" this is not valid range, maybe treat as length?
                // This case is ambiguous based on typical mask definitions.
                // For now, if it's a single number in parens, it's not a typical min,max range.
                // The regex (\(\d,?\d*\)) implies a comma for two values or one value if it's (val) or (,val) or (val,).
                // Let's assume if only one value in parens, it's not a valid range for this interpretation.
                 range = null;
            }
        }


        let temp = null;
        if(range?.length == 2 && range[0] && range[1] && Number(range[0]) > Number(range[1])){ // Ensure both are numbers for comparison
            temp = range[0];
            range[0] = range[1];
            range[1] = temp;
        }

        rtn[0] = getHelpText(matches[1], length, range);
        rtn[1] = mask.replace(matches[0], '&lt;value&gt;');

        return rtn;
    },

    /**
     * Tests a given value against a Heurist entry mask to validate it.
     *
     * @function rst_RunEntryMask
     * @memberof HEURIST4.dbs
     * @param {string} mask - The entry mask string (e.g., "ID-$i(1,100)").
     * @param {string} value - The value to test against the mask.
     * @param {boolean} [true_on_success=false] - If `true`, returns boolean `true` on successful validation against the pattern part,
     *                                          otherwise returns the fully constructed string with the (potentially modified) value
     *                                          or an error message if validation fails.
     * @returns {boolean|string}
     *          - If `true_on_success` is true: `true` if the value part matches the mask's pattern,
     *            otherwise an error string explaining the mismatch.
     *          - If `true_on_success` is false: The fully constructed string with the value part formatted/validated
     *            (e.g., "ID-50") if valid, or an error string if invalid.
     *          - Returns 'Invalid entry mask provided' if the mask itself is malformed.
     */
    rst_RunEntryMask: function(mask, value, true_on_success = false){

        /**
         * @function handleNumbers
         * @private
         */
        function handleNumbers(type, mask_pattern_part, to_replace_in_original_mask, val_str, len_constraint, range_constraint){

            if(value.match(/[^\d.]/) !== null){ // Check for non-digit or decimal point
                return 'Input contains non-numeric characters';
            }

            let output_val;
            if (type === 'i') { // Integer
                if (val_str.includes('.')) return 'Integer cannot contain decimal point';
                output_val = Number.parseInt(val_str);
            } else { // Decimal or Numeric
                output_val = Number.parseFloat(val_str);
            }

            if(isNaN(output_val)){ // Check if parsing failed
                let type_text = type === 'i' ? 'an integer' : (type === 'd' ? 'a decimal' : 'numeric');
                return `Input is not ${type_text}`;
            }

            // Apply length constraint for decimals (number of decimal places)
            if(type !== 'i' && len_constraint > 0){
                output_val = Number(output_val.toFixed(len_constraint)); // Re-number to drop trailing zeros if appropriate after toFixed
            }

            let final_check_val = (type === 'i') ? output_val : Number.parseFloat(val_str); // Use original float for range check if not integer

            // Range check
            if(range_constraint?.length == 2){
                const min = range_constraint[0] !== '' ? Number(range_constraint[0]) : -Infinity;
                const max = range_constraint[1] !== '' ? Number(range_constraint[1]) : Infinity;
                if(final_check_val < min || final_check_val > max){
                    return `Input is out of range ${range_constraint[0]} - ${range_constraint[1]}`;
                }
            }

            // Length constraint for integers (number of digits)
            if(type === 'i' && len_constraint > 0 && String(Math.abs(output_val)).length > len_constraint){
                return `Input has too many digits, limited to ${len_constraint} digits`;
            }

            // If true_on_success, we just care if it's valid up to this point
            if (true_on_success) return true;

            return mask_pattern_part.replace(to_replace_in_original_mask, String(output_val));
        }

        /**
         * @function getTestOutput
         * @private
         */
        function getTestOutput(original_mask_str, pattern_to_replace, mask_char_type, val_to_test, len_constr, range_constr){

            let output_str = '';
            let regex_results = null;

            switch(mask_char_type){

                case 'a': // Alphabetic (allows spaces and some punctuation)
                    // This regex is more permissive than strictly alphabetic. Adjust if needed.
                    regex_results = val_to_test.match(/^[\w\s.,'"?!()[\]\-`:;/]+$/);
                    if (regex_results === null) {
                        output_str = 'Input contains invalid characters for alphabetic string';
                    } else if (len_constr > 0 && val_to_test.length > len_constr) {
                        output_str = `Input is larger than ${len_constr} characters`;
                    } else {
                        output_str = true_on_success ? true : original_mask_str.replace(pattern_to_replace, val_to_test);
                    }
                    break;

                case 'd': // Decimal
                case 'i': // Integer
                case 'n': // Numeric (can be float or integer)
                    output_str = handleNumbers(mask_char_type, original_mask_str, pattern_to_replace, val_to_test, len_constr, range_constr);
                    break;

                case 'm': // Mixed alphanumeric
                    regex_results = val_to_test.match(/^[\w\d\s.,'"?!()[\]\-`:;/]+$/);
                     if (regex_results === null) {
                        output_str = 'Input contains invalid characters for mixed alphanumeric string';
                    } else if (len_constr > 0 && val_to_test.length > len_constr) {
                        output_str = `Input is larger than ${len_constr} characters`;
                    } else {
                         output_str = true_on_success ? true : original_mask_str.replace(pattern_to_replace, val_to_test);
                    }
                    break;

                default:
                    output_str = `Mask's format is invalid, unknown type '${mask_char_type}'`;
                    break;
            }

            return output_str;
        }

        let matches = mask.match(/\$([adimn])(\d+)?(\(\d+,\d+\))\$/);

        if(!matches){
            return 'Invalid entry mask provided';
        }

        const mask_pattern_part = matches[0]; // The full e.g., "$a10(1,5)"
        const type_char = matches[1];
        let length_constraint = 0;
        let range_constraint_arr = null;

        // Similar logic to rst_InterpretEntryMask for parsing length and range
        if (matches[2] && /^\d+$/.test(matches[2])) {
            length_constraint = Number.parseInt(matches[2]);
            if (matches[3] && matches[3].startsWith('(')) {
                range_constraint_arr = matches[3].replace(/[()]/g, '').split(',');
            }
        } else if (matches[2] && matches[2].startsWith('(')) {
            range_constraint_arr = matches[2].replace(/[()]/g, '').split(',');
        }

        if (range_constraint_arr && range_constraint_arr.length === 1 && matches[2].includes(',')) {
             if (matches[2].startsWith('(,')) range_constraint_arr.unshift(''); else range_constraint_arr.push('');
        }
        if (range_constraint_arr && range_constraint_arr.length === 1 && !matches[2].includes(',')) {
            range_constraint_arr = null; // Single value in parens is not a range here.
        }


        if(range_constraint_arr?.length == 2 && range_constraint_arr[0] && range_constraint_arr[1] && Number(range_constraint_arr[0]) > Number(range_constraint_arr[1])){
            let temp = range_constraint_arr[0];
            range_constraint_arr[0] = range_constraint_arr[1];
            range_constraint_arr[1] = temp;
        }

        let result = getTestOutput(mask, mask_pattern_part, type_char, value, length_constraint, range_constraint_arr);

        // If true_on_success, result is already boolean true or an error string.
        if (true_on_success) {
            return result;
        }

        // If not true_on_success, and result is an error string, return it.
        if (typeof result === 'string' && result !== mask.replace(mask_pattern_part, String(value))) {
            // Check if result indicates an error (i.e., it's not the successfully substituted string)
            // This comparison is a bit tricky. A more robust way is for getTestOutput/handleNumbers
            // to return a specific error object or boolean for success when not in true_on_success mode.
            // For now, if 'result' is a string and it's not the simple replacement, assume it's an error message.
            // Or, more simply, if it doesn't start with the prefix of the mask (if any), it's an error.
            const mask_prefix = mask.substring(0, mask.indexOf(mask_pattern_part));
            if (!result.startsWith(mask_prefix)) {
                return result; // It's an error message
            }
        }

        // Otherwise, result is the successfully formatted string.
        return result;
    },

    /**
     * Removes duplicate hierarchical prefixes from a label string.
     * For example, "Australia.New South Wales.New South Wales.Sydney"
     * becomes "Australia.New South Wales.Sydney" if `trm_separator` is '.'.
     *
     * @function trm_RemoveDupHierarchy
     * @memberof HEURIST4.dbs
     * @param {string} label - The label string, possibly with hierarchical parts.
     * @param {string} [trm_separator='.'] - The separator character used for hierarchy.
     * @returns {string} The label with duplicate hierarchical prefixes removed.
     *                   Returns the original label if it's empty or contains no separator.
     */
    trm_RemoveDupHierarchy: function(label, trm_separator = '.'){

        if(window.hWin.HEURIST4.util.isempty(label) || label.indexOf(trm_separator) === -1){
            return label;
        }

        trm_separator = window.hWin.HEURIST4.util.isempty(trm_separator) ? '.' : trm_separator;

        let parts = label.split(trm_separator);
        let i = 1;

        while(i < parts.length){

            let prefix = parts.slice(0, i).join(trm_separator);
            let remainder = parts.slice(i).join(trm_separator);

            // check if prefix appears at start
            if(remainder.startsWith(prefix + trm_separator)){ // Check with separator to avoid partial match like "Term.Termite"
                parts = parts.slice(i); // Remove the repeated prefix
                i = 1; // Restart search from the new beginning of parts
            }else{ // no repeat, continue searching
                i++;
            }
        }

        return parts.join(trm_separator);
    }

}//end dbs

}
//alias
window.$Db = window.hWin.HEURIST4.dbs;