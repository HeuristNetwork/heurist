/**
* recordFindDuplicates.js - Find duplicates by record type and selected fields
*                           It uses levenshtein function on server side
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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
"caption":"name of thematic map",
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
        
        path: 'widgets/entity/popups/', //location of this widget
        
        htmlContent: 'thematicMapping.html',
        helpContent: 'thematicMapping.html' //in context_help folder
    },
    

    selectedFields:null,
    
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
    },
            
    //
    // Save thematic mappig
    //
    doAction: function(){

            var rty_ID = this.selectRecordScope.val();

            if(rty_ID>0){


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
                $('.rtt-tree').parent().show();
            }else{
                $('.rtt-tree').parent().hide();
            }
            this.selectedFields = [];
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

            var main_area = this.element.find('#div_work_area').empty();
            $('<select id="selected_fields" size="5" style="min-width:400px">').appendTo(main_area);
            
            var allowed_fieldtypes = ['rec_Title','rec_ID',
                'enum','freetext','year','date','integer','float','resource'];
            
            //generate treedata from rectype structure
            var treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, allowed_fieldtypes );
            
        //load treeview
        var treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }

        treediv.fancytree({
            //extensions: ["filter"],
            //            extensions: ["select"],
            checkbox: true,
            selectMode: 3,  // hierarchical multi-selection
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
                }else if(false){

                    if(data.node.parent && data.node.parent.data.type == 'enum'){ // make term options inline and smaller
                        $(data.node.li).css('display', 'inline-block');
                        $(data.node.span.childNodes[0]).css('display', 'none');

                        if(data.node.key == 'term'){
                            $(data.node.parent.ul).css({'transform': 'scale(0.8)', 'padding': '0px', 'position': 'relative', 'left': '-12px'});
                        }
                    }
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
                that._fillSortField();
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


    _fillSortField: function(){
        
                    var tree = this.element.find('.rtt-tree').fancytree("getTree");
                    var fieldIds = tree.getSelectedNodes(false);
                    var k, len = fieldIds.length;
                    
                    var sel = this.element.find('#selected_fields');
                    var keep_val = sel.val();
                    sel.empty();
                    
                    for (k=0;k<len;k++){
                        var node =  fieldIds[k];
                        if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;
                        

                        var key = node.key.split(':');
                        key = key[key.length-1];
                        window.hWin.HEURIST4.ui.addoption(sel[0], key, node.data.name+' ('+node.data.code+')');
                        
                    }
                    sel.val(keep_val);
                    if(!(sel[0].selectedIndex>0)) sel[0].selectedIndex = 0;

    }
                        


});

