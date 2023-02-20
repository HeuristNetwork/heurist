/**
* recordFindDuplicates.js - Find duplicates by record type and selected fields
*                           It uses levenshtein function on server side
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
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

/*

It creates thematic map in the following format

{
"title":"name of thematic map",
"symbol":{}, //base symbology. if it is not defined use default layer symbology
"fields":[
{"code":"10:123",  //rules: field id or rt:dt to search field in linked records
    "title":"Person weight","type":"integer",  //besides standard field type we can use aggregate types: sum, count, avg
    "range_type": "exact|equal|log"  // exact (default) - one value or list of values or exact range (suitable for all), 
                                     // equal - values be split by ranges according to current min/max suitable for numeric and dates
                                     // log - logariphmic
    "ranges":[  //sample of exact ranges
       {"value":"40", "title":"","symbol":{ } }, //symbology that overwrites some properties of base symbology
       {"value":"40<>50", "symbol":{ } },
       {"value":"69,71,75", "symbol":{ } }
    
    //sample equal of log ranges - need to find min/max values beforehand
    "range_count":5,   
    "ranges":[  
       {"value":0,symbol":{ } }, //symbology that overwrites some properties of base symbology
       ....
       {"value":5,symbol":{ } }
    ]
},
{"code":"10:15","title":"Gender","type":"enum",
....
}
],
"rectypes":["10"]
}

{
"fields":[}
12:1109



*/
$.widget( "heurist.thematicMapping", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 780,
        width:  1100,
        modal:  true,
        title:  'Define thematic mapping',
        default_palette_class: 'ui-heurist-design', 
        
        maplayer_query: null,
        thematic_mapping: null,
        
        path: 'widgets/entity/popups/', //location of this widget
        
        htmlContent: 'thematicMapping.html',
        helpContent: 'thematicMapping.html' //in context_help folder
    },
    
    baseLayerSymbol: null,
    currentField: 0,
    selectedFields:{},
    
    _destroy: function() {
        this._super(); 
        
        var treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        treediv.remove();
        
    },
        
    //    
    //
    //
    _getActionButtons: function(){
        var res = this._super();
        var that = this;
        res[1].text = window.hWin.HR('Save thematic map');
        res[0].text = window.hWin.HR('Cancel');
        return res;
    },    
        
    //
    // Fills record type selector. Parent method is overwritten
    //
    _fillSelectRecordScope: function (){

        this.selectRecordScope.empty();

        var opt, selScope = this.selectRecordScope.get(0);
        
        this.selectRecordScope = window.hWin.HEURIST4.ui.createRectypeSelectNew( selScope,
        {
            topOptions: [{key:'-1',title:'select record type...'}],
            useHtmlSelect: false,
            useCounts: true,
            showAllRectypes: true
        });
        
        
        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        this._onRecordScopeChange();
        
        selScope = this.selectRecordScope.get(0);
        window.hWin.HEURIST4.ui.initHSelect(selScope);
        
        var fields_sel = this.element.find('#selected_fields');
        
        this._on(fields_sel,{change: this._onThemeFieldSelect});

        this._on(this.element.find('button[id^="btn_f"]').button(), 
                                    {click:this._onThemeFieldAction});
        this.element.find('button[id^="btn_f_range_add"]').button({icon:'ui-icon-circle-b-plus'});
        
        this._initSymbolEditor(this.element.find('#tm_symbol'));
        
        
        this.options.thematic_mapping = window.hWin.HEURIST4.util.isJSON( this.options.thematic_mapping );
        
        //load list of thematic maps
        var themes_list = this.element.find('#thematic_maps_list');
        this._on(themes_list,{change: this._onThematicMapSelect});
        themes_list.empty();
        
        if(this.options.thematic_mapping)
        {
            if(!$.isArray(this.options.thematic_mapping)) this.options.thematic_mapping = [this.options.thematic_mapping];
        }else{
            this.options.thematic_mapping = [];//
        }
            
        var i=0;
        while(i<this.options.thematic_mapping.length){
            var t_map = this.options.thematic_mapping[i];
            if(t_map.fields){
                window.hWin.HEURIST4.ui.addoption(themes_list[0], i, t_map.title);
                i++;
            }else{
                this.baseLayerSymbol = t_map;
                this.options.thematic_mapping.splice(i,1);
            }
        }

        if(themes_list.find('option').length==0){
            this._addThematicMap();
        }else{
            themes_list[0].selectedIndex = 0;
            themes_list.trigger('change');
        }
        
        this._on(this.element.find('#btn_map_remove').button(), 
                                    {click:this._onThematicMapDelete});

        this._on(this.element.find('#btn_tm_add'),
                                    {click:this._addThematicMap});
        
    },
            
    //
    // Save thematic mapping
    //
    doAction: function(){
        
        //get values
        if(this._saveThematicMap()){
            if(this.baseLayerSymbol){
                this.options.thematic_mapping.unshift(this.baseLayerSymbol);
            }
            this._context_on_close =  this.options.thematic_mapping;
            this.closeDialog();
        }
        
        
    },
    
    //
    //
    //
    _hideProgress: function (){
        this._super(); 
        this.element.find('#div_search').show();  
    },
    
    //
    // overwritten - reload treeview
    //
    _onRecordScopeChange: function() 
    {
        var isdisabled = this._super();
        
        //window.hWin.HEURIST4.util.setDisabled( this.element.parents('.ui-dialog').find('#btnDoAction2'), isdisabled );
        
        var rtyID = this.selectRecordScope.val();
        
        if(this._selectedRtyID!=rtyID ){
            if(rtyID>0){
                //reload treeview
                this._loadRecordTypesTreeView( rtyID );
                //$('.rtt-tree').parent().show();
            }else{
                //$('.rtt-tree').parent().hide();
            }
        }
        
        return isdisabled;
    },
    
    //
    // show treeview with record type structure
    //
    _loadRecordTypesTreeView: function(rtyID){
        
        var that = this;

        if(this._selectedRtyID!=rtyID ){
            
            this._selectedRtyID = rtyID;

            //var main_area = this.element.find('#div_work_area').empty();
            //$('<select id="selected_fields" size="5" style="min-width:400px">').appendTo(main_area);
            
            var allowed_fieldtypes = [//'rec_Title','rec_ID',
                'enum','year','date','integer','float','resource']; //'freetext',
            
            //generate treedata from rectype structure
            var treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, allowed_fieldtypes );
            
        //load treeview
        var treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        
        treedata[0].expanded = true;

        treediv.fancytree({
            //extensions: ["filter"],
            //            extensions: ["select"],
            //checkbox: true,
            //selectMode: 3,  // single
            checkbox: false,
            selectMode: 1,  // single
            source: treedata,
            beforeSelect: function(event, data){
                // A node is about to be selected: prevent this, for folder-nodes:
                if( data.node.hasChildren() ){

                    if(data.node.isExpanded()){
                        for(var i = 0; i < data.node.children.length; i++){
                            let node = data.node.children[i];

                            if(node.key == 'term'){ // if node is a term
                                node.setSelected(true); // auto select 'term' option to add term name
                            }
                        }
                    }
                    return false;
                }
            },
            renderNode: function(event, data){
                if(false && data.node.data.type == "enum") { // hide blue and expand arrows for terms
                    $(data.node.span.childNodes[0]).hide();
                    $(data.node.span.childNodes[1]).hide();
                }
                if(data.node.parent && (data.node.parent.data.type == 'resource' || data.node.parent.data.type == 'rectype')){ // add left border+margin
                    $(data.node.li).attr('style', 'border-left: black solid 1px !important;margin-left: 9px;');
                }
                if(!(data.node.data.type == 'resource' || data.node.data.type == 'rectype'))
                {
                    //define action button
                   
                    var item = $(data.node);
                    var item_li = $(data.node.li);
                    if($(item).find('.svs-contextmenu3').length==0){
                     
                        var parent_span = item_li.children('span.fancytree-node');

                        //add icon
                        var actionspan = $('<div class="svs-contextmenu3" style="padding: 0px 20px 0px 0px;" data-parentid="'
                        +item.data.parent_id+'" data-code="'+data.node.key+'">'
                        +'<span class="ui-icon ui-icon-circle-b-plus" title="Add field" style="font-size:0.9em"></span>'
                        +'</div>').appendTo(parent_span);
                        
                        actionspan.find('.ui-icon-circle-b-plus').click(function(event){
                            var ele = $(event.target);
                            window.hWin.HEURIST4.util.stopEvent(event);
                            that._addThemeField( ele.parents('[data-code]').attr('data-code') );                           
                        });
                        
                        //hide icons on mouse exit
                        function _onmouseexit(event){
                            var node;
                            if($(event.target).is('li')){
                                node = $(event.target).find('.fancytree-node');
                            }else if($(event.target).hasClass('fancytree-node')){
                                node =  $(event.target);
                            }else{
                                //hide icon for parent 
                                node = $(event.target).parents('.fancytree-node');
                                if(node) node = $(node[0]);
                            }
                            var ele = node.find('.svs-contextmenu3');
                            ele.hide();
                        }               

                        $(parent_span).hover(
                            function(event){
                                var node;
                                if($(event.target).hasClass('fancytree-node')){
                                    node =  $(event.target);
                                }else{
                                    node = $(event.target).parents('.fancytree-node');
                                }
                                if(! ($(node).hasClass('fancytree-loading') )){
                                    var ele = $(node).find('.svs-contextmenu3');
                                    ele.css({'display':'inline-block'});//.css('visibility','visible');
                                }
                            }
                        );               
                        $(parent_span).mouseleave(
                            _onmouseexit
                        );                                                  
                        
                        
                    }
                    
                    /*
                    if(data.node.parent && data.node.parent.data.type == 'enum'){ // make term options inline and smaller
                        $(data.node.li).css('display', 'inline-block');
                        $(data.node.span.childNodes[0]).css('display', 'none');

                        if(data.node.key == 'term'){
                            $(data.node.parent.ul).css({'transform': 'scale(0.8)', 'padding': '0px', 'position': 'relative', 'left': '-12px'});
                        }
                    }
                    */
                }
                if(data.node.data.type == 'separator'){
                    $(data.node.span).attr('style', 'background: none !important;color: black !important;'); //stop highlighting
                    $(data.node.span.childNodes[1]).hide(); //checkbox for separators
                }
            },
            lazyLoad: function(event, data){
                var node = data.node;
                var parentcode = node.data.code; 
                var rectypes = node.data.rt_ids;

                var res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                    rectypes, allowed_fieldtypes, parentcode );
                if(res.length>1){
                    data.result = res;
                }else{
                    data.result = res[0].children;
                }

                return data;                                                   
            },
            loadChildren: function(e, data){
                setTimeout(function(){
                    //that._assignSelectedFields();
                    },500);
            },
            select: function(e, data) {
                //that._addThemeField();
            },
            click: function(e, data){

                if(data.node.data.type == 'separator'){
                    return false;
                }

                var isExpander = $(e.originalEvent.target).hasClass('fancytree-expander');
                var setDefaults = !data.node.isExpanded();

                if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                    
                    if(!isExpander){
                        data.node.setExpanded(!data.node.isExpanded());
                    }
                
                    if(setDefaults){
                        for(var i = 0; i < data.node.children.length; i++){
                            let node = data.node.children[i];

                            if(node.key == 'term'){ // if node is a term
                                node.setSelected(true); // auto select 'term' option to add term name
                            }
                        }
                    }
                }else if( data.node.lazy && !isExpander) {
                    data.node.setExpanded( true );
                }
            },
            dblclick: function(e, data) {
                if(data.node.data.type == 'separator'){
                    return false;
                }
                data.node.toggleSelected();
            },
            keydown: function(e, data) {
                if( e.which === 32 ) {
                    data.node.toggleSelected();
                    return false;
                }
            }
        });
            
        }   
    },

    //
    //
    //
    _addThematicMap: function(){
        
        if(this._saveThematicMap()){
        
            let last_idx = this.options.thematic_mapping.length;
            const newname = 'New Thematic map';
            this.options.thematic_mapping.push({title:newname, active:false, fields:[]});

            
            var themes_list = this.element.find('#thematic_maps_list');
            window.hWin.HEURIST4.ui.addoption(themes_list[0], last_idx, newname);
            //ele.value = $(ele).uniqueId();
            //this.options.thematic_mapping[last_idx].uid = ele.value;
            
            themes_list[0].selectedIndex = last_idx;
            themes_list.trigger('change');
        }
    },
    
    
    //
    // 
    //
    _onThematicMapDelete: function(unconditional){
        if(this.currentThemeIdx>=0){
            
            var that = this;

            if(unconditional!==true){    
                window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                    function(){that._onThematicMapDelete(true)});
                return;
            }
            
            var themes_list = this.element.find('#thematic_maps_list');
            this.options.thematic_mapping.splice(this.currentThemeIdx, 1);
            //remove from select
            themes_list.find('option:eq(' + this.currentThemeIdx + ')').remove();
            
            if(this.options.thematic_mapping.length==0){
                //offer exit
                
                window.hWin.HEURIST4.msg.showMsgDlg('<br>You removed all thematic maps. Exit this form?',
                    function(){
                        that._context_on_close =  (that.baseLayerSymbol)?that.baseLayerSymbol:'';    
                        that.closeDialog();
                    });
                
            }else{
                this.currentField = 0;
                this.currentThemeIdx = -1;
                themes_list[0].selectedIndex = 0;
                themes_list.trigger('change');
            }
        }
    },
    
    //
    // 
    //
    _saveThematicMap: function(){
        
        if(this.currentThemeIdx>=0){
        
            var t_map = this.options.thematic_mapping[this.currentThemeIdx];
        
            t_map.title = this.element.find('#tm_name').val();
            t_map.active = this.element.find('#tm_active').is(':checked');
            t_map.symbol = window.hWin.HEURIST4.util.isJSON(this.element.find('#tm_symbol').val());
            if(!t_map.symbol) t_map.symbol =  '';

            t_map.fields = [];
            
            this._saveThemeField();
            
            this.currentField = 0;
            
            var len = Object.keys(this.selectedFields).length;
            for (var k=0;k<len;k++){
                key = Object.keys(this.selectedFields)[k];
                if(this.selectedFields[key].ranges.length>0){
                    t_map.fields.push( this.selectedFields[key] );    
                }
            }
            
            if(window.hWin.HEURIST4.util.isempty(t_map.title)){
                window.hWin.HEURIST4.msg.showMsgErr('Title is mandatory');            
                return false;
            }
            if(t_map.fields.length==0){
                window.hWin.HEURIST4.msg.showMsgErr('Need to define at least one field with ranges/categories');
                return false;
            }

            //rename in the list
            this.element.find('#thematic_maps_list').find('option:eq(' + this.currentThemeIdx + ')').html(t_map.title);
            
            this.options.thematic_mapping[this.currentThemeIdx] = t_map;
            
        }
        
        return true;
    },

    //
    // load thematic map on selection in the list
    //
    _onThematicMapSelect: function( event ){
        
        if(this._saveThematicMap()){
        
            this.currentThemeIdx = event?event.target.selectedIndex:0;
            
            var t_map = this.options.thematic_mapping[this.currentThemeIdx];
        
            this.element.find('#tm_name').val(t_map.title);
            this.element.find('#tm_active').prop('checked', t_map.active);
            
            var base_symbol = window.hWin.HEURIST4.util.isJSON( t_map.symbol );
            base_symbol = (!base_symbol)?'':JSON.stringify(base_symbol);
            this.element.find('#tm_symbol').val(base_symbol);
            
            this.selectedFields = {};
            
            var flds = t_map.fields;
            
            var fields_sel = this.element.find('#selected_fields');
            fields_sel.empty();
            
            if(flds){
                for(var i=0; i<flds.length; i++){
                    var fld = flds[i];
                    var key = fld.code.split(':');
                    key = key[key.length-1];
                    
                    this.selectedFields[key] = fld;
                    
                    window.hWin.HEURIST4.ui.addoption(fields_sel[0], key, fld.title+' ('+fld.code+'  '+key+')');
                }
                
                fields_sel[0].selectedIndex = 0;
                fields_sel.trigger('change');
            }        
        }else{
             document.getElementById('thematic_maps_list').selectedIndex = this.currentThemeIdx;
        }
        
    },
    
    //-----------------------
    //
    //
    //
    _addThemeField: function( nodekey )
    {
        var tree = this.element.find('.rtt-tree').fancytree("getTree");
        
        var node = tree.getNodeByKey(nodekey);
        
        var key = node.key.split(':');
        key = key[key.length-1];
        
        if(!this.selectedFields[key]){
            this.selectedFields[key] = {code:node.data.code, title:node.data.name, ranges:[]};
            var sel = this.element.find('#selected_fields');
            
            //+' ('+this.selectedFields[key].code+'  '+key+')'
            window.hWin.HEURIST4.ui.addoption(sel[0], key, this.selectedFields[key].title);
            
            if(!(sel[0].selectedIndex>0)){
                sel[0].selectedIndex = 0;  
                sel.trigger('change');
            } 
        } 
        
    },
    
    
    _addThemeField_old: function(){
        
        var tree = this.element.find('.rtt-tree').fancytree("getTree");
        var fieldIds = tree.getSelectedNodes(false);
        var k, len = fieldIds.length;
        
        
        for (k=0;k<len;k++){
            var node =  fieldIds[k];
            if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;

            var key = node.key.split(':');
            key = key[key.length-1];
            
            if(!this.selectedFields[key]){
                this.selectedFields[key] = {code:node.data.code, title:node.data.name, ranges:[]};
            } 
            
        }

        var sel = this.element.find('#selected_fields');
        var keep_val = sel.val();
        sel.empty();
        len = Object.keys(this.selectedFields).length;
        for (k=0;k<len;k++){
            key = Object.keys(this.selectedFields)[k];
            window.hWin.HEURIST4.ui.addoption(sel[0], key, this.selectedFields[key].title+' ('+this.selectedFields[key].code+'  '+key+')');
        }

        
        sel.val(keep_val);
        if(!(sel[0].selectedIndex>0)) sel[0].selectedIndex = 0;
    },
    
    //
    //
    //
    _saveThemeField: function(){
        
        if(this.currentField>0){
            var selfield = this.selectedFields[this.currentField];
            //get values from UI
            var main_area = this.element.find('#div_work_area');        
            var f_ranges = main_area.find('#f_ranges');
            
            if(f_ranges.children().length>0){
                $.each(f_ranges.children(), function(i, ele){

                    ele = $(ele);
                    
                    let range_uid = ele.attr('id');

                    $.each(selfield.ranges,function(i,item){
                        if(item.uid  == range_uid){
                            
                            let val1 = ele.find('input.val1').val();
                            let val2 = ele.find('input.val2').val();
                            if(val1 && val2) {
                                val1 = val1+'<>'+val2
                            }else if(val2) {
                                val1 = val2;
                            }
                                
                            if(val1){
                                selfield.ranges[i].value = val1;
                                var symb = window.hWin.HEURIST4.util.isJSON(ele.find('.field-symbol').val());
                                selfield.ranges[i].symbol = symb?symb:'';
                                selfield.ranges[i].uid = null;
                                delete selfield.ranges[i].uid;
                            }else{
                                //remove empty value ranges
                                selfield.ranges.splice(i,1);        
                            }
                            
                            return false;
                        }
                    });
                });
            }else{
                selfield.ranges = [];
            }

            selfield.title = main_area.find('#f_title').val();
            
            this.selectedFields[this.currentField] = selfield;
        }
        
    },
    
    //
    //  saves previous field/range settings 
    //  and shows title and ranges for current field
    //
    _onThemeFieldSelect: function(event){                                                           
        
        var main_area = this.element.find('#div_work_area');        
        var f_ranges = main_area.find('#f_ranges');
        var selfield;
        
        //save previous
        this._saveThemeField();
        
        this.currentField = event?$(event.target).val():0;
        
        f_ranges.empty()
        
        if(this.currentField>0){
            this.element.find('#div_work_area').show();    
        
            selfield = this.selectedFields[this.currentField];
            
            //add title
            main_area.find('#f_title').val(selfield.title);
            
            //add ranges elements
            for (var k=0;k<selfield.ranges.length;k++){
                this._defThemeFieldRange(k, selfield.ranges[k]);
            }
        }else{
            this.element.find('#div_work_area').hide();    
        }
    },
    
    //
    // inits and returns range element
    //
    _defThemeFieldRange: function(idx, range){                                                           
        
       var ele = $('<div style="padding:5px" class="field-range">'
       +'<span class="ui-icon ui-icon-circle-b-close" style="margin:2px 0 0 12px;cursor:pointer"/>'
       +'<input class="val1 text ui-widget-content ui-corner-all" style="width:100px;margin-left:5px"/>'
       +'<span>&nbsp;&lt;&gt;&nbsp;</span><input class="val2 text ui-widget-content ui-corner-all" style="width:100px"/>'
       +'<span style="display:inline-block;width:50px"/>'
       +'<input class="field-symbol text ui-widget-content ui-corner-all" style="width:250px"/>'
       +'</div>').appendTo(this.element.find('#f_ranges'));
       
       ele.uniqueId();
       var uid = ele.attr('id');
       range.uid = uid;
       
       var val1 = range.value, val2 = '';
       if(val1 && val1.indexOf('<>')>0){
           var vals = val1.split('<>');
           val2 = (vals && vals.length==2)?vals[1]:'';
           val1 = (vals && vals.length==2)?vals[0]:'';
       }
       
       ele.find('input.val1').val(val1)
       ele.find('input.val2').val(val2);
       if(range.symbol){
            ele.find('.field-symbol').val($.isPlainObject(range.symbol)?JSON.stringify(range.symbol):range.symbol);    
       }
       
       this._initSymbolEditor( ele.find('.field-symbol') ); 
       
       this._on(ele.find('.ui-icon-circle-b-close'),{click:function(event){
           var ele = $(event.target).parents('.field-range');
           var range_uid = ele.attr('id');
           ele.remove();
           
           var selfield = this.selectedFields[this.currentField];
           
           $.each(selfield.ranges,function(i,item){
              if(item.uid  == range_uid){
                    selfield.ranges.splice(i,1);     
                    return false;
              }
           });
       }});
                        
    },
    
    //
    //
    //
    _initSymbolEditor: function(fele){
        
        var f_ranges = this.element.find('#f_ranges');
        fele.attr('readonly','readonly');
        
        var $btn_edit_clear = $('<span>')
        .addClass("smallbutton ui-icon ui-icon-circlesmall-close")
        .attr('tabindex', '-1')
        .attr('title', 'Reset default symbology')
        .appendTo( f_ranges.find(fele.parent()) )
        .css({'line-height': '20px',cursor:'pointer',
            outline: 'none','outline-style':'none', 'box-shadow':'none',  'border-color':'transparent'})
            .on( { click: function(){ window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                function(){fele.val('');}); }});
        
        var $btn_edit_switcher = $( '<span>open editor</span>', {title: 'Open symbology editor'})
            .addClass('smallbutton btn_add_term')
            .css({'line-height': '20px','vertical-align':'top',cursor:'pointer','text-decoration':'underline'})
            .appendTo( fele.parent('div') );
        
        $btn_edit_switcher.on( { click: function(){
                var current_val = window.hWin.HEURIST4.util.isJSON( fele.val() );
                if(!current_val) current_val = {};
                window.hWin.HEURIST4.ui.showEditSymbologyDialog(current_val, 4, function(new_value){
                    fele.val(JSON.stringify(new_value));
                });
        }});
            
    },//_initSymbolEditor
    
    //
    //  toolbar button action
    //
    _onThemeFieldAction: function(event){
        
        if(this.currentField>0){
            
            var that = this;
        
            var key = $(event.target).is('button')
                                ?$(event.target).attr('id')
                                :$(event.target).parent().attr('id');
            
            if(key=='btn_f_remove'){
                window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                function(){

                that.element.find('#selected_fields').find('option[value="'+that.currentField+'"]').remove();                
                that.selectedFields[that.currentField] = null;
                delete that.selectedFields[that.currentField];
                that.currentField = 0;
                that._onThemeFieldSelect();
                
                });
                
            }else if(key=='btn_f_range_add'){
                
                var selfield = this.selectedFields[this.currentField];
                var idx = selfield.ranges.length
                selfield.ranges.push({value:'', symbol:''});
                
                this._defThemeFieldRange(idx, selfield.ranges[idx]);
            
            }else if(key=='btn_f_range_auto'){
                
                //find min/max and unique values show ranges dialog
                
                
            
            }else if(key=='btn_f_range_reset'){
                
                window.hWin.HEURIST4.msg.showMsgDlg('<br>Are you sure?',
                function(){
                    that.selectedFields[this.currentField].ranges = [];
                    that.element.find('#f_ranges').empty();
                });
                
                
            }
            
        }
    },
    
    //
    // Searches for min/max and unique values for given field
    //
    _findFieldValues: function(code){
        
        //this.options.maplayer_query
        
        
    }
    

});

