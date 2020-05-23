/**
* Manipulations with collection from result list (add,remove,clear,list,save as search,perform action)
* It has 
* reference to resultList
* allowed record types for collection
* 
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2019 University of Sydney
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

var Hul = window.hWin.HEURIST4.util;

$.widget( "heurist.resultListCollection", {

    // default options
    options: {
        rectype_set:null,  //array of allowed record types
        resultList: null,  //reference to source result list
        search_realm: null,
        action_Label: 'Create Map',
        action_Function: null,
        
        action_mode: 'map', //or filter
        instructionText: '',
        target_db: ''
    },

    _selection: null,     //current set of selected records (not just ids)
    _collection: null,

    // the widget's constructor
    _create: function() {

        var that = this;

        this.element
        // prevent double click to select text
        .disableSelection();
        
        //padding:4px 2px 4px 14px
        this.labelCollectionInfo = $('<div style="display:inline-block;vertical-align:bottom;min-height:21px;font-weight:bold;padding-right:30px;font-size:13px">')
                .appendTo(this.element);
        
        //create set of buttons
        this.divMainMenuItems = $('<ul>').addClass('horizontalmenu')
                .css({display:'inline-block','font-style':'italic','font-size':'0.8em'})
                .appendTo($('<div style="display:inline-block;vertical-align:bottom">').appendTo(this.element));

        this._initBtn('Add');
        //this._initBtn('Remove');
        this._initBtn('Clear');
        //this._initBtn('List');
        
        if(false && this.options.action_mode=='map'){

            var btn = $('<button>',{
                text: window.hWin.HR(this.options.action_Label), 
            }).button()
            .css({'font-weight':'bold', 'font-style':'italic', 'margin-right':'10px',
                'background':'lightgray'}) //padding:'10px', 'font-size':'1.2em'
            .appendTo( this.element );
            
            this['btn_'+name] = btn;
                
            this._on( this['btn_'+name], {
                    click : this.createMapSpace
                });
        }else{
            this._initBtn('Action');    
            if(this.options.action_mode=='map') this['btn_Action'].find('a').css({'font-weight':'bold'});
        }
        
        this.divMainMenuItems.menu();
        
        
        if(this.options.resultList){ //has the same realm as parent recultList
            this.options.search_realm = this.options.resultList.resultList('option', 'search_realm');
        } 
        
        this.labelInstruction = $('<div>').text(this.options.instructionText)
                            .addClass('heurist-helper2').css({padding:'4px'}).appendTo(this.element); //float:'right',
        //-----------------------     listener of global events
        var sevents = window.hWin.HAPI4.Event.ON_REC_SELECT+' '
                        +window.hWin.HAPI4.Event.ON_REC_COLLECT;

        $(window.hWin.document).on(sevents, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                if(data && data.source!=that.element.attr('id') && that._isSameRealm(data)) {

                    if(data.reset){
                        that._selection = null;
                    }else{
                        that._selection = window.hWin.HAPI4.getSelection(data.selection, false);
                    }
                }
            }else if(e.type == window.hWin.HAPI4.Event.ON_REC_COLLECT){
                
                that.collectionRender( data.collection );
            }
        });
        
        
        // small result list to display collected records
        this.recordList = $('<div>')
                .css({'font-size':'9px',position:'relative',height:'80px'})
                .hide().appendTo(this.element);
        
        this.recordList.resultList( {
                       eventbased: false, //do not listent global events
                       select_mode: 'none',
                       show_toolbar: false,
                       view_mode: 'list',
                       renderer:function( recordset, record ){
                           var recIcon = window.hWin.HAPI4.iconBaseURL +
                              recordset.fld(record, 'rec_RecTypeID') + '.png';
                           var recTitle = recordset.fld(record, 'rec_Title'); 
                           var recTitle_strip2 = window.hWin.HEURIST4.util.stripTags(recTitle);
                           
                           return '<div class="recordDiv collected_perm" style="height:18px;padding:0px 2px;"><div class="recordIcons">'
        +     '<img src="'+window.hWin.HAPI4.baseURL+'hclient/assets/16x16.gif'
        +     '" class="rt-icon" style="background-image: url(&quot;'+recIcon+'&quot;);"/></div>'
        + '<div class="recordTitle" style="left:22px;top:4px">' + recTitle_strip2 + '</div>'
        + '</div>';
                           
                       }
            
        } );
        

        this._refresh();

        //get collection
        window.hWin.HEURIST4.collection.collectionUpdate();
    }, //end _create

    //
    //
    //
    _isSameRealm: function(data){
        return !this.options.search_realm || (data && this.options.search_realm==data.search_realm);
    },

    
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

        if(window.hWin.HAPI4.has_access()){
        }else{
        }
    },
    //
    // custom, widget-specific, cleanup.
    _destroy: function() {

        $(window.hWin.document).off(window.hWin.HAPI4.Event.ON_REC_SELECT);

        this.btn_Add.remove();
        this.btn_Remove.remove();
        this.btn_Clear.remove();
        this.btn_List.remove();
        this.btn_Action.remove();
        this.divMainMenuItems.remove();
        this.labelCollectionInfo.remove();
        this.labelInstruction.remove();
        
        this.recordList.remove();
    },

    _initBtn: function(name){
        
        var label = (name=='Action')?this.options.action_Label:name;

        var link = $('<a>',{
            text: window.hWin.HR(label), href:'#'
        });//IJ 2015-06-26 .css('font-weight','bold');
        
        this['btn_'+name] = $('<li data-action="'+name+'">')
            .css({background: 'lightgray','margin-right':'10px'})
            .append(link)
            .appendTo( this.divMainMenuItems );

        
        this._on( this['btn_'+name], {
                click : this.menuActionHandler
            });
        
    },


    menuActionHandler: function(event){

        var that = this;
        var ele = $(event.target);
        if(!ele.is('li')){
            ele = ele.parents('li');
        }
        
        var action = ele.attr('data-action');

        if(action == "Add"){

            window.hWin.HEURIST4.collection.collectionAdd(null, this._selection);
            this.selectNone();

        }else if(action == "Remove"){

            window.hWin.HEURIST4.collection.collectionDel(null, this._selection);
            this.selectNone();

        }else if(action == "Clear"){

            window.hWin.HEURIST4.collection.collectionClear();

        }else if(action == "List"){

            window.hWin.HEURIST4.collection.collectionShow();

        }else if(action == "Save"){

            window.hWin.HEURIST4.collection.collectionSave();

        }else if(action == "Action"){

            if(this.options.action_mode=='map'){
                
                this.createMapSpace();
            }else{
                window.hWin.HEURIST4.collection.collectionSave();
            }
            
        }

    },
    
    //
    // reset selection
    //    
    selectNone: function(){
        this._selection = null;
        $(this.document).trigger(window.hWin.HAPI4.Event.ON_REC_SELECT, 
            {selection:null, source:this.element.attr('id'), search_realm:this.options.search_realm} );
    },
    

    //-------------------------------------- COLLECTIONS -------------------------------
    //
    //
    //
    createMapSpace: function(){
        
        if(!this.options.target_db){
            
            window.hWin.HEURIST4.msg.showMsgErr('Wrong configuration. Target database for mapspace is not defined');
            
        }else
        if(!Hul.isempty(this._collection)){
            
            //create virtual record set for temporal mapspace
            
            //open
            var url = window.hWin.HAPI4.baseURL 
            +'viewers/map/mapPreview.php?db='+window.hWin.HAPI4.database
            +'&ids='+this._collection.join(",")
            +'&target_db='+this.options.target_db;

            var init_params = {'ids': this._collection.join(","), target_db:this.options.target_db};

            window.hWin.HEURIST4.msg.showDialog(url, {height:'600', width:'1000',
                window: window.hWin,  //opener is top most heurist window
                dialogid: 'map_preview_dialog',
                params: init_params,
                title: window.hWin.HR('Preview mapspace'),
                class:'ui-heurist-bg-light',
                callback: function(location){
                    if( !window.hWin.HEURIST4.util.isempty(location) ){
                        
                    }
                }
            } );
        
        }else{
            window.hWin.HEURIST4.msg.showMsgFlash('Please add records to the collection for map');
        }
    },

    collectionRender: function(_collection) {
        
        this._collection = _collection;

        this.labelCollectionInfo.html( window.hWin.HR('Collected: ') + 
                (_collection && _collection.length>0?_collection.length:'0') + ' datasets');
                
        if(_collection && _collection.length>0){
            this.recordList.resultList('updateResultSet', new hRecordSet(_collection));
            this.recordList.show();
        }else{
            this.recordList.hide();
        }
                
    },
    
    warningOnExit: function( callback_continue ){

        var col = this._collection; //window.hWin.HEURIST4.collection.collectionGet();
        if( col && col.length>0 ){
            
                var that = this, $dlg, buttons = {};
                buttons['Save Map'] = function(){ 
                    that.createMapSpace();
                    $dlg.dialog('close'); 
                }; 
                buttons['Continue'] = function(){ 
                    callback_continue();
                    $dlg.dialog('close'); 
                };
            
            
            $dlg = window.hWin.HEURIST4.msg.showMsgDlg(
                '<h4>Records have been collected</h4>'
                +'<p>Do you want to save these as a map to appear in My Maps?</p>'
                +'<p>(you don\'t have to save a map now, the collection is remembered in any case for later use)</p>',
                buttons,
                {title:'Confirm'});

        }else{
            callback_continue();
        }

    }
    

});
