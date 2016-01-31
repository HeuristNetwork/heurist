/**
* Record manager - Search header
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


$.widget( "heurist.searchRecord", {

    // default options
    options: {
        
        add_new_record: true,
        rectype_set: null,  //array of record types to limit search
        
        // callbacks - events
        onstart:null,
        onresult:null
        
    },
    
    _need_load_content:true,

    // the widget's constructor
    _create: function() {

        // prevent double click to select text
        this.element.disableSelection();

        // Sets up element to apply the ui-state-focus class on focus.
        //this._focusable($element);   
        //this._refresh();

    }, //end _create

    // Any time the widget is called with no arguments or with only an option hash, 
    // the widget is initialized; this includes when the widget is created.
    _init: function() {

            var that = this;
            
            if(this._need_load_content){        
                this.element.load(top.HAPI4.basePathV4+'hclient/widgets/entity/searchRecord.html', 
                function(response, status, xhr){
                    that._need_load_content = false;
                    if ( status == "error" ) {
                        top.HEURIST4.msg.showMsgErr(response);
                    }else{
                        that._init();
                    }
                });
                return;
            }
            
            //init buttons
            this.btn_search_start = this.element.find('#btn_search_start')
                //.css({'width':'6em'})
                .button({label: top.HR("Start search"), text:false, icons: {
                    secondary: "ui-icon-search"
                }});
                        
            this.btn_add_record = this.element.find('#btn_add_record')
                .css({'min-width':'11.9em'})
                .button({label: top.HR("Add Record"), icons: {
                    primary: "ui-icon-plus"
                }})
                .click(function(e) {
                        alert('Add record');
                    });
                    
            this.input_search = this.element.find('#input_search')
                .on('keypress',
                function(e){
                    var code = (e.keyCode ? e.keyCode : e.which);
                        if (code == 13) {
                            top.HEURIST4.util.stopEvent(e);
                            e.preventDefault();
                            that.startSearch();
                        }
                });
            
                
            this.selectRectype = this.element.find('#sel_rectypes');
            
            this.selectRectype.empty();
            top.HEURIST4.ui.createRectypeSelect(this.selectRectype.get(0), 
                this.options.rectype_set, 
                this.options.rectype_set?null:top.HR('Any Record Type'));
                      
            var ishelp_on = top.HAPI4.get_prefs('help_on')==1;
            this.element.find('.heurist-helper1').css('display',ishelp_on?'block':'none');
    
            //force search if rectype_set is defined
            if(this.selectRectype.val()>0){
                this.selectRectype.change();
            }
            
            this._on( this.selectRectype, {
                change: function(event){
                    
                    if(this.selectRectype.val()>0){
                        lbl = top.HR('Add')+' '+ this.selectRectype.find( "option:selected" ).text();
                    }else{
                        lbl = top.HR("Add Record");
                    }
                    
                    this.btn_add_record.button('option','label',lbl);
                    this.startSearch();
                }
            });
            
            this._on( this.element.find('input[type=checkbox]'), {
                change: function(event){
                    this.startSearch();
                }});
            this._on( this.btn_search_start, {
                click: function(event){
                    this.startSearch();
                }});
            
        
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
        
        if(this.options.add_new_record){
            this.btn_add_record.show();
            this.element.find('#lbl_add_record').show();
        }else{
            this.btn_add_record.hide();
            this.element.find('#lbl_add_record').hide();
        }

    },
    // 
    // custom, widget-specific, cleanup.
    _destroy: function() {
        // remove generated elements
        //this.select_rectype.remove();
    },
    
    //
    // public methods
    //
    startSearch: function(){
        
            var qstr = '', domain = 'a';
            if(this.selectRectype.val()!=''){
                qstr = qstr + 't:'+this.selectRectype.val();
            }   
            if(this.input_search.val()!=''){
                qstr = qstr + ' title:'+this.input_search.val();
            }
            
            if(this.element.find('#cb_selected').is(':checked')){
                qstr = qstr + ' ids:' + top.HAPI4.get_prefs('recent_Records');
            }
            if(this.element.find('#cb_modified').is(':checked')){
                qstr = qstr + ' sortby:-m after:"1 week ago"';
            }
            if(this.element.find('#cb_bookmarked').is(':checked')){
                domain = 'b';
                if(qstr==''){
                    qstr = 'sortby:t';
                }
            }
            
            //noothing defined
            if(qstr==''){
                this._trigger( "onresult", null, {recordset:new hRecordSet()} );
            }else{
                this._trigger( "onstart" );
        
                var request = { q: qstr,
                                w: domain,
                                limit: 100000,
                                needall: 1,
                                detail: 'ids',
                                id: Math.round(new Date().getTime() + (Math.random() * 100))};
                                //source: this.element.attr('id') };

                var that = this;                                                
                //that.loadanimation(true);

                top.HAPI4.RecordMgr.search(request, function( response ){
                    //that.loadanimation(false);
                    if(response.status == top.HAPI4.ResponseStatus.OK){
                        that._trigger( "onresult", null, 
                            {recordset:new hRecordSet(response.data), request:request} );
                    }else{
                        top.HEURIST4.msg.showMsgErr(response);
                    }

                });
            }
    }

});
