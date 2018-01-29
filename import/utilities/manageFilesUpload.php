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
    * @copyright   (C) 2005-2016 University of Sydney
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
        <link rel="stylesheet" type="text/css" href="../../ext/jquery-ui-themes-1.12.1/themes/base/jquery-ui.css" id="theme"/>
        
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
<!--        
        <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-ui.js"></script>
-->
        <link rel="stylesheet" href="http://blueimp.github.io/Gallery/css/blueimp-gallery.min.css">
        <!-- CSS to style the file input field as button and adjust the Bootstrap progress bars -->
        <link rel="stylesheet" href="../../external/jquery-file-upload/css/jquery.fileupload.css">
        <link rel="stylesheet" href="../../external/jquery-file-upload/css/jquery.fileupload-ui.css">

    </head>


    <body class="popup" style="margin:0 !important; color:black;">

        <?php

            // TODO: Do we need this for anything?
            mysql_connection_overwrite(DATABASE);
            if(mysql_error()) {
                die("Sorry, could not connect to the database (mysql_connection_overwrite error)");
            }

            print "<h2>FILE MANAGEMENT</h2>";
            print "<p>Members of the database managers group can upload ".
                "multiple files and/or large files to the database scratch space. <br>";
            print "Most commonly, files will be uploaded prior ".
                "to importing data from them or running the in situ multimedia import.<br>";
            print "The function is restricted to database managers to reduce the risk of ".
                "other users filling the database with unwanted material.<br>";
            print "</p>";


            // ----Visit #1 - CHECK MEDIA FOLDERS --------------------------------------------------------------------------------
            
            if(!array_key_exists('mode', $_REQUEST)) {

                // Find out which folders are allowable - the default scratch space plus any
                // specified for FieldHelper indexing in Advanced Properties
                $query1 = "SELECT sys_MediaFolders, sys_MediaExtensions from sysIdentification where 1";
                $res1 = mysql_query($query1);
                if (!$res1 || mysql_num_rows($res1) == 0) {
                    die ("<p><b>Sorry, unable to read the sysIdentification table from the current database. ".
                        "Possibly wrong database format, please consult Heurist team</b></p>");
                }

                $system_folders = array(HEURIST_THUMB_DIR,
                        HEURIST_FILESTORE_DIR."generated-reports/",
                        HEURIST_ICON_DIR,
                        HEURIST_FILESTORE_DIR."settings/",
                        HEURIST_FILESTORE_DIR.'documentation_and_templates/',
                        HEURIST_FILESTORE_DIR.'backup/',
                        HEURIST_FILESTORE_DIR.'entity/',
                        HEURIST_SMARTY_TEMPLATES_DIR,
                        HEURIST_XSL_TEMPLATES_DIR);
                if(defined('HEURIST_HTML_DIR')) array_push($system_folders, HEURIST_HTML_DIR);
                if(defined('HEURIST_HML_DIR')) array_push($system_folders, HEURIST_HML_DIR);


                // Get the set of directories defined in Advanced Properties as FieldHelper indexing directories
                // These are the most likely location for bulk upload (of images) and restricting to these directories
                // avoids the risk of clobbering the system's required folders (since most people won't know what they're called)
                $row1 = $row = mysql_fetch_row($res1);
                $mediaFolders = $row1[0];
                $dirs = explode(';', $mediaFolders); // get an array of folders
                $dirs2 = array();

                // MEDIA FOLDERS ALWAYS RELATIVE TO HEURIST_FILESTORE_DIR
                foreach ($dirs as $dir){
                    if( $dir && $dir!="*") {
                        
                        if(substr($dir, -1) != '/'){  //add last slash
                            $dir .= "/";
                        }

                        
                        /* changed to check that folder is in HEURIST_FILESTORE_DIR 
                        if(!file_exists($dir) ){ //probable this is relative
                            $orig = $dir;
                            chdir(HEURIST_FILESTORE_DIR);
                            $dir = realpath($dir);
                        }
                        */
                        $dir = str_replace('\\','/',$dir);     
                        
                        if(!( substr($dir, 0, strlen(HEURIST_FILESTORE_DIR)) === HEURIST_FILESTORE_DIR )){
                            chdir(HEURIST_FILESTORE_DIR);
                            $resdir = realpath($dir);   //realpath returns false if folder does not exist
                            if($resdir===false){
                                $dir = HEURIST_FILESTORE_DIR.'/'.$dir;
                                $dir = str_replace('//','/',$dir);     
                            }else{
                                $dir = $resdir;    
                            }

                            //realpath gives real path on remote file server
                            if(strpos($dir, '/srv/HEURIST_FILESTORE/')===0){
                                $dir = str_replace('/srv/HEURIST_FILESTORE/', HEURIST_UPLOAD_ROOT, $dir);
                            }else
                            if(strpos($dir, '/misc/heur-filestore/')===0){
                                $dir = str_replace('/misc/heur-filestore/', HEURIST_UPLOAD_ROOT, $dir);
                            }
                            $dir = str_replace('\\','/',$dir);     
                            if(!( substr($dir, 0, strlen(HEURIST_FILESTORE_DIR)) === HEURIST_FILESTORE_DIR )){
                                // Folder must be in heurist filestore directory
                                
                                print 'Folder "'.$dir.'" is not in heurist filestore directory<br>';
                                
                                continue;
                            }
                        }
                        
                        if(file_exists($dir) && is_dir($dir) && !in_array($dir, $system_folders)){
                            array_push($dirs2, $dir);
                        }else{
                            if(in_array($dir, $system_folders)){
                                print 'Folder "'.$dir.'" is system one and can not be used for file upload<br>';    
                            }else{
                                print 'Folder "'.$dir.'" does not exist<br>';
                                if (!mkdir($dir, 0777, true)) {
                                    print 'Unable to create or wtite into folder '.$dir;    
                                }else{
                                    print 'Created successfully<br>';    
                                    array_push($dirs2, $dir);
                                }
                                
                            }
                            
                        }
                    }
                }
                $dirs = $dirs2;

                // add the scratch directory, which will be the default for upload of material for import
                //array_push($dirs, HEURIST_FILESTORE_DIR.'scratch/');
                //array_push($dirs, HEURIST_FILES_DIR);
//error_log('3.'.print_r($dirs,true));                            

                // The defined list of file extensions for FieldHelper indexing.
                // For the moment keep this in as a restriction on file types which can be uploaded
                // Unlike indexing, we add the user-defined set to the default set
                $mediaExts = "jpg,jpeg,sid,png,gif,tif,tiff,bmp,rgb,doc,docx,odt,xsl,xslx,mp3,mp4,mpeg,avi,wmv,wmz,aif,aiff, ".
                    "mid,midi,wms,wmd,qt,evo,cda,wav,csv,tsv,tab,txt,rtf,xml,xsl,xslt,hml,kml,shp,htm,html,xhtml,ppt,pptx,zip,gzip,tar";
                    // default set to allow
                $mediaExts = $mediaExts.$row1[1];
                // TODO: we should eliminate any duplicate extensions which might have been added by the user

                if ($mediaFolders=="" || count($dirs) == 1) {
                    print ("<p>If you wish to upload files to a directory other than the scratch space, define the folders on the database administration page <br />".
                        "(in Database > Advanced Properties > Additional Folders for indexing), where you can also add additional file extensions.</p>");
                }else{
                    print "<p><b>Allowable extensions for upload:</b> $mediaExts</p>";
                }
                
                print  "<br/><p><a href='../../admin/setup/dbproperties/editSysIdentificationAdvanced.php?db=".HEURIST_DBNAME."&popup=3' "
                    ." title='Open form to edit properties which determine the handling of files and directories in the database upload folders'>"
                    ."Click here to set media/upload folders</a></p>";

            } // Visit #1


        ?>

        <!--  Acknowledgements: this code closely follows the published JQuery file upload examples -->

        <!-- The file upload form used as target for the file upload widget -->
        <form id="fileupload" action="//jquery-file-upload.appspot.com/" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="upload_thumb_dir" value="<?php echo HEURIST_THUMB_DIR; ?>"/>
            <input type="hidden" name="upload_thumb_url" value="<?php echo HEURIST_THUMB_URL; ?>"/>
            <div><label for="upload_folder" style="color:black;">Select target folder:</label>
                <select name="folder" id="upload_folder">
                    <?php
                        $is_dir_found = false;
                        foreach($dirs as $upload_dir) {
                            if(file_exists($upload_dir)){
                                print '<option value="'.$upload_dir.'">'.$upload_dir.'</option>';
                                $is_dir_found = true;
                            }
                        }
                    ?>
                </select>
                <br><br>
            </div>

            <!-- The fileupload-buttonbar contains buttons to add/delete files and start/cancel the upload -->
            <div class="fileupload-buttonbar" style="display:<?php print $is_dir_found?'block':'none';?>">
                <div class="fileupload-buttons">
                    <!-- The fileinput-button span is used to style the file input field as button -->
                    <span class="fileinput-button">
                        <span>Add files...</span>
                        <input type="file" name="files[]" multiple>
                    </span>
                    <button type="submit" class="start">Start uploads</button>
                    <button type="reset" class="cancel" id="btnCancel">Cancel uploads</button>
                    <!-- Ian 17/6/16 It's quite confusing what these are for
                    <button type="button" class="delete">Delete selected</button>
                    <input type="checkbox" class="toggle">
                    -->
                    <div style="display:inline-block;min-width:40px"></div>
                    <button id="btnFinished">Finished</button>
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
            <table role="presentation" style="display:<?php print $is_dir_found?'block':'none';?>">
            <tbody class="files"></tbody>
            </table>

            
            <div id="msgWarnDir" style="color:red;display:<?php print $is_dir_found?'none':'block';?>">
                You must set folders before uploading files
            </div>
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
                <button class="start" disabled>Upload</button>
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
                <div><span class="error">Upload error</span> {%=file.error%}</div>
              {% } %}
                </td>
                <td>
                <span class="size">{%=o.formatFileSize(file.size)%}</span>
                </td>
                <td>
                <button class="delete" data-type="{%=file.deleteType%}"
                data-url="{%=file.deleteUrl%}"{% if (file.deleteWithCredentials) { %}
                data-xhr-fields='{"withCredentials":true}'{% } %}>Remove</button>
                <!-- another confuising control - presumably meant to be a selection checkbox for multiple removals
                <input type="checkbox" name="delete" value="1" class="toggle">
                -->
              {% if (!file.error) { %}
                <div style="color:blue;display:inline-block;font-weight:bold;">Upload OK</div>
              {% } %}
                </td>
                </tr>
                {% } %}
        </script>


        <script src="../../ext/jquery-ui-1.12.1/jquery-1.12.4.js"></script>
        <script type="text/javascript" src="../../ext/jquery-ui-1.12.1/jquery-ui.js"></script>
        
        <!-- The Templates plugin is included to render the upload/download listings -->
        <script src="http://blueimp.github.io/JavaScript-Templates/js/tmpl.min.js"></script>
        <!-- The Load Image plugin is included for the preview images and image resizing functionality -->
        <script src="http://blueimp.github.io/JavaScript-Load-Image/js/load-image.all.min.js"></script>
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
                    upload_thumb_dir: '<?=HEURIST_THUMB_DIR?>', 
                    url: '<?=HEURIST_BASE_URL?>external/jquery-file-upload/server/php/'
                });
                
                $('#btnFinished')
                        .button({icons:{primary: 'ui-icon-check'}})
                        .click( function(e){ 
                            e.preventDefault();
                            $('#btnCancel').click(); 
                            return false; });
                
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
                
                
                /*
                $('#upload_folder').change(function(){
                    if($('#upload_folder').val()==''){
                        $('.fileupload-buttonbar').hide();
                        $('#presentation').hide();
                    }else{
                        $('.fileupload-buttonbar').show();
                        $('#presentation').show();
                    }    
                });
                */
                
            });
        </script>

    </body>

</html>