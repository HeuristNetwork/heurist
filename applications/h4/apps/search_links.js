/**
* Accordeon: saved and predefined searches
*/
$.widget( "heurist.search_links", {

  // default options
  options: {
    isapplication:true,  // send and recieve the global events

    searchdetails: "map", //level of search results  map - with details, structure - with detail and structure

    // callbacks
    onsearch: null,  //on start search
    onresult: null   //on search result
  },

  currentSearch: null,

  // the constructor
  _create: function() {

    var that = this;
    
    this.user_groups = $( "<div>" )
             .css({"font-size":"0.9em","overflow-y":"auto","height":"100%"})
             .appendTo( this.element )
             .hide();

    this.edit_dialog = null;

    //global listener
    $(this.document).on(top.HAPI.Event.LOGIN+' '+top.HAPI.Event.LOGOUT, function(e, data) {
        that._refresh();
    });
    $(this.document).on(top.HAPI.Event.ON_REC_SEARCHSTART, function(e, data){
        that.currentSearch = data;
    });


    this._refresh();
  }, //end _create

  _setOption: function( key, value ) {
      this._super( key, value );
/*
      if(key=='rectype_set'){
            this._refresh();
      }*/
  },

  /* private function */
  _refresh: function(){

                  if(!top.HAPI.currentUser.usr_SavedSearch){

                    var that = this;

                    top.HAPI.SystemMgr.ssearch_get(
                        function(response){
                            if(response.status == top.HAPI.ResponseStatus.OK){
                                top.HAPI.currentUser.usr_SavedSearch = response.data;
                                that._updateAccordeon();
                            }
                        });
                  }else{
                        this._updateAccordeon();
                  }

  },

  _updateAccordeon: function()
  {
            var islogged = (top.HAPI.currentUser.ugr_ID>0);

            this.user_groups.hide().empty();

            if(islogged){


                this.user_groups.append(
                    $('<div>')
                       .append(this._defineHeader(top.HR('My Bookmarks')))
                       .append( this._define_SVSlist(top.HAPI.currentUser.ugr_ID, "bookmark") ));

                this.user_groups.append(
                    $('<div>')
                       .append( this._defineHeader(top.HR('All Records')))
                       .append( this._define_SVSlist(top.HAPI.currentUser.ugr_ID) ));

                  if(!top.HAPI.currentUser.usr_GroupsList){

                    var that = this;

                    //get details about user groups (names etc)
                    top.HAPI.SystemMgr.mygroups(
                        function(response){
                            if(response.status == top.HAPI.ResponseStatus.OK){
                               top.HAPI.currentUser.usr_GroupsList = response.data;
                                that._updateGroups();
                            }
                        });
                  }else{
                        this._updateGroups();
                  }

            }else{

                this.user_groups
                    .append(
                        $('<div>')
                            .append(this._defineHeader(top.HR('Prepared searches')))
                            .append( this._define_SVSlist( top.HAPI.currentUser.ugr_ID) ))
                    .show();

            }

  },

  /**
  * define header for each accordeon
  */
  _defineHeader: function(name){

       var $header = $('<h3>'+name+'</h3>');
       var that = this;

       $('<div>',{
                id: 'rec_add_link',
                title: top.HR('Click to save current search')
            })
            .button({icons: {primary: "ui-icon-circle-plus"}, text:false})
            .click(function( event ) {
                event.preventDefault();
                that._editSavedSearch();
                return false;
            })
            .appendTo($header);

      return $header
  },

  //
  /**
  * create list of saved searches for given user/group
  * domain - all or bookmark
  */
  _define_SVSlist: function(ugr_ID, domain){

       var ssearches = top.HAPI.currentUser.usr_SavedSearch;
       var cnt = 0;
       var that = this;

       $ul = $('<div>'); //.css({'padding': '0em 0em !important', 'background-color':'red'});

       //add predefined searches
       if(ugr_ID == top.HAPI.currentUser.ugr_ID){

            domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';

            $ul.attr('id', 'svsu-'+ugr_ID+'-'+domain);

            var s_all = "?w="+domain+"&q=sortby:-m after:\"1 week ago\"&label=Recent changes";
            var s_recent = "?w="+domain+"&q=sortby:-m&label=All records";

            $ul.append( this._add_SVSitem(top.HR('Recent changes'), null, s_all));
            $ul.append( this._add_SVSitem(top.HR('All (date order)'), null, s_recent));

            cnt = 2;
       }else{
           $ul.attr('id', 'svsu-'+ugr_ID+'-all');
       }

       for (var svsID in ssearches)
       {
            if(svsID && ssearches[svsID][2]==ugr_ID){

                if(ugr_ID==top.HAPI.currentUser.ugr_ID){

                    var prms = top.HEURIST.util.getUrlQueryAndDomain(ssearches[svsID][1]);
                    var qsearch = prms[0];
                    var domain2  = prms[1];
                    if(domain!=domain2){
                        continue;
                    }
                }

                var name = ssearches[svsID][0];
                $ul.append( this._add_SVSitem(name, svsID) );
                cnt++;
            }
        }

        return $ul;

  },

  /**
  * create item for given saved search
  * return div
  */
  _add_SVSitem: function(name, qid, squery){

            //+(qid?' svsid="'+qid+'"':'')+'>'+(squery?'<div style="display:none;">'+squery+'</div>':'')
            // .css({'cursor':'pointer', 'text-decoration':'underline'})
            var that = this;

            var $resdiv = $('<div>')
                  .addClass('saved-search')
                  .append($('<div>')
                        .addClass('name')
                        .html(name)
                        .on("click", function(){
                                if (qid && top.HAPI.currentUser.usr_SavedSearch){
                                    squery = top.HAPI.currentUser.usr_SavedSearch[qid][1];
                                }
                                that._doSearch2(name, squery);
                                } )
                         );
            if(qid && top.HAPI.currentUser.usr_SavedSearch){

                 $resdiv.find('.name').css('width','75%').attr('id','svs-'+qid);

                 $resdiv
                  .append( $('<div>')
                      .addClass('edit-delete-buttons')
                      .append( $('<div>', { title: top.HR('Edit saved search') })
                            .button({icons: {primary: "ui-icon-pencil"}, text:false})
                            .click(function( event ) {
                                that._editSavedSearch(qid);
                            }) )
                      .append($('<div>',{title: top.HR('Delete saved search') })
                            .button({icons: {primary: "ui-icon-close"}, text:false})
                            .click(function( event ) {
                                that._deleteSavedSearch(qid);
                            }) )
                  );
            }

                  return $resdiv;
   },


  _updateGroups: function(){

           var groups = top.HAPI.currentUser.usr_GroupsList;

           for (var groupID in groups)
           {
                if(groupID){
                    var name = groups[groupID][1];
                    this.user_groups.append(
                    $('<div>')
                       .append( this._defineHeader(name ))
                       .append( this._define_SVSlist(groupID) ));

                }
            }

            this.user_groups.children()
                .accordion({
                    header: "> h3",
                    heightStyle: "content",
                    collapsible: true
                });
            this.user_groups.show();


/* common accordeon
            this.user_groups
            .accordion({
                    header: "> div > h3",
                    heightStyle: "content"
            })
            .sortable({
                axis: "y",
                handle: "h3",
                stop: function( event, ui ) {
                            // IE doesn't register the blur when sorting
                            // so trigger focusout handlers to remove .ui-state-focus
                            ui.item.children( "h3" ).triggerHandler( "focusout" );
                        }
             })
             .show();
*/

  },

  /*
  _doSearch: function(event){

          var qsearch = null;
          var qid = $(event.target).attr('svsid');

          if (qid && top.HAPI.currentUser.usr_SavedSearch){
                qsearch = top.HAPI.currentUser.usr_SavedSearch[qid][1];
          } else {
                qsearch = $(event.target).find('div').html();
                qsearch = qsearch.replace("&amp;","&");
          }
          this._doSearch2(qsearch);
  },*/

  _doSearch2: function(qname, qsearch){

          if ( qsearch ) {

            var prms = top.HEURIST.util.getUrlQueryAndDomain(qsearch);
                qsearch = prms[0];
            var domain  = prms[1];

            // q - query string
            // w  all|bookmark
            // stype  key|all   - key-search tags, all-title and pointer record title, by default rec_Title

            var that = this;

            var request = {q: qsearch, w: domain, f: this.options.searchdetails, orig:'saved', qname:qname};

            //that._trigger( "onsearch"); //this widget event
            //that._trigger( "onresult", null, resdata ); //this widget event
            
            //get hapi and perform search
            top.HAPI.RecordMgr.search(request, $(this.document));
          }

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

            var isEdit = (parseInt(svsID)>0);

            if(isEdit){
               var svs = top.HAPI.currentUser.usr_SavedSearch[svsID];
               svs_id.val(svsID);
               svs_name.val(svs[0]);

               var prms = top.HEURIST.util.getUrlQueryAndDomain(svs[1]);
               var qsearch = prms[0];
               var domain  = prms[1];

               svs_query.val( qsearch );
               svs_ugrid.val(svs[2]==top.HAPI.currentUser.ugr_ID ?domain:svs[2]);
               svs_ugrid.parent().hide();

            }else{ //add new saved search

                svs_id.val('');
                svs_name.val('');

                var domain = this.currentSearch.w;
                domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';

                svs_query.val( this.currentSearch.q );

                //fill with list of user groups in case non bookmark search
                var selObj = svs_ugrid.get(0);
                if(domain!="all"){
                    svs_ugrid.empty();
                    top.HEURIST.util.addoption(selObj, 'bookmark', top.HR('My Bookmarks'));
                }else{
                    top.HEURIST.util.createUserGroupsSelect(selObj, top.HAPI.currentUser.usr_GroupsList,
                            [{key:'all', title:top.HR('All Records')}],
                            function(){
                                svs_ugrid.val(top.HAPI.currentUser.ugr_ID);
                            });
                }
                svs_ugrid.parent().show();
            }
      }
  },

  _editSavedSearch: function(svsID){

    if(  this.edit_dialog==null )
    {
        var that = this;
        var $dlg = this.edit_dialog = $( "<div>" ).appendTo( this.element );

        //load edit dialogue
        $dlg.load("apps/svs_edit.html", function(){

            //find all labels and apply localization
            $dlg.find('label').each(function(){
                 $(this).html(top.HR($(this).html()));
            })

            var allFields = $dlg.find('input');

            that._fromDataToUI(svsID);

            function __doSave(){

                  var message = $dlg.find('.messages');
                  var svs_id = $dlg.find('#svs_ID');
                  var svs_name = $dlg.find('#svs_Name');
                  var svs_query = $dlg.find('#svs_Query');
                  var svs_ugrid = $dlg.find('#svs_UGrpID');

                  allFields.removeClass( "ui-state-error" );

                  var bValid = top.HEURIST.util.checkLength( svs_name, "Name", message, 3, 25 )
                           && top.HEURIST.util.checkLength( svs_query, "Query", message, 1 );


                  if(bValid){

                    var request = {  //svs_ID: svsID, //?svs_ID:null,
                                    svs_Name: svs_name.val(),
                                      svs_Query: svs_query.val(),
                                        svs_UGrpID: svs_ugrid.val()};

                    var isEdit = ( parseInt(svs_id.val()) > 0 );

                    if(isEdit){
                        request.svs_ID = svs_id.val();
                    }

                    var domain = "all";
                    //special case for personal saved searches - they are devided to bookmarks and all
                    var ugrid = svs_ugrid.val();
                    if(ugrid=="all" || ugrid=="bookmark"){
                            domain = ugrid;
                            request.svs_UGrpID = top.HAPI.currentUser.ugr_ID;
                            if(domain!="all"){
                                request.svs_Query = '?q='+svs_query.val()+'&w='+domain;
                            }
                    }

                    //
                    top.HAPI.SystemMgr.ssearch_save(request,
                        function(response){
                            if(response.status == top.HAPI.ResponseStatus.OK){

                                var svsID = response.data;

                                if(!top.HAPI.currentUser.usr_SavedSearch){
                                    top.HAPI.currentUser.usr_SavedSearch = {};
                                }

                                top.HAPI.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];

                                $dlg.dialog( "close" );

                                //update UI
                                if(isEdit){
                                    $('#svs-'+svsID).html(request.svs_Name);
                                }else{
                                    $('#svsu-'+request.svs_UGrpID+'-'+domain).append( that._add_SVSitem(request.svs_Name, svsID) );
                                }


                            }else{
                                message.addClass( "ui-state-highlight" );
                                message.text(response.message);
                            }
                        }

                    );

                  }
            }

            allFields.on("keypress",function(event){
                  var code = (event.keyCode ? event.keyCode : event.which);
                  if (code == 13) {
                      __doSave();
                  }
            });


            $dlg.dialog({
              autoOpen: false,
              height: 240,
              width: 350,
              modal: true,
              resizable: false,
              title: top.HR('Edit saved search'),
              buttons: [
                {text:top.HR('Save'), click: __doSave},
                {text:top.HR('Cancel'), click: function() {
                  $( this ).dialog( "close" );
                }}
              ],
              close: function() {
                allFields.val( "" ).removeClass( "ui-state-error" );
              }
            });

            $dlg.dialog("open");

        });
    }else{
        //show dialogue
        this._fromDataToUI(svsID);
        this.edit_dialog.dialog("open");
    }

  },

  _deleteSavedSearch: function(svsID){


        var svs = top.HAPI.currentUser.usr_SavedSearch[svsID];
        if(!svs) return;

        top.HEURIST.util.showMsgDlg(top.HR("Delete? Please confirm"),  function(){
            
                    top.HAPI.SystemMgr.ssearch_delete({ids:svsID, UGrpID: svs[2]},
                        function(response){
                            if(response.status == top.HAPI.ResponseStatus.OK){

                                 //remove from UI
                                $('#svs-'+svsID).parent().remove();
                                 //remove from
                                delete top.HAPI.currentUser.usr_SavedSearch[svsID];

                            }else{
                                top.HEURIST.util.showMsgErr(response);
                            }
                        }

                    );
         }, "Confirmation");                 

  },


  // events bound via _on are removed automatically
  // revert other modifications here
  _destroy: function() {
    // remove generated elements
    if(this.edit_dialog) {
        this.edit_dialog.remove();
    }

    this.user_groups.remove();
  }

});

/*

jQuery(document).ready(function(){
    $('.accordion .head').click(function() {
        $(this).next().toggle();
        return false;
    }).next().hide();
});

*/