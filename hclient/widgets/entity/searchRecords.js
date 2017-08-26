/**
* Search header for DefTerms manager
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

$.widget( "heurist.searchRecords", $.heurist.searchEntity, {

    //
    _initControls: function() {
        this._super();

        var that = this;

        //-----------------
        this.selectRectype = this.element.find('#sel_rectypes');

        this.selectRectype.empty();
        window.hWin.HEURIST4.ui.createRectypeSelect(this.selectRectype.get(0), 
            this.options.rectype_set, 
            this.options.rectype_set?null:window.hWin.HR('Any Record Type'));

        this.btn_add_record = this.element.find('#btn_add_record')
        .css({'min-width':'11.9em','z-index':2})
        .button({label: window.hWin.HR("Add Record"), icons: {
            primary: "ui-icon-plus"
        }})
        .click(function(e) {
            if(that.selectRectype.val()>0){
                that._trigger( "onaddrecord", null, that.selectRectype.val() );
            }else{
                alert('Select rectype first (@todo - popup to select record type to be added)');
            }
        });        
            
        //force search if rectype_set is defined
        this._on( this.selectRectype, {
            change: function(event){

                if(this.selectRectype.val()>0){
                    lbl = window.hWin.HR('Add')+' '+ this.selectRectype.find( "option:selected" ).text();
                }else{
                    lbl = window.hWin.HR("Add Record");
                }

                this.btn_add_record.button('option','label',lbl);
                this.startSearch();
            }
        });

        this._on( this.element.find('input[type=checkbox]'), {
            change: function(event){
                this.startSearch();
        }});

        if(this.selectRectype.val()>0){
            this.selectRectype.change();
        }
    },  

    //
    // public methods
    //
    startSearch: function(){

        this._super();

        var request = {}

        var qstr = '', domain = 'a', qobj = [];
        if(this.selectRectype.val()!=''){
            qstr = qstr + 't:'+this.selectRectype.val();
            
            qobj.push({"t":this.selectRectype.val()});
        }   
        if(this.input_search.val()!=''){
            qstr = qstr + ' title:'+this.input_search.val();
            
            qobj.push({"title":this.input_search.val()});
        }

        if(this.element.find('#cb_selected').is(':checked')){
            qstr = qstr + ' ids:' + window.hWin.HAPI4.get_prefs('recent_Records');
            
            qobj.push({"ids":window.hWin.HAPI4.get_prefs('recent_Records')});
        }
        
        if(this.options.parententity>0){
            //filter out records with parent entiy (247) field
            var DT_PARENT_ENTITY  = window.hWin.HAPI4.sysinfo['dbconst']['DT_PARENT_ENTITY'];
            var pred = {}; pred["f:"+DT_PARENT_ENTITY]="NULL";
            qobj.push(pred);
        }
        
        if(this.element.find('#cb_modified').is(':checked')){
            qstr = qstr + ' sortby:-m after:"1 week ago"';
            
            qobj.push({"sortby":"-m after:\"1 week ago\""});
        }
        if(this.element.find('#cb_bookmarked').is(':checked')){
            domain = 'b';
            if(qstr==''){
                qstr = 'sortby:t';
                qobj.push({"sortby":"t"});
            }
        }            
        
        if(qstr==''){
            this._trigger( "onresult", null, {recordset:new hRecordSet()} );
        }else{
            this._trigger( "onstart" );

            var request = { 
                //q: qstr, 
                q: qobj,
                w: domain,
                limit: 100000,
                needall: 1,
                detail: 'ids',
                id: window.hWin.HEURIST4.util.random()}
            //source: this.element.attr('id') };

            var that = this;                                                
            //that.loadanimation(true);

            window.hWin.HAPI4.RecordMgr.search(request, function( response ){
                //that.loadanimation(false);
                if(response.status == window.hWin.HAPI4.ResponseStatus.OK){
                    that._trigger( "onresult", null, 
                        {recordset:new hRecordSet(response.data), request:request} );
                }else{
                    window.hWin.HEURIST4.msg.showMsgErr(response);
                }

            });
            
        }            
    }
    

});
