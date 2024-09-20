/**
* Class to import file data from CSV
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
 * @class HImportFileData
 * @augments HImportBase
 * @classdesc 
 *  For handling the bulk addition or replacement of already registered file details by CSV.
 *  For bulk registeration see HImportMedia
 *
 * @function matchColumns - Perform column matching base on imported column headers
 * @function doPrepare - Prepare data for adding/updating file details
 * @function doPost - Send the prepared data server side to add/update file details
 */

class HImportFileData extends HImportBase{

    constructor(){
        let field_selectors = ['#file_id', '#file_desc', '#file_cap', '#file_rights', '#file_owner', '#file_vis'];
        super(0, 'ulf', field_selectors, false);
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

            if(column.indexOf('id') >= 0 || column.indexOf('file') >= 0){

                $('#file_id').val(idx);

            }else if(column.indexOf('desc') >= 0){

                $('#file_desc').val(idx);

            }else if(column.indexOf('cap') >= 0 || column.indexOf('caption') >= 0){

                $('#file_cap').val(idx);

            }else if(column.indexOf('rights') >= 0 || column.indexOf('copyright') >= 0){

                $('#file_rights').val(idx);

            }else if(column.indexOf('owner') >= 0 || column.indexOf('copyowner') >= 0){

                $('#file_owner').val(idx);

            }else if(column.indexOf('vis') >= 0 || column.indexOf('whocanview') >= 0){

                $('#file_vis').val(idx);

            }
        }
    }

    /**
     * Prepare CSV data for adding/replacing already registered file details
     */
    doPrepare(){

        this.prepared_data = [];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.parsed_data)){
            this.updatePreparedInfo('<i>No data. Upload and parse</i>', 0);
            return;
        }

        const file_id = $('#file_id').val();
        const file_desc = $('#file_desc').val();
        const file_cap = $('#file_cap').val();
        const file_rights = $('#file_rights').val();
        const file_owner = $('#file_owner').val();
        const file_vis = $('#file_vis').val();

        if(file_id < 0 || (file_desc < 0 && file_cap < 0 && file_rights < 0 && file_owner < 0 && file_vis < 0)){

            let missing = file_id < 0 ? 'File ID' : 'A file data field';

            this.updatePreparedInfo(`<span style="color:red">${missing} must be defined</span>`, 0);
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
            if(file_id >= row.length){
                continue;
            }

            const id_empty = window.hWin.HEURIST4.util.isempty(row[file_id]);
            const missing_details = row[file_desc] || row[file_cap] || row[file_rights] || row[file_owner] || row[file_vis];
            if(id_empty || window.hWin.HEURIST4.util.isempty(missing_details)){

                let missing = id_empty ? ['file ID'] : [];
                !missing_details || missing.push('file data');

                msg += `Row #${count} is missing: ${missing.join(' and ')}<br>`;

                continue;
            }

            this.createRecord(row, {
                ID: file_id,
                ulf_Description: file_desc,
                ulf_Caption: file_cap,
                ulf_Copyright: file_rights,
                ulf_Copyowner: file_owner,
                ulf_WhoCanView: file_vis
            });
        }//for

        msg = this.prepared_data.length == 0 ? '<span style="color:red">No valid file details to import</span>' : msg;
        this.updatePreparedInfo(msg, this.prepared_data.length);
    }

    /**
     * Sends prepared data server side to add/replace registered file details
     */
    doPost(){

        let request = {
            import_data: $('[name="dtl_handling"]:checked').val(),
            id_type: $('#file_id_type').val()
        };
        super.doPost(request);
    }
}