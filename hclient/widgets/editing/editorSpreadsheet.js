/**
 * @file editorSpreadsheet.js
 * @brief Provides a wrapper class `EditorSpreadsheet` to integrate the jSpreadsheet editor.
 *
 * @fileOverview Provides a wrapper class `EditorSpreadsheet` to integrate the jSpreadsheet editor
 *               with a textarea element for spreadsheet editing capabilities.
 *               It supports lazy loading of jSpreadsheet assets and various configuration options.
 *
 * @project     Heurist academic knowledge management system
 *
 * @link        https://HeuristNetwork.org
 * @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
 * @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
 * @author      Brandon McKay   <blmckay13@gmail.com>
 * @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
 * @since       7.0
 */

class EditorSpreadsheet{

    static #dataEvents = new Set([
        'onafterchanges', 'onchangestyle',
        'oninsertrow', 'ondeleterow', 'onmoverow',
        'oninsertcolumn', 'ondeletecolumn', 'onmovecolumn',
        'onsort', 'onundo', 'onredo',
        'onresizecolumn', 'onresizerow'
    ]);

    #DEBUG = false;

    #inputDiv;
    #input;
    #inputDisplay = '';

    #editor;
    #editorContainer;
    #importInput;

    #jspreadsheet;
    #defaultConfig = {
        //tabs: false, // worksheets not handled, for now
        allowExport: true,
        //allowMoveWorksheet: false,
        //allowRenameWorksheet: false,
        //allowDeleteWorksheet: false,
        worksheets: []
    };
    #defaultSheetConfig = {
        data: [],
        columns: [],
        minDimensions: [4, 3],
        tableOverview: true,
        tableHeight: '25em',
        tableWidth: '50em',
        worksheetName: 'Sheet'
    };
    #configuration = {};

    #data = {};
    #initialInput;
    #initialTableValue;

    #handledTypes = ['csv', 'json', 'table'];
    #dataType;
    #exportOptions = {
        type: '',
        headers: true,
        raw: true,
        styling: false
    };

    #loadingScripts = false;

    constructor(_inputElement, config = {}){

        if(_inputElement instanceof jQuery){
            _inputElement = _inputElement[0];            
        }

        if(!_inputElement || !(_inputElement instanceof HTMLElement)){
            throw new Error('No input element has been provided');
        }

        if(_inputElement.classList.contains('input-div')){
            this.#inputDiv = _inputElement;
            this.#input = _inputElement.querySelector('textarea');
        }else if(_inputElement.tagName === 'TEXTAREA'){
            this.#input = _inputElement;
            this.#inputDiv = _inputElement.closest('.input-div');
        }

        if(!(this.#input instanceof HTMLTextAreaElement) || !(this.#inputDiv instanceof HTMLElement)){
            throw new Error('Unable to determine the source textarea and the input container');
        }

        this.#configuration = typeof config === 'object' && config instanceof Object ? config : this.#defaultConfig;
    }

    #parseData(){

        let data = this.#input.value.trim();

        if(data === ''){
            this.#dataType = 'csv';
            this.#exportOptions.type = 'table';
            return '';
        }

        if(/^<table/.test(data)){
            this.#dataType = 'table';
            return this.#parseTable(data);
        }

        try{

            const json = JSON.parse(data);

            if(!Array.isArray(json)){
                throw new Error('JSON must contain an array.');
            }

            this.#dataType = 'json';

            return this.#parseJSON(json);

        }catch(error){

            this.#logError(error);
            if(/^[\[{]/.test(data)){
                throw new Error(`Invalid JSON: ${error.message}`);
            }
        }

        try{
            this.#dataType = 'csv';
            return this.#parseCSV(data);
        }catch(error){
            this.#logError(error);
            throw new Error('Unknown data provided.');
        }
    }

    #parseJSON(json){

        if(Array.isArray(json) && json.length === 0 || Object.keys(json).length === 0){
            throw new Error('JSON data cannot be empty.');
        }

        // Array of objects
        if(json[0] && typeof json[0] === 'object' && !Array.isArray(json[0])){

            const columns = Object.keys(json[0]);

            if(columns.length === 0){
                throw new Error('JSON object is missing keys.');
            }

            const data = json.map(row => columns.map(column => row[column] ?? ''));

            return [{
                data: data,
                columns: columns.map(column => ({ title: column, type: 'text', name: column }))
            }];
        }

        // Array of arrays
        if(json.every(Array.isArray)){
            return [{
                data: json,
                columns: json[0].map((_, index) => ({ title: `Column ${index + 1}`, type: 'text', name: `column${index}` }))
            }];
        }

        throw new Error('No data.');
    }

    #parseCSV(data){

        const delimiter = this.#detectDelimiter(data);

        const rows = [];

        let row = [];
        let field = '';
        let isQuoted = false;

        for(let i = 0; i < data.length; i ++){

            const prev = i === 0 ? '' : data[i - 1];
            const char = data[i];
            const next = data[i + 1];

            // Quote mark
            if(char === '"'){

                if(isQuoted && prev === '\\'){
                    field += '"';
                    i ++;
                }else{
                    isQuoted = !isQuoted;
                }

                continue;
            }

            // Special characters outside of quotes
            if(char === delimiter && !isQuoted){ // Delimiter outside of quotes

                row.push(field);
                field = '';

                continue;
            }else if((char === "\n" || char === "\r") && !isQuoted){ // Line Feed/Newline outside of quotes

                if(char === "\n" && next === "\r"){
                    i ++;
                }

                row.push(field);
                rows.push(row);

                field = '';
                row = [];

                continue;
            }

            field += char;
        }

        if(isQuoted){
            throw new Error('CSV contains unclosed quoted field.');
        }

        row.push(field);
        rows.push(row);

        // Cleanup empty rows
        while(rows.length && rows[rows.length - 1].every(cell => cell === '')){
            rows.pop();
        }

        if(rows.length === 0){
            return [{data: [], columns: []}];
        }

        //const columns = this.#settings.firstRowHeader ? rows.shift() : ;

        return [{
            data: rows,
            columns: rows[0].map((_, index) => ({title: `Column ${index + 1}`, type: 'text'}))
        }];
    }

    #parseTable(data){

        data = data.trim();

        if(!/^<table/.test(data)){
            return;
        }

        this.#createEditorContainer();

        /*
        const container = document.createElement('div');
        container.style.display = 'none';
        container.innerHTML = data;
        this.#editorContainer.append(container);
        */
        this.#editorContainer.innerHTML = data;

        const config = this.#getConfiguration();
        config['minDimensions'] = [4, 3];
        config['tableOverview'] = true;
        config['tableHeight'] = '25em';
        config['tableWidth'] = '50em';
        config['worksheetName'] = 'Sheet';

        this.#jspreadsheet = jspreadsheet(this.#editorContainer.querySelector('table'), config);
        this.#editor = this.#inputDiv.querySelector('.jss_spreadsheet');
        this.#initialInput = this.#input.value;
        this.#initialTableValue = this.#serialiseData() ?? '';
    }

    #detectDelimiter(data){

        const delimiters = [',', "\t", '|', ';'];

        const firstLine = data.split(/\r?\n/)[0];

        const delimiterResults = delimiters.map((delimiter) => ({
            delimiter: delimiter, count: firstLine.split(delimiter).length
        }));

        return delimiterResults.sort((a, b) => b.count - a.count)[0].delimiter;
    }

    show(){

        if(typeof jspreadsheet !== 'function'){
            this.#loadScripts();
            return;
        }else if(this.#jspreadsheet){
            return this.#jspreadsheet;
        }

        this.#inputDisplay = this.#input.style.display;
        this.#input.style.display = 'none';

        try{
            this.#data = this.#parseData();
        }catch(error){
            console.error(error);
            this.#switchToText();
            return;
        }

        this.#exportOptions.type = this.#dataType;

        if(this.#jspreadsheet){
            return this.#jspreadsheet;
        }

        const config = this.#getConfiguration();
        const worksheetConfig = structuredClone(this.#defaultSheetConfig);

        worksheetConfig['data'] = this.#data[0].data;
        worksheetConfig['columns'] = this.#data[0].columns;

        config.worksheets.push(worksheetConfig);

        this.#createEditorContainer();

        // Init editor
        this.#jspreadsheet = new jspreadsheet(this.#editorContainer, config);
        this.#editor = this.#inputDiv.querySelector('.jss_spreadsheet');
        this.#initialInput = this.#input.value;
        this.#initialTableValue = this.#serialiseData() ?? '';

        return this.#jspreadsheet;
    }

    #getConfiguration(){

        const config = structuredClone(this.#defaultConfig);

        config.onbeforecreateworksheet = (config, index) => ({ worksheetName: `Sheet ${index + 1}` });

        config.onevent = (event, a, b, c, d) => {

            if(event === 'onchangestyle'){
                this.#exportOptions.styling = true;
            }

            if(this.#jspreadsheet && EditorSpreadsheet.#dataEvents.has(event)){
                this.#updateInput();
            }
        };

        config.toolbar = (toolbar) => {

            // Replace save with save_alt/export
            const saveIndex = toolbar.items.findIndex(item => item.content === 'save');
            const divisor = {
                type: 'divisor'
            };

            if(saveIndex >= 0){

                toolbar.items[saveIndex].content = 'save_alt';
                toolbar.items[saveIndex].tooltip = 'Export table as CSV';

                if(typeof tabularjs === 'function'){

                    toolbar.items.splice(saveIndex, 0, {
                        type: 'icon',
                        content: 'upload_file', // file_upload
                        tooltip: 'Upload file',
                        class: 'js-upload-file material-icons',
                        onclick: () => {
                            if(this.#importInput){
                                this.#importInput.click();
                            }
                        }
                    });

                    toolbar.items.splice(saveIndex, 0, divisor);
                }
            }

            toolbar.items.unshift(divisor);

            toolbar.items.unshift({
                type: 'icon',
                content: 'settings',
                tooltip: 'Edit settings for this spreadsheet',
                class: 'js-edit-settings material-icons',
                onclick: () => this.#editExportSettings()
            });

            return toolbar;
        };

        return config;
    }

    #createEditorContainer(){

        // Editor container
        this.#editorContainer = document.createElement('div');
        this.#editorContainer.classList.add('heurist-spreadsheet-editor');
        this.#editorContainer.style.display = 'inline-block';
        this.#inputDiv.prepend(this.#editorContainer);

        if(typeof tabularjs === 'function'){

            this.#importInput = document.createElement('input');
            this.#importInput.classList.add('heurist-jss-file-upload');
            this.#importInput.type = 'file';
            this.#importInput.accept = '.xlsx,.xls,.ods,.xml,.html,.htm,.csv,.tsv,.tab,.txt,.wks,.wk1,.wk3,.wk4,.123,.dif,.slk,.sylk,.dbf';

            this.#importInput.addEventListener('change', async (event) => {

                const file = event.target.files[0];
                if(!file) { return; }

                const result = await tabularjs(file);

                let idx = 0;
                for(const worksheet of result.worksheets){

                    const sheetData = worksheet.data;
                    this.#jspreadsheet[idx].setData(sheetData);

                    idx ++;

                    break; // @todo - remove once worksheet handling has been finalised
                }
            });
        }
    }

    hide(){

        this.destroy();

        this.#input.style.display = this.#inputDisplay;
    }

    #updateInput(){

        const tableValue = this.#serialiseData();
        // Keep the original field text when the grid is unchanged or edits are undone.
        const value = tableValue === this.#initialTableValue ? this.#initialInput : tableValue;
        if(value === this.#input.value || !tableValue){
            return;
        }

        this.#input.value = value;
        this.#input.dispatchEvent(new Event('change', {bubbles: false}));
    }

    #serialiseData(){

        let type = this.#handledTypes.includes(this.#exportOptions.type) ? this.#exportOptions.type : '';
        type = type === '' && this.#dataType !== '' ? this.#dataType : type;
        switch(type){
            case 'json':
                return this.#toJSON();
            case 'csv':
                return this.#toCSV();
            case 'table':
                return this.#toTable();
            default:
                window.hWin.HEURIST4.msg.showMsgFlash('Please select an export format...', 3000);
                this.#editExportSettings();
                return null;
        }
    }

    #editExportSettings(){

        let modal;
        let dialog = this.#editorContainer.querySelector('.heurist-jss-export-format');

        if(!dialog){

            dialog = document.createElement('div');
            dialog.classList.add('heurist-jss-export-format');
            dialog.style.display = 'none';

            dialog.innerHTML = `Please select an output format below:<br><br>
            <label><input type="radio" name="export-format" value="table" /> Table</label><br>
            <label><input type="radio" name="export-format" value="csv" /> CSV</label><br>
            <label><input type="radio" name="export-format" value="json" /> JSON</label><br>
            <br><label><input type="checkbox" name="include-styling" /> Include styling</label><br>
            <label><input type="checkbox" name="include-headers" /> Include column headers</label><br>
            <label><input type="checkbox" name="raw-output" /> Get data (e.g. formula result, not the formula itself)</label><br>
            <div style="position: absolute; bottom: 1em; right: 1em;">
                <button class="ui-button-action ui-button ui-corner-all ui-widget">Save data</button>
                <button class="ui-button ui-corner-all ui-widget">Cancel</button>
            </div>`;

            this.#editorContainer.append(dialog);

            let $__performExport = () => {

                this.#exportOptions = {
                    type: dialog.querySelector('[name="export-format"]:checked').value,
                    headers: dialog.querySelector('[name="include-headers"]').checked,
                    raw: dialog.querySelector('[name="raw-output"]').checked,
                    styling: dialog.querySelector('[name="include-styling"]').checked
                }

                modal.close();

                this.#updateInput();
            };

            let $__closeModal = () => {
                modal.close();
            };

            dialog.querySelector('button:nth-child(1)').addEventListener('click', $__performExport)
            dialog.querySelector('button:nth-child(2)').addEventListener('click', $__closeModal);
        }

        const type = this.#handledTypes.includes(this.#exportOptions.type) ? this.#exportOptions.type : 'csv';
        dialog.querySelector(`[name="export-format"][value="${type}"]`).checked = true;

        dialog.querySelector(`[name="include-styling"]`).checked = this.#exportOptions.styling;
        dialog.querySelector(`[name="include-headers"]`).checked = this.#exportOptions.headers;
        dialog.querySelector(`[name="raw-output"]`).checked = this.#exportOptions.raw;

        modal = jSuites.modal(dialog, {
            title: window.hWin.HR('Settings'),
            closed: false,
            width: '35em',
            height: '25em',
            onopen: () => {},
            onclose: () => {}
        });
    }

    #toJSON(){

        let data = this.#getData();

        if(this.#exportOptions.headers){

            const headers = this.#jspreadsheet[0].getHeaders().split(',');

            data.map(row => {

                let rowData = {};
                for(let i = 0; i < headers.length; i++){
                    rowData[headers[i]] = row[i] ?? '';
                }

                return rowData;
            });
        }

        return JSON.stringify(data);
    }

    #toCSV(){

        let data = this.#getData();

        data = data.map(row => 
            row.map(value => {
                const text = String(value ?? '');
                return `"${text.replace(/"/g, '\\"')}"`;
            }).join(',') // "\t"
        );

        if(this.#exportOptions.headers){
            const headers = this.#jspreadsheet[0].getHeaders().join(',');
            data.unshift(headers);
        }

        return data.join("\n");
    }

    #toTable(){

        const headers = this.#jspreadsheet[0].getHeaders().split(',');
        const data = this.#jspreadsheet[0].getData(false, this.#exportOptions.raw);

        let table = `<table>`;

        table += '<colgroup>';
        let headerHTML = '<thead><tr>';
        headers.forEach((header, index) => {
            const col = this.#jspreadsheet[0].cols[index].colElement;
            table += `<col width="${col.getAttribute('width')}">`;
            headerHTML += `<th>${header}</th>`
        });
        table += '</colgroup>';
        headerHTML += `</tr></thead>`;

        if(this.#exportOptions.headers){
            table += headerHTML;
        }

        let y = -1;
        table += '<tbody>';
        data.forEach(row => {

            ++ y;
            const rowElement = this.#jspreadsheet[0].rows[y].element;
            table += `<tr${rowElement.style.cssText !== '' ? ` style="${rowElement.style.cssText}"` : ''}>`;

            let x = 0;

            row.forEach(cell => {

                const attributes = this.#exportOptions.styling ? this.#getCellAttributes(x, y) : '';

                cell.replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');

                table += `<td${attributes !== '' ? ` ${attributes}` : ''}>${cell ?? ''}</td>`
            });
            table += '</tr>';
        });
        table += '</tbody></table>';

        return table;
    }

    #getData(){

        const data = this.#jspreadsheet[0].getData(false, this.#exportOptions.raw);

        let y = -1;
        return data.map(row => {

            ++ y;
            let x = 0;

            return row.map(cell => {

                const attributes = this.#exportOptions.styling ? this.#getCellAttributes(x, y) : '';
                x ++;

                cell.replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');

                return attributes.length > 0 ? `<span ${attributes}>${cell}</span>` : cell;
            });
        });
    }

    #getCellAttributes(x, y){

        const td = this.#jspreadsheet[0].getCellFromCoords(x, y);
        if(!td){
            return '';
        }

        let attributes = '';
        for(const attr of td.attributes){

            if((!attr.name.startsWith('data-') && attr.name !== 'style') || attr.name === 'data-x' || attr.name === 'data-y'){
                continue;
            }

            attributes += `${attr.name}="${attr.value}"`;
        }

        return attributes;
    }

    #loadScripts(){

        if(this.#loadingScripts){
            return;
        }

        this.#loadingScripts = true;

        // CSS styles
        $.getStyles('https://cdn.jsdelivr.net/npm/jsuites@6/dist/jsuites.min.css');
        $.getStyles('https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css');
        $.getStyles('https://fonts.googleapis.com/css?family=Material+Icons'); // for toolbar icons

        $.getMultiScriptsSequental([
            'https://cdn.jsdelivr.net/npm/jsuites@6/dist/jsuites.min.js', // Required, jSuites
            'https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/index.min.js', // Core, jSpreadsheet
            'https://cdn.jsdelivr.net/npm/tabularjs@2/dist/index.min.js' // for file upload, TabularJS
        ])
        .then(() => {
            this.show();
        })
        .catch(() => {
            window.hWin.HEURIST4.msg.showMsg_ScriptFail();
            this.#switchToText();
        })
        .finally(() => {
            this.#loadingScripts = false;
        });
    }

    destroy(){

        if(this.#jspreadsheet){
            jspreadsheet.destroy(this.#editor);
            //this.#jspreadsheet[0].closeEditor();
            this.#jspreadsheet = null;
        }

        if(this.#editorContainer){

            this.#editorContainer.remove();

            this.#editor = null;
            this.#editorContainer = null;
        }

        // Reset class variables
        this.#data = {};
        this.#initialInput = null;
        this.#initialTableValue = null;
        this.#dataType = '';
    }

    #switchToText(){

        const formatSwitchers = this.#inputDiv.querySelectorAll('.smallbutton');
        const textOption = Array.from(formatSwitchers).find(switcher => switcher.textContent.includes('text'));

        if(textOption){
            const event = new Event('click', {bubbles: false});
            textOption.dispatchEvent(event);
        }
    }

    clear(){
        this.#jspreadsheet[0].setData([]);
        this.#updateInput();
    }

    #logError(error){
        if(this.#DEBUG){
            console.error(error);
        }
    }
}
