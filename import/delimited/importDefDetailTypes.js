/**
* Class to import record fields from CSV, also assign directly to record types
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
 * @class HImportDetailTypes
 * @augments HImportBase
 * @classdesc For handling the bulk importing of new base fields by CSV
 *
 * @function matchColumns - Perform column matching base on imported column headers
 * @function doPrepare - Prepare data for creating new external media
 */

class HImportDetailTypes extends HImportBase{

    /**
     * @param {integer} dtg_ID - default detail type group ID, can be changed by the user
     */
    constructor(dtg_ID = 0){
        let field_selectors = ['#field_name', '#field_desc', '#field_type', '#field_vocab', '#field_target', '#field_uri'];
        super(dtg_ID, 'dty', field_selectors, !window.hWin.HEURIST4.util.isempty(dtg_ID) && dtg_ID > 0);
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

            if(column.indexOf('name') >= 0 || column.indexOf('label') >= 0
                || column.indexOf('detailtype')>=0){

                $('#field_name').val(idx);

            }else if(column.indexOf('desc') >= 0){

                $('#field_desc').val(idx);

            }else if(column.indexOf('uri')>=0 || column.indexOf('url')>=0
                || column.indexOf('reference')>=0 || column.indexOf('semantic')>=0 ){

                $('#field_uri').val(idx);

            }else if(column.indexOf('type')>=0){

                $('#field_type').val(idx);

            }else if(column.indexOf('vocab')>=0){

                $('#field_vocab').val(idx);

            }else if(column.indexOf('target')>=0){

                $('#field_target').val(idx);

            }
        }
    }

    /**
     * Prepare CSV data for creating new base fields
     */
    doPrepare(){

        this.prepared_data = [];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.parsed_data)){
            this.updatePreparedInfo('<i>No data. Upload and parse</i>', 0);
            return;
        }

        const field_name = $('#field_name').val();
        const field_desc = $('#field_desc').val();
        const field_type = $('#field_type').val();

        const allow_prepare = this.checkRequiredMapping({
            'Name': [field_name],
            'Description': [field_desc],
            'Type': [field_type]
        });
        if(allow_prepare !== true){
            this.updatePreparedInfo(`<span style="color:red">${allow_prepare} must be defined</span>`, 0);
            return;
        }
        
        const field_vocab = $('#field_vocab').val();
        const field_target = $('#field_target').val();
        const field_uri = $('#field_uri').val();

        let msg = '';
        let found_header = $('#csv_header').is(':checked');
        let count = 0;

        for(const row of this.parsed_data){

            if(!found_header){
                found_header = true;
                continue;
            }

            count ++;

            const is_valid = this.checkRequiredValues(row, {
                'name': [field_name],
                'description': [field_desc],
                'type': [field_type]
            });
            if(is_valid !== true){
                msg += `Row #${count} is missing: ${is_valid}<br>`;
                $('.tbmain').find(`tr:nth-child(${count})`).addClass('data_error');
                continue;
            }

            this.createRecord(row, {
                dty_Name: field_name,
                dty_HelpText: field_desc,
                dty_Type: field_type,
                dty_SemanticReferenceURL: field_uri,
                dty_JsonTermIDTree: field_vocab,
                dty_PtrTargetRectypeIDs: field_target
            });
        }//for

        msg = this.prepared_data.length == 0 ? '<span style="color:red">No valid detail types to import</span>' : msg;
        this.updatePreparedInfo(msg, this.prepared_data.length);

        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (this.prepared_data.length == 0 || $('#field_dtg').val() == 0));
    }
}