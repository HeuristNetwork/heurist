function saveRecordAndClosePopup(){
    window.HEURIST.edit.save();
}
$.getMultiScripts = function(arr, path) {
    var _arr = $.map(arr, function(scr) {
        return $.getScript( (path||"") + scr );
    });

    _arr.push($.Deferred(function( deferred ){
        $( deferred.resolve );
    }));

    return $.when.apply($, _arr);
}    


var dbname;
var bugReportURL;
var workgroupPopupID;
var caller_id_element;

$(top.document).ready( function(){
    //$.getScript('../../common/js/utilsLoad.js', onDocumentReady );
    onDocumentReady();
}
);

//
//
//
function onDocumentReady(){
    
    dbname = top.HEURIST.getQueryVar("db");
    
    $.getMultiScripts([
          '../../common/php/displayPreferences.php?db='+dbname,
          '../../common/php/getMagicNumbers.php?db='+dbname,
          '../../common/php/loadUserInfo.php?db='+dbname,
          '../../common/php/loadCommonInfo.php?db='+dbname,
    ])
        .done(onScriptsReady)
        .fail(function(error) {
            console.log(error);         
            //alert('Error on script laoding '+error);
        }).always(function() {
            // always called, both on success and error
        }); 
    
}
  
  
function onScriptsReady(){  

window.HEURIST.edit = {
    publicHasChanged: false,
    save_in_progress:false,

    unchanged: function() {
        window.HEURIST.edit.publicHasChanged = false;
    },

    changed: function() {
        window.HEURIST.edit.publicHasChanged = true;
    },

    userCanEdit: function(){
        return true;
    },

    save: function() {
        // Attempt to save the public tab
        if(!window.HEURIST.edit.save_in_progress){

            window.HEURIST.edit.save_in_progress = true;
            var editFrame = document.getElementById("edit-frame");
            var contentWindow = editFrame.contentWindow;
            if (contentWindow) {
                
                var form = contentWindow.document.forms[0];
                if (form && form.onsubmit  &&  ! form.onsubmit()) {
                    window.HEURIST.edit.save_in_progress = false;
                    return;    // submit failed ... up to the individual form handlers to display messages
                }

                top.HEURIST.registerEvent(editFrame, "load", function() {
                    window.HEURIST.edit.save_in_progress = false;
                    var record = (window.HEURIST.record ? window.HEURIST.record :
                                    (window.HEURIST.edit.record ? window.HEURIST.edit.record : null));
                    if (!record) return;
                    window.close(record.title, record.bdValuesByType, record.bibID);
                });

                (form.heuristSubmit || form.submit)();

                return;
            }

        }
        // If we get here, there were no unsaved changes.
        // Grab the biblio title from the saved document, and close the window.
    }
};

window.HEURIST.parameters = top.HEURIST.parseParams(window.location.search);
var rectype = parseInt(window.HEURIST.parameters["rectype"]);
var titleBits = (window.HEURIST.parameters["title"] || "").split(/\s+/);
var db = (window.HEURIST.parameters["db"] || "");
// capitalise the first letter of each word in the title
for (var i=0; i < titleBits.length; ++i) {
    titleBits[i] = titleBits[i].charAt(0).toUpperCase() + titleBits[i].substring(1);
}
var title = titleBits.join(" ");
document.getElementById("title-val").appendChild(document.createTextNode(title || ""));
var url = window.HEURIST.parameters["url"];
var addr = window.HEURIST.parameters["addr"];
var trgRecID = window.HEURIST.parameters["trgRecID"];
var trgRecTitle = window.HEURIST.parameters["trgRecTitle"];
var type = window.HEURIST.parameters["type"];
var text = window.HEURIST.parameters["text"];
var parentOwnership = window.HEURIST.parameters["ownership"];
var parentVisibility = window.HEURIST.parameters["visibility"];

if (! rectype) {  //rectype is not defined - select from dropdown
    var setInitialrectype = function() {
        var rectype = rectypeDropdown.options[rectypeDropdown.selectedIndex].value;

        var editFrame = document.getElementById("edit-frame");
        editFrame.src = "../edit/tabs/publicInfoTab.html?db="+db+"&rectype="+rectype+
                    (parentOwnership? "&ownership="+parentOwnership : "") + 
                    (parentVisibility? "&visibility="+parentVisibility : "");

        editFrame.style.display = "block";

        rectypeDropdown.onchange = setrectype;
    }
    var setrectype = function() {
        if (! confirm("Existing details will be lost - continue?")) return;

        var rectype = rectypeDropdown.options[rectypeDropdown.selectedIndex].value;

        var editFrame = document.getElementById("edit-frame");
        editFrame.contentWindow.setrectype(rectype);
    }


    var rectypeDiv = document.getElementById("rectype-val");
    var rectypeDropdown = document.createElement("select");
        rectypeDropdown.id = "rectype";

    var j = 0;
    var firstOption = rectypeDropdown.options[j++] = new Option("(select a record type)", "");
        firstOption.disabled = true;
        firstOption.selected = true;

    // rectypes displayed in Groups by group display order then by display order within group
    var index;
    for (index in top.HEURIST.rectypes.groups){
        if (index == "groupIDToIndex" ||
        top.HEURIST.rectypes.groups[index].showTypes.length < 1) continue;
        var grp = document.createElement("optgroup");
        grp.label = top.HEURIST.rectypes.groups[index].name;
        rectypeDropdown.appendChild(grp);
        for (var recTypeIDIndex in top.HEURIST.rectypes.groups[index].showTypes) {
            var recTypeID = top.HEURIST.rectypes.groups[index].showTypes[recTypeIDIndex];
            var name = top.HEURIST.rectypes.names[recTypeID];
            rectypeDropdown.appendChild(new Option(name, recTypeID));
        }
    }


    rectypeDropdown.onchange = setInitialrectype;
    rectypeDiv.appendChild(rectypeDropdown);

} else {
    var editFrame = document.getElementById("edit-frame");
    editFrame.src = "../edit/tabs/publicInfoTab.html?db="+db+"&rectype="+rectype+
                    (title ? "&title="+encodeURIComponent(title):"") +
                    (addr ? "&addr="+addr : "") +
                    (type ? "&type="+type : "") +
                    (text ? "&text="+text : "") +
                    (trgRecID ? "&trgRecID="+trgRecID : "") +
                    (trgRecTitle ? "&trgRecTitle="+trgRecTitle : "") +
                    (parentOwnership? "&ownership="+parentOwnership : "") + 
                    (parentVisibility? "&visibility="+parentVisibility : "");
                
                

                    
    editFrame.style.display = "block";

    //ART: no need anymore since name of record type is specified in title of popup
    //document.getElementById("rectype-val").appendChild(document.createTextNode(top.HEURIST.rectypes.names[rectype]));
    document.getElementById("rectype-val").innerHTML = "";
}

}

