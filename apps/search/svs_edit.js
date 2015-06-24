var Hul = top.HEURIST4.util;

function hSvsEdit(args) {
     var _className = "SvsEdit",
         _version   = "0.4",
         edit_dialog = null,
         callback_method;

     const _NAME = 0, _QUERY = 1, _GRPID = 2;         
         
    /**
    * Initialization
    */
    function _init(args) {
        //this.currentSearch = currentSearch;
    }
    
    /**
    * Assign values to UI input controls
    * 
    * squery - need for new - otherwise it takes currentSearch
    * domain need for new 
    * 
    * return false if saved search and true if rules
    */
    function _fromDataToUI(svsID, squery, groupID){

        var $dlg = edit_dialog;
        if($dlg){
            $dlg.find('.messages').empty();

            var svs_id = $dlg.find('#svs_ID');
            var svs_name = $dlg.find('#svs_Name');
            var svs_query = $dlg.find('#svs_Query');
            var svs_ugrid = $dlg.find('#svs_UGrpID');
            var svs_rules = $dlg.find('#svs_Rules');
            var svs_notes = $dlg.find('#svs_Notes');
            //svs_query.parent().show();
            //svs_ugrid.parent().show();

            var selObj = svs_ugrid.get(0);
            Hul.createUserGroupsSelect(selObj, top.HAPI4.currentUser.usr_GroupsList,
                [{key:'bookmark', title:top.HR('My Bookmarks')}, 
                 {key:'all', title:top.HR('All Records')},
                 {key:0, title:top.HR('Searches for guests')}],
                function(){
                    svs_ugrid.val(Hul.isempty(groupID)?'all':groupID); //  top.HAPI4.currentUser.ugr_ID);
            });
            
            var isEdit = (parseInt(svsID)>0);

            if(isEdit){
                var svs = top.HAPI4.currentUser.usr_SavedSearch[svsID];
                
                
                var request = Hul.parseHeuristQuery(svs[_QUERY]);
                domain  = request.w;
                svs_ugrid.val(svs[_GRPID]==top.HAPI4.currentUser.ugr_ID ?domain:svs[_GRPID]);
                
                svs_ugrid.parent().hide();
                //svs_ugrid.attr('disabled', true);
                
                svs_id.val(svsID);
                svs_name.val(svs[_NAME]);
                svs_query.val( request.q );
                svs_rules.val( request.rules );
                svs_notes.val( request.notes );
                

            }else{ //add new saved search

                svs_id.val('');
                svs_name.val('');
                svs_rules.val('');
                svs_notes.val('');
                
                //var domain = 'all';

                if(Hul.isArray(squery)) { //this is RULES!!!
                    svs_rules.val(JSON.stringify(squery));
                    svs_query.val('');
                    
                } else if( squery && (squery.q || squery.rules) ) {
                    
                    svs_query.val( Hul.isempty(squery)?'':squery.q );
                    svs_rules.val( Hul.isArray(squery.rules)?JSON.stringify(squery.rules):squery.rules );
                    
                } else if(!Hul.isempty(squery)){
                    svs_query.val( squery ); 
                } else {
                   svs_query.val( '' ); 
                }
                
                //svs_ugrid.val('all');

                //fill with list of user groups in case non bookmark search
                /*var selObj = svs_ugrid.get(0);
                if(domain=="bookmark"){
                    svs_ugrid.empty();
                    Hul.addoption(selObj, 'bookmark', top.HR('My Bookmarks'));
                }else{
                    svs_ugrid.val(domain);
                }*/
                //svs_ugrid.parent().show();
                svs_ugrid.attr('disabled', !Hul.isempty(groupID));
            }
            
            var isRules = Hul.isempty(svs_query.val()) && !Hul.isempty(svs_rules.val()); 
            
            if(isRules){
                 svs_query.parent().hide();
                 svs_ugrid.parent().hide();
                 return true;
            }else{
                  svs_query.parent().show();
                  if(!isEdit) svs_ugrid.parent().show();
                  return false;
            }
            
            
            /* never need to hide! 
            if(domain=='rules' || (Hul.isempty(svs_query.val()) &&  !Hul.isempty(svs_rules.val())) ){  //THIS IS RULE!!!
                    svs_query.parent().hide();
                    svs_ugrid.parent().hide();
            }*/
            
        }
     }
     
    /**
    * Show faceted search wizard
    * 
    * @param params
    */
    function _showSearchFacetedWizard ( params ){

        if($.isFunction($('body').search_faceted_wiz)){ //already loaded
            showSearchFacetedWizard(params);  //this function from search_faceted_wiz.js
        }else{
            $.getScript(top.HAPI4.basePath+'apps/search/search_faceted_wiz.js', function(){ showSearchFacetedWizard(params); } );
        }

    }  
    
    // @todo
    function _editRules(ele_rules, squery) {
        
               var that = this;
                
                var url = top.HAPI4.basePath+ "page/ruleBuilderDialog.php?db=" + top.HAPI4.database;
                if(!Hul.isnull(ele_rules)){
                    url = url + '&rules=' + encodeURIComponent(ele_rules.val());
                }
                
                Hul.showDialog(url, { width:1200, height:600, callback: 
                    function(res){
                        if(!Hul.isempty(res)) {
                            if(res.mode == 'apply'){ //NOT USED ANYMORE //&& that._query_request){
                                
                                $(that.document).trigger(top.HAPI4.Event.ON_REC_SEARCH_APPLYRULES, [ res.rules ]); //global app event  
                                
                            }else if(res.mode == 'save') {
                                
                                if(Hul.isnull(ele_rules)){ //call from resultListMenu - create new rule
                                
                                     //replace rules   
                                     if(!Hul.isObject(squery)){
                                        squery = Hul.parseHeuristQuery(squery);
                                     }
                                     squery.rules = res.rules;
                                     //squery = Hul.composeHeuristQuery(params.q, params.w, res.rules, params.notes);
                                    
                                    //mode, groupID, svsID, squery, callback
                                    _showDialog('saved', null, null, squery ); //open new dialog
                                }else{
                                    ele_rules.val( JSON.stringify(res.rules) ); //assign new rules    
                                }
                            }
                        }
                    }});
        
        
    }
     
     /**
     * put your comment there...
     * 
     * @param svsID
     * @param squery
     * @param mode - faceted, rules or saved
     * @param callback
     */
     function _showDialog( mode, groupID, svsID, squery, callback ){

        if(callback){
            callback_method = callback;  
        } 
        
        if (mode == 'faceted'){
        
            var facet_params = {};    
            if(svsID>0){
                var svs = top.HAPI4.currentUser.usr_SavedSearch[svsID];
                if(svs){
                    try {
                        facet_params = $.parseJSON(svs[_QUERY]);
                    }
                    catch (err) {
                        // Do something about the exception here
                        Hul.showMsgDlg(top.HR('Can not init edit for faceted search. Corrupted parameters. Remove this search'), null, "Error");
                        return;
                    }
                }
            }
            
            _showSearchFacetedWizard( {svsID:svsID, domain:groupID, params:facet_params, onsave: callback_method }); 
            //function(event, request){   that._updateAfterSave(request, 'faceted');
            
        }else if (mode == 'rules' && Hul.isnull(svsID)){ //it happens for new rules only

            if(Hul.isnull(squery)) squery = {};
             squery.q = ''; // from rule builder we always save pure query only
             _editRules(null, squery);
            
        }else if(null == edit_dialog){
                //create new dialog
                
            var $dlg = edit_dialog = $( "<div>" ).addClass('ui-heurist-bg-light').appendTo(  $('body') );

            //load edit dialogue
            $dlg.load("apps/search/svs_edit.html?t="+(new Date().time), function(){

                //find all labels and apply localization
                $dlg.find('label').each(function(){
                    $(this).html(top.HR($(this).html()));
                })
                
                $dlg.find("#svs_btnset").css({'width':'20px'}).position({my: "left top", at: "right+4 top", of: $dlg.find('#svs_Rules') });
                
                $dlg.find("#svs_Rules_edit")                  
                    .button({icons: {primary: "ui-icon-pencil"}, text:false})
                    .attr('title', top.HR('Edit Rule Set'))
                    .css({'height':'16px', 'width':'16px'})
                    .click(function( event ) {
                        //that.
                        _editRules( $dlg.find('#svs_Rules') );
                    });
                    
                $dlg.find("#svs_Rules_clear")                  
                    .button({icons: {primary: "ui-icon-close"}, text:false})
                    .attr('title', top.HR('Clear Rule Set'))
                    .css({'height':'16px', 'width':'16px'})
                    .click(function( event ) {
                        $dlg.find('#svs_Rules').val('');
                    });

                var allFields = $dlg.find('input, textarea');

                //that.
                var isRules = _fromDataToUI(svsID, squery, groupID);

                function __doSave(){   //save search

                    var message = $dlg.find('.messages');
                    var svs_id = $dlg.find('#svs_ID');
                    var svs_name = $dlg.find('#svs_Name');
                    var svs_query = $dlg.find('#svs_Query');
                    var svs_ugrid = $dlg.find('#svs_UGrpID');
                    var svs_rules = $dlg.find('#svs_Rules');
                    var svs_notes = $dlg.find('#svs_Notes');

                    allFields.removeClass( "ui-state-error" );

                    var bValid = Hul.checkLength( svs_name, "Name", message, 3, 30 );
                    
                    if(bValid){
                        
                        var bOk = Hul.checkLength( svs_query, "Query", null, 1 );
                        if(!bOk) bOk = Hul.checkLength( svs_rules, "Rules", null, 1 );
                        if(!bOk){
                                message.text("Define query, rules or both.");
                                message.addClass( "ui-state-highlight" );
                                setTimeout(function() {
                                    message.removeClass( "ui-state-highlight", 1500 );
                                }, 500 );
                                bValid = false;         
                        }
                    }

                    if(bValid){

                        var svs_ugrid = svs_ugrid.val();
                        
                        var domain = 'all';    
                        if(svs_ugrid=="all" || svs_ugrid=="bookmark"){
                            domain = svs_ugrid;    
                            svs_ugrid = top.HAPI4.currentUser.ugr_ID;
                            //if(domain!="all"){query_to_save.push('w='+domain);}
                        }
                        if(Hul.isempty(svs_query.val()) && !Hul.isempty(svs_rules.val())){   //PURE RULE SET
                            domain = 'rules';
                            svs_ugrid = top.HAPI4.currentUser.ugr_ID; //@todo!!!! it may by rule accessible by guest
                        }
                        
                        var request = {  //svs_ID: svsID, //?svs_ID:null,
                            svs_Name: svs_name.val(),
                            svs_Query: Hul.composeHeuristQuery(svs_query.val(), domain, svs_rules.val(), svs_notes.val()),
                            svs_UGrpID: svs_ugrid,
                            domain:domain};

                        var isEdit = ( parseInt(svs_id.val()) > 0 );
                        if(isEdit){
                            request.svs_ID = svs_id.val();
                        }

                        //
                        top.HAPI4.SystemMgr.ssearch_save(request,
                            function(response){
                                if(response.status == top.HAPI4.ResponseStatus.OK){

                                    var svsID = response.data;

                                    if(!top.HAPI4.currentUser.usr_SavedSearch){
                                        top.HAPI4.currentUser.usr_SavedSearch = {};
                                    }

                                    top.HAPI4.currentUser.usr_SavedSearch[svsID] = [request.svs_Name, request.svs_Query, request.svs_UGrpID];

                                    $dlg.dialog( "close" );

                                    request.new_svs_ID = svsID;

                                    callback_method.call(that, null, request);
                                    //@todo that._updateAfterSave(request, 'saved');


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
                    height: 450,
                    width: 450,
                    modal: true,
                    resizable: false,
                    title: top.HR(isRules?'Edit Rule Set':'Edit saved search'),
                    buttons: [
                        {text:top.HR('Save'), click: __doSave},
                        {text:top.HR('Cancel'), click: function() {
                            $( this ).dialog( "close" );
                        }}
                    ],
                    close: function() {
                        allFields.val( "input, textarea" ).removeClass( "ui-state-error" );
                    }
                });

                $dlg.dialog("open");
                $dlg.parent().addClass('ui-dialog-heurist');

            });
        }else{
            //show dialogue
            var isRules = _fromDataToUI(svsID, squery, groupID);
            edit_dialog.dialog("option",'title', top.HR(isRules?'Edit Rule Set':'Edit saved search'));
            edit_dialog.dialog("open");
        }
         
     } //end  _showDialog
    
    
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},
        
        remove: function () { 
            //remove edit dialog from body
             edit_dialog.remove();
             edit_dialog = null;
        },

        show: function( mode, groupID, svsID, squery, callback ) { 
             _showDialog( mode, groupID, svsID, squery, callback );
        }

    }

    _init(args);
    return that;  //returns object
}
    
