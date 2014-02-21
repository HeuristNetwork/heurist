    var Hul = top.HEURIST.util;

    //show list of selected rectypes    
    function showSelectedRecTypes(recordTypesSelected){
        
        var $mdiv = $("#selectedRectypes");
        $mdiv.empty();
        if(!Hul.isempty(recordTypesSelected)) {
                var arr = recordTypesSelected.split(","),
                    newvalue = "", txt="",
                    ind, dtName;
                    
                var $table =$("<table>")    
                            
                        for (ind in arr) {
                            var rtID = Number(arr[Number(ind)]);
                            if(!isNaN(rtID)){
                                dtName = top.HEURIST.rectypes.names[rtID];
                                if(!Hul.isnull(dtName)){
                                    
                                    //find dependent recordtypes
                                    
                                     var $tr =$("<tr>")
                                        .append($("<td>").css('font-weight','bold').text(dtName))
                                        .append($("<td>").append("<input type='checkbox' checked='checked'>"))
                                        .append($("<td>").append("<input type='checkbox' checked='checked' disabled='true'>"));
                                        
                                    
                                     
                                     var deprts = findDependentRecordTypes(rtID);
                                     if(deprts.length>0){
                                         var i,j;
                                         for (i=0;i<deprts.length;i++){
                                            if(i>0){
                                                var $tr =$("<tr>")
                                                   .append($("<td>").text(" "))
                                                   .append($("<td>").append("<input type='checkbox' checked='checked'>"))
                                                   .append($("<td>").append("<input type='checkbox' checked='checked' disabled='true'>"));
                                            }
                                            var rtID2 = deprts[i];
                                            dtName = "<b>"+top.HEURIST.rectypes.names[rtID2]+"</b>"; 
                                            
                                            //subdependent
                                            var deprts2 = findDependentRecordTypes(rtID2);
                                            var subnames = [];
                                            for (j=0;j<deprts2.length;j++){
                                                subnames.push(top.HEURIST.rectypes.names[deprts2[j]]);
                                            }
                                            if(subnames.length>0){
                                               dtName = dtName + " >> " + subnames.join(", ");    
                                            }
                                            
                                            $tr.append($("<td>").append(dtName));
                                            $tr.appendTo($table);
                                         }
                                     }else{
                                         $tr.append($("<td>").append(" "));
                                         $tr.appendTo($table);
                                     }
                                }
                            }
                        } //for

                $mdiv.append($("<p>").append($("<i>").html('Please select ONLY entity (record) types which you want to be represented as top level tab groups.<br /><br />'+
'Any entity which is referenced by a pointer or relationship marker within these entity types (eg. a site within a project, a context within a site, '+
'an artefact, sample or feature within a context) is indicated by >> and will be included automatically in the appropriate locations. The Form column '+
'shows whether a data entry form will be included in the app.')));
                        
                $mdiv.append($table);                
                
                $mdiv.append($("<p>").append($("<i>").html('Select additional top level tab groups for your app:')));                

                $("<div>").css('font-weight','bold').append("<input type='checkbox' id='ct0'><label for='ct0'>Control tab<label>").appendTo($mdiv);
                $("<div>").css('padding-left','40px').append("<input type='checkbox' checked='checked' id='ct1'><label for='ct1'>Start/stop synching (always on if not checked)<label>").appendTo($mdiv);
                $("<div>").css('padding-left','40px').append("<input type='checkbox' checked='checked' id='ct2'><label for='ct2'>Start Internal GPS (on from start if not checked)<label>").appendTo($mdiv);
                $("<div>").css('padding-left','40px').append("<input type='checkbox' id='ct3'><label for='ct3'>Connect to External GPS (leave unchecked if no external GPS)<label>").appendTo($mdiv);
                $("<div>").css('padding-left','40px').append("<input type='checkbox' checked='checked' id='ct4'><label for='ct4'>Switch tracklog on/off (tracklog unavailable if not checked)<label>").appendTo($mdiv);
                $("<br>").appendTo($mdiv);
                $("<div>").css('font-weight','bold').append("<input type='checkbox' id='mt0'><label for='mt0'>Map tab<label>").appendTo($mdiv);
                $("<div>").css('padding-left','40px').append("<input type='checkbox' id='mt1'><label for='mt1'>Ordnance survey 1:25K [tiled]<label>").appendTo($mdiv);
                $("<div>").css('padding-left','40px').append("<input type='checkbox' id='mt2'><label for='mt2'>Claire's sketch map [tiled]<label>").appendTo($mdiv);
                $("<div>").css('padding-left','40px').append("<input type='checkbox' id='mt3'><label for='mt3'>New road alignment [KML]<label>").appendTo($mdiv);
                        
            $("#rt_selected").val(recordTypesSelected);
            $("#buttondiv").css('display','block');                        
        }else{
            $("#rt_selected").val("");
            $("#buttondiv").css('display','none');                        
        }
        
    }

    //returns array of recordtypes dependent on given (constains in resopurces and relmarkers)    
    function findDependentRecordTypes(rtID){
        
        var idx_rst_dt_type  = top.HEURIST.rectypes['typedefs']['dtFieldNamesToIndex']["dty_Type"];
        var idx_rst_pointers = top.HEURIST.rectypes['typedefs']['dtFieldNamesToIndex']["rst_PtrFilteredIDs"];
        var rst_fields = top.HEURIST.rectypes['typedefs'][rtID]['dtFields'];
        var res = [];
        
        for (idx in rst_fields) {
            if(Hul.isNumber(idx)){
                var field = rst_fields[idx];
                var dt_type = field[idx_rst_dt_type];
                if(dt_type=="relmarker" || dt_type=="resource"){
                    var dt_pointers = field[idx_rst_pointers];
                    if(!Hul.isempty(dt_pointers)){
                        var ids = dt_pointers.split(",");
                        res = res.concat(ids);
                    }
                }
            }
        }//for
        
        return res;
    }//end findDependentRecordTypes
      
    /**
    * onSelectRectype
    *
    * listener of "Select Record Type" buttons
    * Shows a popup window where you can select record types
    */
    function onSelectRectype(_db)
    {

            var URL;
            var args = $("#rt_selected").val();
            var URL =  top.HEURIST.basePath + "admin/structure/selectRectype.html?type=select&db="+_db;

            if(args) {
                URL =  URL + "&ids=" + args;
            }

            Hul.popupURL(top, URL, {
                "close-on-blur": false,
                "no-resize": true,
                height: 480,
                width: 440,
                callback: showSelectedRecTypes
            });
    }
