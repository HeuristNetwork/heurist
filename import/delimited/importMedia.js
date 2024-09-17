/**
* Class to import recUploadedFiles from CSV
* 
* @returns {Object}
* 
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <osmakov@gmail.com>
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

class HImportMedia extends HImportBase{

    constructor(){
        let field_selectors = ['#field_url', '#field_desc'];
        super(0, 'ulf', field_selectors, false);
    }

    redrawPreviewTable(){

        let rtn = super.redrawPreviewTable();

        const [maxcol, headers] = !rtn ? [0, null] : [rtn[0], rtn[1]];

        if(maxcol <= 0){
            return;
        }

        //AUTODETECT COLUMN ROLES by name
        for(let i = 0; i < maxcol; i++){

            const s = headers[i].toLowerCase();

            if(s.indexOf('url') >= 0 || s.indexOf('path') >= 0 || s.indexOf('uri') >= 0){

                $('#field_url').val(i);

            }else if(s.indexOf('desc') >= 0){

                $('#field_desc').val(i);

            }
        }

        this.doPrepare();
    }

    doPrepare(){

        this._prepareddata = [];

        if(!window.hWin.HEURIST4.util.isArrayNotEmpty(this._parseddata)){
            $('#preparedInfo').html('<i>No data. Upload and parse</i>');
            window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), true);
            return;
        }

        let urls = [];
        let field_url = $('#field_url').val();

        let field_desc = $('#field_desc').val();
        let field_desc_sep = ', Download ';
        let field_desc_concat = $('#field_desc_concat').is(':checked');

        let multival_separator = $('#multival_separator').val();

        let msg = '';

        if(field_url < 0){

            msg = `<span style="color:red">URL/Path must be defined</span>`;

            $('#preparedInfo').html(msg);
            window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), true);
            return;
        }

        const has_header = $('#csv_header').is(':checked');
        let found_header = false;
        let count = 0;

        for(const row of this._parseddata){

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
            for(let _url of _urls){

                _url = _url.trim();
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

                this._prepareddata.push(record);
            }// _urls
        }//for

        $('#preparedInfo2').html('');

        if(this._prepareddata.length==0){
            msg = '<span style="color:red">No valid files to import</span>';
        }else{
            $('#preparedInfo2').html(`n = ${this._prepareddata.length}`);
        }

        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (this._prepareddata.length == 0 || $('#field_dtg').val() == 0));

        $('#preparedInfo').html(msg);
    }

    doPost(){

        let request = {
            is_download: $('#field_download').is(':checked') ? 1 : 0
        };

        super.doPost(request, (response) => {

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            window.hWin.HEURIST4.msg.showMsgDlg(response.data);
        });
    }
}