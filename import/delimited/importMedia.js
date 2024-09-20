/**
* Class to import recUploadedFiles from CSV
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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
 * @class HImportMedia
 * @augments HImportBase
 * @classdesc For handling the bulk registeration of new external files by CSV
 *
 * @function matchColumns - Perform column matching base on imported column headers
 * @function doPrepare - Prepare data for registering new external media
 * @function doPost - Sends the prepared data server side to register new external media
 */

class HImportMedia extends HImportBase{

    constructor(){
        let field_selectors = ['#field_url', '#field_desc'];
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

            if(column.indexOf('url') >= 0 || column.indexOf('path') >= 0 || column.indexOf('uri') >= 0){

                $('#field_url').val(idx);

            }else if(column.indexOf('desc') >= 0){

                $('#field_desc').val(idx);

            }
        }
    }

    /**
     * Prepare CSV data for registering new external files
     */
    doPrepare(){

        this.prepared_data = [];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this.parsed_data)){
            this.updatePreparedInfo('<i>No data. Upload and parse</i>', 0);
            return;
        }

        let urls = [];

        const field_desc = $('#field_desc').val();
        const field_desc_sep = ', Download ';
        const field_desc_concat = $('#field_desc_concat').is(':checked');

        const multival_separator = $('#multival_separator').val();

        const field_url = $('#field_url').val();
        if(field_url < 0){
            this.updatePreparedInfo(`<span style="color:red">URL/Path must be defined</span>`, );
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
            if(field_url >= row.length){
                continue;
            }

            if(window.hWin.HEURIST4.util.isempty(row[field_url])){

                msg += `Row #${count} is missing: File URL or path<br>`;
                continue;                
            }
            
            let _urls = row[field_url].trim();
            let _descriptions = [];

            _urls = multival_separator ? _urls.split(multival_separator) : [_urls];

            if(field_desc > -1 && field_desc < row.length){

                let desc = row[field_desc];

                //remove leading Donwload
                if(!window.hWin.HEURIST4.util.isempty(desc)){

                    if(desc.indexOf(' Download')==0){
                        desc = desc.substring(9);
                    }

                    desc = desc.trim();
                    _descriptions = desc.split(field_desc_sep);
                }
            }

            let url_count = -1;
            for(let url of _urls){

                const _url = url.trim();
                url_count ++;

                // also verify duplication in parent term and in already added
                if(window.hWin.HEURIST4.util.isempty(_url) || urls.indexOf(_url.toLowerCase()) >= 0){
                    continue;
                }

                let _desc = url_count < _descriptions.length ? _descriptions[url_count] : '';
                if(field_desc_concat){ //add other fields to description

                    for(const idx in row){
                        _desc = idx != field_url && idx != field_desc ? `${row[idx]}, ${_desc}` : _desc;
                    }
                }

                urls.push(_url.toLowerCase());

                let record = {};
                record['ulf_ExternalFileReference'] = _url;
                record['ulf_Description'] = _desc;

                this.prepared_data.push(record);
            }// _urls
        }//for

        msg = this.prepared_data.length == 0 ? '<span style="color:red">No valid files to import</span>' : msg;
        this.updatePreparedInfo(msg, this.prepared_data.length);
    }

    /**
     * Sends prepared data server side to register new external files
     */
    doPost(){

        let request = {
            is_download: $('#field_download').is(':checked') ? 1 : 0
        };

        super.doPost(request, (response) => {

            if(response.status != window.hWin.ResponseStatus.OK){
                this.showError(response);
                return;
            }

            window.hWin.HEURIST4.msg.showMsgDlg(response.data);
        });
    }
}