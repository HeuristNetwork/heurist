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
        instructionText: 'Please search for the map document',
        target_db: ''
    },

    _selection: null,     //current set of selected records (not just ids)
    _collection:null,
    _collectionURL:null,

    // the widget's constructor
    _create: function() {

        var that = this;

        this._collectionURL = window.hWin.HAPI4.baseURL + "hsapi/utilities/manageCollection.php";

        this.element
        .css('font-size', '1.3em')
        // prevent double click to select text
        .disableSelection();
        
        this.labelCollectionInfo = $('<div style="display:inline-block;padding:4px 2px 4px 14px">').appendTo(this.element);
        this.labelInstruction = $('<div>').text(this.options.instructionText)
                            .addClass('heurist-helper2').css({float:'right',padding:'4px'}).appendTo(this.element);
        
        //create set of buttons
        this.divMainMenuItems = $('<ul>').addClass('horizontalmenu').appendTo(this.element);

        this._initBtn('Add');
        this._initBtn('Remove');
        this._initBtn('Clear');
        this._initBtn('List');
        this._initBtn('Action');
        this.divMainMenuItems.menu();
        
        if(this.options.resultList){ //has the same realm as parent recultList
            this.options.search_realm = this.options.resultList.resultList('option', 'search_realm');
        } 
        
        //-----------------------     listener of global events
        var sevents = window.hWin.HAPI4.Event.ON_REC_SELECT; //+'  competency'

        $(window.hWin.document).on(sevents, function(e, data) {

            if(e.type == window.hWin.HAPI4.Event.ON_REC_SELECT){

                if(data && data.source!=that.element.attr('id') && that._isSameRealm(data)) {

                    if(data.reset){
                        that._selection = null;
                    }else{
                        that._selection = window.hWin.HAPI4.getSelection(data.selection, false);
                    }
                }
            }
        });

        this._refresh();

        this.collectionRender();
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
        
    },

    _initBtn: function(name){
        
        var label = (name=='Action')?this.options.action_Label:name;

        var link = $('<a>',{
            text: window.hWin.HR(label), href:'#'
        });//IJ 2015-06-26 .css('font-weight','bold');
        
        this['btn_'+name] = $('<li data-action="'+name+'">').append(link)
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

            this.collectionAdd();

        }else if(action == "Remove"){

            this.collectionDel();


        }else if(action == "Clear"){

            this.collectionClear();

        }else if(action == "List"){

            this.collectionShow();

        }else if(action == "Save"){

            this.collectionSave(); //save collection as saved filter

        }else if(action == "Action"){

            //this.collectionSave();
        }

    },
    
    //
    //
    //
    getSelectionIds: function(msg, limit){

        var recIDs_list = [];
        if (this._selection!=null) {
            recIDs_list = this._selection.getIds();
        }

        if (recIDs_list.length == 0 && !Hul.isempty(msg)) {
            window.hWin.HEURIST4.msg.showMsgDlg(msg);
            return null;
        }else if (limit>0 && recIDs_list.length > limit) {
            window.hWin.HEURIST4.msg.showMsgDlg("The number of selected records is above the limit in "+limit);
        }else{
            return recIDs_list;
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

    collectionAdd: function(){

        var recIDs_list = this.getSelectionIds("Please select at least one record to add to collection basket");
        if(Hul.isempty(recIDs_list)) return;

        var recIDs = recIDs_list.join(',')
        
        /*
        var url = window.hWin.HAPI4.baseURL + "?db=" + window.hWin.HAPI4.database + "&q=ids:"+recIDs;    
        if(url.length>10000){
                    window.hWin.HEURIST4.msg.showMsgDlg(
'Collections are saved as a list of record IDs. The URL generated by this collection would exceed the maximum allowable URL length by of' 
+'2083 characters. Please save the current collection as a saved search (which allows a much larger number of records), or add fewer records.', null, window.hWin.HR('Warning'));
        }
        */
        
        var params = {db:window.hWin.HAPI4.database, fetch:1, add:recIDs};

        Hul.sendRequest(this._collectionURL, params, this, this.collectionOnUpdate);

        this.selectNone();
    },

    collectionDel: function(){

        var recIDs_list = this.getSelectionIds("Please select at least one record to remove from collection basket");
        if(Hul.isempty(recIDs_list)) return;

        var params = {db:window.hWin.HAPI4.database, fetch:1, remove:recIDs_list.join(",")};

        Hul.sendRequest(this._collectionURL, params, this, this.collectionOnUpdate);

        this.selectNone();
    },

    collectionClear: function(){

        var params = {db:window.hWin.HAPI4.database, clear:1};

        Hul.sendRequest(this._collectionURL, params, this, this.collectionOnUpdate);
    },

    collectionShow: function(){

        if(!Hul.isempty(this._collection)){

                var url = window.hWin.HAPI4.baseURL + "?db=" + window.hWin.HAPI4.database + "&q=ids:"+this._collection.join(',');
            
                if(url.length>2083){
                    window.hWin.HEURIST4.msg.showMsgDlg(
'Collections are saved as a list of record IDs. The URL generated by this collection would exceed the maximum allowable URL length by of' 
+'2083 characters. Please save the current collection as a saved search (which allows a much larger number of records), or add fewer records.', null, window.hWin.HR('Warning'));
                    
                }else{
                    window.open(url, "_blank");    
                }    
                
        }

    },

    collectionSave: function(){

        if(!Hul.isempty(this._collection)){

            var  app = window.hWin.HAPI4.LayoutMgr.appGetWidgetByName('svs_list');  //window.hWin.HAPI4.LayoutMgr.appGetWidgetById('ha13');
            if(app && app.widget){
                //call method editSavedSearch - save collection as search

                // mode, groupID, svsID, squery
                $(app.widget).svs_list('editSavedSearch', 'saved', null, null, 'ids:'+this._collection.join(","));
            }
        }

    },
    
    collectionOnUpdate: function(that, results) {
        if(!Hul.isnull(results)){
            if(results.status == window.hWin.ResponseStatus.UNKNOWN_ERROR){
                window.hWin.HEURIST4.msg.showMsgErr(results);
            }else{
                that._collection = Hul.isempty(results.ids)?[]:results.ids;
                that.collectionRender();
            }
        }
    },

    collectionRender: function() {
        if(Hul.isnull(this._collection))
        {
            var params = {db:window.hWin.HAPI4.database, fetch:1};
            Hul.sendRequest(this._collectionURL, params, this, this.collectionOnUpdate);
        }else{
            //window.hWin.HR('Collected')
            this.labelCollectionInfo.html( window.hWin.HR('Collection') + (this._collection.length>0?':'+this._collection.length:''));
        }
    }



});
