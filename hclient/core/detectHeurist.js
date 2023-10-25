/**
 * Find heurist object in parent windows or init new one if current window is a top most
 * 
 * @param {Window} win
 */
function _detectHeurist( win ){
    if(win.HEURIST4){ //defined
        return win;
    }

    try{
        win.parent.document;
    }catch(e){
        // not accessible - this is cross domain
        return win;
    }
    if (win.top == win.self) {
        //we are in frame and this is top most window and Heurist is not defined
        //lets current window will be heurist window
        return window;
    }else{
        return _detectHeurist( win.parent );
    }
}
//detect wether this window is top most or inside frame
if(!window.hWin) window.hWin = _detectHeurist(window);

//create canonical list of possible responses to server calls and append to hWin object
if(!window.hWin.ResponseStatus){
    
    window.hWin.ResponseStatus =
    {
            INVALID_REQUEST: "invalid",    // The Request provided was invalid.
            NOT_FOUND: "notfound",         // The requested object not found.
            OK: "ok",                      // The response contains a valid Result.
            REQUEST_DENIED: "denied",      // The webpage is not allowed to use the service. User permissions.
            ACTION_BLOCKED: "blocked",     // No enough rights or action is blocked by constraints
            DB_ERROR: "database",          // A request could not be processed due to a server database error. Most probably this is BUG. Contact developers
            UNKNOWN_ERROR: "unknown",      // A request could not be processed due to a server error. The request may succeed if you try again.
            SYSTEM_CONFIG: "syscfg", // System NON-fatal configuration. Contact system admin
            SYSTEM_FATAL: "system"           // System fatal configuration. Contact system admin
    };
    
}

if(!window.hWin.entityRecordCfg){
    window.hWin.entityRecordCfg = {
        "entityName": "records",

        "tableName": "Records",
        "tablePrefix": "rec",
        
        "helpContent": "records.html",
        
        "searchFormContent": "searchRecords.html",
        
        "entityTitle": "Record",
        "entityTitlePlural": "Records",
        "empty_remark": "No records match the search criteria",
        
        "fields": [
                {
                    "dtID": "rec_ID",
                    "keyField": true,
                    "dtFields":{
                        "dty_Type":"integer",
                        "dty_Role":"primary",
                        "rst_DisplayName": "ID:",
                        "rst_Display":"hidden"
                    }
                },
                {
                    "dtID": "rec_Title",
                    "titleField": true,
                    "dtFields":{
                        "dty_Type":"freetext",
                        "dty_Role":"title",
                        "dty_Size":1023,
                        "rst_DisplayName": "Record title",
                        "rst_DisplayHelpText": "", 
                        "rst_Display":"hidden"
                    }
                },
                {   
                    "dtID": "rec_URL",
                    "dtFields":{
                        "dty_Type":"freetext",
                        "dty_Size":2000,
                        "rst_DisplayWidth": 85,
                        "rst_DisplayName": "Record hyperlink URL",
                        "rst_DisplayHelpText": "This is a special URL field which is hyperlinked in search results. Use where one primary URL applies to <br>each record, eg. for internet bookmarks. These URLs can be auto-verified. Turn on/off in record attributes" ,
                        "rst_Display": "hidden"
                    }
                },
                {
                    "dtID": "rec_ScratchPad",
                    "titleField": true,
                    "dtFields":{
                        "dty_Type":"blocktext",
                        "dty_Size":65535,
                        "rst_DisplayName": "ScratchPad",
                        "rst_DisplayHelpText": "", 
                        "rst_Display": "hidden"
                    }
                },
                {
                    "dtID": "rec_RecTypeID",
                    "dtFields":{
                        "dty_Type":"integer",
                        "dty_Size":5,
                        "rst_DisplayName": "Record type",
                        "rst_DisplayHelpText": "" ,
                        "rst_Display": "hidden"
                    }
                },
                {
                    "dtID": "rec_OwnerUGrpID",
                    "dtFields":{
                        "dty_Type":"integer",
                        "dty_Size":5,
                        "rst_DisplayName": "Ownership",
                        "rst_DisplayHelpText": "" ,
                        "rst_Display": "hidden"
                    }
                },
                {
                    "dtID": "rec_NonOwnerVisibility",
                    "dtFields":{
                        "dty_Type":"freetext",
                        "dty_Size":20,
                        "rst_DisplayName": "Non owner access",
                        "rst_DisplayHelpText": "" ,
                        "rst_Display": "hidden"
                    }
                },
                {
                    "dtID": "rec_NonOwnerVisibilityGroups",
                    "dtFields":{
                        "dty_Type":"freetext",
                        "dty_Role":"virtual",
                        "dty_Size":2000,
                        "rst_Display": "hidden"
                    }
                }
        ]
    };

}


/*if (window.top != window.self) {
//this is frame
} else {
//top most window
window.h4win = window.top;
}*/

