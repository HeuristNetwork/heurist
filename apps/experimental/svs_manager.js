/**
* Saved search manager - list of saved searches by groups. Requires utils.js
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2015 University of Sydney
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


$.widget( "heurist.svs_manager", {

    // default options
    options: {
        isdialog: false, //show in dialog or embedded

        current_UGrpID: null,
        // we take tags from top.HAPI4.currentUser.usr_Tags - array of tags in form [ {ugrp_id:[{tagid:[label, description, usage]}, ....]},...]
        current_order: 0  // order by name
    },

    // the constructor
    _create: function() {

        var that = this;

        this.wcontainer = $("<div>");

        if(this.options.isdialog){

            this.wcontainer
            .css({overflow: 'none !important', width:'100% !important'})
            .appendTo(this.element);

            this.element.dialog({
                autoOpen: false,
                height: 620,
                width: 400,
                modal: true,
                title: top.HR("Manage Saved Searches"),
                resizeStop: function( event, ui ) {
                    that.element.css({overflow: 'none !important','width':'100%'});
                },
                buttons: [
                    {text:top.HR('Create'),
                        title: top.HR("Create new search"),
                        click: function() {
                            that._editSavedSearch();
                    }},
                    {text:top.HR('Close'), click: function() {
                        $( this ).dialog( "close" );
                    }}
                ]
            });

        }else{
            this.wcontainer.addClass('ui-widget-content ui-corner-all').css({'padding':'0.4em',height:'500px'}).appendTo( this.element );
        }

        //---------------------------------------- HEADER
        // Workgroup selector
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

        //----------------------------------------
        var css1;
        if(this.options.isdialog){
            css1 =  {'overflow-y':'auto','padding':'0.4em','top':'60px','bottom':0,'position':'absolute','left':0,'right':0};
        }else{
            css1 =  {'overflow-y':'auto','padding':'0.4em','top':'60px','bottom':0,'position':'absolute','left':0,'right':0};
        }

        this.div_content = $( "<div>" )
        .addClass('list')
        .css(css1)
        .html('list of saved searches')
        //.position({my: "left top", at: "left bottom", of: this.div_toolbar })
        .appendTo( this.wcontainer );

        //-----------------------------------------

        this.edit_dialog = null;

        if(!this.options.isdialog)
        {
            this.div_toolbar = $( "<div>" )
            .css({'width': '100%', height:'2.4em'})
            .appendTo( this.wcontainer );

            this.btn_add = $( "<button>", {
                text: top.HR("Create"),
                title: top.HR("Create new saved search")
            })
            .appendTo( this.div_toolbar )
            .button();

            this._on( this.btn_add, { click: "_editSavedSearch" } );
        }

        if(!top.HAPI4.currentUser.usr_GroupsList){

            var that = this;
            //get details about Workgroups (names etc)
            top.HAPI4.SystemMgr.mygroups(
                function(response){
                    if(response.status == top.HAPI4.ResponseStatus.OK){
                        top.HAPI4.currentUser.usr_GroupsList = response.data;
                        that._updateGroups();
                    }
            });
        }else{
            this._updateGroups();
        }

    }, //end _create

    /* private function */
    _refresh: function(){

        if(!top.HAPI4.currentUser.usr_SavedSearch){

            var that = this;

            top.HAPI4.SystemMgr.ssearch_get( null,
                function(response){
                    if(response.status == top.HAPI4.ResponseStatus.OK){
                        top.HAPI4.currentUser.usr_SavedSearch = response.data;
                        that._renderItems();
                    }
            });
        }else{
            this._renderItems();
        }

    },


    /* private function */
    // events bound via _on are removed automatically
    // revert other modifications here
    _destroy: function() {
        // remove generated elements
        this.select_grp.remove();

        if(this.edit_dialog){
            this.edit_dialog.remove();
        }

        if(this.div_toolbar){
            this.btn_add.remove();
            this.div_toolbar.remove();
        }

        this.wcontainer.remove();
    },

    //fill selector with groups
    _updateGroups: function(){

        var that = this;
        // list of groups for current user
        var selObj = this.select_grp.get(0);
        top.HEURIST4.util.createUserGroupsSelect(selObj, top.HAPI4.currentUser.usr_GroupsList,
            [{key:"bookmark"+top.HAPI4.currentUser.ugr_ID, title:top.HR('My Bookmarks')},{key:top.HAPI4.currentUser.ugr_ID, title:top.HR('All Records')}],
            function(){
                that.select_grp.val(top.HAPI4.currentUser.ugr_ID);
                that.select_grp.change();
        });
    },

    // [ {ugrp_id:[{tagid:[label, description, usage]}, ....]},...]
    _renderItems: function(){

        if(this.div_content){
            //var $allrecs = this.div_content.find('.recordDiv');
            //this._off( $allrecs, "click");
            this.div_content.empty();  //clear
        }

        var that = this;
        var ssearches = top.HAPI4.currentUser.usr_SavedSearch;

        for (var svsID in ssearches)
        {
            if(svsID && (ssearches[svsID][2]== this.options.current_GrpID || "bookmark"+ssearches[svsID][2]== this.options.current_GrpID)){

                var name = ssearches[svsID][0];
                var request = top.HEURIST4.util.parseHeuristQuery(ssearches[svsID][1]);
                var qsearch = request.q;
                var domain2 = request.w;

                if(this.options.current_GrpID==top.HAPI4.currentUser.ugr_ID){
                    if(domain2=="bookmark"){
                        continue;
                    }
                }else if(this.options.current_GrpID == "bookmark"+top.HAPI4.currentUser.ugr_ID){
                    if(domain2=="all"){
                        continue;
                    }
                }

                $itemdiv = $(document.createElement('div'));

                $itemdiv
                .addClass('recordDiv')
                .attr('id', 'svs-'+svsID )
                .attr('svsID', svsID )
                .appendTo(this.div_content);

                $('<div>',{
                    title: name + ' ' + domain2
                })
                .css('display','inline-block')
                .addClass('recordTitle')
                .css({'left':'1em',top:0}) //,'margin':'0.4em', 'height':'1.4em'})
                .html( name )
                .appendTo($itemdiv);


                $itemdiv.append( $('<div>')
                    .addClass('edit-delete-buttons')
                    .css('margin','0.4em 1.2em')
                    .append( $('<div>', { svsid:svsID, title: top.HR('Edit saved search filter') })
                        .button({icons: {primary: "ui-icon-pencil"}, text:false})
                        .click(function( event ) {
                            that._editSavedSearch( $(this).attr('svsid') );
                    }) )
                    .append($('<div>',{ svsID:svsID, title: top.HR('Delete saved search filter') })
                        .button({icons: {primary: "ui-icon-close"}, text:false})
                        .click(function( event ) {
                            that._deleteSavedSearch( $(this).attr('svsid') );
                    }) )
                );
            }
        }
    },

    /**
    * Remove given saved search
    *
    * @param svsID
    */
    _deleteSavedSearch: function(svsID){

        var svs = top.HAPI4.currentUser.usr_SavedSearch[svsID];
        if(!svs) return;
        var that = this;

        top.HEURIST4.msg.showMsgDlg(top.HR("Delete? Please confirm"),  function(){
            top.HAPI4.SystemMgr.ssearch_delete({ids:svsID, UGrpID: svs[2]},
                function(response){
                    if(response.status == top.HAPI4.ResponseStatus.OK){

                        //remove from UI
                        that.find('#svs-list-'+svsID).remove();
                        //remove from
                        delete top.HAPI4.currentUser.usr_SavedSearch[svsID];

                    }else{
                        top.HEURIST4.msg.showMsgErr(response);
                    }
                }

            );
            }, "Confirmation");
    },

    /**
    * Assign values to UI input controls
    */
    _fromDataToUI: function(svsID){

        var $dlg = this.edit_dialog;
        if($dlg){
            $dlg.find('.messages').empty();

            var svs_id = $dlg.find('#svs_ID');
            var svs_name = $dlg.find('#svs_Name');
            var svs_query = $dlg.find('#svs_Query');
            var svs_ugrid = $dlg.find('#svs_UGrpID');
            var svs_rules = $dlg.find('#svs_Rules');
            var svs_notes = $dlg.find('#svs_Notes');

            var isEdit = (parseInt(svsID)>0);

            if(isEdit){
                var svs = top.HAPI4.currentUser.usr_SavedSearch[svsID];

                var request = top.HEURIST4.util.parseHeuristQuery(svs[1]);
                var domain  = request.w;

                svs_id.val(svsID);
                svs_name.val(svs[0]);
                svs_query.val( request.q );
                svs_rules.val( request.rules );
                svs_notes.val( request.notes );

                svs_ugrid.val( svs[2]==top.HAPI4.currentUser.ugr_ID ?domain:svs[2] );
                svs_ugrid.parent().hide();

            }else{ //add new saved search

                svs_id.val('');
                svs_name.val('');
                svs_query.val('');
                svs_rules.val('');
                svs_notes.val('');

                var domain = this.currentSearch.w;
                domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';

                //fill with list of Workgroups in case non bookmark search
                var selObj = svs_ugrid.get(0);
                top.HEURIST4.util.createUserGroupsSelect(selObj, top.HAPI4.currentUser.usr_GroupsList,
                    [{key:'bookmark', title:top.HR('My Bookmarks')}, {key:'all', title:top.HR('All Records')}],
                    function(){
                        svs_ugrid.val(top.HAPI4.currentUser.ugr_ID);
                });
                svs_ugrid.parent().show();
            }
        }
    },

    _editSavedSearch: function(svsID){
        alert('todo!');
    },
    /*
    * Open itself as modal dialogue
    _manageItems: function(){
    this.element.hide();
    showManageSavedSearches();
    },
    */


    show: function(){
        if(this.options.isdialog){
            this.element.dialog("open");
        }else{
            //fill selected value this.element
        }
    }

});

function showManageSavedSearches(){

    var manage_dlg = $('#heurist-svs-dialog');

    if(manage_dlg.length<1){

        manage_dlg = $('<div id="heurist-svs-dialog">')
        .appendTo( $('body') )
        .svs_manager({ isdialog:true });
    }

    manage_dlg.svs_manager( "show" );
}
