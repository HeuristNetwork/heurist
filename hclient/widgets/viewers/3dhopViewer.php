<?php
/**
* 3DHOP viewer for Nexus 3D object (nxs or nxz)
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4.0
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once (dirname(__FILE__).'/../../../hsapi/System.php');
require_once (dirname(__FILE__).'/../../../hsapi/dbaccess/db_files.php');

define('ERROR_REDIR', dirname(__FILE__).'/../../framecontent/infoPage.php');

/*
Parameters:

db - database name
file or ulf_ID - obfuscation id for registred 3object in nxs or nxz format
@todo q  - standard heurist query that return record with 3object media (only first record will be taken)
@todo id - record with 3object media

*/
$is_not_inited = true;
$db = @$_REQUEST['db'];

// init main system class
$system = new System();

if($system->init($db, true, false)){

    if(@$_REQUEST['file'] || @$_REQUEST['ulf_ID']) { //ulf_ID is obfuscation id here

        $fileid = htmlspecialchars(@$_REQUEST['file']? $_REQUEST['file'] :@$_REQUEST['ulf_ID']);
            
        //find file info
        $listpaths = fileGetFullInfo($system, $fileid);
        if(is_array($listpaths) && count($listpaths)>0){
            $fileinfo = $listpaths[0]; //
            $fileExt = $fileinfo['ulf_MimeExt'];
            if ($fileExt=='nxs' || $fileExt=='nxz' || $fileExt=='ply'){
                
                $url = HEURIST_BASE_URL.'?db='.$db.'&file='.$fileid;
                $is_not_inited = false;    
                
            }else{
                $system->addError(HEURIST_ACTION_BLOCKED, 'Requested media is not NEXUS format');      
            }
                
        }else{
            $system->addError(HEURIST_NOT_FOUND, 'Requested file is not found. Check parameter "file"');      
        }
        
    }else{ // if(@$_REQUEST['id']){
        $system->addError(HEURIST_INVALID_REQUEST, 'Parameter "file" is not defined');
    }
}

if($is_not_inited){
    include ERROR_REDIR;
    exit();
}

//$url = "models/40microns.nxz";

define('EDIR','../../../external/3DHOP/');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<meta content="charset=UTF-8"/>
<title>3DHOP - 3D Heritage Online Presenter</title>
<!--STYLESHEET-->
<link type="text/css" rel="stylesheet" href="<?php echo EDIR;?>stylesheet/3dhop.css"/>  
<!--SPIDERGL-->
<script type="text/javascript" src="<?php echo EDIR;?>js/spidergl.js"></script>
<!--JQUERY-->
<script type="text/javascript" src="<?php echo EDIR;?>js/jquery.js"></script>
<!--PRESENTER-->
<script type="text/javascript" src="<?php echo EDIR;?>js/presenter.js"></script>
<!--3D MODELS LOADING AND RENDERING-->
<script type="text/javascript" src="<?php echo EDIR;?>js/nexus.js"></script>
<script type="text/javascript" src="<?php echo EDIR;?>js/ply.js"></script>
<!--TRACKBALLS-->
<script type="text/javascript" src="<?php echo EDIR;?>js/trackball_turntable.js"></script>
<script type="text/javascript" src="<?php echo EDIR;?>js/trackball_turntable_pan.js"></script>
<script type="text/javascript" src="<?php echo EDIR;?>js/trackball_pantilt.js"></script>
<script type="text/javascript" src="<?php echo EDIR;?>js/trackball_sphere.js"></script>
<!--UTILITY-->
<script type="text/javascript" src="<?php echo EDIR;?>js/init.js"></script>
</head>
<body>
<div id="3dhop" class="tdhop" onmousedown="if (event.preventDefault) event.preventDefault()"><div id="tdhlg"></div>
 <div id="toolbar">
  <img id="home"     title="Home"                  src="<?php echo EDIR;?>skins/dark/home.png"            /><br/>
  <img id="zoomin"   title="Zoom In"               src="<?php echo EDIR;?>skins/dark/zoomin.png"          /><br/>
  <img id="zoomout"  title="Zoom Out"              src="<?php echo EDIR;?>skins/dark/zoomout.png"         /><br/>
  <img id="light_on" title="Disable Light Control" src="<?php echo EDIR;?>skins/dark/lightcontrol_on.png" style="position:absolute; visibility:hidden;"/>
  <img id="light"    title="Enable Light Control"  src="<?php echo EDIR;?>skins/dark/lightcontrol.png"    /><br/>
  <img id="full_on"  title="Exit Full Screen"      src="<?php echo EDIR;?>skins/dark/full_on.png"         style="position:absolute; visibility:hidden;"/>
  <img id="full"     title="Full Screen"           src="<?php echo EDIR;?>skins/dark/full.png"            />
 </div>
 <canvas id="draw-canvas" style="background-image: url(<?php echo EDIR;?>skins/backgrounds/light.jpg)"/>
</div>
</body>
<script type="text/javascript">
var presenter = null;

function setup3dhop() { 
    presenter = new Presenter("draw-canvas");

    presenter.setScene({
        meshes: {
            "mesh_1" : { url: "<?php echo $url;?>", mType:"nexus" }
        },
        modelInstances : {
            "model_1" : { mesh : "mesh_1" }
        },
        trackball: { 
            type : TurnTableTrackball,
            trackOptions : {
                startPhi: 35.0,
                startTheta: 15.0,
                startDistance: 2.5,
                minMaxPhi: [-180, 180],
                minMaxTheta: [-30.0, 70.0],
                minMaxDist: [0.5, 3.0]
            }
        }
    });
}

function actionsToolbar(action) {
    if(action=='home') presenter.resetTrackball(); 
    else if(action=='zoomin') presenter.zoomIn();
    else if(action=='zoomout') presenter.zoomOut(); 
    else if(action=='light' || action=='light_on') { presenter.enableLightTrackball(!presenter.isLightTrackballEnabled()); lightSwitch(); } 
    else if(action=='full'  || action=='full_on') fullscreenSwitch(); 
}

$(document).ready(function(){
    init3dhop();

    setup3dhop();
});
</script>
</html>
