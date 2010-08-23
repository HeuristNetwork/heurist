<?php
	/*<!-- info.php

	Copyright 2005 - 2010 University of Sydney Digital Innovation Unit
	This file is part of the Heurist academic knowledge management system (http://HeuristScholar.org)
	mailto:info@heuristscholar.org

	Concept and direction: Ian Johnson.
	Developers: Tom Murtagh, Kim Jackson, Steve White, Steven Hayes,
				Maria Shvedova, Artem Osmakov, Maxim Nikitin.
	Design and advice: Andrew Wilson, Ireneusz Golka, Martin King.

	Heurist is free software; you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation; either version 3
	of the License, or (at your option) any later version.

	Heurist is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
	even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.
	If not, see <http://www.gnu.org/licenses/>
	or write to the Free Software Foundation,Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

	-->*/


$_SERVER['REQUEST_URI'] = @$_SERVER['HTTP_REFERER'];	// URI of the containing page

if (array_key_exists('alt', $_REQUEST)) define('use_alt_db', 1);

require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');
mysql_connection_db_select(DATABASE);

require_once(dirname(__FILE__).'/../../records/relationships/relationships.php');
require_once(dirname(__FILE__).'/../../records/woot/woot.php');

$noclutter = array_key_exists('noclutter', $_REQUEST);

?>
<html>
 <head>
  <link rel="stylesheet" type="text/css" href="<?=HEURIST_SITE_PATH?>common/css/info.css"> 

  <script type="text/javascript">


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

function add_sid() {
	if (top.HEURIST  &&  top.HEURIST.search  &&  top.HEURIST.search.sid) {
		var e = document.getElementById("edit-link");
		if (e) {
			e.href = e.href.replace(/edit\.php\?/, "edit.php?sid="+top.HEURIST.search.sid+"&");
		}
	}
}

  </script>
 </head>
 <body onload="add_sid()" <?php if (! is_logged_in()) { print 'class=not-logged-in'; } else if ($noclutter) { print 'class=noclutter'; } ?>>

<?php
// get a list of workgroups the user belongs to.
$wg_ids = mysql__select_array(USERS_DATABASE.'.UserGroups', 'ug_group_id', 'ug_user_id='.get_user_id());
array_push($wg_ids, 0);

// if we get a record id tehn see if there is a personal bookmark for it.
 if (@$_REQUEST['bib_id'] && !@$_REQUEST['bkmk_id']) {
	$res = mysql_query('select * from personals where pers_rec_id = '.intval($_REQUEST['bib_id']).' and pers_usr_id = '.get_user_id());
	if (mysql_num_rows($res)>0) {
		$row = mysql_fetch_assoc($res);
		$_REQUEST['bkmk_id'] = $row['pers_id'];
	}
}
$pers_id = intval(@$_REQUEST['bkmk_id']);
$rec_id = intval(@$_REQUEST['bib_id']);
if ($pers_id) {
	$res = mysql_query('select * from personals left join records on pers_rec_id=rec_id left join rec_types on rec_type=rt_id where pers_id='.$pers_id.' and pers_usr_id='.get_user_id().' and (not rec_temporary or rec_temporary is null)');
	$bibInfo = mysql_fetch_assoc($res);
	print_details($bibInfo);
} else if ($rec_id) {
	$res = mysql_query('select * from records left join rec_types on rec_type=rt_id where rec_id='.$rec_id.' and not rec_temporary');
	$bibInfo = mysql_fetch_assoc($res);
	print_details($bibInfo);
} else {
	print 'No details found';
}
?>

<div class=detailRow>
 <div class=detailType></div>
 <dic class=detail><br><a href="#" onclick="document.body.className=''; return false;">Show additional detail</a></div>
</div>

<div id=bottom><div></div></div>
</body>
</html>
<?php	/***** END OF OUTPUT *****/


// this functions outputs common info.
function print_details($bib) {
	print_header_line($bib);
	print_public_details($bib);
	print_private_details($bib);
	print_text_details($bib);
	print_relation_details($bib);
	print_linked_details($bib);
	print_other_tags($bib);
}


// this functions outputs the header line of icons and links for managing the record.
function print_header_line($bib) {
	$rec_id = $bib['rec_id'];
	$url = $bib['rec_url'];
	if ($url  &&  ! preg_match('!^[^\\/]+:!', $url))
		$url = 'http://' . $url;

	$webIcon = @mysql_fetch_row(mysql_query("select rd_val from rec_details where rd_rec_id=" . $bib['rec_id'] . " and rd_type=347"));
	$webIcon = @$webIcon[0];
?>
<div class=HeaderRow><h2><?= htmlspecialchars($bib['rec_title']) ?></h2>
<div id=footer>
<h3><?= htmlspecialchars($bib['rt_name']) ?></h3>
<div id=recID>Record ID:<?= htmlspecialchars($rec_id) ?><nobr><span class="link"><a id=edit-link class=normal target=_self href="../editrec/edit.html?bib_id=<?= $rec_id ?>" onclick="return sane_link_opener(this);"><img src="../../common/images/edit-pencil.png" title="Edit Record"></a></span></nobr></div>
</div>
</div>
<div class=detailRowHeader>

<?php if (defined('EXPLORE_URL')  &&  $bib['rec_visibility'] != 'Hidden') { ?>
 <span class="link"><a target=_blank href="<?= EXPLORE_URL . $rec_id ?>"><img src="../../common/images/follow_links_16x16.gif">explore</a></span>
<?php } ?>
<?php if (@$url) { ?>
 <span class="link"><a target=_new href="http://web.archive.org/web/*/<?= htmlspecialchars($url) ?>"><img src="../../common/images/external_link_16x16.gif">page history</a></span>
 <span class="link"><a target=_new href="<?= htmlspecialchars($url) ?>"><img src="../../common/images/external_link_16x16.gif"><?= output_chunker($url) ?></a>
<?php if ($webIcon) print "<img id=website-icon src='" . $webIcon . "'>"; ?>
 </span>
<?php } ?>
 </div>
</div>
<?php
}


//this  function displays private info if there is any.
function print_private_details($bib) {

	$res = mysql_query('select grp_name from records, '.USERS_DATABASE.'.Groups where grp_id=rec_wg_id and rec_id='.$bib['rec_id']);
	$workgroup_name = NULL;
	// check to see if this record is owned by a workgroup
	if (mysql_num_rows($res) > 0) {
		$row = mysql_fetch_row($res);
		$workgroup_name = $row[0];
	}
	// check for workgroup tags
	$res = mysql_query('select grp_name, kwd_name from keyword_links left join keywords on kwl_kwd_id=kwd_id left join '.USERS_DATABASE.'.Groups on kwd_wg_id=grp_id left join '.USERS_DATABASE.'.UserGroups on ug_group_id=grp_id and ug_user_id='.get_user_id().' where kwl_rec_id='.$bib['rec_id'].' and kwd_wg_id and ug_id is not null order by kwl_order');
	$kwds = array();
	while ($row = mysql_fetch_row($res)) array_push($kwds, $row);
	if ( $workgroup_name || count($kwds) || $bib['pers_id']) {
?>
<div class=detailRowHeader>Private info
	<?php
			if ( $workgroup_name) {
	?>
	<div class=detailRow>
		<div class=detailType>Workgroup</div>
		<div class=detail>
			<?php
				print '<span style="font-weight: bold; color: black;">'.htmlspecialchars($workgroup_name).'</span>';
				if ($bib['rec_visibility'] == 'Viewable') print '<span> - read-only to others</span></div></div>';
				else print '<span> - hidden to others</span></div></div>';
				}
			?>
		
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
					print htmlspecialchars($grp.' - ').'<a class=normal style="vertical-align: top;" target=_parent href="'.HEURIST_SITE_PATH.'search/search.html?ver=1&amp;q=tag:'.urlencode($grp_kwd).'&amp;w=all&amp;label='.urlencode($label).'">'.htmlspecialchars($kwd).'<img style="vertical-align: middle; margin: 1px; border: 0;" src="'.HEURIST_SITE_PATH.'common/images/tiny-magglass.gif"></a>';
				}
	?>
	</div>
	</div>

	<?php
			}
		}
		if (array_key_exists('pers_id',$bib)) {
			print_personal_details($bib);
		}
	}


	//this function outputs the personal information from the bookmark
	function print_personal_details($bkmk) {
		$pers_id = $bkmk['pers_id'];

		$tags = mysql__select_array('keyword_links, keywords',
		                            'kwd_name',
		                            'kwl_kwd_id=kwd_id and kwl_pers_id='.$pers_id.' and kwd_wg_id is null order by kwl_order');
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
				print '<a class=normal style="vertical-align: top;" target=_parent href="'.HEURIST_SITE_PATH.'search/search.html?ver=1&amp;q=tag:'.urlencode($tag).'&amp;w=bookmark&amp;label='.urlencode($label).'">'.htmlspecialchars($tags[$i]).'<img style="vertical-align: middle; margin: 1px; border: 0;" src="'.HEURIST_SITE_PATH.'common/images/tiny-magglass.gif"></a>';
			}
			if (count($tags)) {
				print "<br>\n";
			}
		}
	?>
	</div>
	</div>
	<?php

		$res = mysql_query('select ri_label, rc_label, rq_label from ratings_interest, ratings_content, ratings_quality where ri_id='.intval($bkmk['pers_interest_rating']).' and rc_id='.intval($bkmk['pers_content_rating']).' and rq_id='.intval($bkmk['pers_quality_rating']));
		list($ri_label, $rc_label, $rq_label) = mysql_fetch_row($res);
	?>
	<div class=detailRow>
	<div class=detailType>Ratings</div>
	<div class=detail>
	 <span class=label>Interest:</span> <?= $ri_label? $ri_label : '(not set)' ?> &nbsp;&nbsp; <span class=label>Content:</span> <?= $rc_label? $rc_label : '(not set)' ?> &nbsp;&nbsp; <span class=label>Quality:</span> <?= $rq_label? $rq_label : '(not set)' ?>

	</div>
	</div>
	
	<?php	if (@$bkmk['pers_notes']) { ?>
	<div class=detailRow>
	<div class=detailType>Notes</div>
	<div class=detail><?= htmlspecialchars($bkmk['pers_notes']) ?>
	</div>
	</div>
	
	<?php	}
	}
	

	function print_public_details($bib) {
		$bds_res = mysql_query('select rdt_id,
		                               ifnull(rdro.rdr_name, ifnull(rdr.rdr_name, rdt_name)) as name,
		                               rd_val as val,
		                               rd_file_id,
		                               rdt_type,
		                               if(rd_geo is not null, astext(rd_geo), null) as rd_geo,
		                               if(rd_geo is not null, astext(envelope(rd_geo)), null) as bd_geo_envelope
		                          from rec_details
		                     left join rec_detail_types on rdt_id = rd_type
		                     left join rec_detail_requirements rdr on rdr.rdr_rdt_id = rd_type
		                                                          and rdr.rdr_rec_type = '.$bib['rec_type'].'
		                     left join rec_detail_requirements_overrides rdro on rdro.rdr_rdt_id = rd_type
		                                                                     and rdro.rdr_rec_type = '.$bib['rec_type'].'
		                                                                     and rdro.rdr_wg_id = 0
		                         where rd_rec_id = ' . $bib['rec_id'] .'
		                      order by rdro.rdr_order is null,
		                               rdro.rdr_order,
		                               rdr.rdr_order is null,
		                               rdr.rdr_order,
		                               rdt_id,
		                               rd_id');

		$bds = array();
		$thumbs = array();

		while ($bd = mysql_fetch_assoc($bds_res)) {

			if ($bd['rdt_id'] == 603) {
				array_push($thumbs, array(
					'url' => $bd['val'],
					'thumb' => HEURIST_SITE_PATH.'common/php/resize_image.php?file_url='.$bd['val']
				));
			}

			if ($bd['rdt_type'] == 'resource') {

				$res = mysql_query('select rec_title from records where rec_id='.intval($bd['val']));
				$row = mysql_fetch_row($res);
				$bd['val'] = '<a target="_new" href="'.HEURIST_SITE_PATH.'records/viewrec/view.php?bib_id='.$bd['val'].(defined('use_alt_db')? '&alt' : '').'" onclick="return link_open(this);">'.htmlspecialchars($row[0]).'</a>';
			} else if ($bd['rdt_type'] == 'file'  &&  $bd['rd_file_id']) {
				$res = mysql_query('select * from files where file_id='.intval($bd['rd_file_id']));
				$file = mysql_fetch_assoc($res);
				if ($file) {
					$img_url = HEURIST_SITE_PATH.'records/files/fetch_file.php/'.$file['file_orig_name'].'?file_id='.$file['file_nonce'];
					if ($file['file_mimetype'] == 'image/jpeg'  ||  $file['file_mimetype'] == 'image/gif'  ||  $file['file_mimetype'] == 'image/png') {
						array_push($thumbs, array(
							'url' => HEURIST_SITE_PATH.'records/files/fetch_file.php?file_id='.$file['file_nonce'],
							'thumb' => HEURIST_SITE_PATH.'common/php/resize_image.php?file_id='.$file['file_nonce']
						));
					}
					$bd['val'] = '<a target="_surf" href="'.htmlspecialchars($img_url).'"><img src="'.HEURIST_SITE_PATH.'common/images/external_link_16x16.gif">'.htmlspecialchars($file['file_orig_name']).'</a> [' .htmlspecialchars($file['file_size']) . ']';
				}
			} else {
				if (preg_match('/^http:/', $bd['val'])) {
					if (strlen($bd['val']) > 100)
						$trim_url = preg_replace('/^(.{70}).*?(.{20})$/', '\\1...\\2', $bd['val']);
					else
						$trim_url = $bd['val'];
					$bd['val'] = '<a href="'.$bd['val'].'" target="_new">'.htmlspecialchars($trim_url).'</a>';
				} else if ($bd['rd_geo'] && preg_match("/^POLYGON[(][(]([^ ]+) ([^ ]+),[^,]*,([^ ]+) ([^,]+)/", $bd["bd_geo_envelope"], $poly)) {
					list($match, $minX, $minY, $maxX, $maxY) = $poly;
					if ($bd["val"] == "l"  &&  preg_match("/^LINESTRING[(]([^ ]+) ([^ ]+),.*,([^ ]+) ([^ ]+)[)]$/",$bd["rd_geo"],$matches)) {
						list($dummy, $minX, $minY, $maxX, $maxY) = $matches;
					}
					$minX = intval($minX*10)/10;
					$minY = intval($minY*10)/10;
					$maxX = intval($maxX*10)/10;
					$maxY = intval($maxY*10)/10;

					switch ($bd["val"]) {
					  case "p": $type = "Point"; break;
					  case "pl": $type = "Polygon"; break;
					  case "c": $type = "Circle"; break;
					  case "r": $type = "Rectangle"; break;
					  case "l": $type = "Path"; break;
					  default: $type = "Unknown";
					}

					if ($type == "Point")
						$bd["val"] = "<b>Point</b> X ($minX) - Y ($minY)";
					else
						$bd['val'] = "<b>$type</b> X ($minX,$maxX) - Y ($minY,$maxY)";
				} else {
					$bd['val'] = output_chunker($bd['val']);
				}
			}

			array_push($bds, $bd);
		}
	?>
</div>



<div class=detailRowHeader>Public info
<div class=thumbnail>
<?php
	foreach ($thumbs as $thumb) {
		print '<a href="' . htmlspecialchars($thumb['url']) . '" target=_surf>';
		print '<img src="'.htmlspecialchars($thumb['thumb']).'">';
		print '</a>';
	};
?>
</div>
<?php
	foreach ($bds as $bd) {
		print '<div class=detailRow><div class=detailType>'.htmlspecialchars($bd['name']).'</div><div class=detail>'.$bd['val'].'</div></div>';
	}
?>

<div class=detailRow><div class=detailType>Updated</div><div class=detail><?= $bib['rec_modified'] ?></div></div>
<div class=detailRow><div class=detailType>Cite as</div><div class=detail>http://<?= HOST ?>/resource/<?= $bib['rec_id'] ?></div></div></div>
<?php
}


function print_other_tags($bib) {
?>
<div class=detailRow>
	<div class=detailType>Tags</div>
	<div class=detail><nobr><a target=_new href="<?=HEURIST_SITE_PATH?>records/viewrec/follow_links.php?<?php print "bib_id=".$bib['rec_id']; ?>" target=_top onclick="return link_open(this);">[Other users' tags]</a></nobr>
</div></div>
<?php
}


function print_relation_details($bib) {
	$from_res = mysql_query('select rec_details.*
	                           from rec_details
	                      left join records on rec_id = rd_rec_id
	                          where rd_type = 202
	                            and rec_type = 52
	                            and rd_val = ' . $bib['rec_id']);        // 202 = primary resource
	$to_res = mysql_query('select rec_details.*
	                         from rec_details
	                    left join records on rec_id = rd_rec_id
	                        where rd_type = 199
	                          and rec_type = 52
	                          and rd_val = ' . $bib['rec_id']);          // 199 = linked resource

	if (mysql_num_rows($from_res) <= 0  &&  mysql_num_rows($to_res) <= 0) return;
?>
</div>
<div class=detailRowHeader>Related Records
<?php
	while ($reln = mysql_fetch_assoc($from_res)) {
		$bd = fetch_relation_details($reln['rd_rec_id'], true);

		print '<div class=detailRow>';
//		print '<span class=label>' . htmlspecialchars($bd['RelationType']) . '</span>';	//saw Enum change
		print '<div class=detailType>' . htmlspecialchars($bd['RelationValue']) . '</div>'; // fetch now returns the enum string also
		print '<div class=detail>';
		if (@$bd['OtherResource']) {
      			print '<a target=_new href="'.HEURIST_SITE_PATH.'records/viewrec/view.php?bib_id='.$bd['OtherResource']['rec_id'].(defined('use_alt_db')? '&alt' : '').'" onclick="return link_open(this);">'.htmlspecialchars($bd['OtherResource']['rec_title']).'</a>';
		} else {
			print htmlspecialchars($bd['Title']);
		}
		print '&nbsp;&nbsp;';
      		if (@$bd['StartDate']) print htmlspecialchars($bd['StartDate']);
      		if (@$bd['EndDate']) print ' until ' . htmlspecialchars($bd['EndDate']);
		print '</div></div>';
	}
	while ($reln = mysql_fetch_assoc($to_res)) {
		$bd = fetch_relation_details($reln['rd_rec_id'], false);

		print '<div class=detailRow>';
//		print '<span class=label>' . htmlspecialchars($bd['RelationType']) . '</span>';	//saw Enum change
		print '<div class=detailType>' . htmlspecialchars($bd['RelationValue']) . '</div>';
		print '<div class=detail>';
		if (@$bd['OtherResource']) {
      			print '<a target=_new href="'.HEURIST_SITE_PATH.'records/viewrec/view.php?bib_id='.$bd['OtherResource']['rec_id'].(defined('use_alt_db')? '&alt' : '').'" onclick="return link_open(this);">'.htmlspecialchars($bd['OtherResource']['rec_title']).'</a>';
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
	$res = mysql_query('select *
	                      from rec_details
	                 left join rec_detail_types on rdt_id = rd_type
	                 left join records on rec_id = rd_rec_id
	                     where rdt_type = "resource"
	                       and rd_type = rdt_id
	                       and rd_val = ' . $bib['rec_id'] . '
	                       and rec_type != 52');

	if (mysql_num_rows($res) <= 0) return;
?>
<div class=detailRow>
<div class=detailType>Linked From</div>
<div class=detail><a href="<?=HEURIST_SITE_PATH?>search/search.html?w=all&q=linkto:<?=$bib['rec_id']?>" onclick="top.location.href = this.href; return false;"><b>Show list below as search results</b></a> <b>(linkto:<?=$bib['rec_id']?> = records pointing TO this record)</b></div></div>
<?php
	while ($row = mysql_fetch_assoc($res)) {

		print '<div class=detailRow>';
		print '<div class=detailType></div>';
		print '<div class=detail>';
		print '<a target=_new href="'.HEURIST_SITE_PATH.'records/viewrec/view.php?bib_id='.$row['rec_id'].(defined('use_alt_db')? '&alt' : '').'" onclick="return link_open(this);">'.htmlspecialchars($row['rec_title']).'</a>';
		print '</div></div>';
	}
}

function print_text_details($bib) {
	$cmts = getAllComments($bib["rec_id"]);
	$result = loadWoot(array("title" => "record:".$bib["rec_id"]));
	if (! $result["success"] && count($cmts) == 0) return;
?>
</DIV>
<div class=detailRowHeader>Text info

<?php
	print_woot_precis($result["woot"],$bib);
	print_threaded_comments($cmts);
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

  <div><a target=_blank href="<?=HEURIST_SITE_PATH?>records/woot/woot.html?w=record:<?= $bib['rec_id'] ?>&t=<?= $bib['rec_title'] ?>">Click here to edit</a></div>
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


function getAllComments($rec_id) {
	$res = mysql_query("select cmt_id, cmt_deleted, cmt_text, cmt_parent_cmt_id, cmt_date, cmt_modified, cmt_usr_id, Realname from comments left join ".USERS_DATABASE.".Users on cmt_usr_id=Id where cmt_rec_id = $rec_id order by cmt_date");
	$comments = array();
	while ($cmt = mysql_fetch_assoc($res)) {
		if ($cmt["cmt_deleted"]) {
			/* indicate that the comments exists but has been deleted */
			$comments[$cmt["cmt_id"]] = array(
				"id" => $cmt["cmt_id"],
				"owner" => $cmt["cmt_parent_cmt_id"],
				"deleted" => true
			);
			continue;
		}

		$comments[$cmt["cmt_id"]] = array(
			"id" => $cmt["cmt_id"],
			"text" => $cmt["cmt_text"],
			"owner" => $cmt["cmt_parent_cmt_id"],   /* comments that owns this one (i.e. parent, just like in Dickensian times) */
			"added" => $cmt["cmt_date"],
			"modified" => $cmt["cmt_modified"],
			"user" => $cmt["Realname"],
			"userID" => $cmt["cmt_usr_id"],
			"deleted" => false
		);
	}
	return $comments;
}


function output_chunker($val) {
	// chunk up the value so that it will be able to line-break if necessary
	$val = htmlspecialchars($val);
	return preg_replace('/(\\b.{15,20}\\b|.{20}.*?(?=[\x0-\x7F\xC2-\xF4]))/', '\\1<wbr>', $val);
}

?>
