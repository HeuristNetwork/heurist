/**
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

function hexportMenu( container ) {
    const _className = "exportMenu",
    _version   = "0.4";
    let dialog_options=null;

    function _init( container ){

        _initMenu( container );        
        
    }

    function _initMenu( container ){

        if(container && container.attr('id')=='menu_container'){
            container.addClass('ui-menu ui-widget ui-widget-content ui-corner-all');
            container.find('li').addClass('ui-menu-item');
        }

        if(container && container.hasClass('heurist-export-menu6')){
            _initLinks_v6( container );
        }else{
            _initLinks();

            if( window.hWin.HAPI4.sysinfo.db_registeredid>0 ){
                $('#divWarnAboutReg').hide();    
            }else{
                $('#divWarnAboutReg').show();    
            }
        }

        let outputs = window.hWin.HEURIST4.util.getUrlParameter('output', location.search);
        if(outputs && outputs != 'all'){

            outputs = outputs.split(',');

            if(outputs.length == 1){
                container.find(`#menu-export-${outputs[0]} > button`).trigger('click');
            }else{

                let $dlg;
                let msg = 'Select an export format: <select style="margin-left: 10px;">';
                for(const format of outputs){
                    msg += `<option>${format}</option>`;
                }
                msg += '</select>';

                let btns = {};
                btns['Export'] = function(){

                    let format = $dlg.find('select').val();
                    container.find(`#menu-export-${format} > button`).trigger('click');

                    $dlg.dialog('close');

                    window.close();
                };
                btns['Close'] = function(){
                    $dlg.dialog('close');
                    window.close();
                };

                $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Export records', yes: 'Export', no: 'Close'}, {default_palette_class: 'ui-heurist-publish'});
            }
        }
    }
    
    
    //init listeners for auto-popup links
    function _initLinks(menu){

        $('.export-button').each(function(){
            let ele = $(this);
            
            //find url
            let lnk = ele.parent().find('a').css({'text-decoration':'none','color':'black'});
            let href = lnk.attr('href');
            if(!window.hWin.HEURIST4.util.isempty(href)){
                
                if(href!='#'){
                href = href + (href.indexOf('?')>0?'&':'?') + 'db=' + window.hWin.HAPI4.database;
                    if(lnk.hasClass('h3link')){
                        //h3link class on menus implies location of older (vsn 3) code
                        href = window.hWin.HAPI4.baseURL + href;
                    }
                }
                
                lnk.attr('href', href).on('click',
                    function(event){
                        let save_as_file = true;
                        
                        let ele = $(event.target);
                        if(ele.is('span')){
                            save_as_file = false;
                            
                            if(ele.hasClass('mirador')){
                                save_as_file = 'mirador';
                            }
                            
                            ele = ele.parent();
                        }
                        let action = ele.attr('data-action');
                        if(action){
                            _menuActionHandler(event, action, ele.attr('data-logaction'), save_as_file);
                            return false;
                        }else{
                            _onPopupLink(event);
                        }
                    }
                );
            }

            ele.button().on('click',
                    function(event){
                        $(this).parent().find('a').trigger('click');
                    });
            
        });
        
    }

    //
    // init listeners for links in ui-menu version 6
    //
    function _initLinks_v6(menu){
     
        menu.find('li[data-export-action]').on({click:function(event){
            
            let ele = $(event.target);
            if(!ele.is('li')){
                ele = ele.parents('li');
            }
            let action = ele.attr('data-export-action');

            _menuActionHandler(event, action, ele.attr('data-logaction'), true);
            
            return false;
        }});
        
        menu.find('li[data-export-action]').css({'font-size':'smaller', padding:'6px'});
    }
    
    //
    //
    //    
    function _onPopupLink(event){
        
        let action = $(event.target).attr('id');
        
        let body = $(window.hWin.document).find('body');
        let dim = {h:body.innerHeight(), w:body.innerWidth()},
        link = $(event.target);

        let options = { title: link.html() };

        if (link.hasClass('small')){
            options.height=dim.h*0.6; options.width=dim.w*0.5;
        }else if (link.hasClass('portrait')){
            options.height=dim.h*0.8; options.width=dim.w*0.55;
            if(options.width<700) options.width = 700;
        }else if (link.hasClass('large')){
            options.height=dim.h*0.8; options.width=dim.w*0.8;
        }else if (link.hasClass('verylarge')){
            options.height = dim.h*0.95;
            options.width  = dim.w*0.95;
        }else if (link.hasClass('fixed')){
            options.height=dim.h*0.8; options.width=800;
        }else if (link.hasClass('fixed2')){
            if(dim.h>700){ options.height=dim.h*0.8;}
            else { options.height=dim.h-40; }
            options.width=800;
        }else if (link.hasClass('landscape')){
            options.height=dim.h*0.5;
            options.width=dim.w*0.8;
        }

        let url = link.attr('href');
        
            if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
                
                let q = encodeURIComponent(window.hWin.HEURIST4.current_query_request.q);
                if(!window.hWin.HEURIST4.util.isempty(q)) q = '&'+q;
                if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.current_query_request.rules)){
                    q = q + '&rules=' + encodeURIComponent(window.hWin.HEURIST4.current_query_request.rules);
                }
                url = url + '&w=all&a=1' + q;
            }
        
        
        
        if (link.hasClass('refresh_structure')) {
               options['afterclose'] = this._refreshLists;
        }

        if(link && link.attr('data-logaction')){
            window.hWin.HAPI4.SystemMgr.user_log(link.attr('data-logaction'));
        }

        window.hWin.HEURIST4.msg.showDialog(url, options);

        event.preventDefault();
        return false;
    }
    
    //
    // similar in resultListMenu
    //
    function isResultSetEmpty(){
        let recIDs_all = window.hWin.HAPI4.getSelection("all", true);
        if (window.hWin.HEURIST4.util.isempty(recIDs_all)) {
            window.hWin.HEURIST4.msg.showMsgDlg('No results found. '
            +'Please modify search/filter to return at least one result record.');
            return true;
        }else{
            return false;
        }
    }
    
    //
    //
    //
    function _menuActionHandler(event, action, action_log, save_as_file){

        if(action_log){
            window.hWin.HAPI4.SystemMgr.user_log(action_log);
        }

        if(action == "menu-export-csv"){
        
           
            if(isResultSetEmpty()) return;
            
            //window.hWin.HAPI4.currentRecordsetSelection is assigned in resultListMenu

            window.hWin.HEURIST4.ui.showRecordActionDialog('recordExportCSV', dialog_options);
            
        }else if(action == "menu-export-hml-resultset"){ // Current resultset, including rules-based expansion iof applied
            _exportRecords({format:'hml', multifile:false, save_as_file:save_as_file});
            
/*            
        }else if(action == "menu-export-hml-selected"){ // Currently selected records only
            _exportRecords({format:'hml', isAll:false, multifile:false, save_as_file:save_as_file});
            
        }else if(action == "menu-export-hml-plusrelated"){ // Current resulteset plus any related records
            _exportRecords({format:'hml', isAll:true, includeRelated:true, multifile:false, save_as_file:save_as_file});
*/
        }else if(action == "menu-export-hml-multifile"){ // selected + related
            _exportRecords({format:'hml', save_as_file:save_as_file});
            
        }else if(action == "menu-export-json"){ 
            _exportRecords({format:'json', save_as_file:save_as_file});
            
        }else if(action == "menu-export-geojson"){ 
            _exportRecords({format:'geojson', save_as_file:save_as_file});
            
        }else if(action == "menu-export-rdf"){ 
            _exportRecords({format:'rdf', save_as_file:save_as_file});
            
        }else if(action == "menu-export-gephi"){ 

            _popupFields({format:'gephi', save_as_file:save_as_file});

        }else if(action == "menu-export-iiif"){
            _exportRecords({format:'iiif', save_as_file:save_as_file});
            
        }else if(action == "menu-export-kml"){
            _exportKML(true, save_as_file);
        }else if(action == "menu-export-rss"){ //hidden
            _exportFeed('rss');
        }else if(action == "menu-export-atom"){ //hidden
            _exportFeed('atom');
        }
        
        event.preventDefault();
    }
    
    //
    // opts: {format, isAll, includeRelated, multifile, save_as_file}
    //
    function _exportRecords(opts){ // isAll = resultset, false = current selection only

        if(opts.format=='rdf' && !(window.hWin.HAPI4.sysinfo['db_registeredid']>0) ){

           window.hWin.HEURIST4.msg.showMsgDlg(
'<p>Sorry, RDF is only available for databases which have been registered. This is required in order to make your Subject, Predicate and Object URIs unique within the Heurist namespace.</p>'
+'<p>Please go to Design > Register to register your database.</p>');
            return;
        }
    
    
        let q = "",
        layoutString,rtFilter,relFilter,ptrFilter;
        
        let isEntireDb = false;
        
        opts.isAll = (opts.isAll!==false);
        opts.multifile = (opts.multifile===true);

        if(opts.isAll){

            if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
                
                q = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, true);
                
                /*
                q = encodeURIComponent(window.hWin.HEURIST4.current_query_request.q);
                if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.current_query_request.rules)){
                    q = q + '&rules=' + encodeURIComponent(window.hWin.HEURIST4.current_query_request.rules);
                }
                */
                        
                isEntireDb = (window.hWin.HAPI4.currentRecordset && 
                    window.hWin.HAPI4.currentRecordset.length()==window.hWin.HAPI4.sysinfo.db_total_records);
                    
            }

        }else{    //selected only

            if (!window.hWin.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs)) {
                window.hWin.HEURIST4.msg.showMsgDlg("Please select at least one record to export");
                return false;
            }
            q = "?w=all&q=ids:"+this._selectionRecordIDs.join(",");

        }

        if(q!=''){
            
            let script; 
            let params = '';
            const showOptionsDialog = true;
            if(showOptionsDialog){

                if(isEntireDb){
                    params =  'depth=0&linkmode=none';
                }else {
                    if(opts.format!='iiif' && opts.questionResolved!==true){
                        let $expdlg = window.hWin.HEURIST4.msg.showMsgDlg(
'<p>The records you are exporting may contain pointers to other records which are not in your current results set. These records may additionally point to other records.</p>'                
//+'<p>Heurist follows the chain of related records, which will be included in the XML or JSON output. The total number of records exported will therefore exceed the results count indicated.</p>'
//+'<p>To disable this feature and export current result only uncheck "Follow pointers"</p>'
+'<p style="padding:20px 0"><label><input type="radio" name="links" value="direct" style="float:left;margin-right:8px;" checked/>Follow pointers and relationship markers in records <b>(recommended)</b></label>'
+'<br><br><label><input type="radio" name="links" value="direct_links" style="float:left;margin-right:8px;"/>Follow only pointers, ignore relationship markers <warning about losing relationships></label>'
+'<br><br><label><input type="radio" name="links" value="none" style="float:left;margin-right:8px;"/>Don\'t follow pointers or relationship markers (you will lose any data which is referenced by pointer fields in the exported records)</label>'
+'<br><br><label><input type="radio" name="links" value="all" style="float:left;margin-right:8px;"/>Follow ALL connections including reverse pointers" (warning: any commonly used connection, such as to Places, will result in a near-total dump of the database)</label></p>'
+(opts.format=='hml'?'<p><input type="checkbox" name="human_readable_names"/>Include human-readable names for everything '
    +'<div class="heurist-helper3">(NOT RECOMMENDED except for small subset troubleshooting.If checked this will result in a VERY large file and VERY long export time)</div>':'')
+(opts.format=='rdf'?'<p>Since, RDF export is exeprimental please specify the access word: <input type="password" name="rdfpwd"/>':'')

                        , function(){ 
                            if(opts.format=='rdf' && $expdlg.find('input[name="rdfpwd"]').val()!='Tehri'){
                                return;
                            }
                            
                            let val = $expdlg.find('input[name="links"]:checked').val();

                            opts.linksMode = val;
                            opts.questionResolved=true; 
                            
                            opts.showHumanReadableNames = $expdlg.find('input[name="human_readable_names"]').is(':checked');

                            _exportRecords( opts ); 
                        },
                        {
                            yes: 'Proceed',
                            no: 'Cancel'
                        });
                        
                        return;
                    }
                    params =  'depth=all';
                }
                
            }
            /*
            else{
                if ((opts.format === 'hml' || opts.format === 'json') && !opts.confirmNotFollowPointers) {
                    window.hWin.HEURIST4.msg.showMsgDlg(
                        '<p><span style="color:red">WARNING:</span> by allowing the export of records without following pointers, ' +
                        'you will lose any data which is referenced by pointer fields in the exported records. ' +
                        'This may be acceptable for simple lists eg. of places or person names, ' +
                        'but you need to understand the nature of the exported records to be sure that you are not ' +
                        'losing essential data.</p>' +
                        '<p>Exporting and importing a CSV file will give you more control on what fields are exported.</p>' +
                        '<p>Are you sure?</p>', function(){
                            opts.confirmNotFollowPointers = true;
                            _exportRecords(opts);
                        }, {
                            yes: 'Proceed',
                            no: 'Cancel'
                        });
                    return;
                }
                params =  'depth='+(opts.includeRelated?1:0);
            }
            */
            
            params =  params + (opts.linksMode?('&linkmode='+opts.linksMode):'');  

            if(opts.format=='hml'){
                script = 'export/xml/flathml.php';                

                //multifile is for HuNI  
                params =  params + (opts.multifile?'&multifile=1':'');  
                
                if(opts.showHumanReadableNames){
                    params =  params + '&human_readable_names=1';    
                }

            }else{
                
                script = 'hserv/controller/record_output.php';
                
                if(opts.format=='iiif'){

                    if(opts.save_as_file==='mirador'){
                        //create dynamic manifest with given set of media
                        script = 'hclient/widgets/viewers/miradorViewer.php'
                    }else{
                        params = 'format=iiif';
                    }
                }else{
                    params = params + '&format='+opts.format

                    if(opts.format=='gephi'){
                        params += $('#limitGEPHI').is(':checked') ? '&limit=1000' : '';    
                        params += !window.hWin.HEURIST4.util.isempty(opts.fields) ? `&columns=${opts.fields}` : '';    
                    }else if(opts.format=='geojson'){
                        params = params + '&detail_mode='+$('input[name="detail_mode"]:checked').val();        
                    }else if(opts.format=='rdf'){
                        params = params + '&vers=2&serial_format='+$('input[name="serial_format"]:checked').val();        
                        let include_additional_info = '';
                        include_additional_info += $('#include_definition_label').is(':checked')?'1':'0';
                        include_additional_info += $('#include_resource_term_label').is(':checked')?'1':'0';
                        include_additional_info += $('#include_resource_rec_title').is(':checked')?'1':'0';
                        include_additional_info += $('#include_resource_file_info').is(':checked')?'1':'0';
                        if(include_additional_info=='1111'){
                            include_additional_info = '1';
                        }
                        if(include_additional_info!==''){
                            params = params + '&extinfo=' + include_additional_info;
                        }
                    }else{
                        params = params +'&defs=0&extended='+($('#extendedJSON').is(':checked')?2:1);
                    }
                }
            }
            
            if(opts.save_as_file===true){          
                params = params + '&file=1'; //save as file
            }
                

            let url = window.hWin.HAPI4.baseURL + script + 
            q + 
            "&a=1"+
            /*(layoutString ? "&" + layoutString : "") +
            (selFilter ? "&" + selFilter : "") +
            (rtFilter ? "&" + rtFilter : "") +
            (relFilter ? "&" + relFilter : "") +
            (ptrFilter ? "&" + ptrFilter : "") +*/
            "&db=" + window.hWin.HAPI4.database
            +'&'+params;
            
            window.open(url, '_blank');    
        }

        return false;
    }
    
    //
    //
    //
    function _exportKML(isAll, save_as_file){

        let q = "";
        if(isAll){

            q = window.hWin.HEURIST4.query.composeHeuristQuery2(window.hWin.HEURIST4.current_query_request, false);

            if(q=='?'){
                window.hWin.HEURIST4.msg.showMsgDlg("Define filter and apply to database");
                return;
            }


        }else{

            if (!window.hWin.HEURIST4.util.isArrayNotEmpty(this._selectionRecordIDs)) {
                window.hWin.HEURIST4.msg.showMsgDlg("Please select at least one record to export");
                return false;
            }
            q = "?w=all&q=ids:"+this._selectionRecordIDs.join(",");
        }

        if(q!=''){
            let url = window.hWin.HAPI4.baseURL + "export/xml/kml.php" + q + "&a=1&depth=1&db=" + window.hWin.HAPI4.database;
            if(save_as_file){
                url = url + '&file=1';
            }
            
            
            window.open(url, '_blank');
        }

        return false;
    }

    //
    // hidden - noy used 
    //
    function _exportFeed(mode){

        if(!window.hWin.HEURIST4.util.isnull(window.hWin.HEURIST4.current_query_request)){
            let q = encodeURIComponent(window.hWin.HEURIST4.current_query_request.q);

            if(!window.hWin.HEURIST4.util.isempty(q)){
                let w = window.hWin.HEURIST4.current_query_request.w;
                if(window.hWin.HEURIST4.util.isempty(w)) w = 'a';
                if(mode=='rss') {
                    mode = '';
                }else{
                    mode = '&feed='+mode;
                }
                let rules = '';
                if(!window.hWin.HEURIST4.util.isempty(window.hWin.HEURIST4.current_query_request.rules)){
                    rules = '&rules=' + encodeURIComponent(window.hWin.HEURIST4.current_query_request.rules);
                }


                let url = window.hWin.HAPI4.baseURL + 'export/xml/feed.php?&q=' + q + '&w=' + w + '&db=' + window.hWin.HAPI4.database + mode + rules;
                window.hWin.open(url, '_blank');
            }
        }
    }

    //
    // Get fields to output
    //
    function _popupFields(opts){

        let $dlg;

        let msg = 'Would you like to export additional fields, or proceed with the standard fields?';

        let btns = {};
        btns[window.hWin.HR('Select additional fields')] = () => {

            const dty_dialog_options = {
                select_mode: 'select_multi',
                edit_mode: 'popup',
                isdialog: true,
                width: 540,
                selection_on_init: [],
                title: 'Select fields to export',
                filters: {
                    types: [ "enum", "float", "date", "file", "geo", "freetext", "blocktext", "integer", "year", "boolean" ]
                },
                onselect:function(event, data){
    
                    if(data && data.selection){
                        opts['fields'] = data.selection.join();
                    }
    
                    _exportRecords(opts);
                }
            }
    
            window.hWin.HEURIST4.ui.showEntityDialog('defDetailTypes', dty_dialog_options);

            $dlg.dialog('close');
        };

        btns[window.hWin.HR('Proceed without other fields')] = () => { $dlg.dialog('close'); _exportRecords(opts); };

        btns[window.hWin.HR('Cancel')] = () => { $dlg.dialog('close'); };

        $dlg = window.hWin.HEURIST4.msg.showMsgDlg(msg, btns, {title: 'Add additional fields to export'}, {default_palette_class: 'ui-heurist-publish'});
    }
     
    //public members
    let that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},
        
        setDialogOptions: function( _dialog_options ){
            dialog_options = _dialog_options
        }

    }

    _init( container );
    return that;  //returns object
}
    