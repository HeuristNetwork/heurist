/**
* Class to import record fields from CSV, also assign directly to record types
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

class HImportDetailTypes extends HImportBase{

    constructor(dtg_ID = 0){
        let field_selectors = ['#field_name', '#field_desc', '#field_type', '#field_vocab', '#field_target', '#field_uri'];
        super(dtg_ID, 'dty', field_selectors, !window.hWin.HEURIST4.util.isempty(dtg_ID) && dtg_ID > 0);
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

            if(s.indexOf('name') >= 0 || s.indexOf('label') >= 0
                || s.indexOf('detailtype')>=0){

                $('#field_name').val(i);

            }else if(s.indexOf('desc') >= 0){

                $('#field_desc').val(i);

            }else if(s.indexOf('uri')>=0 || s.indexOf('url')>=0
                || s.indexOf('reference')>=0 || s.indexOf('semantic')>=0 ){

                $('#field_uri').val(i);

            }else if(s.indexOf('type')>=0){

                $('#field_type').val(i);

            }else if(s.indexOf('vocab')>=0){

                $('#field_vocab').val(i);

            }else if(s.indexOf('target')>=0){

                $('#field_target').val(i);

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

        let field_name = $('#field_name').val();
        let field_desc = $('#field_desc').val();
        let field_type = $('#field_type').val();

        let msg = '';

        if(field_name < 0 || field_desc < 0 || field_type < 0){

            let missing = [];

            field_name < 0 || missing.push('Name'); 

            field_desc < 0 || missing.push('Description'); 

            field_type < 0 || missing.push('Type');

            let last = missing.pop();
            missing = missing.length == 0 ? last : `${missing.join(', ')} and ${last}`;

            msg = `<span style="color:red">${missing} must be defined</span>`;

            $('#preparedInfo').html(msg);
            window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), true);
            return;
        }

        let field_vocab = $('#field_vocab').val();
        let field_target = $('#field_target').val();
        let field_uri = $('#field_uri').val();

        const has_header = $('#csv_header').is(':checked');
        let found_header = false;
        let count = 0;

        for(const row of this._parseddata){

            count ++;

            if(has_header && !found_header){
                found_header = true;
                continue;
            }

            if(field_name >= row.length || field_desc >= row.length || field_type >= row.length){
                continue;
            }

            let record = {};
            
            if(window.hWin.HEURIST4.util.isempty(row[field_name])
            || window.hWin.HEURIST4.util.isempty(row[field_desc])
            || window.hWin.HEURIST4.util.isempty(row[field_type])){

                let missing = window.hWin.HEURIST4.util.isempty(row[field_name]) ? ['name'] : [];
                !window.hWin.HEURIST4.util.isempty(row[field_desc]) || missing.push('description');
                !window.hWin.HEURIST4.util.isempty(row[field_type]) || missing.push('type');

                let last = missing.pop();
                missing = missing.length == 0 ? last : `${missing.join(', ')} and ${last}`;

                msg += `Row #${count} is missing: ${missing}<br>`;

                continue;
            }

            // Records validate in php
            record['dty_Name'] = row[field_name].trim();
            record['dty_HelpText'] = row[field_desc].trim();
            record['dty_Type'] = row[field_type].trim();

            if(field_uri > -1 && field_uri < row.length){
                record['dty_SemanticReferenceURL'] = row[field_uri];
            }
            if(field_vocab > -1 && field_vocab < row.length){
                record['dty_JsonTermIDTree'] = row[field_vocab];
            }
            if(field_target > -1 && field_target < row.length){
                record['dty_PtrTargetRectypeIDs'] = row[field_target];
            }

            this._prepareddata.push(record);
        }//for

        $('#preparedInfo2').html('');

        if(this._prepareddata.length==0){
            msg = '<span style="color:red">No valid detail types to import</span>';
        }else{
            $('#preparedInfo2').html(`n = ${this._prepareddata.length}`);
        }

        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (this._prepareddata.length == 0 || $('#field_dtg').val() == 0));

        $('#preparedInfo').html(msg);
    }
}