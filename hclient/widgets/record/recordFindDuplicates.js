/**
* recordFindDuplicates.js - Find duplicates by record type and selected fields
*                           It uses levenshtein function on server side
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
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

$.widget( "heurist.recordFindDuplicates", $.heurist.recordAction, {

    // default options
    options: {
    
        height: 780,
        width:  800,
        modal:  true,
        title:  'Find duplicate records',
        
        htmlContent: 'recordFindDuplicates.html',
        helpContent: 'recordFindDuplicates.html' //in context_help folder
    },
    
    //results
    dupes:null,
    summary:null,

    selectedFields:null,
    
    _destroy: function() {
        this._super(); 
        
        var treediv = this.element.find('.rtt-tree');
        if(!treediv.is(':empty') && treediv.fancytree("instance")){
            treediv.fancytree("destroy");
        }
        treediv.remove();
        
        if(this.toolbar)this.toolbar.remove();
    },
        
    _initControls: function() {

        var that = this;

        this._super();    

        //add header and action buttons for container/inline mode
        if(!this.options.isdialog && this.options.is_h6style)
        {
            
            this.element.find('.ent_wrapper').css({top:'36px'});
            
            var fele = this.element.find('.ent_wrapper:first');
            
            $('<div class="ui-heurist-header">'+this.options.title+'</div>').insertBefore(fele);    
            
            //append action buttons
            this.toolbar =  this.element.find('#div_button-toolbar');
            var btns = this._getActionButtons();
            for(var idx in btns){
                this._defineActionButton2(btns[idx], this.toolbar);
            }
        }
        return true;
    },

    //    
    //
    //
    _getActionButtons: function(){
        var res = this._super();
        var that = this;
        res[1].text = window.hWin.HR('Find Duplications');
        res[1].css = {'margin-left':'20px'};
        
        
        res[0].css = {float: 'right', 'margin-top': 10};
        res[0].text = window.hWin.HR('Clear ignoring list');    
        res[0].click = function() { 
                        that._ignoreClear();
                    };
        
        /*
        if(!this.options.isdialog && this.options.is_h6style){
            res.shift();
        }else{
            
        }*/
        
        
        
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
                    topOptions: [{key:'-1',title:'select record type to search...'}],
                    useHtmlSelect: false,
                    useCounts: true,
                    showAllRectypes: true
                });
        
        
        
        this._on( this.selectRecordScope, {
                change: this._onRecordScopeChange} );        
        //this.selectRecordScope.val(this.options.init_scope);    
        //if(selScope.selectedIndex<0) selScope.selectedIndex=0;
        this._onRecordScopeChange();
        
        window.hWin.HEURIST4.ui.initHSelect(selScope);
    },
            
    //
    // 
    //
    doAction: function(){

            var rty_ID = this.selectRecordScope.val();

            this.element.find('#div_result').empty();
            
            if(rty_ID>0){


                var settings = this.getSettings(true);            
                if(!settings) return;

                settings.fields = settings.fields[rty_ID];

                //unique session id    
                var session_id = Math.round((new Date()).getTime()/1000);
                this._showProgress( session_id, false, 1000 );

                var request = {
                    a        : 'dupes',
                    db       : window.hWin.HAPI4.database,
                    rty_ID   : rty_ID,
                    fields   : settings.fields,
                    session  : session_id,
                    startgroup: settings.startgroup,
                    sort_field: settings.sort_field,
                    distance : settings.distance};

                var url = window.hWin.HAPI4.baseURL + 'hsapi/controller/record_verify.php'
                var that = this;

                window.hWin.HEURIST4.util.sendRequest(url, request, null, function(response){
                    that._hideProgress();
                    
                    //render groups
                    if(response.status == window.hWin.ResponseStatus.OK){

                        that.summary = response.data['summary'];
                        response.data['summary'] = null;
                        delete response.data['summary'];
                      
                        that.dupes = response.data;
                        that._renderDuplicates();

                    }else{
                        
                        if(response.status==window.hWin.ResponseStatus.ACTION_BLOCKED){

                            var sMsg = '<p>Finding duplicates in '+response.message+' records will be extremely slow and could overload our server under some circumstances.</p>' 
+'<p>In order to streamline the process, please specify a field on which to sort the records. Typically use the constructed title, or a name or title field which will ensure that potential duplicates sort close to one-another. The sort is alphabetical.</p>' 
+'<p>We then search for duplicates in a sliding window of 10,000 records within this sorted list</p>'
+'<p>You may further increase speed by setting "Group by beginning"</p>';

                            
                            window.hWin.HEURIST4.msg.showMsgErr(sMsg);    
                            
                        }else{
                            window.hWin.HEURIST4.msg.showMsgErr(response);    
                        }
                        
                    }

                    //console.log(response.data);                    
                });

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
    // mode_action true - returns fields to compare
    //
    getSettings: function( mode_action ){
        
            var header_fields = {id:'rec_ID',title:'rec_Title',url:'rec_URL',addedby:'rec_AddedBy',notes:'rec_ScratchPad'};
            function __removeLinkType(dtid){
                if(header_fields[dtid]){
                    dtid = header_fields[dtid];
                }else{
                    var linktype = dtid.substr(0,2); //remove link type lt ot rt  10:lt34
                    if(isNaN(Number(linktype))){
                        dtid = dtid.substr(2);
                    }
                }
                return dtid;
            }
            function __addSelectedField(ids, lvl, constr_rt_id){
                
                if(ids.length < lvl) return;
                
                //take last two - these are rt:dt
                var rtid = ids[ids.length-lvl-1];
                var dtid = __removeLinkType(ids[ids.length-lvl]);
                
                if(!selectedFields[rtid]){
                    selectedFields[rtid] = [];    
                }
                if(constr_rt_id>0){
                    dtid = dtid+':'+constr_rt_id;
                }
                
                //window.hWin.HEURIST4.util.findArrayIndex( dtid, selectedFields[rtid] )<0
                if( selectedFields[rtid].indexOf( dtid )<0 ) {
                    
                    selectedFields[rtid].push(dtid);    
                    
                    //add resource field for parent recordtype
                    __addSelectedField(ids, lvl+2, rtid);
                }
            }
            
            //get selected fields from treeview
            var selectedFields = mode_action?{}:[];
            var tree = this.element.find('.rtt-tree').fancytree("getTree");
            var fieldIds = tree.getSelectedNodes(false);
            var k, len = fieldIds.length;
            
            if(len<1){
                window.hWin.HEURIST4.msg.showMsgFlash('No fields selected. '
                    +'Please select at least one field in tree', 2000);
                return false;
            }
            
            
            for (k=0;k<len;k++){
                var node =  fieldIds[k];
                
                if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;
                
                if(mode_action){
                    var ids = node.data.code.split(":");
                    __addSelectedField(ids, 1, 0);
                }else{
                    selectedFields.push(node.data.code);
                }
                
                //DEBUG console.log( node.data.code );
            }
            //DEBUG 
        
        return {
                fields: selectedFields,
                distance: this.element.find('#distance').val(),
                startgroup: this.element.find('#startgroup').val(),
                sort_field: this.element.find('#sort_field').val()
                };
        
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
            
            
            var allowed_fieldtypes = ['rec_Title','rec_AddedBy','rec_URL','rec_ScratchPad',
                'enum','freetext','blocktext',
                'year','date','integer','float','resource'];
            
            //generate treedata from rectype structure
            var treedata = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, rtyID, allowed_fieldtypes );
            
            treedata = treedata[0].children;
            treedata[0].selected = true;
            //treedata[0].expanded = true; //first expanded
            
            //load treeview
            var treediv = this.element.find('.rtt-tree');
            if(!treediv.is(':empty') && treediv.fancytree("instance")){
                treediv.fancytree("destroy");
            }
            
            treediv.addClass('tree-csv').fancytree({
                //extensions: ["filter"],
                //            extensions: ["select"],
                checkbox: true,
                selectMode: 3,  // hierarchical multi-selection
                source: treedata,
                beforeSelect: function(event, data){
                    // A node is about to be selected: prevent this, for folder-nodes:
                    if( data.node.hasChildren() ){
                        
                        if(data.node.isExpanded()){
                            for(var i=0; i<data.node.children.length; i++){
                                var node = data.node.children[i];
                                if(node.key=='rec_ID' || node.key=='rec_Title'){
                                    node.setSelected(true);
                                }
                            }
                        }
                        return false;
                    }
                },
                /*
                lazyLoad: function(event, data){
                    var node = data.node;
                    var parentcode = node.data.code; 
                    var rectypes = node.data.rt_ids;
                    
                    var res = window.hWin.HEURIST4.dbs.createRectypeStructureTree( null, 6, 
                                                        rectypes, ['header_ext','all'], parentcode );
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
                */
                select: function(e, data) {
                    that._fillSortField();
                },
                click: function(e, data){
                   if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                       data.node.setExpanded(!data.node.isExpanded());
                       //treediv.find('.fancytree-expander').hide();
                       
                   }else if( false && data.node.lazy  ) { //
                       data.node.setExpanded( true );
                   }
                },
                
                dblclick: function(e, data) {
                    data.node.toggleSelected();
                },
                keydown: function(e, data) {
                    if( e.which === 32 ) {
                        data.node.toggleSelected();
                        return false;
                    }
                }
            });
            
            var tree = treediv.fancytree('getTree');
            tree.visit(function(node){
                if(node.lazy){
                    node.folder = false;
                    node.lazy = false;
                    node.removeChildren();
                }
            });
            setTimeout(function(){
                tree.render();
                that._fillSortField();
            },1000);
            
        }   
    },
    
    //
    //
    //
    _fixDuplicatesPopup: function(event){
        
        var sGroupID = $(event.target).attr('data-action-merge');
        var sRecIds = Object.keys(this.dupes[sGroupID]);

                var url = window.hWin.HAPI4.baseURL
                        + 'admin/verification/combineDuplicateRecords.php?bib_ids='
                        + sRecIds.join(',')
                        + '&db=' + window.hWin.HAPI4.database;
                        
                var that = this;
                
                window.hWin.HEURIST4.msg.showDialog(url, {
                    width:700, height:600,
                    default_palette_class:'ui-heurist-explore',
                    title: window.hWin.HR('Combine duplicate records'),
                    callback: function(context) {
                            if(context=='commited'){
                               that.element.find('.group_'+sGroupID).hide();
                            }
                    }
                });
                
                return false;
    },
    
    //
    //
    //
    _ignoreGroup: function(event){

        var sGroupID = $(event.target).attr('data-action-ignore');
        var sRecIds = Object.keys(this.dupes[sGroupID]);

        var request = {
            a        : 'dupes',
            db       : window.hWin.HAPI4.database,
            ignore   : sRecIds.join(',')};

        var url = window.hWin.HAPI4.baseURL + 'hsapi/controller/record_verify.php'

        window.hWin.HEURIST4.util.sendRequest(url, request, null, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                $(event.target).parents('div.group').hide();
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);    
            }
        });

        return false;
    },

    //
    //
    //
    _ignoreClear: function(){

        var request = {
            a        : 'dupes',
            db       : window.hWin.HAPI4.database,
            ignore   : 'clear'};

        var url = window.hWin.HAPI4.baseURL + 'hsapi/controller/record_verify.php'

        window.hWin.HEURIST4.util.sendRequest(url, request, null, function(response){

            if(response.status == window.hWin.ResponseStatus.OK){
                window.hWin.HEURIST4.msg.showMsgFlash('cleared',1000);
            }else{
                window.hWin.HEURIST4.msg.showMsgErr(response);    
            }
        });

        return false;
    },

    
    //
    //
    //
    _renderDuplicates: function(){
        
            var dupes = this.dupes;
            
            var s = '<div style="padding:10px;">' + this.summary['scope'] + ' records have been checked.'
                    + '<p>There are '+this.summary['cnt_records']
                            + ' potential duplicates grouped in '
                            +  this.summary['cnt_groups'] +' groups</b></p>';
            
            if(this.summary['cnt_records']>this.summary['limit']){
                s = s + '<p>Operation has been terminated since the number of possible duplicaions is more than limit in '
                        +this.summary['limit']+' records. Reduce the distance or add additional search field</p>';   
            }else if(this.summary['is_terminated']){

                s = s + '<p>Operation has been terminated by user</p>';
            }
            
            s = s +'<p><b>Merge this group</b> link will ask which members of the group to merge before any changes are made.</p>'
                + '</div>'
            
//onsole.log(dupes);            

            
            var grp_cnt = Object.keys(dupes).length;
            for(var i=0; i<grp_cnt; i++) {

                var rec_ids = Object.keys(dupes[i]);
        
                s = s + '<div style="padding: 10px 20px;" class="group group_'+i+'">';
                
                s = s + '<a href="#" data-action-merge="'+i+'">merge this group</a>&nbsp;&nbsp;&nbsp;&nbsp;'
                    
                    +'<a target="_new" href="'+window.hWin.HAPI4.baseURL+'?db='
                        +window.hWin.HAPI4.database
                        +'&w=all&q=ids:' + rec_ids.join(',') + '">view as search</a>&nbsp;&nbsp;&nbsp;&nbsp;'

                    +'<a href="#" data-action-ignore="'+i+ '">ignore in future</a>';
                        
                        
                //list of records    
                s = s + '<ul style="padding: 10px 30px;" class="group_'+i+'">';
                for(var j=0; j<rec_ids.length; j++) {
                    
                    s = s + '<li>'
                    + '<a target="_new" href="'+window.hWin.HAPI4.baseURL+'viewers/record/viewRecord.php?db='
                    + window.hWin.HAPI4.database
                    + '&saneopen=1&recID='+rec_ids[j]+'">'+rec_ids[j]
                    + ': '+ window.hWin.HEURIST4.util.stripTags(dupes[i][rec_ids[j]])+'</a></li>';
                    
                }
                s = s + '</ul></div>';
            }

            this._off(
                this.element.find('#div_result').find('a[data-action-merge]'),'click');

            
            this.element.find('#div_result').html(s);
           
            this._on(
                this.element.find('#div_result').find('a[data-action-merge]'),
                {click: this._fixDuplicatesPopup });

            this._on(
                this.element.find('#div_result').find('a[data-action-ignore]'),
                {click: this._ignoreGroup });

                
    },

    _fillSortField: function(){
        
                    var tree = this.element.find('.rtt-tree').fancytree("getTree");
                    var fieldIds = tree.getSelectedNodes(false);
                    var k, len = fieldIds.length;
                    
                    var sel = this.element.find('#sort_field');
                    var keep_val = sel.val();
                    sel.empty();
                    
                    for (k=0;k<len;k++){
                        var node =  fieldIds[k];
                        if(window.hWin.HEURIST4.util.isempty(node.data.code)) continue;
                        
                        if(node.data.type=='freetext' || node.data.type=='blocktext'){
                            var key = node.key.split(':');
                            key = key[key.length-1];
                            window.hWin.HEURIST4.ui.addoption(sel[0], key, node.data.name);
                        }
                    }
                    sel.val(keep_val);
                    if(!(sel[0].selectedIndex>0)) sel[0].selectedIndex = 0;

    }
                        


});

