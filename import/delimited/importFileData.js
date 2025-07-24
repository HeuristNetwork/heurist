/**
* importFileData.js - Class HImportFileData
* 
* Class to import file data from CSV
* 
* @project     Heurist academic knowledge management system
*
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Brandon McKay   <blmckay13@gmail.com>
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       6.0
*/

/**
 * @class HImportFileData
 * @augments HImportBase
 * @classdesc
 *  For handling the bulk addition or replacement of already registered file details by CSV.
 *  For bulk registeration see HImportMedia
 *
 * @method doPrepare - Prepare data for adding/updating file details
 * @method doPost - Send the prepared data server side to add/update file details
 */
class HImportFileData extends HImportBase{

    /**
     * Sets up the import UI for file data, extending HImportBase.
     * @return {void}
     */
    constructor(){
        let field_selectors = ['#file_id', '#file_desc', '#file_cap', '#file_rights', '#file_owner', '#file_vis'];
        super(0, 'ulf', field_selectors, false);
    }

    /**
     * Prepare CSV data for adding/replacing already registered file details.
     * @return {void}
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

        const allow_prepare = this.checkRequiredMapping({
            'File ID': [file_id],
            'A file data field': [file_desc, file_cap, file_rights, file_owner, file_vis]
        });
        if(allow_prepare !== true){
            this.updatePreparedInfo(`<span style="color:red">${allow_prepare} must be defined</span>`, 0);
            return;
        }
        
        let msg = '';
        let found_header = !$('#csv_header').is(':checked');
        let count = 0;

        for(const row of this.parsed_data){

            if(!found_header){
                found_header = true;
                continue;
            }

            count ++;

            const is_valid = this.checkRequiredValues(row, {
                'file ID': [file_id],
                'file data': [file_desc, file_cap, file_rights, file_owner, file_vis]
            });
            if(is_valid !== true){
                msg += `Row #${count} is missing: ${is_valid}<br>`;
                $('.tbmain').find(`tr:nth-child(${count})`).addClass('data_error');
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
     * Sends prepared data server side to add/replace registered file details.
     * @return {void}
     */
    doPost(){

        let request = {
            import_data: $('[name="dtl_handling"]:checked').val(),
            id_type: $('#file_id_type').val()
        };
        super.doPost(request);
    }
}