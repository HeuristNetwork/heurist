/**
 * lookupLRC18C.js - Widget for importing ESTC Edition records into the LRC18C database.
 *
 * @fileOverview
 * This file defines the `heurist.lookupLRC18C` jQuery UI widget.
 * It specializes `heurist.lookupESTC` to facilitate searching ESTC "Edition"
 * records (Heurist Record Type 30 from 'ESTC_Helsinki_Bibliographic_Metadata')
 * and importing them into a target Heurist database, presumably the
 * "Libraries_Readers_Culture_18C_Atlantic" database, using predefined mappings.
 *
 * The widget:
 *  1. Loads HTML content from `lookupLRC18C.html` (handled by parent `lookupESTC` when not `_is_works`).
 *  2. Allows users to define search parameters for ESTC Edition records.
 *  3. Displays found records in a list.
 *  4. When a user selects records, `doAction` calls `_importRecords` (from `lookupESTC`)
 *     to import the selected records and their linked entities (Agents, Places, Works)
 *     from the ESTC source database to the target LRC18C database, using the
 *     detailed field and vocabulary mappings defined in `this.mapping_defs`.
 *
 * @package     Heurist academic knowledge management system
 * @subpackage  hclient\widgets\lookup
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Darshan Nagavara   <darshan@intersect.org.au>
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

// The global mapDict is declared here but not directly used within this widget's own methods.
// It might be used by methods in the parent class (lookupESTC) or was intended for other purposes.
let mapDict = {}

/**
 * Widget for looking up and importing ESTC Edition records into the LRC18C database.
 * It defines specific mappings for data transformation during the import process.
 *
 * @widget heurist.lookupLRC18C
 * @extends heurist.lookupESTC
 * @memberof heurist
 */
$.widget("heurist.lookupLRC18C", $.heurist.lookupESTC, {

    /**
     * Defines the detailed mapping rules for importing records from the
     * ESTC Helsinki Bibliographic Metadata database to the target (LRC18C) database.
     * This object is structured by source record type IDs from the ESTC database.
     *
     * Each key is a source Record Type ID (e.g., 10 for Agents, 49 for Works, 12 for Places, 30 for Editions).
     * The value is an object specifying:
     * - `rty_ID`: The target Record Type ID in the LRC18C database.
     * - `key`: The source field ID in the ESTC database used as a primary key for matching/linking.
     * - `details`: An object where keys are source field IDs (from ESTC) and values are target
     *              field IDs (in LRC18C). This defines direct field-to-field mapping.
     *              Comments often indicate the original field names.
     * - `vocabularies`: An array of source vocabulary (Term List) IDs from ESTC that need to be
     *                   synchronized or mapped to corresponding vocabularies in the LRC18C database.
     *                   Comments indicate the mapping (e.g., "book formats for 256 => 991").
     *
     * This mapping definition is crucial for the `_importRecords` method in the parent `lookupESTC` widget.
     *
     * @memberof heurist.lookupLRC18C
     * @instance
     * @type {Object}
     */
    mapping_defs:{
        10:{ // Source: ESTC Agent (rt:10)
            rty_ID:10, // Target: LRC18C Agent (rt:10)
            key:253,   // ESTC Name Unified (fieldID:253) is the key
            details: { // Field mappings (sourceFieldID: targetFieldID)
                250: 1,    // Standarised Name -> Title
                18: 18,    // Given Name -> Given Name
                248: 999,  // Designation (commented out or placeholder target)
                1: 1046,   // Family Name -> Family Name
                252: 1098, // ESTC Actor ID -> ESTC Actor ID
                253: 1086, // ESTC Name Unified -> ESTC Name Unified (also key)
                287: 132,  // Also Known As -> Also Known As
                10: 10,    // Birth Date -> Birth Date
                11: 11,    // Death Date -> Death Date
                249: 1049, // Prefix -> Prefix (ENUM!)
                279: 1050, // Suffix to Name -> Suffix to Name
                278: 1000  // Agent Type -> Agent Type (ENUM!)
            }
        },

        49:{ // Source: ESTC Work (rt:49)
            rty_ID:56, // Target: LRC18C Work (rt:56)
            key:271,   // Project Record ID (fieldID:271) is the key
            details: { 
                1: 1,      // Title -> Title
                276: 1091, // Full/Extended title -> Full/Extended title
                271: 1092, // Project Record ID -> Project Record ID (also key)
                273: 1093  // Helsinki Work Name -> Helsinki Work Name
                // Other fields like Helsinki Work ID, Assignation, Raw Data are noted but not directly mapped here.
            }
        },    

        12:{ // Source: ESTC Place (rt:12)
            rty_ID:12, // Target: LRC18C Place (rt:12)
            key:268,   // ESTC Place ID (fieldID:268) is the key
            details: { 
                1: 1,      // Title -> Title
                260: 939,  // Region -> Region (ENUM)
                264: 940,  // Country -> Country (ENUM)
                265: 1089, // ESTC location ID -> ESTC location ID
                133: 133,  // Place type -> Place type (ENUM - Cleaned Upper Territory vocab)
                268: 1090  // ESTC Place ID -> ESTC Place ID (also key)
            }
        },    

        30:{ // Source: ESTC Book(edition) (rt:30)
            rty_ID:55, // Target: LRC18C Edition (rt:55)
            key:254,   // ESTC ID (fieldID:254) is the key
            details: {
                1: 1,        // Title -> Title
                9: 10,       // Year of First Volume -> Year of First Volume
                275: 955,    // Year of Final Volume -> Year of Final Volume
                256: 991,    // Book Format -> Book Format (ENUM)
                259: 238,    // Place -> Place (POINTER to Place rt:12)
                285: 1096,   // Summary Publisher Statement -> Summary Publisher Statement
                254: 1094,   // ESTC ID -> ESTC ID (also key)
                15: 1106,    // Author -> Author (POINTER to Agent rt:10)
                284: 949,    // Work -> Work (POINTER to Work rt:56)
                277: 1095,   // Extended Edition title -> Extended Edition title
                137: 962,    // No of volumes -> No of volumes
                290: 1107,   // No of parts -> No of parts
                270: 652     // Imprint details -> Imprint details
            }
        },

        /**
         * Array of source vocabulary (Term List) IDs from the ESTC database
         * that need to be synchronized or mapped during the import process.
         * Comments indicate the mapping target or original purpose.
         */
        vocabularies:[
            5430, // ESTC Book Formats (e.g., for field 256 mapped to target field 991)
            5432, // ESTC Region 18C (e.g., for field 260 mapped to target field 939)
            5436, // ESTC Country 18C (e.g., for field 264 mapped to target field 940)
            5039, // ESTC Place Type (e.g., for field 133 mapped to target field 133)
            507,  // ESTC Prefix/Honorific (e.g., for field 249 mapped to target field 1049)
            5848  // ESTC Agent Type (e.g., for field 278 mapped to target field 1000)
        ]
    },

    /**
     * Defines the mapping from UI input field placeholders (e.g., `__edition_name__`)
     * to the query parameters for searching ESTC Edition records (Heurist Record Type 30)
     * in the source ESTC database. This structure is identical to that in `lookupESTC_editions.js`
     * as both widgets target the same source records for searching.
     *
     * @memberof heurist.lookupLRC18C
     * @instance
     * @type {Object}
     * @see heurist.lookupESTC_editions#search_mapping for detailed structure.
     */
    search_mapping: {
        t: '30', // Target record type: ESTC Edition records
        'f:1': '@__edition_name__',
        'f:9': '__edition_date__',
        'linkedto:15': {t: '10', 'f:250': '__edition_author__'},
        'linkedto:284': {t: '49', 'f:272': '__edition_work__'},
        'linkedto:259': {t: '12', title: '__edition_place__'},
        'f:137': '=__vol_count__',
        'f:290': '=__vol_parts__',
        'f:256': '__select_bf__',
        'f:254': '@__estc_no__',
        'sortby': 'f:__sort_by_field__'
    },

    /**
     * Handles the action when a user selects records from the ESTC search results
     * and confirms the intention to import them.
     * This method overrides the parent `doAction` from `lookupESTC`.
     *
     * 1. Retrieves the IDs of the selected records from the result list. If no records
     *    are selected, it returns.
     * 2. Shows a loading coverall.
     * 3. Calls `this._importRecords` (a method from the parent `lookupESTC` widget)
     *    with the comma-separated list of selected record IDs. `_importRecords` will
     *    use `this.mapping_defs` to manage the import process from the
     *    'ESTC_Helsinki_Bibliographic_Metadata' database to the target LRC18C database.
     *
     * @memberof heurist.lookupLRC18C
     * @instance
     * @override
     * @returns {void}
     */
    doAction: function(){

        let that = this;

        let sels = this.recordList.resultList('getSelected', true); //ids of selected records
        if(!sels || sels.length == 0){
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront( that._as_dialog.parent() );

        this._importRecords(sels.join(','));
    }
});