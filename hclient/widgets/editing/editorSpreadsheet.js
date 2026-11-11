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

    #DEBUG = false;

    #inputDiv;
    #input;
    #inputDisplay = '';

    #editor;
    #editorContainer;
    #saveButton;
    #importInput;

    #jspreadsheet;
    #defaultConfig = {
        //tabs: false, // worksheets not handled, for now
        toolbar: true,
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
    #dataType; // [csv, json, table]

    #loadingScripts = false;
    _boundSaveEvent;

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

        //this.#loadScripts();
    }

    #parseData(){

        let data = this.#input.value.trim();

        if(false && /^<table/.test(data)){
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

            const char = data[i];
            const next = data[i + 1];

            // Quote mark
            if(char === '"'){

                if(isQuoted && next === '"'){
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

        const container = document.createElement('div');
        container.style.display = 'none';
        const table = document.getElementById('spreadsheet-container');
        container.innerHTML = data;

        this.#jspreadsheet = jspreadsheet(document.getElementById('my-target-table'));
    }

    #detectDelimiter(data){

        const delimiters = [',', "\t", '|', ';'];

        const firstLine = data.split(/\r?\n/)[0];

        const delimiterResults = delimiters.map((delimiter) => ({
            delimiter: delimiter, count: firstLine.split(delimiter).length
        }));

        return delimiterResults.sort((a, b) => b.count - a.count)[0].delimiter;
    }

    #setupInterface(){

        // Setup save button
        this.#saveButton = document.createElement('button');
        this.#saveButton.textContent = 'Save data';
        this.#saveButton.classList.add('ui-button-action', 'ui-button', 'ui-corner-all', 'ui-widget');

        this._boundSaveEvent = this.updateInput.bind(this);
        this.#saveButton.addEventListener('click', this._boundSaveEvent);

        this.#editorContainer.prepend(this.#saveButton);

        /* Setup file input, currently unavailable
        this.#importFile = document.createElement('input');
        this.#importFile.type = 'file';
        this.#importFile.after(this.#saveButton);
        this.#importFile.fileupload({...});
        */
    }

    show(){

        if(typeof jspreadsheet !== 'function'){
            this.#loadScripts();
            return;
        }else if(this.#jspreadsheet){
            return;
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

        if(this.#dataType === 'table' || this.#jspreadsheet){
            return;
        }

        const config = structuredClone(this.#defaultConfig);
        config.onbeforecreateworksheet = (config, index) => ({ worksheetName: `Sheet ${index + 1}` });
        const worksheetConfig = structuredClone(this.#defaultSheetConfig);

        worksheetConfig['data'] = this.#data[0].data;
        worksheetConfig['columns'] = this.#data[0].columns;

        config.worksheets.push(worksheetConfig);

        // Editor container
        this.#editorContainer = document.createElement('div');
        this.#editorContainer.classList.add('heurist-spreadsheet-editor');
        this.#editorContainer.style.display = 'inline-block';
        this.#inputDiv.prepend(this.#editorContainer);

        // Init editor
        this.#jspreadsheet = new jspreadsheet(this.#editorContainer, config);
        this.#editor = this.#inputDiv.querySelector('.jss_spreadsheet');

        this.#setupInterface();

        return this.#jspreadsheet;
    }

    hide(){

        this.destroy();

        this.#input.style.display = this.#inputDisplay;
    }

    updateInput(){

        switch(this.#dataType){
            case 'json':
                this.#input.value = this.#toJSON();
                break;
            case 'csv':
                this.#input.value = this.#toCSV();
                break;
            case 'table':    
                this.#input.value = this.#toTable();
                break;
            default:
                window.hWin.HEURIST4.msg.showMsgFlash('Invalid output format...', 3000);
                return;
                break;
        }

        const event = new Event('change', {bubbles: false});

        this.#input.dispatchEvent(event);
    }

    #toJSON(){
        return JSON.stringify(this.#jspreadsheet[0].getData());
    }

    #toCSV(){

        const data = this.#jspreadsheet[0].getData();

        return data.map(row => 
            row.map(value => {
                const text = String(value ?? '');
                return `"${text.replace(/"/g, '""')}"`;
            }).join(',') // "\t"
        ).join("\n");
    }

    #toTable(){

        const headers = this.#jspreadsheet[0].getHeaders().join(',');
        const data = this.#jspreadsheet[0].getData();

        let table = `<table>`;

        table += `<thead><tr>`;
        headers.forEach(header => table += `<th>${header}</th>`);
        table += `</tr></thead>`;

        table += '<tbody>';
        data.forEach(row => {
            table += '<tr>';
            row.forEach(value => table += `<td>${value ?? ''}</td>`)
            table += '</tr>';
        })
        table += '</tbody>';

        return table;
    }

    #loadScripts(){

        if(this.#loadingScripts){
            return;
        }

        this.#loadingScripts = true;

        // CSS styles
        $.getStyles('https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/jspreadsheet.min.css');
        $.getStyles('https://cdn.jsdelivr.net/npm/jsuites@6/dist/jsuites.min.css');
        $.getStyles('https://fonts.googleapis.com/css?family=Material+Icons'); // for toolbar

        $.getMultiScriptsSequental(['https://cdn.jsdelivr.net/npm/jspreadsheet-ce@5/dist/index.min.js','https://cdn.jsdelivr.net/npm/jsuites@6/dist/jsuites.min.js'])
            .then(() => {
                this.show();
            })
            .catch(() => {
                window.hWin.HEURIST4.msg.showMsg_ScriptFail();
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

        if(this.#saveButton){
            this.#saveButton.removeEventListener('click', this._boundSaveEvent);
            this.#saveButton.remove();
            this.#saveButton = null;
        }

        if(this.#editorContainer){

            this.#editorContainer.remove();

            this.#editor = null;
            this.#editorContainer = null;
        }
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
    }

    #logError(error){
        if(this.#DEBUG){
            console.error(error);
        }
    }
}
