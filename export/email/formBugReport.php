<?php

/*
* Copyright (C) 2005-2016 University of Sydney
*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except
* in compliance with the License. You may obtain a copy of the License at
*
* http://www.gnu.org/licenses/gpl-3.0.txt
*
* Unless required by applicable law or agreed to in writing, software distributed under the License
* is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
* or implied. See the License for the specific language governing permissions and limitations under
* the License.
*/

/**
* form to enter info about bug, on submitting it send this info as rectype 68-216 to Heurist email
* invoked from formEmailRecordPopup.html
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Export
*/


require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');

?>
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel=stylesheet href="../../common/css/global.css">
        <link rel=stylesheet href="../../common/css/edit.css">
        <link rel=stylesheet href="../../common/css/admin.css">

        <script src="../../common/php/loadHAPI.php"></script>
        <script>
            if (!top.HAPI && typeof HAPI == "object"){
                HAPI.importSymbols(this,top);
            }
        </script>

        <script type="text/javascript">

            function submit_email() {
                top.HEURIST.util.xhrFormSubmit(window.document.forms[0], handleSaved)
            }
            function handleSaved(json) {
                var vals = eval(json.responseText);
                if (! vals) {
                    window.location.reload();	// no changes to save ... make with the flishy-flashy
                    return;
                }

                if (vals.error) {
                    var ele = window.parent.document.getElementById('btnSubmit');
                    if(ele) ele.style.visibility = 'visible';
                    alert(vals.error);
                    return;
                }

                if (! vals.matches) {
                    // regular case -- we get back an object containing updates to the record details
                    //for (var i in vals)	parent.HEURIST.edit.record[i] = vals[i];
                    window.location.reload();
                }
                else {
                    // we have been supplied with a list of biblio records that look like this one
                    //if (parent.popupDisambiguation) parent.popupDisambiguation(vals.matches);
                }
            }

            function verifyInput() {
                // Return true if and only if all required fields have been filled in.
                // Otherwise, display a terse message describing missing fields.
                if (window.HEURIST.uploadsInProgress && window.HEURIST.uploadsInProgress.counter > 0) {
                    // can probably FIXME if it becomes an issue ... register an autosave with the upload completion handler
                    alert("File uploads are in progress ... please wait");
                    return false;
                }
                var elname = window.document.getElementById('type:<?=DT_BUG_REPORT_NAME?>[]');
                if(elname.value === ''){
                    alert("'Title' field is required. Specify concise informative title for bug or feature.");
                    elname.focus();
                    return false;
                }
                window.document.forms[0].heuristSubmit = submit_email;
                return true;
            }

            //
            function doUploadFile(event)
            {
                top.HEURIST.edit.uploadFileInput.call(window, event.target);
            }

            //need as callback after file upload
            function changed() {
            }
            //
            var uploadCompleted = function(inputDiv, bdValue) {
                //var thisRef = this;	// for great closure
                //var windowRef = this.document.parentWindow  ||  this.document.defaultView  ||  this.document._parentWindow;

                if (bdValue  &&  bdValue.file) {
                    // A pre-existing file: just display details and a remove button
                    var hiddenElt = inputDiv.hiddenElt = window.document.createElement("input");
                    hiddenElt.name = "type:<?=DT_BUG_REPORT_FILE?>[]";//inputDiv.name;
                    hiddenElt.value = hiddenElt.defaultValue = (bdValue && bdValue.file)? bdValue.file.id : "0";
                    hiddenElt.type = "hidden";
                    inputDiv.appendChild(hiddenElt);

                    var link = inputDiv.appendChild(window.document.createElement("a"));
                    if (bdValue.file.nonce) {
                        link.href = top.HEURIST.baseURL+"records/files/downloadFile.php/" + /*encodeURIComponent(bdValue.file.origName)*/
                        "?ulf_ID=" + encodeURIComponent(bdValue.file.nonce)+"&db=<?=HEURIST_DBNAME?>";
                    } else if (bdValue.file.remoteURL) {
                        link.href = bdValue.file.remoteURL;
                    }
                    link.target = "_surf";
                    link.onclick = function() { top.open(link.href, "", "width=300,height=300,resizable=yes"); return false; };

                    link.appendChild(window.document.createTextNode(bdValue.file.origName));	//saw TODO: add a title to this which is the bdValue.file.description

                    var linkImg = link.appendChild(window.document.createElement("img"));
                    linkImg.src = top.HEURIST.baseURL+"common/images/external_link_16x16.gif";
                    linkImg.className = "link-image";

                    var fileSizeSpan = inputDiv.appendChild(window.document.createElement("span"));
                    fileSizeSpan.className = "file-size";
                    fileSizeSpan.appendChild(window.document.createTextNode("[" + bdValue.file.fileSize + "]"));
                    /*
                    var removeImg = inputDiv.appendChild(window.document.createElement("img"));
                    removeImg.src = top.HEURIST.baseURL+"common/images/12x12.gif";
                    removeImg.className = "delete-file";
                    removeImg.title = "Remove this file";
                    var windowRef = window.document.parentWindow  ||  window.document.defaultView  ||  window.document._parentWindow;
                    top.HEURIST.registerEvent(removeImg, "click", function() {
                    thisRef.removeFile(inputDiv);
                    windowRef.changed();
                    });
                    */
                    inputDiv.valueElt = hiddenElt;
                    inputDiv.className = "file-div";
                }
            }


            function addFileUploadInput()
            {
                //var dt = ["AssociatedFile",null,"file","0",	"AssociatedFile","1","open","1",null,null,null,null,"221"];
                //var rfr =
                //var newInput = new top.HEURIST.edit.inputs.BibDetailFileInput(dt, rfr, fieldValues, container);

                var el = window.document.getElementById("fileUploadInput");
                var el_div = window.document.getElementById("fileUploadDiv");

                el_div.input = el;
                el.constructInput = uploadCompleted;
                el.replaceInput = function(inputDiv, bdValue) {
                    inputDiv.innerHTML = "";
                    this.constructInput(inputDiv, bdValue);
                };
            }

            //		jsonAction="stub"
        </script>

    </head>
    <body class="popup" width="600" height="400" onLoad="addFileUploadInput()"> <!-- size set at call -->
        <script src="../../common/js/utilsLoad.js"></script>
        <script src="../../common/php/displayPreferences.php"></script>


        <form method=post enctype="multipart/form-data"
            onsubmit="return verifyInput();"
            action="stub" target=save-frame>

            <input type="hidden" name="save-mode" id="save-mode">
            <input type="hidden" name="notes" id="notes">
            <input type="hidden" name="rec_url" id="rec_url">
            <input type="hidden" name="rectype" id="rectype" value="<?=RT_BUG_REPORT?>">
            <div id=all-inputs>

                <div class="input-row required">
                    <div class="input-header-cell">Title</div>
                    <div class="input-cell">
                        <input style="width: 70ex;" title="Title" id="type:<?=DT_BUG_REPORT_NAME?>[]" name="type:<?=DT_BUG_REPORT_NAME?>[]" class="in" autocomplete="off" type="text">
                        <div class="help prompt">Please give a concise but descriptive title for the bug or feature request</div>
                    </div>
                </div>
                <div class="input-row">
                    <div class="input-header-cell">Description</div>
                    <div class="input-cell">
                        <textarea style="width: 70ex;" title="Description" name="type:<?=DT_BUG_REPORT_DESCRIPTION?>[]" class="in" rows="3"></textarea>
                        <div class="help prompt">Please give a detailed description of the bug or feature request</div>
                    </div>
                </div>
                <div class="input-row">
                    <div class="input-header-cell">Steps to reproduce</div>
                    <div class="input-cell">
                        <textarea style="width: 70ex;" title="Steps to reproduce" name="type:<?=DT_BUG_REPORT_STEPS?>[]" class="in" rows="3"></textarea>
                        <div class="help prompt">Describe in detail what actions led up to the bug, or how you would get to the feature</div>
                    </div>
                </div>
                <div class="input-row">
                    <div class="input-header-cell">Screenshot
                    </div>
                    <div class="input-cell">
                        <div id="fileUploadDiv" class="file-div empty"
                            style="width: 80ex;" title="Screenshot">
                            <input class="file-select" id="fileUploadInput"
                                name="type:<?=DT_BUG_REPORT_FILE?>[]" type="file" onChange="doUploadFile(event)"></div>
                        <div class="help prompt">Upload a screen capture or document showing the bug or describing the feature in more detail (optional)</div>
                    </div>
                </div>
            </div>
        </form>

    </body>
</html>
