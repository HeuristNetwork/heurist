/**
* Class to import record types from CSV
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Brandon McKay   <blmckay13@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     6.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

/**
 * @class HImportRecordTypes
 * @augments HImportBase
 * @classdesc For handling the bulk importing of new record types by CSV
 *
 * @function matchColumns - Perform column matching base on imported column headers
 * @function doPrepare - Prepare data for creating new record types
 */

class HImportRecordTypes extends HImportBase{

    /**
     * @param {integer} rtg_ID - default record type group ID, can be changed by the user
     */
    constructor(rtg_ID = 0){
        let field_selectors = ['#field_name', '#field_desc', '#field_uri'];
        super(rtg_ID, 'rty', field_selectors, !window.hWin.HEURIST4.util.isempty(rtg_ID) && rtg_ID > 0);
    }

    /**
     * Attempt to automatically match column headers to mappable fields
     *
     * @param {array} headers - array of column headers, to use for matching
     */
    matchColumns(headers = []){

        if(headers.length == 0){
            return;
        }

        for(const idx in headers){

            const column = headers[idx].toLowerCase();

            if(column.indexOf('name') >= 0 || column.indexOf('rectype') >= 0){

                $('#field_name').val(idx);

            }else if(column.indexOf('desc') >= 0){

                $('#field_desc').val(idx);

            }else if(column.indexOf('uri') >= 0 || column.indexOf('url') >= 0
                || column.indexOf('reference') >= 0 || column.indexOf('semantic') >= 0){

                $('#field_uri').val(idx);

            }
        }
    }

    /**
     * Prepare CSV data for creating new record types
     */
    doPrepare(){

        this.prepared_data = [];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.parsed_data)){
            this.updatePreparedInfo('<i>No data. Upload and parse</i>', 0);
            return;
        }

        const field_name = $('#field_name').val();
        const field_desc = $('#field_desc').val();
        const field_uri = $('#field_uri').val();
        if(field_name < 0 || field_desc < 0){

            let missing = field_name < 0 ? ['Name'] : [];
            field_desc >= 0 || missing.push('Description');

            this.updatePreparedInfo(`<span style="color:red">${missing.join(' and ')} must be defined</span>`, 0);
            return;
        }

        let msg = '';
        const has_header = $('#csv_header').is(':checked');
        let found_header = false;
        let count = 0;

        for(const row of this.parsed_data){

            count ++;

            if(has_header && !found_header){
                found_header = true;
                continue;
            }

            if(field_name >= row.length || field_desc >= row.length){
                continue;
            }

            const name_empty = window.hWin.HEURIST4.util.isempty(row[field_name]);
            const desc_empty = window.hWin.HEURIST4.util.isempty(row[field_desc]);
            if(name_empty || desc_empty){

                let missing = name_empty ? ['name'] : [];
                !desc_empty || missing.push('description');

                msg += `Row #${count} is missing: ${missing.join(' and ')}<br>`;

                continue;
            }

            this.createRecord(row, {
                rty_Name: field_name,
                rty_Description: field_desc,
                rty_SemanticReferenceURL: field_uri
            });
        }//for

        msg = this.prepared_data.length == 0 ? '<span style="color:red">No valid record types to import</span>' : msg;
        this.updatePreparedInfo(msg, this.prepared_data.length);

        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (this.prepared_data.length == 0  || $('#field_rtg').val() == 0));
    }
}