/**
* Filed type manager - list of field types by groups or by record type structure. Requires utils.js
* 
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
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


$.widget( "heurist.fieldtype_manager", {

    // default options
    options: {
        isdialog: false, //show in dialog or embedded
        list_top: '80px',
        isselector: false, //show in checkboxes to select
        
        selection:[], 

        forRecordTypes: null, //if record type is defined - do not show grouping
        allowedFieldTypes: null, 
        
        callback: null,  //callback function
        
        current_GrpID: null,
        // we take tags from top.HAPI4.currentUser.usr_Tags - array of tags in form [ {ugrp_id:[{tagid:[label, description, usage]}, ....]},...]
        current_order: 1  // order by name
    },
    
    entries: [], 

    // the constructor
    _create: function() {

        var that = this;

        this.wcontainer = $("<div>");

        if(this.options.isdialog){
            
            
    
            var onDialogClose = function(){
                if(this.options.callback){
                     var fields = [];
                     for(var i=0;i<entries.length;i++){
                           if( $.inArray(entries[i][0], this.options.selection) > -1) {
                                //[entryID, name, usage, is_selected, dttype]
                                fields.push({id: entries[i][0], title: entries[i][1], type: entries[i][4] });
                           }
                     }
                     this.options.callback.call(this, fields);
                }
            }
    
            
            

            this.wcontainer
            .css({overflow: 'none !important', width:'100% !important' })
            .appendTo(this.element);

            this.element.css({overflow: 'none !important'})                

            this.element.dialog({
                autoOpen: false,
                height: 620,
                width: 400,
                modal: true,
                title: top.HR(this.options.isselector?"Select Field types":"Manage Field types"),
                resizeStop: function( event, ui ) {
                    that.element.css({overflow: 'none !important','width':'100%'});
                },
                close: onDialogClose, 
                buttons: [
                    {text:top.HR('Select'),
                        click: function() {
                            //that._editSavedSearch();
                    }},
                    {text:top.HR('Close'), click: function() {
                        $( this ).dialog( "close" );
                    }}
                ]
            });

        }else{
            //.addClass('ui-widget-content ui-corner-all').css({'padding':'0.4em',height:'500px'})
            this.wcontainer.appendTo( this.element );
        }

        //---------------------------------------- HEADER
        // group selector
        this.select_grp = $( "<select>", {width:'96%'} )
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo( this.wcontainer );
        this._on( this.select_grp, {
            change: function(event) {
                //load tags for this group
                var val = event.target.value; //ugrID
                if(this.options.current_GrpID==val) return;  //the same

                this.options.current_GrpID = val;
                this._refresh();
            }
        });

        //---------------------------------------- SEARCH
        this.search_div = $( "<div>").css({'display':'inline-block', height:'2.2em', 'padding-top':'4px' }).appendTo( this.wcontainer ); // width:'36%', 

        this.lbl_message = $( "<label>").css({'padding-right':'5px'})
        .html(top.HR('Filter'))
        .appendTo( this.search_div );
        
        this.input_search = $( "<input>" ) //, {width:'100%'}
        .addClass("text ui-widget-content ui-corner-all")
        .appendTo( this.search_div );

        this._on( this.input_search, {
            keyup: function(event) {
                //filter tags
                var tagdivs = $(this.element).find('.recordTitle');
                tagdivs.each(function(i,e){   
                    var s = $(event.target).val().toLowerCase();
                    $(e).parent().css('display', (s=='' || e.innerHTML.toLowerCase().indexOf(s)>=0)?'block':'none');
                });
            }
        });

        //---------------------------------------- ORDER
        this.sort_div = $( "<div>").css({ 'float':'right', height:'2.2em', height:'2.2em', 'padding-top':'4px' }).appendTo( this.wcontainer );

        this.lbl_message = $( "<label>").css({'padding-right':'5px'})
        .html(top.HR('Sort'))
        .appendTo( this.sort_div );

        this.select_order = $( "<select><option value='1'>"+
            top.HR("by name")+"</option><option value='2'>"+
            top.HR("by type")+"</option><option value='4'>"+
            top.HR("selected")+"</option></select>", {'width':'80px'} )

        .addClass("text ui-widget-content ui-corner-all")
        .appendTo( this.sort_div );
        this._on( this.select_order, {
            change: function(event) {
                var val = Number(event.target.value); //order
                this.options.current_order = val;
                this._renderItems();
            }
        });


        //----------------------------------------
        var css1 =  {'overflow-y':'auto','padding':'0.4em','top':this.options.list_top, 'bottom':0,'position':'absolute','left':0,'right':0};
        /*if(this.options.isdialog){
            css1 =  {'overflow-y':'auto','padding':'0.4em','top':'80px','bottom':0,'position':'absolute','left':0,'right':0};
        }else{
            css1 =  {'overflow-y':'auto','padding':'0.4em','top':'80px','bottom':0,'position':'absolute','left':0,'right':0};  
        }*/

        this.div_content = $( "<div>" )
        .addClass('list')
        .css(css1)
        .html('list of field types')
        //.position({my: "left top", at: "left bottom", of: this.div_toolbar })
        .appendTo( this.wcontainer );

        //-----------------------------------------
        if(this.options.forRecordTypes){
            this._refersh();
        }else{
            this._updateGroups();    
        }

    }, //end _create


    _setOptions: function( options ) {
        this._superApply( arguments );
        if(top.HEURIST4.util.isnull(this.options.selection)){
            this.options.selection = [];
        }
        this._refresh();
    },


    /* private function */
    _refresh: function(){
        
        //hide group selector if for record types
        if(this.options.forRecordTypes){
            this.select_grp.hide();    
        }else{
            this.select_grp.show();
        }
        
        this._renderItems();
    },


    /* private function */
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        this.select_grp.remove();

        if(this.div_toolbar){
            this.div_toolbar.remove();
        }

        this.wcontainer.remove();
    },

    //fill selector with groups  
    _updateGroups: function(){

        var selObj = this.select_grp.get(0);
        top.HEURIST4.ui.createRectypeGroupSelect( selObj, top.HR('all groups') );

        this.select_grp.val( top.HEURIST4.detailtypes.groups[0].id);
        this.select_grp.change();

    },

    //
    _renderItems: function(){

        if(this.div_content){
            //var $allrecs = this.div_content.find('.recordDiv');
            //this._off( $allrecs, "click");
            this.div_content.empty();  //clear
        }

        entries = [],
        entryID, name, usage, is_selected;
        
        var idx_dty_type = top.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_Type;
        
        if(this.options.forRecordTypes){ //show that belong to particular record type only
        
            //add default set recTitle and recModified
            entries.push(['title', 'RecTitle', 0, this.options.selection.indexOf('title'), 'header']);
            entries.push(['modified', 'Modified', 0, this.options.selection.indexOf('modified'), 'header']);
            
            var rectypes = this.options.forRecordTypes;

            if(rectypes!='all'){


                if(!top.HEURIST4.util.isArray(rectypes)){
                    rectypes = [rectypes];
                }
                
                var fieldtypes_ids = [], fieldtypes_ids2;

                $.arrayIntersect = function(a, b)
                {
                    return $.grep(a, function(i)
                        {
                            return $.inArray(i, b) > -1;
                    });
                };                

                //find common fields
                for (var i=0; i<rectypes.length; i++){
                    var rtId = rectypes[i];
                    if(top.HEURIST4.rectypes.typedefs[rtId]){
                        fieldtypes_ids2 = [];
                        for (entryID in  top.HEURIST4.rectypes.typedefs[1].dtFields)
                            if(entryID){
                                fieldtypes_ids2.push(entryID);
                        }
                        if(fieldtypes_ids.length>0){
                            fieldtypes_ids = $.arrayIntersect(fieldtypes_ids, fieldtypes_ids2)            
                        }else{
                            fieldtypes_ids = fieldtypes_ids2;
                        }
                    }
                }
                

                //allowedFieldTypes:allowedtypes
                for (var i=0; i<fieldtypes_ids.length; i++){
                        
                        entryID = fieldtypes_ids[i];

                        if(rectypes.length>1){ //take from generallist
                              name = top.HEURIST4.detailtypes.names[entryID];
                              dttype = top.HEURIST4.detailtypes.typedefs[entryID].commonFields[idx_dty_type];
                        }else{
                              var field = top.HEURIST4.rectypes.typedefs[rectypes[0]].dtFields[entryID];
                              name = field[top.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.rst_DisplayName];
                              dttype = field[top.HEURIST4.rectypes.typedefs.dtFieldNamesToIndex.dty_Type];
                        }

                        if( top.HEURIST4.util.isempty(allowedFieldTypes) || $.inArray(dttype, allowedFieldTypes) ){
                            usage = 0; //  top.HEURIST4.detailtypes.rtUsage[entryID];
                            is_selected =  this.options.selection.indexOf(entryID);
                            entries.push([entryID, name, usage, is_selected, dttype]);
                        }
                                
                }

            }
            
        }else{    //show by groups
            
            var idx_dty_grpid = top.HEURIST4.detailtypes.typedefs.fieldNamesToIndex.dty_DetailTypeGroupID;
            for (entryID in  top.HEURIST4.detailtypes.names)
            {
                if( entryID && (this.options.current_GrpID==0 || this.options.current_GrpID==top.HEURIST4.detailtypes.typedefs[entryID].commonFields[idx_dty_grpid]) ){

                    name = top.HEURIST4.detailtypes.names[entryID];
                    usage = 0; //  top.HEURIST4.detailtypes.rtUsage[entryID];
                    is_selected =  this.options.selection.indexOf(entryID);
                    dttype = top.HEURIST4.detailtypes.typedefs[entryID].commonFields[idx_dty_type];

                    entries.push([entryID, name, usage, is_selected, dttype]);
                }
            }           
        }

        var val = this.options.current_order;
        entries.sort(function (a,b){
            if(val==1){
                return a[val].toLowerCase()>b[val].toLowerCase()?1:-1;
            }else{
                return a[val]<b[val]?1:-1;
            }
        });               

        var that = this;
        var filter_name = this.input_search.val().toLowerCase();
        var i;
        for(i=0; i<entries.length; ++i) {

            var rt = entries[i];
            entryID = rt[0];
            name = rt[1];
            usage = rt[2];
            is_selected = rt[3];
            dtype = rt[4];

            $itemdiv = $(document.createElement('div'));

            $itemdiv
            .addClass('recordDiv')
            .attr('id', 'rt-'+entryID )
            .css('display', (filter_name=='' || name.toLowerCase().indexOf(filter_name)>=0)?'block':'none')
            .appendTo(this.div_content);


            if(this.options.isselector){  //checkbox
                $('<input>')
                .attr('type','checkbox')
                .attr('rtID', entryID )
                .attr('checked', (is_selected>=0) )  //?false:true ))
                .addClass('recordIcons')
                .css('margin','0.4em')
                .click(function(event){
                    var entryID = $(this).attr('rtID');
                    var idx = that.options.selection.indexOf(entryID);
                    if(event.target.checked){
                        if(idx<0){
                            that.options.selection.push(entryID);
                        }
                    }else if(idx>=0){
                        that.options.selection.splice(idx,1);
                    }
                })
                .appendTo($itemdiv);
            }

            /* no icon for field type
            $iconsdiv = $(document.createElement('div'))
            .addClass('recordIcons')
            .appendTo($itemdiv);

            //record type icon
            $('<img>',{
                src:  top.HAPI4.basePathV4+'hclient/assets/16x16.gif'
            })
            .css('background-image', 'url('+ top.HAPI4.iconBaseURL + entryID + '.png)')
            .appendTo($iconsdiv);
            */

            $('<div>',{
                title: name
            })
            .css('display','inline-block')
            .addClass('recordTitle')
            .css({top:0,'margin':'0.4em', 'height':'1.4em', 'left':'70px'})
            .html( name  )
            .appendTo($itemdiv);

            //field type
            $('<div>')
            .css({'margin':'0.4em', 'height':'1.4em', 'position':'absolute','right':'60px'})
            .css('display','inline-block')
            .html( dtype )
            .appendTo($itemdiv);
            
            //usage 
            /*$('<div>')
            .css({'margin':'0.4em', 'height':'1.4em', 'position':'absolute','right':'60px'})
            .css('display','inline-block')
            .html( usage )
            .appendTo($itemdiv);*/
        }           
    },


    show: function(){
        if(this.options.isdialog){
            this.element.dialog("open");
        }else{
            //fill selected value this.element
        }
    }

});

function showManageFieldTypes( recordtypes, fields, allowedtypes, callback ){

    var manage_dlg = $('#heurist-fieldtype-dialog');
    

    if(manage_dlg.length<1){

        manage_dlg = $('<div id="heurist-fieldtype-dialog">')
        .appendTo( $('body') )
        .fieldtype_manager({ isdialog:true, forRecordTypes:recordtypes, allowedFieldTypes:allowedtypes, isselector:true, selection:selection, callback:callback });
    }else{
        manage_dlg.fieldtype_manager('option', { forRecordTypes:recordtypes, allowedFieldTypes:allowedtypes, isselector:true, selection:selection, callback:callback });
    }

    manage_dlg.fieldtype_manager( "show" );
}
