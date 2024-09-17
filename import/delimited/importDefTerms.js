/**
* Class to import terms from CSV
* 
* @param _trm_ParentTermID - id of parent term
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

class HImportTerms extends HImportBase{

    _vcg_ID = 0;
    _trm_ParentTermID = 0;
    _trm_Domain = 'enum';

    _is_Translations = false;

    constructor(trm_ParentTermID, vcg_ID, is_Translations){

        let field_selectors = ['#field_term', '#field_code', '#field_desc', '#field_uri', '#field_ref_term', '#field_ref_id', '#field_trn_term', '#field_trn_desc'];
        super(0, 'trm', field_selectors, false);

        let vcg_Details = vcg_ID > 0 ? $Db.vcg(vcg_ID) : null;
        let parent_Details = trm_ParentTermID > 0 ? $Db.trm(trm_ParentTermID) : null;

        this._vcg_ID = vcg_ID;
        this._trm_ParentTermID = trm_ParentTermID;
        this._is_Translations = is_Translations;

        if(!vcg_Details && !parent_Details){

            let msg = 'Neither vocabulary group nor vocabulary defined';
            msg = vcg_ID > 0 ? `Vocabulary group #${vcg_ID} not found` : msg;
            msg = trm_ParentTermID > 0 ? `Vocabulary #${vcg_ID} not found` : msg;

            $('body').empty();
            $('body').html(`<h2>${msg}</h2>`);

            return;
        }
        if(vcg_ID > 0){
            this._trm_Domain = vcg_Details['vcg_Domain'];
        }else if(trm_ParentTermID > 0){
            const trm_VocabularyID = $Db.getTermVocab(trm_ParentTermID);
            this._trm_Domain = $Db.trm(trm_VocabularyID, 'trm_Domain');
        }

        // Override click event for import button, need to check the separator setting
        $('#btnImportData').off('click');
        $('#btnImportData').on('click', () => {

            let trm_sep = $('#term_separator').val();
            if(!window.hWin.HEURIST4.util.isempty(trm_sep)){

                let $dlg = null;
                let btns = {};
                btns['Proceed'] = () => { $dlg.dialog('close'); this.doPost(); };
                btns['Clear character'] = () => { $dlg.dialog('close'); $('#term_separator').val(''); this.doPost(); };

                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                    `If this character [${trm_sep}] appears in your terms they will be split into<br>two or more separate terms nested as hierarchy.<br><br>`
                    + 'This can generate a complete mess if used unintentionally.', 
                    btns, 
                    {title: 'Terms with sub-terms', yes: 'Proceed', no: 'Clear character'}, {default_palette_class: 'ui-heurist-design'}
                );
            }else{
                this.doPost();
            }
        });

        if(is_Translations){ // Set display for translation importing
            $('.trm_translation').show();
            $('.trm_import').hide();
        }
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

            if(s.indexOf('term') >= 0 || s.indexOf('label') >= 0){

                $('#field_term').val(i);

            }else if(s.indexOf('code') >= 0){

                $('#field_code').val(i);

            }else if(s.indexOf('desc') >= 0){

                $('#field_desc').val(i);

            }else if(s.indexOf('uri')>=0 || s.indexOf('url')>=0
                || s.indexOf('reference')>=0 || s.indexOf('semantic')>=0){

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

        if(this._is_Translations){
            this.doPrepareTranslation();
            return;
        }

        let msg = '';

        let field_term = $('#field_term').val();

        if(field_term < 0){

            msg = `<span style="color:red">Term (Label) must be defined</span>`;

            $('#preparedInfo').html(msg);
            window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), true);
            return;
        }

        let field_code = $('#field_code').val();
        let field_desc = $('#field_desc').val();
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

            if(field_term >= row.length){
                continue;
            }

            let record = {};

            if(window.hWin.HEURIST4.util.isempty(row[field_term])){

                msg += `Row #${count} is missing: Term label<br>`;
                continue;
            }

            record['trm_Label'] = row[field_term].trim();
            record['trm_Domain'] = this._trm_Domain;

            if(this._trm_ParentTermID > 0){ record['trm_ParentTermID'] = this._trm_ParentTermID; }
            if(this._vcg_ID > 0){ record['trm_VocabularyGroupID'] = this._vcg_ID; }

            if(field_desc > -1 && field_desc < row.length){
                record['trm_Description'] = row[field_desc];
            }
            if(field_code > -1 && field_code < row.length){
                record['trm_Code'] = row[field_code];
            }
            if(field_uri > -1 && field_uri < row.length){
                record['trm_SemanticReferenceURL'] = row[field_uri];
            }

            this._prepareddata.push(record);
        }//for

        $('#preparedInfo2').html('');

        if(this._prepareddata.length==0){
            msg = `<span style="color:red">No valid ${this._vcg_ID > 0 ? 'vocabulary' : 'terms'} to import</span>`;   
        }else{
            $('#preparedInfo2').html(`n = ${this._prepareddata.length}`);
        }

        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (this._prepareddata.length == 0  || $('#field_rtg').val() == 0));

        $('#preparedInfo').html(msg);
    }

    doPrepareTranslation(){

        let msg = '';

        let field_ref_term = $('#field_ref_term').val();

        if(field_ref_term < 0){

            msg = `<span style="color:red">Reference Term (Label) must be defined</span>`;

            $('#preparedInfo').html(msg);
            window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), true);
            return;
        }

        let field_trn_term = $('#field_trn_term').val();
        let field_trn_desc = $('#field_trn_desc').val();

        const has_header = $('#csv_header').is(':checked');
        let found_header = false;
        let count = 0;

        for(const row of this._parseddata){

            count ++;

            if(has_header && !found_header){
                found_header = true;
                continue;
            }

            if(field_ref_term >= row.length){
                continue;
            }

            let record = {};

            if(window.hWin.HEURIST4.util.isempty(row[field_ref_term])){

                msg += `Row #${count} is missing: Term label<br>`;
                continue;
            }

            record['ref_id'] = row[field_ref_term].trim();

            if(field_trn_term > -1 && field_trn_term < row.length){
                record['trm_Label'] = row[field_trn_term];
            }
            if(field_trn_desc > -1 && field_trn_desc < row.length){
                record['trm_Description'] = row[field_trn_desc];
            }

            this._prepareddata.push(record);
        }//for

        $('#preparedInfo2').html('');

        if(this._prepareddata.length==0){
            msg = `<span style="color:red">No valid translations to import</span>`;   
        }else{
            $('#preparedInfo2').html(`n = ${this._prepareddata.length}`);
        }

        window.hWin.HEURIST4.util.setDisabled($('#btnImportData'), (this._prepareddata.length == 0  || $('#field_rtg').val() == 0));

        $('#preparedInfo').html(msg);
    }

    /**
     * Imports new terms/translations
     */
    doPost(){

        if(this._prepareddata.length < 1){
            return;
        }

        window.hWin.HEURIST4.msg.bringCoverallToFront($('body'));

        let request = {
            a: 'batch',
            entity: 'defTerms',
            request_id: window.hWin.HEURIST4.util.random()
        };

        if(this._is_Translations){
            request['set_translations'] = JSON.stringify(this._prepareddata);
            request['vcb_ID'] = this._trm_ParentTermID;
        }else{
            request['fields'] = JSON.stringify(this._prepareddata);
            request['term_separator'] = $('#term_separator').val();
        }

        window.hWin.HAPI4.EntityMgr.doRequest(request, (response) => {

            if(response.status != window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.sendCoverallToBack();
                window.hWin.HEURIST4.msg.showMsgErr(response);
                return;
            }

            if(this._is_Translations){

                window.hWin.HEURIST4.msg.sendCoverallToBack();
                window.close( {result: response.data} );
            }else{

                // need to refresh local defintions
                let trm_IDs = response.data;
                window.hWin.HAPI4.EntityMgr.refreshEntityData('trm', () => {
                    window.hWin.HEURIST4.msg.sendCoverallToBack();
                    window.close( {result: trm_IDs} );
                });
            }
        });
    }
}