/**
* Class to import record types from CSV
*
* @returns {Object}
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

class HImportRecordTypes extends HImportBase{

    constructor(rtg_ID = 0){
        let field_selectors = ['#field_name', '#field_desc', '#field_uri'];
        super(rtg_ID, 'rty', field_selectors, !window.hWin.HEURIST4.util.isempty(rtg_ID) && rtg_ID > 0);
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

            if(s.indexOf('name') >= 0 || s.indexOf('rectype') >= 0){

                $('#field_name').val(i);

            }else if(s.indexOf('desc') >= 0){

                $('#field_desc').val(i);

            }else if(s.indexOf('uri') >= 0 || s.indexOf('url') >= 0
                || s.indexOf('reference') >= 0 || s.indexOf('semantic') >= 0){

                $('#field_uri').val(i);

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

        let msg = '';

        let field_name = $('#field_name').val();
        let field_desc = $('#field_desc').val();
        let field_uri = $('#field_uri').val();

        if(field_name < 0 || field_desc < 0){

            if(field_name < 0){ 
                msg = 'Name'; 
            }
            if(field_desc < 0){ 
                msg = msg == '' ? 'Description' : `${msg} and Description`; 
            }
            msg = `<span style="color:red">${msg} must be defined</span>`;

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

            if(field_name >= row.length || field_desc >= row.length){
                continue;
            }

            let record = {};

            if(window.hWin.HEURIST4.util.isempty(row[field_name])
            || window.hWin.HEURIST4.util.isempty(row[field_desc])){

                let missing = window.hWin.HEURIST4.util.isempty(row[field_name]) ? 'name' : '';
                if(window.hWin.HEURIST4.util.isempty(row[field_desc])){
                    missing = missing == '' ? 'description' : `${missing} and description`;
                }

                msg += `Row #${count} is missing: ${missing}<br>`;

                continue;
            }

            record['rty_Name'] = row[field_name].trim();
            record['rty_Description'] = row[field_desc].trim();

            if(field_uri > -1 && field_uri < row.length){
                record['rty_SemanticReferenceURL'] = row[field_uri];
            }

            this._prepareddata.push(record);
        }//for

        $('#preparedInfo2').html('');

        if(this._prepareddata.length==0){
            msg = '<span style="color:red">No valid record types to import</span>';   
        }else{
            $('#preparedInfo2').html(`n = ${this._prepareddata.length}`);
        }

        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (this._prepareddata.length == 0  || $('#field_rtg').val() == 0));

        $('#preparedInfo').html(msg);
    }
}