    var Hul = top.HEURIST.util;
     
    function showSelectedRecTypes(recordTypesSelected){
        var txt = "";
        if(!Hul.isempty(recordTypesSelected)) {
                        var arr = recordTypesSelected.split(","),
                            newvalue = "", txt="",
                            ind, dtName;
                        for (ind in arr) {
                            var ind2 = Number(arr[Number(ind)]);
                            if(!isNaN(ind2)){
                                dtName = top.HEURIST.rectypes.names[ind2];
                                if(!Hul.isnull(dtName)){
                                    if (!txt) {
                                        newvalue = ind2;
                                        txt = dtName;
                                    }else{
                                        newvalue += "," + ind2;
                                        txt += ", " + dtName;
                                    }
                                }
                            }
                        } //for

            $("#rt_selected").val(recordTypesSelected);
        }else{
            $("#rt_selected").val("");
        }
        $("#selectedRectypes").html(txt);    
        
        $("#buttondiv").css('display',txt?'block':'none');                        
    }
      
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
