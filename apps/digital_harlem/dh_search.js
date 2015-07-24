/**
* Template to define new widget
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2014 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*  
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/


$.widget( "heurist.dh_search", {                                                                             

    // default options
    options: {
        // callbacks
    },
    
    _resultset:null,
    
    _currentquery: null,
    
    _currenttype: -1,
    
    _searches:[
/*      [
    {"var":"1","id":"1","rtid":"4",  "code":"4:1","title":"Name of organisation","type":"freetext","levels":[],"search":[]},
    {"var":"2","id":"22","rtid":"4", "code":"4:22","title":"Organisation type","type":"enum","levels":[],"search":[]},
    {"var":"3","id":"1","rtid":"10", "code":"4:16:10:1","title":"Family name","type":"freetext","levels":[],"search":[]},
    {"var":"4","id":"19","rtid":"10","code":"4:16:10:19","title":"Honorific","type":"enum","levels":[],"search":[]},
    {"qa":[{"t":4},{"f:1":"X0"},{"f:22":"X1"}, {"linked_to:16":[{"t":"10"},{"f:1":"X2"},{"f:19":"X3"}  ] } ] }
      ], */
      [   //EVENTS
    {"var":"1","id":"74","rtid":"14", "code":"14:74","title":"Type of Event","type":"enum","levels":[],"search":[]},
    
    {"var":"2","id":"80","rtid":"15", "code":"14:rt100:15:80","title":"Charge / Conviction","type":"enum","levels":[],"search":[]},
    
    {"var":"3","id":"97","rtid":"10","code":"14:rf109:10:97","title":"Birthplace of Participant(s)","type":"enum","levels":[],"search":[]},
    {"var":"4","id":"92","rtid":"10","code":"14:rf109:10:92","title":"Occupation","type":"enum","levels":[],"search":[]},
    {"var":"5","id":"98","rtid":"10","code":"14:rf109:10:98","title":"Race","type":"enum","levels":[],"search":[]},
    {"var":"6","id":"20","rtid":"10","code":"14:rf109:10:20","title":"Gender","type":"enum","levels":[],"search":[]},
    {"var":"7","id":"18","rtid":"10","code":"14:rf109:10:18","title":"Surname","type":"freetext","isfacet":true,"levels":[],"search":[]},

    {"var":"8","id":"1","rtid":"11", "code":"14:rt99:12:lt73:11:1","title":"Street Name","type":"freetext","levels":[],"search":[]},
    {"var":"9","id":"89","rtid":"16","code":"14:rt99:12:lf90:16:89","title":"Location type","type":"enum","isfacet":true,"levels":[],"search":[]},
    
    
    {"qa":[{"t":14},{"f:74":"X0"}, 
              {"related_to:100":[{"t":"15"},{"f:80":"X1"}]},   //charge/conviction
              {"relatedfrom:109":[{"t":"10"},{"f:97":"X2"},{"f:92":"X3"},{"f:98":"X4"},{"f:20":"X5"},{"f:18":"X6"}]},   //persons involved
              
              {"related_to:99":[{"t":"12"}, {"linked_to:73":[{"t":"11"},{"f:1":"X7"}]}, {"linkedfrom:90":[{"t":"16"},{"f:89":"X8"}]}  ]}  //address->street(11)  and linkedfrom role(16) role of place
           ]}
      ], 
      [   //PEOPLE
    {"var":"1","id":"1","rtid":"10",  "code":"10:1","title":"First Name","type":"freetext","levels":[],"search":[]},
    {"var":"2","id":"96","rtid":"10", "code":"10:96","title":"Second Name(s)","type":"freetext","levels":[],"search":[]},
    {"var":"3","id":"18","rtid":"10", "code":"10:18","title":"Surname","type":"freetext","levels":[],"search":[]},

    {"var":"4","id":"20","rtid":"10","code":"10:20","title":"Gender","type":"enum","levels":[],"search":[]},
    {"var":"5","id":"97","rtid":"10","code":"10:97","title":"Birthplace of Participant(s)","type":"enum","levels":[],"search":[]},
    {"var":"6","id":"92","rtid":"10","code":"10:92","title":"Occupation","type":"enum","levels":[],"search":[]},
    {"var":"7","id":"98","rtid":"10","code":"10:98","title":"Race","type":"enum","levels":[],"search":[]},
    
    {"qa":[{"t":10},{"f:1":"X0"},{"f:96":"X1"},{"f:18":"X2"},{"f:20":"X3"},{"f:97":"X4"},{"f:92":"X5"},{"f:98":"X6"}  ]}
      ],
      [    //ADDRESS
    {"var":"1","id":"1","rtid":"12","code":"12:1","title":"Street Number","type":"freetext","levels":[],"search":[]},
    {"var":"2","id":"1","rtid":"11", "code":"12:lt73:11:1","title":"Street Name","type":"freetext","levels":[],"search":[]},
    {"var":"3","id":"1","rtid":"16","code":"12:lf90:16:1","title":"Location Name","type":"freetext","levels":[],"search":[]},
    {"var":"4","id":"3","rtid":"12","code":"12:3","title":"Keywords in Comments","type":"freetext","levels":[],"search":[]},
    {"var":"5","id":"89","rtid":"16","code":"12:lf90:16:89","title":"Location type","type":"enum","levels":[],"search":[]},

    {"qa":[{"t":12},{"f:1":"X0"},
            {"linked_to:73":[{"t":"11"},{"f:1":"X1"}]},
            {"linkedfrom:90":[{"t":"16"},{"f:1":"X2"},{"f:89":"X4"} ]},
            {"f:3":"X3"}]}
      ]
    ],
    
    //CHURCHES    {"t":"12"},{"linkedfrom:90":[{"t":"16"},{"f:89":"4042"}]} 
    
   
/*
   1. go trough all fields with isfacet=true
   2. use code parameter to create reverse query to search facet values
   
samples of faceted queries for some variables  

use code parameter for variable to build invert query    

1) for zero level just select f:74 from the same query

2) for 1st level  a) find relation type b) invert it

code 12:lf90:16:89

select f:89 where  {qa:[ {"t":"16"}, {"linked_to:90":[{ids:....   .... }]} ] }

3) two levels

"code":"14:rt99:12:lf90:16:89"           

select f:89 where {qa:[ {"t":"16"}, {"linked_to:90":[{"t":"12"}, {"relatedfrom:99":[ {ids:....   .... }]} ] }

*/      
   _createFacetQueries: function(content_id){
       
       
       $.each(this._searches[content_id], function(index, field){
       
           if(field['var'] && field['code'] && field['isfacet']){
               //create new query and add new parameter
               var code = field['code'];
               code = code.split(':');
               
               function __crt( idx ){
                   var qp = null;
                   if(idx>0){  //this relation or link
                       
                        var pref = '';
                        qp = {};
                        
                        qp['t'] = code[idx];
                        var fld = code[idx-1]; //link field
                        if(fld.indexOf('lf')==0){
                            pref = 'linked_to';    
                        }else if(fld.indexOf('lt')==0){
                            pref = 'linkedfrom';    
                        }else if(fld.indexOf('rf')==0){
                            pref = 'related_to';    
                        }else if(fld.indexOf('rt')==0){
                            pref = 'relatedfrom';    
                        }
                        qp[pref+':'+fld.substr(2)] = __crt(idx-2);    
                   }else{ //this is simple field
                       qp = {'ids':'XYZ'};
                   }
                   return qp;
               }
               
               field['facet'] = __crt( code.length-2 );
               
           }
       });
       
   },
    

    // the widget's constructor
    _create: function() {

        var that = this;

        // prevent double click to select text
        //this.element.disableSelection();

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);
        
        this.tab_content = $(document.createElement('div')).css({width:'100%',height:'100%','overflow-y':'auto'}).appendTo(this.element);
        
        this.tab_control = $(document.createElement('div'))
            .css({width:'100%'})  //position:'absolute', top:'0.2em', bottom:'3.6em', 
            .appendTo(this.tab_content);
        this.tab_header  = $(document.createElement('ul')).appendTo(this.tab_control);
        
        //add tabs
        //each tab contain search form
        this._addSearchForm(0, 'Events');
        this._addSearchForm(1, 'Persons');
        this._addSearchForm(2, 'Addresses');
        
        this.tab_control.tabs();   
        
        this.res_div = $('<div>')
        .css('padding','1em')
        //.css({position:'absolute', height:'1.2em', bottom:'2.4em'})
        .appendTo(this.tab_content).hide();
        
        this.res_lbl = $('<label>').css('padding','0.4em').appendTo(this.res_div);
        
        this.res_name = $('<input>').css('padding','0.4em').appendTo(this.res_div);
               
        this.res_btn = $('<button>', {text:top.HR('Map')+' >'})
        .button().on("click", function(event){ that._onAddLayer(); } )
        .appendTo(this.res_div);

        this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {
    },

    //Called whenever the option() method is called
    //Overriding this is useful if you can defer processor-intensive changes for multiple option change
    _setOptions: function( ) {
        this._superApply( arguments );
    },

    /* 
    * private function 
    * show/hide buttons depends on current login status
    */
    _refresh: function(){

    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        this.tab_header.remove();
        this.tab_control.remove();
        this.res_div.control.remove();
        this.tab_content.remove();  
        
        //$(this.document).off(this._events);
        
    },
    
    
    _addSearchForm: function( content_id, name ) {
        
       this._createFacetQueries( content_id );
       
       //var $d = $(document.createElement('div')).appendTo(this.element);
       this.tab_header.append('<li><a href="#dh_search_'+content_id+'">'+ top.HR(name) +'</a></li>');
       
       //create form
       var $container = $('<div id="dh_search_'+content_id+'">');
       this.tab_control.append($container);
       
       var $fieldset = $("<fieldset>").css({'font-size':'0.9em','background-color':'white'}).addClass('fieldset_search').appendTo($container);
       
       var query_orig = null, _inputs={};
       
       $.each(this._searches[content_id], function(idx, field){
       
           if(field['var']){
                var inpt = $("<div>").editing_input(   //this is our widget for edit given fieldtype value
                    {
                        varid: 'X'+idx,
                        recID: -1,
                        rectypeID: field['rtid'],
                        dtID: field['id'],
                        rectypes: top.HEURIST4.rectypes,
                        values: '',
                        readonly: false,
                        title: field['title'],
                        showclear_button: false,
                        detailtype: field['type']  //overwrite detail type from db (for example freetext instead of memo)
                });

                inpt.appendTo($fieldset);
                _inputs['X'+idx] = inpt;
                
           }else if(field['qa']){
                query_orig = field['qa'];
           }
       });
       
        // control buttons - save and cancel         
        var btn_div = $('<div>')
        //.css({position:'absolute', height:'1.2em', bottom:'2.4em'})
        .css({'width':'212px', 'text-align':'right'})
        .appendTo($container);

        var btn_submit = $('<button>', {text:top.HR('Submit')})
        .button()  //.on("click", function(event){ this._save(); } )
        .appendTo(btn_div);

//    {"qa":[{"t":4},{"f:1":"X1"},{"f:22":"X2"}, {"linked_to:16":[{"t":"10"},{"f:1":"X3"},{"f:19":"X4"}  ] } ] }
        
        
        this._on( btn_submit, {
                    click: function(){
                        
                        var isform_empty = true;
                        
                        function __fillQuery(q){
                            $(q).each(function(idx, predicate){
                                
                                $.each(predicate, function(key,val)
                                {
                                    if( $.isArray(val) ){
                                         __fillQuery(val);
                                    }else{
                                        if(typeof val === 'string' && val.indexOf('X')===0){
                                            
                                            //find input widget and get its value
                                            var vals = $(_inputs[val]).editing_input('getValues');
                                            if(vals && vals.length>0 && !top.HEURIST4.util.isempty(vals[0])){
                                                predicate[key] = vals[0];
                                                isform_empty = false;
                                            }else{
                                                predicate[key] = '';
                                            }
                                        }
                                    }
                                });                            
                                
                                //@todo - remove entire branch if none of variables are defined
                                
                                
                            });
                        }

                        //add to request faceted queries
                        /*
                        var facets = [];
                        $.each(this._searches[content_id], function(index, field){
                            if(field['isfacet'] && field['code']){
                                //create new query and add new parameter
                                facets.push(['code']);
                            }
                        });
                        */

                          
                        var query = JSON.parse(JSON.stringify(query_orig)); //clone 
                        __fillQuery(query);
                        
                        if(isform_empty){
                            top.HEURIST4.util.showMsgErr('Define at least one search criterion');
                            return;
                        }
                        
                        var that = this;
                        var request = {qa: query, w: 'a', f: 'map', l:3000, source:this.element.attr('id'), facets: facets };
                        
                        //perform search
                        top.HAPI4.RecordMgr.search(request, 
                                function(response) {

                                        that.loadanimation(false);
                                    
                                        if(response.status == top.HAPI4.ResponseStatus.OK){
                                            that._currentquery = query;
                                            that._currenttype  = content_id;
                                            that._resultset = new hRecordSet(response.data);
                                        }else{
                                            top.HEURIST4.util.showMsgErr(response.message);
                                            return;
                                        }
                                    
                                        that.res_div.show();
                                            
                                        if(that._resultset.count_total()>0){
                                            that.res_lbl.html('Found '+that._resultset.count_total()+' matches....<br>Provide a layer name<br>');
                                            that.res_name.val('');
                                            that.res_name.show();
                                            that.res_btn.show();
                                            
                                            that._recalculateFacets(content_id);
                                            
                                        }else{
                                            that.res_lbl.html('Found no matches....');
                                            that.res_name.hide();
                                            that.res_btn.hide();
                                        }
                                }
                        );     
                        
                        this.res_div.hide();
                        this._resultset = null;
                        this._currentquery = null;
                        this.loadanimation(true);
                                           
                        
                    }});
        
        $('<button>', {text:top.HR('Reset')})
        .button().on("click", function(event){  } )
        .appendTo(btn_div);
        
                //
       
    },
    
    // perform search for facet values and redraw facet fields
    // query - current query - if resultset > 1000, use query
    _recalculateFacets: function(content_id, field_index){
        
        return;
        // this._currentquery
        // this._resultset
        if(!field_index) field_index = 0;

        var i=0;
        for(;i<this._searches[content_id].length;i++){
            var field = this._searches[content_id][i];
            if(i>=field_index && field['isfacet'] && field['facet']){
                //start search
                
                if(this._resultset && this._resultset.count_total()>1000){
        
                }
                

               _recalculateFacets(content_id, i+1);                 
               break;
            }
        }
        
    },
    
    _onSearchResult: function(){
        
    },

    loadanimation: function(show){
        if(show){
            //this.tab_control.hide();
            this.res_div.css('background','url('+top.HAPI4.basePath+'assets/loading-animation-white.gif) no-repeat center center');
        }else{
            this.res_div.css('background','none');
            //this.tab_control.show();
        }
    },
    
    // add kayer to current map document
    _onAddLayer: function(){
       
        if(top.HEURIST4.util.isempty(this.res_name.val() )){
           top.HEURIST4.util.showMsgErr('Define name of layer');
           return;
        }
        
        var rules = ''; 
        
        if(this._currenttype==1){
            //find person->events->addresses and person->addresses
            rules = [{"query":"t:14 relatedfrom:10","levels":[{"query":"t:12 relatedfrom:14"}]},{"query":"t:12 relatedfrom:10"}];
        }else if(this._currenttype==0){ //events->addresses
            rules = [{"query":"t:12 relatedfrom:14"}];
        }
        
        var params = {id:"dhs"+Math.floor((Math.random() * 10000) + 1), title:this.res_name.val(), query: {qa:this._currentquery, rules:rules} };
        
        var app = appGetWidgetByName('app_timemap');  //appGetWidgetById('ha51'); 
        if(app && app.widget){
            $(app.widget).app_timemap('addQueryLayer', params);
        }
      
    }
    
});
