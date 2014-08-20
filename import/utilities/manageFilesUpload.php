<?php

    /**
    * manageFilesUpload.php
    * Uploading of single/multiple/large data files to <dbname> directories, notably .../scratchspace
    * Intended primarily for uploading data files to be imported, but could also be used for uploading a bunch of images
    * Note that scratch directory should be marked web inacessible to ensure dangerous files cannot be uploaded and then executed
    * TODO: address security concern above
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2014 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @author      Ian Johnson     <ian.johnson@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     3.2
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

    require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
    require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');

    /* TODO: This function only works for individual files within PHP limit, we need to use bulk/segmented uploader
    Use jQuery File Upload plugin. By making use of Chunked file uploads (with chunks smaller than 4GB),
    the potential file size is unlimited.
    */
    require_once(dirname(__FILE__)."/../../records/files/uploadFile.php");

    if (! is_logged_in()) {
        header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
        return;
    }

    if (! is_admin()) { // TO DO: Change this to members of database managers
        print "<html><body><p>Only members of the Database Managers group may upload files</p></body></html>";
        return;
    }

?>

<html>

    <head>

        <!-- Force latest IE rendering engine or ChromeFrame if installed -->
        <!--[if IE]>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <![endif]-->
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <title>File upload manager</title>
        <link rel=stylesheet href="../../common/css/global.css" media="all">


        <!-- jQuery UI styles
        <link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/themes/base/jquery-ui.css" id="theme"> -->
        <link rel="stylesheet" href="../../external/jquery/jquery-ui-1.10.2/themes/base/jquery-ui.css" id="theme">
        <!-- Demo styles -->
        <link rel="stylesheet" href="../../external/jquery-file-upload/css/demo.css">
        <!--[if lte IE 8]>
        <link rel="stylesheet" href="../../external/jquery-file-upload/css/demo-ie8.css">
        <![endif]-->
        <style>
            /* Adjust the jQuery UI widget font-size: */
            .ui-widget {
                font-size: 0.95em;
            }
        </style>
        <!-- blueimp Gallery styles -->
        <link rel="stylesheet" href="http://blueimp.github.io/Gallery/css/blueimp-gallery.min.css">
        <!-- CSS to style the file input field as button and adjust the Bootstrap progress bars -->
        <link rel="stylesheet" href="../../external/jquery-file-upload/css/jquery.fileupload.css">
        <link rel="stylesheet" href="../../external/jquery-file-upload/css/jquery.fileupload-ui.css">

    </head>


    <body class="popup">

        <?php

            // TODO: Do we need this for anything?
            mysql_connection_overwrite(DATABASE);
            if(mysql_error()) {
                die("Sorry, could not connect to the database (mysql_connection_overwrite error)");
            }

            print "<h2>FILE MANAGEMENT</h2>";
            print "<p>This function allows members of the database managers group to upload ".
                "multiple files and/or large files to the database scratch space<br>";
            print "and delete files from that space. Most commonly files will be uploaded prior ".
                "to importing data from them or running the in situ import of images.<br>";
            print "The function is restricted to database owners/managers to reduce the risk of ".
                "other users filling the database with unwanted material.<br>";
            print "</p>";


            // ----Visit #1 - CHECK MEDIA FOLDERS --------------------------------------------------------------------------------

            if(!array_key_exists('mode', $_REQUEST)) {

                // Find out which folders are allowable - the default scratch space plus any
                // specified for FieldHelper indexing in Advanced Properties
                $query1 = "SELECT sys_MediaFolders, sys_MediaExtensions from sysIdentification where sys_ID=1";
                $res1 = mysql_query($query1);
                if (!$res1 || mysql_num_rows($res1) == 0) {
                    die ("<p><b>Sorry, unable to read the sysIdentification table from the current database. ".
                        "Possibly wrong database format, please consult Heurist team</b></p>");
                }

                // Get the set of directories defined in Advanced Properties as FieldHelper indexing directories
                // These are the most likely location for bulk upload (of images) and restricting to these directories
                // avoids the risk of clobbering the system's required folders (since most people won't know what they're called)
                $row1 = $row = mysql_fetch_row($res1);
                $mediaFolders = $row1[0];
                $dirs = explode(';', $mediaFolders); // get an array of folders
                // add the scratch directory, which will be the default for upload of material for import
                array_push($dirs, HEURIST_UPLOAD_ROOT.'scratch');

                // The defined list of file extensions for FieldHelper indexing.
                // For the moment keep this in as a restriction on file types which can be uploaded
                // Unlike indexing, we add the user-defined set to the default set
                $mediaExts = "jpg,jpeg,sid,png,gif,tif,tiff,bmp,rgb,doc,docx,odt,xsl,xslx,mp3,mp4,mpeg,avi,wmv,wmz,aif,aiff,"+
                    "mid,midi,wms,wmd,qt,evo,cda,wav,csv,tsv,tab,txt,rtf,xml,xsl,xslt,hml,kml,shp,htm,html,xhtml,ppt,pptx,zip,gzip,tar";
                    // default set to allow
                $mediaExts = $mediaExts.$row1[1];
                // TODO: we should eliminate any duplicate extensions which might have been added by the user

                if ($mediaFolders=="" || count($dirs) == 1) {
                    print ("<p><b>If you wish to upload files to a directory other than the scratch space, define the folders in <br />".
                        "Designer View > Database > Advanced Properties > Additional Folders for indexing.<br />".
                        "You may also add additional file extensions.</b></p>");
                }else{
                    print "<p><b>Allowable extensions for upload:</b> $mediaExts</p>";
                }

                print  "<p><a href='../../admin/setup/dbproperties/editSysIdentificationAdvanced.php?db=".HEURIST_DBNAME."&popup=1'
                    .title='Open form to edit properties which determine the handling of files and directories in the database upload folders'>"
                    ."Click here to set media/upload folders</a></p>";

            } // Visit #1


        ?>

        <!--  Acknowledgements: this code closely follows the published JQuery file upload examples -->

        <!-- The file upload form used as target for the file upload widget -->
        <form id="fileupload" action="//jquery-file-upload.appspot.com/" method="POST" enctype="multipart/form-data">

            <div><label for="upload_folder" style="color:black;">Select target folder:</label>
                <select name="folder" id="upload_folder">
                    <?php
                        foreach($dirs as $upload_dir) {
                            print '<option value="'.$upload_dir.'">'.$upload_dir.'</option>';
                        }
                    ?>
                </select>
                <br><br>
            </div>

            <!-- The fileupload-buttonbar contains buttons to add/delete files and start/cancel the upload -->
            <div class="fileupload-buttonbar">
                <div class="fileupload-buttons">
                    <!-- The fileinput-button span is used to style the file input field as button -->
                    <span class="fileinput-button">
                        <span>Add files...</span>
                        <input type="file" name="files[]" multiple>
                    </span>
                    <button type="submit" class="start">Start upload</button>
                    <button type="reset" class="cancel">Cancel upload</button>
                    <button type="button" class="delete">Delete</button>
                    <input type="checkbox" class="toggle">
                    <!-- The global file processing state -->
                    <span class="fileupload-process"></span>
                </div>

                <!-- The global progress state -->
                <div class="fileupload-progress fade" style="display:none">
                    <!-- The global progress bar -->
                    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100"></div>
                    <!-- The extended global progress state -->
                    <div class="progress-extended">&nbsp;</div>
                </div>

            </div>


            <!-- The table listing the files available for upload/download -->
            <table role="presentation">
            <tbody class="files"></tbody>
            </table>

        </form>


        <!-- The template to display files available for upload -->
        <script id="template-upload" type="text/x-tmpl">
            {% for (var i=0, file; file=o.files[i]; i++) { %}
                <tr class="template-upload fade">
                <td>
                <span class="preview"></span>
                </td>
                <td>
                <p class="name">{%=file.name%}</p>
                <strong class="error"></strong>
                </td>
                <td>
                <p class="size">Processing...</p>
                </td>
                <td>
                {% if (!i && !o.options.autoUpload) { %}
                <button class="start" disabled>Start</button>
                {% } %}
            {% if (!i) { %}
                <button class="cancel">Cancel</button>
                {% } %}
            </td>
            </tr>
            {% } %}
        </script>


        <!-- The template to display files available for download -->
        <script id="template-download" type="text/x-tmpl">
            {% for (var i=0, file; file=o.files[i]; i++) { %}
                <tr class="template-download fade">
                <td>
                <span class="preview">
                {% if (file.thumbnailUrl) { %}
                <a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" data-gallery><img src="{%=file.thumbnailUrl%}"></a>
                {% } %}
            </span>
            </td>
            <td>
            <p class="name">
            <a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" {%=file.thumbnailUrl?'data-gallery':''%}>{%=file.name%}</a>
            </p>
            {% if (file.error) { %}
                <div><span class="error">Error</span> {%=file.error%}</div>
                {% } %}
                </td>
                <td>
                <span class="size">{%=o.formatFileSize(file.size)%}</span>
                </td>
                <td>
                <button class="delete" data-type="{%=file.deleteType%}"
                data-url="{%=file.deleteUrl%}"{% if (file.deleteWithCredentials) { %}
                data-xhr-fields='{"withCredentials":true}'{% } %}>Delete</button>
                <input type="checkbox" name="delete" value="1" class="toggle">
                </td>
                </tr>
                {% } %}
        </script>


        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
        <script src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.4/jquery-ui.min.js"></script>
        <!-- The Templates plugin is included to render the upload/download listings -->
        <script src="http://blueimp.github.io/JavaScript-Templates/js/tmpl.min.js"></script>
        <!-- The Load Image plugin is included for the preview images and image resizing functionality -->
        <script src="http://blueimp.github.io/JavaScript-Load-Image/js/load-image.min.js"></script>
        <!-- The Canvas to Blob plugin is included for image resizing functionality -->
        <script src="http://blueimp.github.io/JavaScript-Canvas-to-Blob/js/canvas-to-blob.min.js"></script>
        <!-- blueimp Gallery script -->
        <script src="http://blueimp.github.io/Gallery/js/jquery.blueimp-gallery.min.js"></script>
        <!-- The Iframe Transport is required for browsers without support for XHR file uploads -->
        <script src="../../external/jquery-file-upload/js/jquery.iframe-transport.js"></script>
        <!-- The basic File Upload plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload.js"></script>
        <!-- The File Upload processing plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-process.js"></script>
        <!-- The File Upload image preview & resize plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-image.js"></script>
        <!-- The File Upload audio preview plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-audio.js"></script>
        <!-- The File Upload video preview plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-video.js"></script>
        <!-- The File Upload validation plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-validate.js"></script>
        <!-- The File Upload user interface plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-ui.js"></script>
        <!-- The File Upload jQuery UI plugin -->
        <script src="../../external/jquery-file-upload/js/jquery.fileupload-jquery-ui.js"></script>

        <script type="text/javascript">
            $(function () {
                'use strict';

                // Initialize the jQuery File Upload widget:
                $('#fileupload').fileupload({
                    // Uncomment the following to send cross-domain cookies:
                    //xhrFields: {withCredentials: true},
                    url: '<?=HEURIST_BASE_URL?>external/jquery-file-upload/server/php/'
                });
                // Enable iframe cross-domain access via redirect option:
                $('#fileupload').fileupload(
                    'option',
                    'redirect',
                    window.location.href.replace(
                        /\/[^\/]*$/,
                        '<?=HEURIST_BASE_URL?>external/jquery-file-upload/cors/result.html?%s'
                    )
                );

                // Load existing files:
                $('#fileupload').addClass('fileupload-processing');
                $.ajax({
                    // Uncomment the following to send cross-domain cookies:
                    //xhrFields: {withCredentials: true},
                    url: $('#fileupload').fileupload('option', 'url'),
                    dataType: 'json',

                    /*
                    disableImageResize: /Android(?!.*Chrome)|Opera/
                    .test(window.navigator.userAgent),
                    maxFileSize: 5000000,
                    acceptFileTypes: /(\.|\/)(gif|jpe?g|png)$/i
                    */

                    context: $('#fileupload')[0]
                }).always(function () {
                    $(this).removeClass('fileupload-processing');
                }).done(function (result) {
                    $(this).fileupload('option', 'done')
                    .call(this, $.Event('done'), {result: result});
                });
            });
        </script>

    </body>

</html>