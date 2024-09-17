/**
* Class to import file data from CSV
*
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

class HImportFileData extends HImportBase{

    constructor(){
        let field_selectors = ['#file_id', '#file_desc', '#file_cap', '#file_rights', '#file_owner', '#file_vis'];
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

            if(s.indexOf('id') >= 0 || s.indexOf('file') >= 0){

                $('#file_id').val(i);

            }else if(s.indexOf('desc') >= 0){

                $('#file_desc').val(i);

            }else if(s.indexOf('cap') >= 0 || s.indexOf('caption') >= 0){

                $('#file_cap').val(i);

            }else if(s.indexOf('rights') >= 0 || s.indexOf('copyright') >= 0){

                $('#file_rights').val(i);

            }else if(s.indexOf('owner') >= 0 || s.indexOf('copyowner') >= 0){

                $('#file_owner').val(i);

            }else if(s.indexOf('vis') >= 0 || s.indexOf('whocanview') >= 0){

                $('#file_vis').val(i);

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

        let file_id = $('#file_id').val();
        //let file_id_type = $('#file_id_type').val(); always has a value
        let file_desc = $('#file_desc').val();
        let file_cap = $('#file_cap').val();
        let file_rights = $('#file_rights').val();
        let file_owner = $('#file_owner').val();
        let file_vis = $('#file_vis').val();

        let msg = '';

        if(file_id < 0 || (file_desc < 0 && file_cap < 0 && file_rights < 0 && file_owner < 0 && file_vis < 0)){

            msg = file_id < 0 ? 'File ID' : 'A file data field';
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
            if(file_id >= row.length){
                continue;
            }

            let record = {};

            const missing_details = row[file_desc] || row[file_cap] || row[file_rights] || row[file_owner] || row[file_vis];
            if(window.hWin.HEURIST4.util.isempty(row[file_id])
            || window.hWin.HEURIST4.util.isempty(missing_details)){

                let missing = window.hWin.HEURIST4.util.isempty(row[file_id]) ? ['file ID'] : [];
                !missing_details || missing.push('file data');

                let last = missing.pop();
                missing = missing.length == 0 ? last : `${missing.join(', ')} and ${last}`;

                msg += `Row #${count} is missing: ${missing}<br>`;

                continue;
            }

            // Records validate in php
            record['ID'] = row[file_id];

            if(file_desc > -1 && file_desc < row.length){
                record['ulf_Description'] = row[file_desc];
            }
            if(file_cap > -1 && file_cap < row.length){
                record['ulf_Caption'] = row[file_cap];
            }
            if(file_rights > -1 && file_rights < row.length){
                record['ulf_Copyright'] = row[file_rights];
            }
            if(file_owner > -1 && file_owner < row.length){
                record['ulf_Copyowner'] = row[file_owner];
            }
            if(file_vis > -1 && file_vis < row.length){
                record['ulf_WhoCanView'] = row[file_vis];
            }

            this._prepareddata.push(record);
        }//for

        $('#preparedInfo2').html('');

        if(this._prepareddata.length==0){
            msg = '<span style="color:red">No valid file details to import</span>';
        }else{
            $('#preparedInfo2').html(`n = ${this._prepareddata.length}`);
        }

        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (this._prepareddata.length == 0 || $('#field_dtg').val() == 0));

        $('#preparedInfo').html(msg);
    }

    doPost(){

        let request = {
            import_data: $('[name="dtl_handling"]:checked').val(),
            id_type: $('#file_id_type').val()
        };
        super.doPost(request);
    }
}