<?php
/**
* actionTokens.php - Access tokens for Map services (used as base maps) and API.
* 
* It keeps tokens for authentication for third-party applications: Map services and APIs.
* They are defined as global vars in heuristConfigIni.php
* 
* @project     Heurist academic knowledge management system
* @package hclient\core
* @link https://HeuristNetwork.org
* @copyright (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author Artem Osmakov <osmakov@gmail.com>
* @author Ian Johnson <ian.johnson.heurist@gmail.com>
* @since 6.0
*/

$parentIni = dirname(__FILE__)."/../../../heuristConfigIni.php";

// parent directory configuration file is optional, hence include not require
$accessToken_MapBox = '';
$accessToken_MapTiles = '';
$accessToken_GeonamesAPI = '';

if (is_file($parentIni)){
    include_once $parentIni;
}

echo 'var accessToken_MapBox="'.$accessToken_MapBox.'",';
echo 'accessToken_MapTiles="'.$accessToken_MapTiles.'";';
?>
