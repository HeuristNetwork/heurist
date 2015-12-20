<?php
/*
* Copyright (C) 2005-2015 University of Sydney
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
* Header part for all pages
*
* list of included javascripts depends on record type
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2015 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/
?>
<html>
<head>
<META http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title><?=$g_title?></title>
<meta content="width=device-width; initial-scale=1.0; maximum-scale=2.0; user-scalable=1;" name="viewport">
<?php
if(isset($record)){
    print '<meta name="id" content="'.$record->id().'">';
    print '<meta name="class" content="'.$record->type_classname().'">';
}
?>
<link type="image/x-icon" href="<?=$urlbase ?>images/favicon.ico" rel="icon">
<link type="image/x-icon" href="<?=$urlbase ?>images/favicon.ico" rel="shortcut icon">
<link type="text/css" href="<?=$urlbase ?>style.css" rel="stylesheet">

<script type="text/javascript">
					RelBrowser = {
						baseURL: "<?=$urlbase ?>"
					};
				</script>
              
                
<script src="<?=$urlbase ?>js/timemap.js/2.0.1/lib/jquery-1.6.2.min.js" type="text/javascript"/></script>
<script type="text/javascript" src="<?=$urlbase ?>js/cookies.js"></script>
<script type="text/javascript" src="<?=$urlbase ?>js/fontsize.js"></script>
<script type="text/javascript" src="<?=$urlbase ?>js/history.js"></script>
<script type="text/javascript" src="<?=$urlbase ?>js/search.js"></script>
<script type="text/javascript" src="<?=$urlbase ?>js/menu.js"></script>
<script type="text/javascript" src="<?=$urlbase ?>js/tooltip.js"></script>
<script type="text/javascript" src="<?=$urlbase ?>js/swfobject.js"></script>
<script type="text/javascript" src="<?=$urlbase ?>js/media.js"></script>
<?php                           
if(!isset($starttime)){
	$starttime = explode(' ', microtime());
	$starttime = $starttime[1] + $starttime[0];
}

if(@$thisis_browsepage){
            print '<script src="'.$urlbase.'js/browse.js" type="text/javascript"></script>';
    if(true || $is_generation){
            print '<script type="text/javascript">';
			require("gen_browse.php");
            print '</script>';
	}else{
            print '<script  type="text/javascript" src="'.$urlbase.'php/gen_browse.php?r='.$type.'"></script>';
	}
            //print '<script type="text/javascript"></script>';
            //require('gen_browse.php');
            //print '</script>';
}
if(isset($extraScripts)){
	echo $extraScripts;
}

if(isset($record)){
?>
		<link rel="stylesheet" href="<?=$urlbase ?>boxy.css" type="text/css" media="screen"/>
<!--[if IE]>
	<link rel="stylesheet" href="<?=$urlbase ?>/boxy-ie.css" type="text/css" media="screen"/>
<![endif]-->
<!--[if lt IE 8]>
	<style type="text/css">
		.entity-information { zoom: 1; }
	</style>
<![endif]-->

						<script type="text/javascript" src="<?=$urlbase ?>js/jquery.boxy.js" type="text/javascript"></script>
						<script type="text/javascript" src="<?=$urlbase ?>js/popups.js" type="text/javascript"></script>

<?php
if($record->type()==RT_ENTITY || $record->type()==RT_MAP){
?>
						<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
						<script type="text/javascript">
							Timeline_urlPrefix = RelBrowser.baseURL+"js/timemap.js/2.0.1/lib/";
						</script>

						<script type="text/javascript" src="<?=$urlbase ?>js/timemap.js/2.0.1/lib/mxn/mxn.js?(googlev3)"></script>
						<script type="text/javascript" src="<?=$urlbase ?>js/timemap.js/2.0.1/lib/timeline-1.2.js"></script>
						<script type="text/javascript" src="<?=$urlbase ?>js/timemap.js/2.0.1/src/timemap.js"></script>
						<script type="text/javascript" src="<?=$urlbase ?>js/timemap.js/2.0.1/src/param.js"></script>
						<script type="text/javascript" src="<?=$urlbase ?>js/timemap.js/2.0.1/src/loaders/xml.js"></script>
						<script type="text/javascript" src="<?=$urlbase ?>js/timemap.js/2.0.1/src/loaders/kml.js"></script>

						<script src="<?=$urlbase ?>js/wkt2tm.js" type="text/javascript"></script>
						<script src="<?=$urlbase ?>js/mapping.js" type="text/javascript"></script>
<?php
}else if($record->type()==RT_ENTRY){
?>
					<script src="http://yui.yahooapis.com/2.7.0/build/yahoo/yahoo-min.js" type="text/javascript"></script>
					<script src="http://yui.yahooapis.com/2.7.0/build/event/event-min.js" type="text/javascript"></script>
					<script src="http://yui.yahooapis.com/2.7.0/build/history/history-min.js" type="text/javascript"></script>
					<script src="<?=$urlbase ?>js/highlight.js" type="text/javascript"></script>
<?php
}}
?>



</head>
<body class="">
<?php
if(isset($record) && $record->type()==RT_ENTRY){
?>
					<iframe id="yui-history-iframe" src="<?=$urlbase ?>images/minus.png"></iframe>
					<input id="yui-history-field" type="hidden"/>
<?php
}
?>
<div id="header"></div>
<div id="subheader">
	<div id="navigation">
		<div id="breadcrumbs"></div>
	</div>
</div>
<div id="middle">
<div id="container">
