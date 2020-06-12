<?php

    /**
    * Search and select record. It is used in relationship editor and pointer selector
    * 
    * It is combination of generic record search by title (maybe it should be separated into widget) abd resultList widget
    *
    * @package     Heurist academic knowledge management system
    * @link        http://HeuristNetwork.org
    * @copyright   (C) 2005-2020 University of Sydney
    * @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
    * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
    * @version     4.0
    */

    /*
    * Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
    * with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
    * Unless required by applicable law or agreed to in writing, software distributed under the License is
    * distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
    * See the License for the specific language governing permissions and limitations under the License.
    */

define('PDIR','../../../');    
    
require_once(dirname(__FILE__)."/../initPage.php");
?>
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>external/jquery.fancytree/skin-themeroller/ui.fancytree.css" />
        <script type="text/javascript" src="<?php echo PDIR;?>external/jquery.fancytree/jquery.fancytree-all.min.js"></script>


        <!--      

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageUsrTags.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchUsrTags.js"></script>
        
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageDefFileExtToMimetype.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchDefFileExtToMimetype.js"></script>


        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageRecUploadedFiles.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchRecUploadedFiles.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageUsrTags.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchUsrTags.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/manageSysGroups.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/entity/searchSysGroups.js"></script>
-->
        <style>
            .fancytree-hide{
                display: none;
            }
            ul.fancytree-container li {
               padding:0; 
            }
            .fancytree-ext-filter .fancytree-node.fancytree-match{
                font-weight: normal;
            }
            .fancytree-focused{
                background-color:lightblue;
            }
            .fancytree-title{
                font-weight: normal !important;
                white-space:normal;                
            }
            .fancybox-container{
                z-index:99999999;
            }
        </style>
        
        <script type="text/javascript">
            // Callback function on map initialization
            function onPageInit(success){
                if(success){
                    
                    var opt_rectypes = $("#opt_rectypes").get(0);
                    window.hWin.HEURIST4.ui.createRectypeSelect( opt_rectypes, null, [{key:'',title:'select...'}], false);
                    
                    $("#opt_rectypes").change(function(){
                        if($(opt_rectypes).val()>0){
                            loadTreee($(opt_rectypes).val());
                        }
                    });
                                    
                }
            }
             
            //
            function loadTreee(rectype_id){
             
                var that = this;   
                var treediv = $('#divTree');
                
                var allowed_fieldtypes = ['enum','freetext',"year","date","integer","float","resource","relmarker"];
                
                window.hWin.HAPI4.SystemMgr.get_defs({rectypes: rectype_id,
                    mode:5, parentcode:null, //special node - returns data for treeview
                    fieldtypes:allowed_fieldtypes},  //ART20150810 this.options.params.fieldtypes.join() },

                    function(response){

                        if($.isArray(response) || response.status == window.hWin.ResponseStatus.OK){
                
                            if($.isArray(response)){
                                  treedata = response;
                            }else 
                            if(response.data.rectypes) {
                                treedata = response.data.rectypes;
                            }
                            treedata[0].expanded = true; //first expanded

//console.log(response.data.rectypes);                            
                            //setTimeout(function(){
                            treediv.fancytree({
                                //extensions: ["filter"],
                                //            extensions: ["select"],
                                checkbox: true,
                                selectMode: 3,  // hierarchical multi-selection
                                source: treedata,
                                lazyLoad: function(event, data){
                                    var node = data.node;
console.log(node.data.rt_ids);                                    
                                    
                                    var sURL = window.hWin.HAPI4.baseURL + "hsapi/controller/sys_structure.php";
                                    /*"?db="
                                        + window.hWin.HAPI4.database
                                        +'&mode=4&rectypes='+data.node.key
                                        +'&fieldtypes='+allowed_fieldtypes.join(',');
                                    */                    
                                    data.result = {
                                        url: sURL,
                                        data: {db:window.hWin.HAPI4.database, mode:5, parentcode:node.data.code, 
                                            rectypes:node.data.rt_ids, fieldtypes:allowed_fieldtypes}
                                    } 
                                                                       
                                },
                                beforeSelect: function(event, data){
                                    // A node is about to be selected: prevent this, for folder-nodes:
                                    if( data.node.hasChildren() ){
                                        return false;
                                    }
                                },
                                select: function(e, data) {
                                },
                                click: function(e, data){
                                   if($(e.originalEvent.target).is('span') && data.node.children && data.node.children.length>0){
                                       data.node.setExpanded(!data.node.isExpanded());
                                       //treediv.find('.fancytree-expander').hide();
                                       
                                        var showrev = true; //$('#fsw_showreverse').is(":checked");
                                        var tree = treediv.fancytree("getTree");
                                        tree.visit(function(node){ //show hide all reverse pointers
                                            if(node.data.isreverse==1){ 
                                                if(showrev){
                                                    $(node.li).show();
                                                }else{
                                                    $(node.li).hide();
                                                }
                                            }
                                        });
                                   }else if( data.node.lazy) {
                                       data.node.setExpanded( true );
                                   }
                                },
                                dblclick: function(e, data) {
                                    data.node.toggleSelected();
                                },
                                keydown: function(e, data) {
                                    if( e.which === 32 ) {
                                        data.node.toggleSelected();
                                        return false;
                                    }
                                }
                                // The following options are only required, if we have more than one tree on one page:
                                //          initId: "treeData",
                                //cookieId: "fancytree-Cb3",
                                //idPrefix: "fancytree-Cb3-"
                            });
                            //},1000);
                            
                            setTimeout(function(){
                                    var showrev = true;
                                    var tree = treediv.fancytree("getTree");
                                    tree.visit(function(node){
                                    if(node.data.isreverse==1){ //  window.hWin.HEURIST4.util.isArrayNotEmpty(node.children) &&
                                        if(showrev){
                                            $(node.li).show();
                                        }else{
                                            $(node.li).hide();
                                        }
                                    }
                                });
                            },1000);

                            
                        }else{
                            window.hWin.HEURIST4.msg.redirectToError(response.message);
                        }
                    }
                    );
                
            }
                
                
                
        </script>
    </head>

    <!-- HTML -->
    <body>
<?php
            /*
    function getRelativePath($basePath, $targetPath)
    {
        $targetPath = str_replace("\0", '', $targetPath);
        $targetPath = str_replace('\\', '/', $targetPath);
        
        
print $targetPath.'<br>';    

        if ($basePath === $targetPath) {
            return '';
        }
        //else  if(strpos($basePath, $targetPath)===0){
        //    $relative_path = $dirname;


        $sourceDirs = explode('/', isset($basePath[0]) && '/' === $basePath[0] ? substr($basePath, 1) : $basePath);
        $targetDirs = explode('/', isset($targetPath[0]) && '/' === $targetPath[0] ? substr($targetPath, 1) : $targetPath);
        array_pop($sourceDirs);
        $targetFile = array_pop($targetDirs);

        foreach ($sourceDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($sourceDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }

        $targetDirs[] = $targetFile;
        $path = str_repeat('../', count($sourceDirs)).implode('/', $targetDirs);

        // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
        // (see http://tools.ietf.org/html/rfc3986#section-4.2).
        return '' === $path || '/' === $path[0]
            || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? './'.$path : $path;
    }
    print HEURIST_FILESTORE_DIR.'<br>';
    print getRelativePath(HEURIST_FILESTORE_DIR, 'C:\\xampp\\htdocs\\HEURIST_FILESTORE\\artem_delete11\\klassiki\\');
     */   
?>    
      <div>
        <select name="sa_rectype" id="opt_rectypes" class="text ui-widget-content ui-corner-all" style="min-width:250px"></select>
      </div>

      <div style="width: 290px; font-size: smaller; margin-top: 0px; margin-bottom: 5px;" id="divTree">
      </div>
        
    </body>
</html>
