<?php
/*
 * publish.php - to save, load, preview and link to published searches
 *
 * @version 2007-04-23
 * @author Erik Baaij, Marco Springer, Kim Jackson, Maria Shvedova
 * (c) 2007 Archaeological Computing Laboratory, University of Sydney
 */

require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once('load_output_styles.php');

//______________________________________________________
//functions
//_______________________________________________________
//function to ccheck wether this search can be viewed (only owner or members of owner's workgoup)
function check_if_autorised(){
	$pubquery = 'select * from saved_searches where ss_id='.$_REQUEST['pub_id'];
	$res = mysql_query($pubquery);
	if ($pub = mysql_fetch_assoc($res)) {
		if ($pub['ss_usr_id'] != get_user_id()){
			$rows = get_wg();
		  	if (!empty($rows)){
		    	if (!array_key_exists($pub['ss_wg_id'], $rows)){
			 		$authorise = false;
			 	} else {
			 		$authorise = true;
			 	}
			}

		} else {
			$authorise = true;
		}
	}
	return $authorise;
}

//function to select workgroups users belong to
function get_wg(){
	$res = mysql_query('select a.grp_name, a.grp_id from '.USERS_DATABASE.'.Groups a, '.USERS_DATABASE.'.UserGroups b where b.ug_group_id =a.grp_id and b.ug_user_id = '.get_user_id(). ' ORDER BY a.grp_name');
	$rows = array();

	while ($row = mysql_fetch_assoc($res)){
		$rows[$row['grp_id']] = $row['grp_name'];
	}

	return $rows;
}


//function to force use of specific style
function force_pub ($id, $args){
	if ($args){
		$args = "&style=".$args;
	}

	$pub['ss_publish_args'] = $args;
 	mysql__update('saved_searches', 'ss_id='.$id, $pub);
}


function fill_styles(){
	$arr_styles = load_output_styles();//load output styles
	$arr_styles['genericxml'] = 'Generic XML'; //push the remaining "static" styles into array  - temporary measure, until we have all stylesheets written in xslt
  	$arr_styles['endnoterefer'] = 'EndNote REFER';
  	asort($arr_styles);

  	foreach ($arr_styles as $key => $value) { //load xsl-stylesheet based styles
   		if (@$force_style == $key) { //predefine the style if it is 'forced'
	 		echo '<option value="'.$key.'" selected>'.$value.'</option>';
   		} else {
	 		echo '<option value="'.$key.'">'.$value.'</option>';
   		}
 	}
}

//______________________________________________________________________________
//main section of the program


if (! is_logged_in()) {
    header('Location: ' . HEURIST_URL_BASE . 'common/connect/login.php');
    return;
}

mysql_connection_db_insert(DATABASE);


//force use of style
if (@$_REQUEST['op'] == 'force') {
	$ss_query = force_pub($_REQUEST['pub_id'], $_REQUEST['force_args']);

}


//fixme - check if missing query parameters
//this is required so the blank query will still appear in the saved pub searches list, and hence can be manipulated

//load

if (@$_REQUEST['pub_id']) {
	if ($authorise = check_if_autorised()){
		//get ss_query
 		$pubquery = 'select * from saved_searches where ss_id='.$_REQUEST['pub_id'];
 		$res = mysql_query($pubquery);
		if ($pub = mysql_fetch_assoc($res)) {
			$label = $pub['ss_name'];
			$wg_id = $pub['ss_wg_id'];

			if ($pub['ss_publish_args'] != ""){
		 		$fs = explode("=", strstr($pub['ss_publish_args'], "&style="));
 	     		$force_style = $fs[1];
				$forced = true;
			}

			$query = $pub['ss_query'];
	 	}

 	} else {
  		unset($_REQUEST['pub_id']);
  		die('The search id you have provided is either not valid or you are not authorised to change publishing settings on the search');
	}

} else { //if no pub_id
	header('Location: ' . BASE_PATH );
}

 //style based on wether it's forced or not : default to html

if (!@$force_style) {
 	$force_style = 'html';
}

?>

<html>
<head>
<!--
heurist.html
Copyright 2006, 2007 Tom Murtagh and Kim Jackson
http://heuristscholar.org/

Template file for major pages in Heurist
- - -

This file is part of Heurist.

Heurist is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

Heurist is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
-->
<title>Heurist</title>
<link rel=stylesheet href="../../css/heurist.css">
<link rel=stylesheet href="../../css/publish.css">

</head>
<body onLoad="init();">

<script src="../../common/lib/heurist.js"></script>	<!-- core HEURIST functions -->
<script src="../../common/lib/display-preferences.php"></script>	<!-- sets body css classes based on prefs in session -->

 <script type="text/javascript">
 <!--'
function init(){
	var style = '<?= $force_style ?>';
  	setContactLink ();
  	// a hack. NEED TO COME UP WITH A BETTER SOLUTION!! LOOKS fairly ugly when it flashes!
  	//set selected property on style select
  	for ( i=0; i<document.getElementById('style_select').length; i++ ) {
   		if (document.getElementById('style_select').options[i].value == style) {
    		document.getElementById('style_select').selectedIndex=i;
		}
   	}
  	setStyle(style, false);
}

function setStyle(style, checkIfForced) {
	var script;
	var args = '';
	var pub_id = '<?= $_REQUEST['pub_id'] ?>';
	var addjavascript;


	switch (style) {
		case 'genericxml':
		    script = 'xmlexport.php';
			args = '&style=' + style;
			addjavascript = false;
			break;

		case 'endnotexml':
		script = 'xmlexport.php';
			args = '&out=xml&style=' + style+'.xsl';
			addjavascript = false;
			break;

		case 'endnoterefer':
			script = 'search_endnote.php';
			args = '&style=' + style;
			addjavascript = false;
			break;

		default:
			script = 'xmlexport.php';
			args = '&style=' + style+'.xsl';
			addjavascript = true;
			break;
	}



   var e = document.getElementById('preview-frame');
   var forpreviewframe = '';
   var publink = '';

   if (document.getElementById('chk_force').checked) {
   		publink = '<?= HEURIST_URL_BASE ?>output/pubwizard/published.php?pub_id='+ pub_id;
		forpreviewframe = publink + args;
   } else {
   		publink = '<?= HEURIST_URL_BASE ?>output/pubwizard/published.php?pub_id='+pub_id+args;
		forpreviewframe = publink+'&depth=1';
   }


   var iframedisplay = '';

   	if (addjavascript) {
		iframedisplay = '<script type="text/javascript" src="'+publink+'&js">\n</script>\n<noscript>\n<iframe width="80%" height="70%" frameborder="0" src="'+ publink +'">\n</iframe>\n</noscript>';
   	} else {
   		iframedisplay ='<iframe width="80%" height="70%" frameborder="0" src="'+ publink +'">\n</iframe>';
 	}


  	if (e)
  	e.src = forpreviewframe;
 	e.onload = function(){
		document.getElementById("loading-msg").style.display="none";
	};

	document.getElementById('embed').value = iframedisplay;
   	//force specific style
    document.getElementById('force_args').value =  style;
    createDynaLink(publink);

	if (checkIfForced) {
		if (document.getElementById('chk_force').checked){
			document.forceform.submit();
		}
	}

} //eofun

function createDynaLink(publink){
	var b = document.getElementsByTagName('span')['link-span'];
  	if (b) {
   		b.parentNode.removeChild(b);
  	}
    //create the link
    newlink = document.createElement('a');
    newlink.setAttribute('id','dynalink');
    newlink.setAttribute('href', publink);
    newlink.setAttribute('target','_blank');
    linkText=document.createTextNode('link to published page');
    // add the text as a child of the link
    newlink.appendChild(linkText);

    //create div
    var newspan = document.createElement('span');
    newspan.setAttribute("id", "link-span");


  	//append new div right after embedding code div
  	document.getElementById('emb-span').appendChild(newspan);
  	//append link to div
  	document.getElementById('link-span').appendChild(newlink);
  	//add text to div
 	textdiv = document.createTextNode (' [opens new window - copy and paste to your web page].');
  	newspan.appendChild(textdiv);

}


function forceSearch() {
	//if unchecked, no forced style
	if (!document.getElementById('chk_force').checked) {
		document.getElementById('force_args').value = '';
	}

	document.forceform.submit();
	return false;
}




function setContactLink (){
	document.getElementById('contact-link').href += '?subject=HEURIST+v' + top.HEURIST.VERSION +
														'+user:' + '<?= get_user_username(); ?>' +
														'+' + 'Publishing Wizard';
}



 -->
 </script>
<table id=page class=expander border=0 cellspacing=0 cellpadding=0>
<tr><td id=header>

<table border=0 cellspacing=0 cellpadding=0 class=expander>
<tr>
<td id=logo-cell rowspan=2><a href="../../"><img src="../../common/images/hlogo-small.jpg" alt=Heurist></a></td>
<td id=title-cell rowspan=2>

<!-- PAGE TITLE -->
&nbsp;

</td>
<td id=quicklink-cell><nobr>
<a href="#" onClick="top.HEURIST.util.popupURL(window, '../../help/about.html'); return false;">About</a> |
<a href=http://www.timemap.net/tmissues.php target=_blank>Report a bug</a> |
<a id=contact-link href="mailto:<?=HEURIST_MAIL_TO_INFO?>">Contact us</a>
</nobr></td>
</tr>
<tr><td id=menu-cell>

<!-- MENU -->
&nbsp;

</td></tr>
</table>
</td></tr>
<tr><td id=header-bottom></td></tr>
<tr><td id=toolbar>

<!-- TOOLBAR -->

</td></tr>
<tr><td id=main><table id=main-table class=expander border=0 cellspacing=0 cellpadding=0>
 <tr>
 <td id=right-cell><table id=right-panel class="expander panel" border=0 cellspacing=0 cellpadding=0>
  <tr>
    <td class=panel-header>

<!-- RIGHT PANEL HEADER -->
   <b>Publishing wizard</b>

  </td>
    <td class=panel-header id=sideheader><a  href="../../help/index.html?Publish" target="_blank">case study</a></td>
  </tr>
  <tr><td class=panel-main colspan="2"><div class="expander panel-main-div" id=right-panel-content>
<!-- <div id=right-panel-content class="autosize panel-main-content">  -->
<!-- RIGHT PANEL CONTENT -->
<div id=pagetext>

<div id=wizard-subheading >Choose output style:</div>

 <div class=wizard-box>
 <div class="spacer">
 <form  id="forceform" name="forceform" method="post" >
 <select id="style_select" style="width: 180px;" onChange="setStyle(this.options[this.selectedIndex].value, true);">
 <? fill_styles();?>
 </select>
<input type="hidden" name="op" value="force">
<!-- default style -->
<input type="hidden" id="force_args" name="force_args" value="html">
<span style="padding: 5px;"></span><input title="Forces search to be obligatorily processed through the specified style" type="checkbox" name="chk_force" id="chk_force" <?= (@$forced ? 'checked' : '') ?>  onClick="forceSearch();">
Lock the output to be displayed in this style
<span style="padding: 5px;"><img src="../../common/images/lb.gif" align="top"><a  href="../../help/index.html?Publish" target="_blank">adding stylesheet</a></span>
</form>
</div>
</div>
<div class="breaker"></div>
<div class="breaker"></div>
<table cellpadding="0" cellspacing="0">
<tr>
<td id=wizard-subheading>Preview:
<div class="breaker"></div>
</td><td width="50px"><span style="padding-left:10px; color:#990000; " id="loading-msg">loading...</span><div class="breaker"></div></td>
<td valign="top"><iframe id="frame-rowcount" name="frame-rowcount" scrolling="no" width="120px;" height="16px;" frameborder="0" style="margin-left: 10px; overflow:hidden;" src="<?='/cocoon'.INSTALL_DIR.'/'. $_REQUEST['pub_id'] ?>/rowcount.xsl"></iframe></td>

</tr>
</table>
<iframe id="preview-frame" width="100%" height="50%" frameborder="0" style="border-bottom: 1px solid lightgrey; border-left: 1px solid lightgrey; border-right: 1px solid lightgrey; border-top: 1px solid lightgrey; overflow:scroll;" src=""></iframe>
<div class="breaker"></div>
<div class="breaker"></div>
<div><span id=wizard-subheading >Embed the code below in your webpage or use:</span><span style="padding:5px;"></span><span id = "emb-span"></span>
<span id ="link-span">
<a id="dynalink" href="published.php?pub_id=<?= $_REQUEST['pub_id'] ?>&style=<?= $force_style; ?>" target="_blank">link to published page</a> [opens new window - copy and paste to your web page].</span></div>
<div class="breaker"></div>

<span class="spacer"></span>
<textarea id="embed" style="width: 70%; height: 90px;">&lt;iframe width=&quot;80%&quot; height=&quot;70%&quot; frameborder=&quot;0&quot; src=&quot;published.php?pub_id=<?= $_REQUEST['pub_id'] ?>&style=<?= $force_style; ?>&quot;&gt;&lt;/iframe&gt;" </textarea>
<div class="breaker"></div><div class="breaker"></div>
<div id=query-highlight>&nbsp;<img src="../../common/images/small-magglass.gif" align="absbottom"/> Search query:
<?php echo $query;?>

</div>
<!--</div> -->
 </div></td></tr>



 </table></td></tr></table></td><!-- end of #main --></tr>

<!--
<tr><td id=footer>

PAGE FOOTER

</td></tr>
-->
</table>

</body>
</html>
