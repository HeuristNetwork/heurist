function hSvsEdit(args) {
     var _className = "SvsEdit",
         _version   = "0.4",
         edit_dialog = null,
         currentSearch = null;

    /**
    * Initialization
    */
    function _init(currentSearch) {

        this.currentSearch = currentSearch;
        
        
    }
    
    /**
    * Assign values to UI input controls
    * 
    * squery - need for new - otherwise it takes currentSearch
    * domain need for new 
    */
    function fromDataToUI(svsID, squery, domain){

        var $dlg = this.edit_dialog;
        if($dlg){
            $dlg.find('.messages').empty();

            var svs_id = $dlg.find('#svs_ID');
            var svs_name = $dlg.find('#svs_Name');
            var svs_query = $dlg.find('#svs_Query');
            var svs_ugrid = $dlg.find('#svs_UGrpID');
            var svs_rules = $dlg.find('#svs_Rules');
            var svs_notes = $dlg.find('#svs_Notes');
            svs_query.parent().show();
            svs_ugrid.parent().show();

            var isEdit = (parseInt(svsID)>0);

            if(isEdit){
                var svs = top.HAPI4.currentUser.usr_SavedSearch[svsID];

                var request = Hul.parseHeuristQuery(svs[_QUERY]);
                domain  = request.w;

                svs_ugrid.val(svs[2]==top.HAPI4.currentUser.ugr_ID ?domain:svs[_GRPID]);
                svs_ugrid.parent().hide();
                
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
                }else if(!Hul.isempty(squery)) {
                    svs_query.val( squery );
                }else if(Hul.isnull(this.currentSearch)){
                    svs_query.val( '' );
                }else{
                    //domain = this.currentSearch.w;
                    //domain = (domain=='b' || domain=='bookmark')?'bookmark':'all';
                    svs_query.val( this.currentSearch.q );
                    svs_rules.val( Hul.isArray(this.currentSearch.rules)?JSON.stringify(this.currentSearch.rules):this.currentSearch.rules );   //@todo - stringigy????
                }

                //fill with list of user groups in case non bookmark search
                var selObj = svs_ugrid.get(0);
                if(domain=="bookmark"){
                    svs_ugrid.empty();
                    Hul.addoption(selObj, 'bookmark', top.HR('My Bookmarks'));
                }else{
                    Hul.createUserGroupsSelect(selObj, top.HAPI4.currentUser.usr_GroupsList,
                        [{key:'all', title:top.HR('All Records')}],
                        function(){
                            svs_ugrid.val(top.HAPI4.currentUser.ugr_ID);
                    });
                    svs_ugrid.val(domain);
                }
                svs_ugrid.parent().show();
            }
            
            if(domain=='rules' || (Hul.isempty(svs_query.val()) &&  !Hul.isempty(svs_rules.val())) ){  //THIS IS RULE!!!
                    svs_query.parent().hide();
                    svs_ugrid.parent().hide();
            }
            
        }
     }

    
    
    //public members
    var that = {

        getClass: function () {return _className;},
        isA: function (strClass) {return (strClass === _className);},
        getVersion: function () {return _version;},



    }

    _init(currentSearch);
    return that;  //returns object
}
    
