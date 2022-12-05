<?php
/**
* 3D online viewer for 
*  obj, 3ds, stl, ply, gltf, glb, off, 3dm, fbx, dae, wrl, 3mf, ifc, brep, step, iges, fcstd, bim
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
if(true){

$is_not_inited = true;
$db = @$_REQUEST['db'];

// init main system class
$system = new System();

if($system->init($db, true, false)){

    if(@$_REQUEST['file'] || @$_REQUEST['ulf_ID']) { //ulf_ID is obfuscation id here

        $fileid = @$_REQUEST['file']? $_REQUEST['file'] :@$_REQUEST['ulf_ID'];
            
        //find file info
        $listpaths = fileGetFullInfo($system, $fileid);
        if(is_array($listpaths) && count($listpaths)>0){
            $fileinfo = $listpaths[0]; //
            $fileExt = $fileinfo['ulf_MimeExt'];
            $allowed_exts = array('obj', '3ds', 'stl', 'ply', 'gltf', 'glb', 'off', '3dm', 'fbx', 'dae', 'wrl', '3mf', 'ifc', 'brep', 'step', 'iges', 'fcstd', 'bim');
            
            if(in_array($fileExt, $allowed_exts)){
                
                $system->initPathConstants($db);
                
                $url = HEURIST_FILESTORE_URL.$fileinfo['fullPath'];
                //$url = HEURIST_BASE_URL.'?db='.$db.'&file='.$fileid;
                $is_not_inited = false;    
                
            }else{
                $system->addError(HEURIST_ACTION_BLOCKED, 'Requested 3D media is not supported format');      
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

}

define('EDIR','../../../external/3D/');

//$url = EDIR."models/car.glb";
//$url = EDIR."models/solids.obj";
//$url = 'https://mbh.huma-num.fr/sites/default/files/mbh-3d/alcazar_ms_11_reliure.fbx';
//$url = EDIR."models/alcazar_ms_11_reliure.fbx"; 

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<meta content="charset=UTF-8"/>
<title>3D Viewer</title>
<script type="text/javascript" src="<?php echo EDIR;?>o3dv.min.js"></script>
</head>
<body>
<div id="online_3d_viewer" class="online_3d_viewer" style="width:100%;height:100%">
</div>
</body>
<script type="text/javascript">

OV.SetExternalLibLocation ('libs'); // tell the engine where to find the libs folder

function setup3viewer() { 
    
    if(false){
        //attribute for div model="<?php echo $url?>"
        OV.Init3DViewerElements (); // init all viewers on the page    
        return;
    }
    
    // get the parent element of the viewer
    let parentDiv = document.getElementById ('online_3d_viewer');

    // initialize the viewer with the parent element and some parameters
    let opts1 = {
        backgroundColor : new OV.RGBAColor (255, 255, 255, 255),
        defaultColor : new OV.RGBColor (200, 200, 200),
        edgeSettings : {
            showEdges : false,
            edgeColor : new OV.RGBColor (0, 0, 0),
            edgeThreshold : 1
        },
        //toolbarDiv: 
        /*        
        environmentSettings : {
            environmentMap : [
                '<?php echo EDIR;?>envmaps/fishermans_bastion/posx.jpg',
                '<?php echo EDIR;?>envmaps/fishermans_bastion/negx.jpg',
                '<?php echo EDIR;?>envmaps/fishermans_bastion/posy.jpg',
                '<?php echo EDIR;?>envmaps/fishermans_bastion/negy.jpg',
                '<?php echo EDIR;?>envmaps/fishermans_bastion/posz.jpg',
                '<?php echo EDIR;?>envmaps/fishermans_bastion/negz.jpg'
            ],
            backgroundIsEnvMap : false
        },
        onModelLoaded : () => {
            let model = viewer.GetModel ();
            // do something with the model
        },
        camera : new OV.Camera (
            new OV.Coord3D (-1.5, -3.0, 2.0),
            new OV.Coord3D (0.0, 0.0, 0.0),
            new OV.Coord3D (0.0, 0.0, 1.0)
        ),
        */
    };
    
    let opts2 = {};
    
    let viewer = new OV.EmbeddedViewer (parentDiv, opts1);

    // load a model providing model urls
    viewer.LoadModelFromUrlList ([
        '<?php echo $url;?>'
    ]);
}

window.addEventListener('DOMContentLoaded', (event) => {
    setup3viewer();
});
</script>
</html>