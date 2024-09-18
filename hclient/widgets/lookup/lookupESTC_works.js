/**
 * lookupESTC_works.js
 *
 *  1) Loads html content from lookupESTC_works.html
 *  2) User defines search parameters and searches Works (rt:49) in ESTC database
 *  3) Found records are show in list. User selects a record (var sels)
 *  4) doAction calls search for details about selected work's enum fields Retrieves data from ESTC_Helsinki_Bibliographic_Metadata
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

$.widget("heurist.lookupESTC_works", $.heurist.lookupESTC, {

    //    
    //
    //
    _initControls: function () {

        this.element.find('#btnStartSearch').parent().parent().position({
            my: 'left center',
            at: 'right center',
            of: '#ent_header > fieldset'
        });

        return this._super();
    },

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

        for(const fld_Name of fields){

            let dty_ID = this.options.mapping.fields[fld_Name];
            if(dty_ID < 1){
                continue;
            }

            // defintions mapping can be found in the original version => lookupLRC18C.js
            switch(fld_Name){
                case 'title':
                    dlg_response[dty_ID] = details[1] ? details[1] : '';
                    break;
                case 'extendedTitle':
                    dlg_response[dty_ID] = details[276] ? details[276] : '';
                    break;
                case 'projectId':
                    dlg_response[dty_ID] = details[271] ? details[271] : '';
                    break;
                case 'helsinkiTitle':
                    dlg_response[dty_ID] = details[273] ? details[273] : '';
                    break;
                case 'helsinkiId':
                    dlg_response[dty_ID] = details[272] ? details[272] : '';
                    break;
                case 'helsinkiIdAssignation':
                    dlg_response[dty_ID] = details[298] ? details[298] : '';
                    break;
                case 'helsinkiRawData':
                    dlg_response[dty_ID] = details[236] ? details[236] : '';
                    break;
                default:
                    break;
            }
        }

        let term_id = '';

        if(Object.hasOwn(details, 298)){
            term_id = details[298][0];
        }

        if(window.hWin.HEURIST4.util.isempty(term_id)){
            that.closingAction(dlg_response);
            return;
        }
        
        this._importTerms(dlg_response, term_id);
    },

    // Get the user input from lookupESTC_works.html and build the query string
    // Then lookup ESTC database if the query produces any search results
    _doSearch: function () {

        let query = {t: "49"}; //search for Works

        if(this.element.find('#work_name').val() != ''){ // work_name
            query['f:1'] = this.element.find('#work_name').val() ;
        }
        if(this.element.find('#project_id').val() != ''){ // project_id
            query['f:271'] = this.element.find('#project_id').val();
        }
        if(this.element.find('#helsinki_name').val() != ''){ // helsinki_name
            query['f:273'] = this.element.find('#helsinki_name').val();
        }
        if(this.element.find('#helsinki_id').val() != ''){ // helsinki_id
            query['f:272'] = this.element.find('#helsinki_id').val();
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