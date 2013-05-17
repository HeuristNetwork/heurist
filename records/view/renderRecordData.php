<?php

/*
* Copyright (C) 2005-2013 University of Sydney
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
* UI for viewing record 
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/View   
*/


$_SERVER['REQUEST_URI'] = @$_SERVER['HTTP_REFERER'];	// URI of the containing page

if (array_key_exists('alt', $_REQUEST)) define('use_alt_db', 1);

require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../common/php/Temporal.php');

mysql_connection_select(DATABASE);

require_once(dirname(__FILE__).'/../../common/php/getRecordInfoLibrary.php');
require_once(dirname(__FILE__).'/../../records/woot/woot.php');

require_once(dirname(__FILE__).'/../../records/files/uploadFile.php');


$noclutter = array_key_exists('noclutter', $_REQUEST);

$terms = getTerms();

// get a list of workgroups the user belongs to. - ARTEM - NOT USED
$wg_ids = mysql__select_array(USERS_DATABASE.'.sysUsrGrpLinks', 'ugl_GroupID', 'ugl_UserID='.get_user_id());
array_push($wg_ids, 0);

// if we get a record id then see if there is a personal bookmark for it.
 if (@$_REQUEST['recID'] && !@$_REQUEST['bkmk_id']) {
	$res = mysql_query('select * from usrBookmarks where bkm_recID = '.intval($_REQUEST['recID']).' and bkm_UGrpID = '.get_user_id());
	if (mysql_num_rows($res)>0) {
		$row = mysql_fetch_assoc($res);
		$_REQUEST['bkmk_id'] = $row['bkm_ID'];
	}
}
$bkm_ID = intval(@$_REQUEST['bkmk_id']);
$rec_id = intval(@$_REQUEST['recID']);
?>
<html>
<head>
	<link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/global.css">
	<script src="../../external/jquery/jquery-1.6.min.js"></script>
	<!-- script type="text/javascript" src="../../external/js/simple_js_viewer/script/core/Simple_Viewer_beta_1.1.js"></script>
	<script type="text/javascript" src="../../records/files/initViewer.js"></script -->
	<script type="text/javascript" src="../../common/js/hintDiv.js"></script> <!-- for mapviewer roolover -->

	<script type="text/javascript">

function zoomInOut(obj,thumb,url) {
	var thumb = thumb;
	var url = url;
	var currentImg = obj;
	if (currentImg.parentNode.className != "fullSize"){
		currentImg.src = url;
		currentImg.parentNode.className = "fullSize";

	}else{
		currentImg.src = thumb;
		currentImg.parentNode.className = "thumb_image";
	}
}

//
//
function showPlayer(obj, id, url) {

	var currentImg = obj;

	if (currentImg.parentNode.className != "fullSize"){  //thumb to player
		currentImg.style.display = 'none';
		currentImg.parentNode.className = "fullSize";
		//add content to player div
		top.HEURIST.util.sendRequest(url, function(xhr) {
			var obj = xhr.responseText;
//alert('!!!!'+obj);
			if (obj){
				var  elem = document.getElementById('player'+id);
				elem.innerHTML = obj;
				elem.style.display = 'block';
				elem = document.getElementById('lnk'+id);
				elem.style.display = 'block';
			}
		}, null);

	}
}
function hidePlayer(id) {
	var  elem = document.getElementById('player'+id);
	elem.innerHTML = '';
	elem.style.display = 'none';
	elem = document.getElementById('lnk'+id);
	elem.style.display = 'none';

	elem = document.getElementById('img'+id);
	elem.parentNode.className = "thumb_image";
	elem.style.display = 'inline-block';
}


function start_roll_open() {
		window.roll_open_id = setInterval(roll_open, 100);
}

function roll_open() {
	var wfe = window.frameElement;
	if (! wfe) return;
	var current_height = parseInt(wfe.style.height);

	var final_height = document.getElementById('bottom').offsetTop + 2;

	if (final_height > current_height + 30) {
		// setTimeout(roll_open, 100);

		// linear
		//wfe.style.height = (current_height + 20) + 'px';

		wfe.style.height = current_height + Math.round(0.5*(final_height-current_height)) + 'px';
	} else {
		wfe.style.height = final_height + 'px';

		clearInterval(window.roll_open_id);
	}
}

function sane_link_opener(link) {
	if (window.frameElement  &&  window.frameElement.name == 'viewer') {
		top.location.href = link.href;
		return false;
	}
}

function link_open(link) {
	if (top.HEURIST  &&  top.HEURIST.util  &&  top.HEURIST.util.popupURL) {
		top.HEURIST.util.popupURL(top, link.href, { width: 600, height: 500 });
		return false;
	}
	else return true;
}

//on document load
function add_sid() {
	if (top.HEURIST  &&  top.HEURIST.search  &&  top.HEURIST.search.results.querySid) {
		var e = document.getElementById("edit-link");
		if (e) {
			e.href = e.href.replace(/editRecord\.html\?/, "editRecord.html?sid="+top.HEURIST.search.results.querySid+"&");
		}
	}
}

	</script>
</head>
<body class="popup" onLoad="add_sid();">

<script src="../../records/edit/digitizer/mapViewer.js"></script>

<?php

if ($bkm_ID) {
	$res = mysql_query('select * from usrBookmarks left join Records on bkm_recID=rec_ID left join defRecTypes on rec_RecTypeID=rty_ID where bkm_ID='.$bkm_ID.' and bkm_UGrpID='.get_user_id().' and (not rec_FlagTemporary or rec_FlagTemporary is null)');
	$bibInfo = mysql_fetch_assoc($res);
	print_details($bibInfo);
} else if ($rec_id) {
	$res = mysql_query('select * from Records left join defRecTypes on rec_RecTypeID=rty_ID where rec_ID='.$rec_id.' and not rec_FlagTemporary');
	$bibInfo = mysql_fetch_assoc($res);
	print_details($bibInfo);
} else {
	print 'No details found';
}
?>

<div id=bottom><div></div></div>

</body>
</html>
<?php	/***** END OF OUTPUT *****/


// this functions outputs common info.
function print_details($bib) {
	print_header_line($bib);
	print_public_details($bib);
	print_private_details($bib);
	print_other_tags($bib);
	print_text_details($bib);
	print_relation_details($bib);
	print_linked_details($bib);
}


// this functions outputs the header line of icons and links for managing the record.
function print_header_line($bib) {
	$rec_id = $bib['rec_ID'];
	$url = $bib['rec_URL'];
	if ($url  &&  ! preg_match('!^[^\\/]+:!', $url))
		$url = 'http://' . $url;

	$webIcon = @mysql_fetch_row(mysql_query("select dtl_Value from recDetails where dtl_RecID=" . $bib['rec_ID'] . " and dtl_DetailTypeID=347"));  //MAGIC NUMBER
	$webIcon = @$webIcon[0];
//
?>

<div id=recID>Record ID:<?= htmlspecialchars($rec_id) ?><span class="link"><a id=edit-link class="normal"
	onClick="return sane_link_opener(this);"
	target=_new href="../edit/editRecord.html?db=<?=HEURIST_DBNAME?>&recID=<?= $rec_id ?>"><img src="../../common/images/edit-pencil.png" title="Edit record"></a></span>
</div>

<div class=HeaderRow style="margin-bottom:<?=((@$url)?'20px;':'0px;min-height:0px;')?>"><h2 style="text-transform:none; line-height:16px"><?= $bib['rec_Title'] ?></h2>
	<div id=footer>

        <h3>
        	<div <?="style='padding-left:20px;height:16px;background-repeat: no-repeat;background-image:url(".HEURIST_ICON_SITE_PATH.$bib['rty_ID'].".png)'"?> >
        		<?= htmlspecialchars($bib['rty_Name'])." [".$bib['rty_ID']."]" ?>
        	</div>
        </h3>
        <br>
        <?php if (@$url) { ?>
        <span class="link">
			<a target="_new" class="external-link" href="http://web.archive.org/web/*/<?=htmlspecialchars($url)?>">page history</a>
        	<a target="_new" class="external-link" href="<?=htmlspecialchars($url)?>"><?= output_chunker($url) ?></a>
        <?php if ($webIcon) print "<img id=website-icon src='" . $webIcon . "'>"; ?>
         </span>
        <?php } ?>
	</div>

<?php
}


//this  function displays private info if there is any.
function print_private_details($bib) {

	$res = mysql_query('select grp.ugr_Name,grp.ugr_Type,concat(grp.ugr_FirstName," ",grp.ugr_LastName) from Records, '.USERS_DATABASE.'.sysUGrps grp where grp.ugr_ID=rec_OwnerUGrpID and rec_ID='.$bib['rec_ID']);
	$workgroup_name = NULL;
	// check to see if this record is owned by a workgroup
	if (mysql_num_rows($res) > 0) {
		$row = mysql_fetch_row($res);
		$workgroup_name = $row[1] == 'user'? $row[2] : $row[0];
	}
	// check for workgroup tags
	$res = mysql_query('select grp.ugr_Name, tag_Text from usrRecTagLinks left join usrTags on rtl_TagID=tag_ID left join '.USERS_DATABASE.'.sysUGrps grp on tag_UGrpID=grp.ugr_ID left join '.USERS_DATABASE.'.sysUsrGrpLinks on ugl_GroupID=ugr_ID and ugl_UserID='.get_user_id().' where rtl_RecID='.$bib['rec_ID'].' and tag_UGrpID is not null and ugl_ID is not null order by rtl_Order');
	$kwds = array();
	while ($row = mysql_fetch_row($res)) array_push($kwds, $row);
	if ( $workgroup_name || count($kwds) || $bib['bkm_ID']) {
?>
<div class=detailRowHeader>Private
	<?php
			if ( $workgroup_name) {
	?>
	<div class=detailRow>
		<div class=detailType>Ownership</div>
		<div class=detail>
			<?php
				print '<span style="font-weight: bold; color: black;">'.htmlspecialchars($workgroup_name).'</span>';
				switch ($bib['rec_NonOwnerVisibility']) {
					case 'hidden':
						print '<span> - hidden to others</span></div></div>';
						break;
					case 'viewable':
						print '<span> - read-only to other logged-in users</span></div></div>';
						break;
					case 'public':
					default:
						print '<span> - read-only to general public</span></div></div>';
				}
			}

	$ratings = array("0"=>"none",
					"1"=> "*",
					"2"=>"**",
					"3"=>"***",
					"4"=>"****",
					"5"=>"*****");

	$rating_label = @$ratings[@$bib['bkm_Rating']?$bib['bkm_Rating']:"0"];
	?>

	<div class=detailRow>
	<div class=detailType>Rating</div>
	<div class=detail>
	 <!-- <span class=label>Rating:</span> --> <?= $rating_label? $rating_label : 'none' ?>
	</div>
	</div>

	<?php
			if ($kwds) {
	?>
	<div class=detailRow>
	<div class=detailType>Workgroup tags</div>
	<div class=detail>
	<?php
				for ($i=0; $i < count($kwds); ++$i) {
					$grp = $kwds[$i][0];
					$kwd = $kwds[$i][1];
					if ($i > 0) print '&nbsp; ';
					$grp_kwd = $grp.'\\\\'.$kwd;
					$label = 'Tag "'.$grp_kwd.'"';
					if (preg_match('/\\s/', $grp_kwd)) $grp_kwd = '"'.$grp_kwd.'"';
					print htmlspecialchars($grp.' - ').'<a class=normal style="vertical-align: top;" target=_parent href="'.HEURIST_SITE_PATH.'search/search.html?db='.HEURIST_DBNAME.'&ver=1&amp;q=tag:'.urlencode($grp_kwd).'&amp;w=all&amp;label='.urlencode($label).'" title="Search for records with tag: '.htmlspecialchars($kwd).'">'.htmlspecialchars($kwd).'<img style="vertical-align: middle; margin: 1px; border: 0;" src="'.HEURIST_SITE_PATH.'common/images/magglass_12x11.gif"></a>';
				}
	?>
	</div>
	</div>

	<?php
			}
		}
		if (array_key_exists('bkm_ID',$bib)) {
			print_personal_details($bib);
		}
	}


	//this function outputs the personal information from the bookmark
	function print_personal_details($bkmk) {
		$bkm_ID = $bkmk['bkm_ID'];
		$rec_ID = $bkmk['bkm_RecID'];
		$tags = mysql__select_array('usrRecTagLinks, usrTags',
									'tag_Text',
									"rtl_TagID=tag_ID and rtl_RecID=$rec_ID and tag_UGrpID = ".
									$bkmk['bkm_UGrpID']." order by rtl_Order");
	?>
	<div class=detailRow>
	<div class=detailType>Personal Tags</div>
	<div class=detail>
	<?php
		if ($tags) {
			for ($i=0; $i < count($tags); ++$i) {
				if ($i > 0) print '&nbsp; ';
				$tag = $tags[$i];
				$label = 'Tag "'.$tag.'"';
				if (preg_match('/\\s/', $tag)) $tag = '"'.$tag.'"';
				print '<a class=normal style="vertical-align: top;" target=_parent href="'.HEURIST_SITE_PATH.'search/search.html?db='.HEURIST_DBNAME.'&ver=1&amp;q=tag:'.urlencode($tag).'&amp;w=bookmark&amp;label='.urlencode($label).'" title="Search for records with tag: '.htmlspecialchars($tags[$i]).'">'.htmlspecialchars($tags[$i]).'<img style="vertical-align: middle; margin: 1px; border: 0;" src="'.HEURIST_SITE_PATH.'common/images/magglass_12x11.gif"></a>';
			}
			if (count($tags)) {
				print "<br>\n";
			}
		}
	?>
	</div>
	</div>

	<?php
	}


	function print_public_details($bib) {

		global $terms;

		$bds_res = mysql_query('select dty_ID,
		                               ifnull(rdr.rst_DisplayName, dty_Name) as name,
		                               dtl_Value as val,
		                               dtl_UploadedFileID,
		                               dty_Type,
		                               if(dtl_Geo is not null, astext(dtl_Geo), null) as dtl_Geo,
		                               if(dtl_Geo is not null, astext(envelope(dtl_Geo)), null) as bd_geo_envelope
		                          from recDetails
		                     left join defDetailTypes on dty_ID = dtl_DetailTypeID
		                     left join defRecStructure rdr on rdr.rst_DetailTypeID = dtl_DetailTypeID
		                                                          and rdr.rst_RecTypeID = '.$bib['rec_RecTypeID'].'
		                         where dtl_RecID = ' . $bib['rec_ID'] .'
		                      order by rdr.rst_DisplayOrder is null,
		                               rdr.rst_DisplayOrder,
		                               dty_ID,
		                               dtl_ID');

		$bds = array();
		$thumbs = array();

		while ($bd = mysql_fetch_assoc($bds_res)) {

			if ($bd['dty_ID'] == 603) { //DT_FULL_IMAG_URL
				array_push($thumbs, array(
					'url' => $bd['val'],
					'thumb' => HEURIST_SITE_PATH.'common/php/resizeImage.php?db='.HEURIST_DBNAME.'&file_url='.$bd['val']
				));
			}

			if ($bd['dty_Type'] == 'enum') {

				if(array_key_exists($bd['val'], $terms['termsByDomainLookup']['enum'])){
					$bd['val'] = output_chunker($terms['termsByDomainLookup']['enum'][$bd['val']][0]);
				}else{
					$bd['val'] = "";
				}

			}else if ($bd['dty_Type'] == 'relationtype') {

				$bd['val'] = output_chunker($terms['termsByDomainLookup']['relation'][$bd['val']][0]);

			}else if ($bd['dty_Type'] == 'date') {

				$bd['val'] = temporalToHumanReadableString($bd['val'], true);
				$bd['val'] = output_chunker($bd['val']);

			}else if ($bd['dty_Type'] == 'resource') {

				$res = mysql_query('select rec_Title from Records where rec_ID='.intval($bd['val']));
				$row = mysql_fetch_row($res);
				$bd['val'] = '<a target="_new" href="'.HEURIST_SITE_PATH.'records/view/renderRecordData.php?db='.HEURIST_DBNAME.'&recID='.$bd['val'].(defined('use_alt_db')? '&alt' : '').'" onclick="return link_open(this);">'.htmlspecialchars($row[0]).'</a>';
			}
			else if ($bd['dty_Type'] == 'file'  &&  $bd['dtl_UploadedFileID']) {

				//All works with recUploadedFiles MUST be centralized in uploadFile.php
				$filedata = get_uploaded_file_info($bd['dtl_UploadedFileID'], false);

				if($filedata){

					$filedata = $filedata['file'];
					$remoteSrc = $filedata['remoteSource'];

					//add to thumbnail list
					$isplayer = (array_key_exists('playerURL', $filedata) && $filedata['playerURL']);
					if (is_image($filedata) || $isplayer)
					{
						if($isplayer && is_image($filedata) && is_logged_in()){
							$filedata['playerURL'] .= "&annedit=yes";
						}

						array_push($thumbs, array(
							'id' => $filedata['id'],
							'url' => $filedata['URL'],   //download
							'thumb' => $filedata['thumbURL'],
							'player' => $isplayer?$filedata['playerURL'].(($remoteSrc=='youtube')?"":"&height=60%"):null  //link to generate player html
						));
					}

					if($filedata['URL']==$filedata['remoteURL']){ //remote resource
						$bd['val'] = '<a target="_surf" class="external-link" href="'.htmlspecialchars($filedata['URL']).'">'.htmlspecialchars($filedata['URL']).'</a>';
					}else{
						$bd['val'] = '<a target="_surf" class="external-link" href="'.htmlspecialchars($filedata['URL']).'">'.htmlspecialchars($filedata['origName']).'</a> '.($filedata['fileSize']>0?'[' .htmlspecialchars($filedata['fileSize']) . 'kB]':'');
					}
				}

				/* OLD WAY
				$res = mysql_query('select * from recUploadedFiles left join defFileExtToMimetype on ulf_MimeExt = fxm_Extension where ulf_ID='.intval($bd['dtl_UploadedFileID']));
				$file = mysql_fetch_assoc($res);
				if ($file) {
					$img_url = HEURIST_SITE_PATH.'records/files/downloadFile.php/'.$file['ulf_OrigFileName'].'?db='.HEURIST_DBNAME.'&ulf_ID='.$file['ulf_ObfuscatedFileID'];
					if ($file['fxm_MimeType'] == 'image/jpeg'  ||  $file['fxm_MimeType'] == 'image/gif'  ||  $file['fxm_MimeType'] == 'image/png') {
						array_push($thumbs, array(
							'url' => HEURIST_SITE_PATH.'records/files/downloadFile.php?ulf_ID='.$file['ulf_ObfuscatedFileID'],
							'thumb' => HEURIST_SITE_PATH.'common/php/resizeImage.php?ulf_ID='.$file['ulf_ObfuscatedFileID']
						));
					}
					$bd['val'] = '<a target="_surf" href="'.htmlspecialchars($img_url).'" class="external-link">'.htmlspecialchars($file['ulf_OrigFileName']).'</a> [' .htmlspecialchars($file['ulf_FileSizeKB']) . 'kB]';
				}
				*/
			} else {
				if (preg_match('/^https?:/', $bd['val'])) {
					if (strlen($bd['val']) > 100)
						$trim_url = preg_replace('/^(.{70}).*?(.{20})$/', '\\1...\\2', $bd['val']);
					else
						$trim_url = $bd['val'];
					$bd['val'] = '<a href="'.$bd['val'].'" target="_new">'.htmlspecialchars($trim_url).'</a>';
				} else if ($bd['dtl_Geo'] && preg_match("/^POLYGON[(][(]([^ ]+) ([^ ]+),[^,]*,([^ ]+) ([^,]+)/", $bd["bd_geo_envelope"], $poly)) {
					list($match, $minX, $minY, $maxX, $maxY) = $poly;
					if ($bd["val"] == "l"  &&  preg_match("/^LINESTRING[(]([^ ]+) ([^ ]+),.*,([^ ]+) ([^ ]+)[)]$/",$bd["dtl_Geo"],$matches)) {
						list($dummy, $minX, $minY, $maxX, $maxY) = $matches;
					}
					/*   redundant
                    $minX = intval($minX*10)/10;
					$minY = intval($minY*10)/10;
					$maxX = intval($maxX*10)/10;
					$maxY = intval($maxY*10)/10;
                    */

					switch ($bd["val"]) {
					  case "p": $type = "Point"; break;
					  case "pl": $type = "Polygon"; break;
					  case "c": $type = "Circle"; break;
					  case "r": $type = "Rectangle"; break;
					  case "l": $type = "Path"; break;
					  default: $type = "Unknown";
					}

					if ($type == "Point")
						$bd["val"] = "<b>Point</b> ".round($minX,7).", ".round($minY,7);
					else
						$bd['val'] = "<b>$type</b> X ".round($minX,7).", ".round($maxX,7).
                                                 " Y ".round($minY,7).", ".round($maxY,7);

                     $geoimage = "<img class='geo-image' src='".HEURIST_SITE_PATH."common/images/geo.gif' onmouseout='{mapViewer.hide();}' onmouseover='{mapViewer.showAtStatic(event, ".$bib['rec_ID'].");}'>&nbsp;";

                     $bd['val'] = $geoimage.$bd['val'];

				} else {
					$bd['val'] = output_chunker($bd['val']);
				}
			}

			array_push($bds, $bd);
		}
	?>
</div>



<div class=detailRowHeader>Shared
<div  class=thumbnail>
<?php
	foreach ($thumbs as $thumb) {
		print '<div class=thumb_image>';
		if($thumb['player']){
			print '<img id="img'.$thumb['id'].'" src="'.htmlspecialchars($thumb['thumb']).'" onClick="showPlayer(this,'.$thumb['id'].',\''. htmlspecialchars($thumb['player']) .'\')">';
			print '<div id="player'.$thumb['id'].'" style="min-height:240px;min-width:320px;display:none;"></div>';
		}else{  //for usual image
			print '<img src="'.htmlspecialchars($thumb['thumb']).'" onClick="zoomInOut(this,\''. htmlspecialchars($thumb['thumb']) .'\',\''. htmlspecialchars($thumb['url']) .'\')">';
		}
		print '<br/><div class="download_link">';
		if($thumb['player']){
			print '<a id="lnk'.$thumb['id'].'" href="#" style="display:none;" onclick="hidePlayer('.$thumb['id'].')">CLOSE</a>&nbsp;';
		}
		print '<a href="' . htmlspecialchars($thumb['url']) . '" target=_surf class="image_tool">DOWNLOAD</a></div>';
		print '</div>';
	};
?>
</div>
<?php
	foreach ($bds as $bd) {
		print '<div class=detailRow style="width:100%;border:none 1px #00ff00;"><div class=detailType>'.htmlspecialchars($bd['name']).'</div><div class=detail>'.$bd['val'].'</div></div>';
	}
?>

<div class=detailRow><div class=detailType>Updated</div><div class=detail><?= $bib['rec_Modified'] ?></div></div>
<div class=detailRow><div class=detailType>Cite as</div><div class=detail><a target=_blank class="external-link"
    href="<?= HEURIST_BASE_URL ?>resolver.php?recID=<?= $bib['rec_ID']."&db=".HEURIST_DBNAME ?>">
          <?= HEURIST_BASE_URL ?>resolver.php?recID=<?= $bib['rec_ID']."&db=".HEURIST_DBNAME ?></a></div></div></div>
<?php
}


function print_other_tags($bib) {
?>
<div class="detailRow">
	<div class="detailType">Tags</div>
	<div class="detail">
		<a target="_new" href="<?=HEURIST_SITE_PATH?>records/view/viewRecordTags.php?db=<?=HEURIST_DBNAME?>&recID=<?=$bib['rec_ID']?>" target=_top onclick="return link_open(this);">[Other users' tags]</a>
	</div>
</div>
<?php
}
$relRT = (defined('RT_RELATION')?RT_RELATION:0);
$relSrcDT = (defined('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:0);
$relTrgDT = (defined('DT_TARGET_RESOURCE')?DT_TARGET_RESOURCE:0);


function print_relation_details($bib) {

	global $relRT,$relSrcDT,$relTrgDT;

	$from_res = mysql_query('select recDetails.*
	                           from recDetails
	                      left join Records on rec_ID = dtl_RecID
	                          where dtl_DetailTypeID = '.$relSrcDT.
	                           ' and rec_RecTypeID = '.$relRT.
	                           ' and dtl_Value = ' . $bib['rec_ID']);        //primary resource
	$to_res = mysql_query('select recDetails.*
	                         from recDetails
	                    left join Records on rec_ID = dtl_RecID
	                        where dtl_DetailTypeID = '.$relTrgDT.
	                         ' and rec_RecTypeID = '.$relRT.
	                         ' and dtl_Value = ' . $bib['rec_ID']);          //linked resource

	if (mysql_num_rows($from_res) <= 0  &&  mysql_num_rows($to_res) <= 0) return;
?>
</div>
<div class=detailRowHeader>Related
<?php
	while ($reln = mysql_fetch_assoc($from_res)) {
		$bd = fetch_relation_details($reln['dtl_RecID'], true);

		print '<div class=detailRow>';
//		print '<span class=label>' . htmlspecialchars($bd['RelationType']) . '</span>';	//saw Enum change
		if(array_key_exists('RelTerm',$bd)){
			print '<div class=detailType>' . htmlspecialchars($bd['RelTerm']) . '</div>'; // fetch now returns the enum string also
		}
		print '<div class=detail>';
		if (@$bd['RelatedRecID']) {
			print '<a target=_new href="'.HEURIST_BASE_URL.'records/view/renderRecordData.php?db='.HEURIST_DBNAME.'&recID='.$bd['RelatedRecID']['rec_ID'].(defined('use_alt_db')? '&alt' : '').'" onclick="return link_open(this);">'.htmlspecialchars($bd['RelatedRecID']['rec_Title']).'</a>';
		} else {
			print htmlspecialchars($bd['Title']);
		}
		print '&nbsp;&nbsp;';
		if (@$bd['StartDate']) print htmlspecialchars(temporalToHumanReadableString($bd['StartDate']));
		if (@$bd['EndDate']) print ' until ' . htmlspecialchars(temporalToHumanReadableString($bd['EndDate']));
		print '</div></div>';
	}
	while ($reln = mysql_fetch_assoc($to_res)) {
		$bd = fetch_relation_details($reln['dtl_RecID'], false);

		print '<div class=detailRow>';
//		print '<span class=label>' . htmlspecialchars($bd['RelationType']) . '</span>';	//saw Enum change
		if(array_key_exists('RelTerm',$bd)){
			print '<div class=detailType>' . htmlspecialchars($bd['RelTerm']) . '</div>';
		}
		print '<div class=detail>';
		if (@$bd['RelatedRecID']) {
			print '<a target=_new href="'.HEURIST_BASE_URL.'records/view/renderRecordData.php?db='.HEURIST_DBNAME.'&recID='.$bd['RelatedRecID']['rec_ID'].(defined('use_alt_db')? '&alt' : '').'" onclick="return link_open(this);">'.htmlspecialchars($bd['RelatedRecID']['rec_Title']).'</a>';
		} else {
			print htmlspecialchars($bd['Title']);
		}
		print '&nbsp;&nbsp;';
		if (@$bd['StartDate']) print htmlspecialchars($bd['StartDate']);
		if (@$bd['EndDate']) print ' until ' . htmlspecialchars($bd['EndDate']);
		print '</div></div>';
	}
}


function print_linked_details($bib) {
global $relRT;
	$res = mysql_query('select *
	                      from recDetails
	                 left join defDetailTypes on dty_ID = dtl_DetailTypeID
	                 left join Records on rec_ID = dtl_RecID
	                     where dty_Type = "resource"
	                       and dtl_DetailTypeID = dty_ID
	                       and dtl_Value = ' . $bib['rec_ID'] . '
	                       and rec_RecTypeID != '.$relRT);

	if (mysql_num_rows($res) <= 0) return;
?>
<div class=detailRowHeader>Linked from

<div class=detailRow>
<div class=detailType>Referencing records</div>
<div class=detail><a href="<?=HEURIST_SITE_PATH?>search/search.html?db=<?=HEURIST_DBNAME?>&w=all&q=linkto:<?=$bib['rec_ID']?>" onClick="top.location.href = this.href; return false;"><b>Show list below as search results</b></a>
    <!--  <br> <i>Search = linkto:<?=$bib['rec_ID']?> <br>(returns records pointing TO this record)</i> -->
    </div></div>
<?php
	$rectypesStructure = getAllRectypeStructures();

	while ($row = mysql_fetch_assoc($res)) {

		print '<div class=detailRow>';
		print '<div class=detailType>Linked from</div>';
		print '<div class=detail>';
		print '<img class="rft" style="background-image:url('.HEURIST_ICON_SITE_PATH.$row['rec_RecTypeID'].'.png)" title="'.$rectypesStructure['names'][$row['rec_RecTypeID']].'" src="'.HEURIST_SITE_PATH.'common/images/16x16.gif">&nbsp;';
		print '<a target=_new href="'.HEURIST_SITE_PATH.'records/view/renderRecordData.php?db='.HEURIST_DBNAME.'&recID='.$row['rec_ID'].(defined('use_alt_db')? '&alt' : '').'" onclick="return link_open(this);">'.htmlspecialchars($row['rec_Title']).'</a>';
		print '</div></div>';
	}
}

function print_text_details($bib) {
	$cmts = getAllComments($bib["rec_ID"]);
	$result = loadWoot(array("title" => "record:".$bib["rec_ID"]));
	if (! $result["success"] && count($cmts) == 0) return;
?>
</div>
<div class=detailRowHeader>Text

<?php

	print_woot_precis($result["woot"],$bib);
	print_threaded_comments($cmts);
    print '<br>&nbsp;'; // avoid ugly spacing
}


/*
	loadWoot returns:

	{	success
		errorType?
		woot? : {	id
					title
					version
					creator
					permissions : {	type
									userId
									userName
									groupId
									groupName
					} +
					chunks : {	number
								text
								modified
								editorId
								ownerId
								permissions : {	type
												userId
												userName
												groupId
												groupName
								} +
					} +
		}
	}
Array (
	[id] => 2372
	[title] => record:45171
	[version] => 4
	[creator] => 1
	[permissions] => Array (
		[0] => Array (
			[type] => RW
			[userId] => 1
			[userName] => johnson
			[groupId] =>
			[groupName] => ) )
			[chunks] => Array (
				[0] => Array (
					[number] => 1
					[text] => test private to Ian
					[modified] => 2010-03-08 16:46:08
					[editorId] => 1
					[ownerId] => 1
					[permissions] => Array (
						[0] => Array (
							[type] => RW
							[userId] => 1
							[userName] => johnson
							[groupId] =>
							[groupName] => ) ) ) ) )
*/
function print_woot_precis($woot,$bib) {

	$content = "";
	foreach ($woot["chunks"] as $chunk) {
		$content .= $chunk["text"] . " ";
	}
	if (strlen($content) == 0) return;
?>
<div class=detailRow>
<div class=detailType>WYSIWYG Text</div>
<div class=detail>
<?php
	$content = preg_replace("/<.*?>/", " ", $content);
	if (strlen($content) > 500) {
		print substr($content, 0, 500) . " ...";
	} else {
		print $content;
	}
?>

  <div><a target=_blank href="<?=HEURIST_SITE_PATH?>records/woot/woot.html?db=<?=HEURIST_DBNAME?>&w=record:<?= $bib['rec_ID'] ?>&t=<?= $bib['rec_Title'] ?>">Click here to edit</a></div>
</div>
</div>
<?php
}


function print_threaded_comments($cmts) {
	if (count($cmts) == 0) return;
?>
<div class=detailRow>
<div class=detailType>Thread Comments</div>
<div class=detail>
<?php
	$printOrder = orderComments($cmts);
	$level = 1;
	foreach ($printOrder as $pair) {
		$level = 20 * $pair["level"];
		print '<div style=" font-style:italic; padding: 0px 0px 0px ';
		print $level;
		print  'px ;"> ['.$cmts[$pair['id']]["user"]. "] " . $cmts[$pair['id']]["text"] . "</div>";
	}
?>
</div>
</div>
</div>
<?php
}


function orderComments($cmts) {
		$orderedCmtIds = array();
		$orderErrCmts = array();
		foreach ($cmts as $id => $cmt) {
			//handle root nodes
			if ($cmt['owner'] == 0) {
				// skip deleted or children with deleted parents
				if ($cmt['deleted']) continue;
				$level = $cmts[$id]["level"] = 0;
				array_push($orderedCmtIds,$id);
			}else {	//note this algrithm assumes comments are ordered by date and that a child comment always has a more recent date
					// handle deleted or children of deleted
				if ($cmts[$cmt["owner"]]["deleted"]) $cmt["deleted"] = true;
				if ($cmt["deleted"]) continue;
				$ownerIndex = array_search($cmt["owner"],$orderedCmtIds);
				$insertIndex = count($orderedCmtIds);  //set insertion to end of array as default
				if($ownerIndex === FALSE) {  // breaks assumption write code to fix up the ordering here
					array_push($orderErrCmts,array( 'id' => $id, 'level' => 1));
				}else if ($ownerIndex +1 < $insertIndex) { //not found at the end of the array  note array index +1 = array offset
					if (array_key_exists($cmt["owner"],$cmts) && array_key_exists("level",$cmts[$cmt["owner"]])){
						$cmts[$id]["level"]  = 1 + $cmts[$cmt["owner"]]["level"] ; //child so increase the level
						for ($i = $ownerIndex+1; $i < $insertIndex; $i++) {
							if ( $cmts[$orderedCmtIds[$i]]["level"] < $cmts[$id]["level"]) { //found insertion point
								$insertIndex = $i;
								break;
							}
						}
						// insert id at index point
						array_splice($orderedCmtIds,$insertIndex,0,$id);
					}else{
						//something is wrong just add it to the end
						array_push($orderErrCmts,array( 'id' => $id, 'level' => 1));
					}
				}else{ //parent node is at the end of the array so just append
					$cmts[$id]["level"]  = 1 + $cmts[$cmt["owner"]]["level"] ; //child so increase the level
					array_push($orderedCmtIds,$id);
				}
			}
		}
		$ret = array();
		foreach ( $orderedCmtIds as $id) {
			array_push($ret, array( 'id' => $id, 'level' => $cmts[$id]['level']));
		}
		if (count($orderErrCmts)) $orderedCmtIds = array_merge($orderedCmtIds,$orderErrCmts);
		return $ret;
}


function output_chunker($val) {
	// chunk up the value so that it will be able to line-break if necessary
	$val = htmlspecialchars($val);
	return preg_replace('/(\\b.{15,20}\\b|.{20}.*?(?=[\x0-\x7F\xC2-\xF4]))/', '\\1<wbr>', $val);
}

?>
