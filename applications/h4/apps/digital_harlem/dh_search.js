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
    
    _searches:[
/*      [
    {"var":"1","id":"1","rtid":"4",  "code":"4:1","title":"Name of organisation","type":"freetext","levels":[],"search":[]},
    {"var":"2","id":"22","rtid":"4", "code":"4:22","title":"Organisation type","type":"enum","levels":[],"search":[]},
    {"var":"3","id":"1","rtid":"10", "code":"4:16:10:1","title":"Family name","type":"freetext","levels":[],"search":[]},
    {"var":"4","id":"19","rtid":"10","code":"4:16:10:19","title":"Honorific","type":"enum","levels":[],"search":[]},
    {"qa":[{"t":4},{"f:1":"X0"},{"f:22":"X1"}, {"linked_to:16":[{"t":"10"},{"f:1":"X2"},{"f:19":"X3"}  ] } ] }
      ], */
      [
    {"var":"1","id":"74","rtid":"14", "code":"14:74","title":"Type of Event","type":"enum","levels":[],"search":[]},
    
    {"var":"2","id":"80","rtid":"15", "code":"14:100:15:80","title":"Charge / Conviction","type":"enum","levels":[],"search":[]},
    
    {"var":"3","id":"97","rtid":"10","code":"14:109:10:97","title":"Birthplace of Participant(s)","type":"enum","levels":[],"search":[]},
    {"var":"4","id":"92","rtid":"10","code":"14:109:10:92","title":"Occupation","type":"enum","levels":[],"search":[]},
    {"var":"5","id":"98","rtid":"10","code":"14:109:10:98","title":"Race","type":"enum","levels":[],"search":[]},
    {"var":"6","id":"20","rtid":"10","code":"14:109:10:20","title":"Gender","type":"enum","levels":[],"search":[]},
    {"var":"7","id":"1","rtid":"10", "code":"14:109:10:1","title":"Surname","type":"freetext","levels":[],"search":[]},

    {"var":"8","id":"1","rtid":"11", "code":"14:99:12:73:11:1","title":"Street Name","type":"freetext","levels":[],"search":[]},
    {"var":"9","id":"89","rtid":"16","code":"14:99:12:90:16:89","title":"Location type","type":"enum","levels":[],"search":[]},
    
    
    {"qa":[{"t":14},{"f:74":"X0"}, 
              {"related_to:100":[{"t":"15"},{"f:80":"X1"}]},  
              {"related_to:109":[{"t":"10"},{"f:97":"X2"},{"f:92":"X3"},{"f:98":"X4"},{"f:20":"X5"},{"f:1":"X6"}]},
              
              {"related_to:99":[{"t":"12"},{"linked_to:73":[{"t":"11"},{"f:1":"X7"}]}, {"linkedfrom:90":[{"t":"16"},{"f:89":"X8"}]}  ]}
           ]}
      ], 
      [
    {"var":"1","id":"18","rtid":"10", "code":"10:18","title":"First Name","type":"freetext","levels":[],"search":[]},
    {"var":"2","id":"96","rtid":"10", "code":"10:96","title":"Second Name(s)","type":"freetext","levels":[],"search":[]},
    {"var":"3","id":"1","rtid":"10", "code":"10:1","title":"Surname","type":"freetext","levels":[],"search":[]},

    {"var":"4","id":"20","rtid":"10","code":"10:20","title":"Gender","type":"enum","levels":[],"search":[]},
    {"var":"5","id":"97","rtid":"10","code":"10:97","title":"Birthplace of Participant(s)","type":"enum","levels":[],"search":[]},
    {"var":"6","id":"92","rtid":"10","code":"10:92","title":"Occupation","type":"enum","levels":[],"search":[]},
    {"var":"7","id":"98","rtid":"10","code":"10:98","title":"Race","type":"enum","levels":[],"search":[]},
    
    {"qa":[{"t":10},{"f:18":"X0"},{"f:96":"X1"},{"f:1":"X2"},{"f:20":"X3"},{"f:97":"X4"},{"f:92":"X5"},{"f:98":"X6"}  ]}
      ],
      []
    ],

    // the widget's constructor
    _create: function() {

        var that = this;

        // prevent double click to select text
        //this.element.disableSelection();

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);
        
        this.tab_control = $(document.createElement('div'))
            .css({position:'absolute', top:'0.2em', bottom:'3.6em', width:'100%'})
            .appendTo(this.element);
        this.tab_header  = $(document.createElement('ul')).appendTo(this.tab_control);
        
        //add tabs
        //each tab contain search form
        this._addSearchForm(0, 'Events');
        this._addSearchForm(1, 'Persons');
        this._addSearchForm(2, 'Addresses');
        
        this.tab_control.tabs();   
        


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
    },
    
    
    _addSearchForm: function( content_id, name ) {
       
       //var $d = $(document.createElement('div')).appendTo(this.element);
       this.tab_header.append('<li><a href="#dh_search_'+content_id+'">'+ top.HR(name) +'</a></li>');
       
       //create form
       var $container = $('<div id="dh_search_'+content_id+'">');
       this.tab_control.append($container);
       
       var $fieldset = $("<fieldset>").css({'font-size':'0.9em','background-color':'white'}).addClass('fieldset_search').appendTo($container);
       
       var query_orig = null, _inputs={};
       
       $.each(this._searches[content_id], function(idx, field){
       
           if(field['var']){
                var inpt = $("<div>").editing_input(
                    {
                        varid: 'X'+idx,
                        recID: -1,
                        rectypeID: field['rtid'],
                        dtID: field['id'],
                        rectypes: top.HEURIST4.rectypes,
                        values: '',
                        readonly: false,
                        title: field['title']
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
        .appendTo($container);

        var btn_submit = $('<button>', {text:top.HR('Submit')})
        .button()  //.on("click", function(event){ this._save(); } )
        .appendTo(btn_div);

//    {"qa":[{"t":4},{"f:1":"X1"},{"f:22":"X2"}, {"linked_to:16":[{"t":"10"},{"f:1":"X3"},{"f:19":"X4"}  ] } ] }
        
        
        this._on( btn_submit, {
                    click: function(){
                        
                        function __fillQuery(q){
                            $(q).each(function(idx, predicate){
                                
                                $.each(predicate, function(key,val)
                                {
                                    if( $.isArray(val) ){
                                         __fillQuery(val);
                                    }else{
                                        if(typeof val === 'string' && val.indexOf('X')===0){
                                            var vals = $(_inputs[val]).editing_input('getValues');
                                            if(vals && vals.length>0 && !top.HEURIST4.util.isempty(vals[0])){
                                                predicate[key] = vals[0];
                                            }else{
                                                predicate[key] = '';
                                            }
                                        }
                                    }
                                });                            
                                
                            });
                        }
                          
                        var query = JSON.parse(JSON.stringify(query_orig)); 
                        __fillQuery(query);
                        
                        
                        var request = {qa: query, w: 'a', f: 'map', source:this.element.attr('id') };

                        top.HAPI4.currentRecordset = null;
                        //perform search
                        top.HAPI4.RecordMgr.search(request, $(this.document));                        
                        
                       //this._doSearch();     
                        
                    }});
        
        $('<button>', {text:top.HR('Reset')})
        .button().on("click", function(event){  } )
        .appendTo(btn_div);
               

                //
       
    },
    
    _doSearch: function(){
        
    }

});
