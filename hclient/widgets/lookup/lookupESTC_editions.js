/**
 * lookupESTC_editions.js
 *
 *  1) Loads html content from lookupLRC18C.html
 *  2) User defines search parameters and searches Book(edition) (rt:30) in ESTC database
 *  3) Found records are show in list. User selects a record (var sels)
 *  4) doAction calls search for details about selected book ids and linked Agent(rt:10), Place (12) and Work (49)
 *      Retrieves data from ESTC_Helsinki_Bibliographic_Metadata
 *
 * @package     Heurist academic knowledge management system
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney
 * @author      Artem Osmakov   <osmakov@gmail.com>
 * @author      Darshan Nagavara   <darshan@intersect.org.au>
 * @author      Brandon McKay   <blmckay13@gmail.com>
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @version     4.0
 */

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

$.widget("heurist.lookupESTC_editions", $.heurist.lookupESTC, {

    // getActionButtons

    // Show a confirmation window after user selects a record from the lookup query results
    // If the user clicks "Check Author", then call method _checkAuthor
    doAction: function(){

        let [recset, record] = this._getSelection(true);
        if(recset?.length() < 0 || !record){
            return;
        }

        let dlg_response = {};
        let fields = Object.keys(this.options.mapping.fields); // mapped fields names, to access fields of rec

        let details = record.d;

        if(!details){
            this._getRecordDetails(recset, record);
            return;
        }

        let recpointers = [];
        let term_id = '';

        for(const fld_Name of fields){

            let dty_ID = this.options.mapping.fields[fld_Name];
            if(dty_ID < 1){
                continue;
            }

            // defintions mapping can be found in the original version => lookupLRC18C.js
            switch(fld_Name){
                case 'originalID':
                    dlg_response[dty_ID] = sels.fld(record, 'rec_ID');
                    break;
                case 'title':
                    dlg_response[dty_ID] = details[1] ? details[1] : '';
                    break;
                case 'estcID':
                    dlg_response[dty_ID] = details[254] ? details[254] : '';
                    break;
                case 'yearFirstVolume':
                    dlg_response[dty_ID] = details[9] ? details[9] : '';
                    break;
                case 'yearLastVolume':
                    dlg_response[dty_ID] = details[275] ? details[275] : '';
                    break;
                case 'summary':
                    dlg_response[dty_ID] = details[285] ? details[285] : '';
                    break;
                case 'extendedTitle':
                    dlg_response[dty_ID] = details[277] ? details[277] : '';
                    break;
                case 'numOfVolumes':
                    dlg_response[dty_ID] = details[137] ? details[137] : '';
                    break;
                case 'numOfParts':
                    dlg_response[dty_ID] = details[290] ? details[290] : '';
                    break;
                case 'imprintDetails':
                    dlg_response[dty_ID] = details[270] ? details[270] : '';
                    break;
                case 'place':
                    dlg_response[dty_ID] = details[259] ? details[259] : '';
                    break;
                case 'author':
                    dlg_response[dty_ID] = details[15] ? details[15] : '';
                    break;
                case 'work':
                    dlg_response[dty_ID] = details[284] ? details[284] : '';
                    break;
                case 'bookFormat':
                    dlg_response[dty_ID] = details[256] ? details[256] : '';
                    break;
                default:
                    break;
            }
        }
        
        if(Object.hasOwn(details, 259)){ // Place - Rec Pointer
            recpointers.push(details[259]);
        }
        if(Object.hasOwn(details, 15)){ // Author - Rec Pointer
            recpointers.push(details[15]);
        }
        if(Object.hasOwn(details, 284)){ // Works - Rec Pointer
            recpointers.push(details[284]);
        }

        if(Object.hasOwn(details, 256)){ // Book format - Term
            term_id = details[256][0];
        }

        this._importRecPointers(dlg_response, recpointers.join(','), term_id);
    },

    /* Get the user input from lookupLRC18C.html and build the query string */
    /* Then lookup ESTC database if the query produces any search results */
    _doSearch: function () {

        let query = {t: "30"}; //search for Books

        if(this.element.find('#edition_name').val() != ''){
            query['f:1'] = `@${this.element.find('#edition_name').val()}`;
        }
        if(this.element.find('#edition_date').val() != ''){
            query['f:9'] = this.element.find('#edition_date').val();
        }
        if(this.element.find('#edition_author').val() != ''){
            //Standardised agent name  - 250
            query['linkedto:15'] = {"t":"10", "f:250":this.element.find('#edition_author').val()};
        }
        if(this.element.find('#edition_work').val() != ''){
            query['linkedto:284'] = {"t":"49","f:272":this.element.find('#edition_work').val()};
            //Helsinki work ID - 272
            //Project Record ID - 271
        }
        if(this.element.find('#edition_place').val() != ''){
            query['linkedto:259'] = {"t":"12", "title":this.element.find('#edition_place').val()};
        }
        if(this.element.find('#vol_count').val() != ''){
            query['f:137'] = `=${this.element.find('#vol_count').val()}`;

        }
        if(this.element.find('#vol_parts').val() != ''){
            query['f:290'] = `=${this.element.find('#vol_parts').val()}`;
        }
        if(this.element.find('#select_bf').val()>0){
            query['f:256'] = this.element.find('#select_bf').val();
        }
        if(this.element.find('#estc_no').val() != ''){
            query['f:254'] = `@${this.element.find('#estc_no').val()}`;
        }

        if(this.element.find('#sort_by_field').val() > 0){ // Sort by field
            let sort_by_key = "'sortby'"
            query[sort_by_key.slice(1, -1)] = `f:${this.element.find('#sort_by_field').val()}`;
        }

        let missingSearch = (Object.keys(query).length <= 2); // query has t and sortby keys at minimum

        if(missingSearch){
            window.hWin.HEURIST4.msg.showMsgFlash('Please specify some criteria to narrow down the search...', 1000);
            return;
        }
        
        this._super(query);
    }
});