<?php

	/*<!--
	* filename, brief description, date of creation, by whom
	* @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
	* @link: http://HeuristScholar.org
	* @license http://www.gnu.org/licenses/gpl-3.0.txt
	* @package Heurist academic knowledge management system
	* @todo
	-->*/

	define ('SAVE_URI', 'DISABLED');
	require_once(dirname(__FILE__).'/../common/connect/applyCredentials.php');
	require_once(dirname(__FILE__).'/../common/php/dbMySqlWrappers.php');

	mysql_connection_db_select(USERS_DATABASE);
?>
<html>
	<head>
		<title>Advanced search</title>

		<link rel="stylesheet" type="text/css" href="../common/css/global.css">
		<link rel="stylesheet" type="text/css" href="../common/css/search.css">

		<script src='queryBuilder.js'></script>

		<script type="text/javascript">


	var last_vals = new Object();

	var updateTimeoutID = 0,
		allowUpdate = false;

	function update(elt) {

		if(!allowUpdate){
			return;
		}

		if (updateTimeoutID) { clearTimeout(updateTimeoutID); updateTimeoutID = 0; }

		var q_elt = document.getElementById('q');

		var snippet;

/*		if (elt.name == 'sortby') {
			var repeat_elt = document.getElementById('sortby_multiple');
			var repeat_elt_label = document.getElementById('sortby_multiple_label');
			if (elt.options[elt.selectedIndex].value.substr(0, 2) == 'f:') {
				repeat_elt.disabled = false;
				// repeat_elt_label.innerHTML = 'Ignore duplicate ' + elt.options[elt.selectedIndex].text + ' fields';
				repeat_elt_label.style.color = 'black';
			} else {
				repeat_elt.disabled = true;
				repeat_elt.checked = false;
				// repeat_elt_label.innerHTML = 'Ignore duplicate detail fields';
				repeat_elt_label.style.color = 'gray';
			}
		}
*/
		if (elt.name === "type") {
			// update the list of record-type-specific bib-detail-types
			var typeOptgroup = document.getElementById("rectype-specific-fields");
			var typeSelect = document.getElementById("fieldtype");
			var prevValue = typeSelect.options[typeSelect.selectedIndex].value;

			typeOptgroup.innerHTML = "";	// remove all record-type-specific options

			var rt = elt.options[elt.selectedIndex].value.replace(/"/g, "");;
			for (var rftID in top.HEURIST.rectypes.names) {
				if (top.HEURIST.rectypes.names[rftID] === rt) {
					rt = rftID;
					break;
				}
			}
			var bdr = top.HEURIST.rectypes.typedefs[rt];
			if (! bdr) {
				// no type specified; hide type-specific options
				typeOptgroup.style.display = "none";
			}
			else {
				var bdts = top.HEURIST.detailTypes.typedefs;
				for (var bdtID in bdr['dtFields']) {
					typeOptgroup.appendChild(new Option(bdr['dtFields'][bdtID][0], '"' + bdts[bdtID]['commonFields'][0] + '"'));
				}
				typeOptgroup.label = top.HEURIST.rectypes.names[rt] + " fields";
				typeOptgroup.style.display = "";
			}

			for (var i=0; i < typeSelect.options.length; ++i) {
				if (typeSelect.options[i].value === prevValue) {
					typeSelect.selectedIndex = i;
					break;
				}
			}
		}


		if (elt.value) {
			if (elt.name == 'field') {
				var ft_elt = document.getElementById('fieldtype');
				if (ft_elt.selectedIndex == 0)
				snippet = HQuery.makeQuerySnippet('all', elt.value);
				else
				snippet = HQuery.makeQuerySnippet('field:'+ft_elt.options[ft_elt.selectedIndex].value, elt.value);

			} else if (elt.name == 'fieldtype') {
				var field_elt = document.getElementById('field');
				if (field_elt.value == '') return;
				if (elt.selectedIndex == 0)
				snippet = HQuery.makeQuerySnippet('all', field_elt.value);
				else
				snippet = HQuery.makeQuerySnippet('field:'+elt.options[elt.selectedIndex].value, field_elt.value);

			} else if (elt.name == 'sortby') {
//				if (elt.value.match(/^f:|^field:/)) {
//					var sortby_field = document.getElementById('ascdesc').value + elt.value + (document.getElementById('sortby_multiple').checked? '' : ':m');
//					snippet = HQuery.makeQuerySnippet(elt.name, sortby_field);
//				} else {
					snippet = HQuery.makeQuerySnippet(elt.name, document.getElementById('ascdesc').value + elt.value);
//				}

			} else {
				snippet = HQuery.makeQuerySnippet(elt.name, elt.value);
			}
		} else {
			snippet = '';
		}

		if (snippet == last_vals[elt.name]  &&  q_elt.value.indexOf(snippet) >= 0) return;

		var new_q_val;
		if (last_vals[elt.name]  &&  elt.name != 'fieldtype') {
			// attempt to replace the existing value for this field with the new value
			new_q_val = (' '+q_elt.value).replace(last_vals[elt.name], snippet);

			// if we couldn't find the existing value, then just concatenate the snippet
			if (new_q_val == (' '+q_elt.value)) { new_q_val = q_elt.value + ' ' + snippet; }
		} else {
			if (q_elt.value)
			new_q_val = q_elt.value + ' ' + snippet;
			else
			new_q_val = snippet;
		}

		if (new_q_val.match(/\bOR\b(?!.*\bAND\b)/)  &&  new_q_val.length > snippet.length  &&  ! snippet.match(/^sortby:/)) {
			/*
			One of the snippets contains an OR, so search construction will not do what the user expects ...
			e.g. if they type  xxx  in the title box, and  x OR y  in the tag box,
			then the constructed search would be
			xxx tag:x OR tag:y
			which would find title xxx and tag x, OR tag y
			when what they want is title xxx and tag x, OR title xxx and tag y.
			i.e. the OR is not distributed.
			Now, we could do a mass-o piece-by-piece OR distribution,
			or we can introduce top-top-level AND.
			Guess which is easier and more flexible? (and more likely to DWIM?)
			*/
			new_q_val = new_q_val.replace(snippet, 'AND '+snippet);
		}

		last_vals[elt.name] = snippet;
		if (elt.name == 'fieldtype') last_vals['field'] = '';
		q_elt.value = new_q_val.replace(/^\s*AND\b|\bAND\s*$|\bAND\s+(?=AND\b)/g, '').replace(/^\s+|\s+$/g, '');
	}


	function do_search() {
		/*
		var s_elt = top.document.getElementById('s');
		var s_val = document.getElementById('sortby').value;
		if (s_val && s_val.match(/^f:\d+$/)) {
		var f_elt = top.document.getElementById('f');
		f_elt.value = s_val;
		f_elt.disabled = false;
		}
		s_elt.value = s_val;
		*/
		window.close(document.getElementById('q').value);
	}


	function load_query() {
		var params = top.HEURIST.parseParams(location.search);

		var q_str = decodeURIComponent(params["q"]);
		document.getElementById('q').value = q_str;

		var q_bits = HQuery.parseQuery(q_str);
		if (q_bits) {
			for (q_key in q_bits) {
				if (document.getElementById(q_key)) {
					var val = '';
					for (var i=0; i < q_bits[q_key].length; ++i) {
						if (val) val += ' OR ';
						val += q_bits[q_key][i].join(' ');
					}
					if (q_key == 'sortby'  &&  val[0] == '-') {
						document.getElementById(q_key).value = val.substring(1);
						document.getElementById('ascdesc').value = '-';
					}
					document.getElementById(q_key).value = val;

				} else if (q_key.match(/^field:\d+$/)) {
					document.getElementById('fieldtype').value = q_key.substr(6);
					document.getElementById('field').value = q_bits[q_key];

				} else if (q_key == 'all') {
					document.getElementById('fieldtype').value = '';
					document.getElementById('field').value = q_bits[q_key];
				}
			}
		}

		reconstruct_query();

		// document.getElementById('tag').focus();
	}


	function reconstruct_query() {
		// reconstruct the query in the SEARCH box (using the canonical fully-modified form)

		var field_names = ['title', 'tag', 'url', 'type', 'user']; //'notes',

		var q_val = '';
		for (var i=0; i < field_names.length; ++i) {
			var field_val = document.getElementById(field_names[i]).value;
			if (! field_val) continue;

			if (! q_val) {
				q_val = HQuery.makeQuerySnippet(field_names[i], field_val);
			} else {
				if (q_val.match(/\bOR\b(?!.*AND\b)/)) q_val += ' AND';	// query contains an OR with no trailing AND
				q_val += ' ' + HQuery.makeQuerySnippet(field_names[i], field_val);
			}
		}

		if (document.getElementById('field').value) {
			var field_val = document.getElementById('field').value;
			var field_spec = '';
			if (document.getElementById('fieldtype').value)
			field_spec = HQuery.makeQuerySnippet('field:' + document.getElementById('fieldtype').value, field_val);
			else
			field_spec = HQuery.makeQuerySnippet('all', field_val);

			if (! q_val) {
				q_val = field_spec;
			} else {
				if (q_val.match(/\bOR\b(?!.*AND\b)/)) q_val += ' AND';	// query contains an OR with no trailing AND
				q_val += ' ' + field_spec;
			}
		}

		var sortby = document.getElementById('sortby').value;
		var sign = document.getElementById('ascdesc').value;
		if (! (sortby == 't'  &&  sign == '')) {
			if (! q_val) {
				q_val = 'sortby:' + sign + sortby;
			} else {
				if (q_val.match(/\bOR\b(?!.*AND\b)/)) q_val += ' AND';	// query contains an OR with no trailing AND
				q_val += ' sortby:' + sign + sortby;
			}
		}

		document.getElementById('q').value = q_val;
	}


	function keypress(e) {
		var targ;
		if (! e) e = window.event;
		if (e.target) targ = e.target;
		else if (e.srcElement) targ = e.srcElement;
		if (targ.nodeType == 3) targ = targ.parentNode;

		var code = e.keyCode;
		if (e.which) code = e.which;

		if (code == 13) do_search();
		if (code == 32 || code == 37 || code == 39) return true;
		if ((code < 8) || (code >= 10  &&  code < 040) || (code == 041) || (code >= 043 && code <= 046) || (code >= 050 && code <= 053) || (code == 057) || (code >= 073 && code <= 0100) || (code >= 0133 && code <= 0136) || (code == 0140) || (code >= 0173 && code <= 0177)) return false;

		if (updateTimeoutID) clearTimeout(updateTimeoutID);
		updateTimeoutID = setTimeout(function() { update(targ) }, 100);

		return true;
	}


	function add_tag(tag) {
		if (tag.indexOf(' ')) tag = '"' + tag + '"';

		var q_elt = document.getElementById('q');
		if (q_elt.value) q_elt.value += ' ';
		q_elt.value += 'tag:' + tag;
	}


	var filterTimeout = 0;
	function invoke_refilter() {
		if (filterTimeout) clearTimeout(filterTimeout);
		filterTimeout = setTimeout(refilter_usernames, 50);
	}

	function refilter_usernames() {
		if (filterTimeout) clearTimeout(filterTimeout);
		filterTimeout = 0;


		var user_search_val = document.getElementById('user_search').value.toLowerCase().replace(/^\s+|\s+$/g, '');
		var user_elt = document.getElementById('user');
		var all_user_elt = document.getElementById('users_all');

		if (user_search_val == '') {
			user_elt.options[0].text = '(specify partial user name)';
			user_elt.selectedIndex = 0;
			return;
		}

		user_elt.disabled = true;
		user_elt.options[0].text = user_search_val + ' [searching]';
		user_elt.selectedIndex = 0;

		user_elt.length = 1;

		var num_matches = 0;
		var first_match = 0;
		for (var i=1; i < all_user_elt.options.length; ++i) {
			if (all_user_elt.options[i].text.toLowerCase().indexOf(user_search_val) >= 0) {
				user_elt.options[user_elt.options.length] = new Option('\xA0'+all_user_elt.options[i].text, all_user_elt.options[i].value);
				++num_matches;
			}
		}

		if (num_matches > 0) {
			if (num_matches == 1) {
				user_elt.options[0].text = user_search_val + '\xA0\xA0\xA0[1 match]';
			} else {
				user_elt.options[0].text = user_search_val + '\xA0\xA0\xA0[' + num_matches + ' matches]';
			}
			user_elt.selectedIndex = 0;
			user_elt.options[0].disabled = true;

		} else {
			user_elt.options[0].text = user_search_val + ' [no matches]';
		}

		user_elt.disabled = false;
		user_elt.options[0].style.color = user_elt.style.color = 'gray';
	}

	function reset_usernames() {
		if (filterTimeout) clearTimeout(filterTimeout);
		filterTimeout = 0;

		var user_elt = document.getElementById('user');
		user_elt.options[0].text = '(matching users)';
		user_elt.options[0].style.color = user_elt.style.color = 'black';
		while (user_elt.length > 1)
		user_elt.remove(user_elt.length - 1);
	}

	function keypressRedirector(e) {
		if (! e) e = window.event;
		if (e.keyCode) code = e.keyCode;
		else if (e.which) code = e.which;

		var user_search_elt = document.getElementById('user_search');
		var user_elt = document.getElementById('user');

		if (code >= 48  && code <= 126  ||  code == 32) {
			user_search_elt.value += String.fromCharCode(code);
			refilter_usernames();

			e.cancelBubble = true;
			return false;
		} else if (code == 8) {
			user_search_elt.value = user_search_elt.value.substring(0, user_search_elt.value.length-1);
			refilter_usernames();

			e.cancelBubble = true;
			return false;
		}

		return true;
	}

	function clear_fields() {
		document.getElementById('q').value='';
		var elts = document.getElementsByTagName('input');
		for (var i = 0; i < elts.length; i++){
			elts[i].value = elts[i].defaultValue;
		}

		var elts = document.getElementsByTagName('select');
		for (var i = 0; i < elts.length; i++){
			elts[i].selectedIndex = 0;
		}
		reset_usernames();
	}

	//
	//
	//
	function add_to_search(id){
		allowUpdate = true;
		var ele = document.getElementById(id);
		update(ele);
		allowUpdate = false;
	}

	//
	//
	//
	function setIndividually(ele){
		allowUpdate = !ele.checked;
		var elts = document.getElementsByTagName('button');
		for (var i = 0; i < elts.length; i++){
			if(elts[i].id!="btnSearch"){
				elts[i].style.visibility = allowUpdate?"hidden":"visible";
			}
		}
	}

		</script>

	</head>
	<body class="popup" width=700 height=500 style="overflow: hidden;" onLoad="load_query();">

		<div class="advanced-search-row" style="border-bottom: 1px solid #7f9db9;padding-bottom:10px;">
			<b>Search&nbsp;string</b>
			<div class="searchInput" style="position:relative; display:inline-block;">
				<input id=q name=q type=text style="width:600;">
			</div>
			<button id="btnSearch" type="button" onClick="do_search();" class="button">Search</button>

			<div style="width: 100%; text-align: center; padding-top: 4px;">Build search using fields below, or edit the search string directly. <a href="#" onClick="clear_fields(); return false;">Clear search string</a></div>
		</div>
		<div style="padding-left: 30px; color: #666;border-bottom: 1px solid #7f9db9;padding-bottom:10px;">
			<p>See also <a href="#" onClick="top.HEURIST.util.popupURL(window, '<?=HEURIST_URL_BASE?>help/advanced_search.html'); return false;">help for advanced search</a>.</p>
			<div>Use <b>tag:</b>, <b>type:</b>, <b>url:</b>, <b>notes:</b>, <b>owner:</b>, <b>user:</b>, <b>field:</b> and <b>all:</b> modifiers.<br>
				To find records with geographic objects that contain a given point, use <b>latitude</b> and <b>longitude</b>, e.g.
				<b>latitude:10 longitude:100</b><br>
				Use e.g. <b>title=</b><i>xxx</i> to match exactly, similarly <b>&lt;</b> or <b>&gt;</b>.<br>
				To find records that include either of two search terms, use an uppercase OR. e.g. <b>timemap OR &quot;time map&quot;</b><br>
			To omit records that include a search term, precede the term with a single dash. e.g. <b>-maps -tag:timelines</b></div>

			<div style="text-align:right;padding-right:10px;">
				<label for="setInd">Set individually: </label>
				<input id="setind" iname="setind" type="checkbox" style="display:inline-block !important;" checked="checked" onchange="setIndividually(this)" />
			</div>
		</div>

		<div class="advanced-search-row"  style="border-bottom: 1px solid #7f9db9;padding-bottom:10px;">
			<label for="sortby">Sort by:</label>
			<script>
				function setAscDescLabels(sortbyValue) {
					var ascLabel = document.getElementById("asc-label");
					var descLabel = document.getElementById("desc-label");

					if (sortbyValue === "r"  ||  sortbyValue === "p") {
						// ratings should have best first,
						// popularity should have most-popular first
						ascLabel.text = "decreasing";
						descLabel.text = "increasing";
					}
					else {
						ascLabel.text = "ascending";
						descLabel.text = "descending";
					}
				}
			</script>
			<select name=sortby id=sortby onChange="setAscDescLabels(options[selectedIndex].value); update(this);">
				<option value=rt>record type</option>
				<option value=t>record title</option>
				<option value=u>record URL</option>
				<option value=m>date modified</option>
				<option value=a>date added</option>
				<option value=r>personal rating</option>
				<option value=p>popularity</option>
				<optgroup label="Detail fields">
					<?php
						$res = mysql_query('select dty_ID, dty_Name from '.DATABASE.'.defDetailTypes order by dty_Name');
						while ($row = mysql_fetch_assoc($res)) {
						?>
						<option value="f:&quot;<?= $row['dty_Name'] ?>&quot;"><?= htmlspecialchars($row['dty_Name']) ?></option>
						<?php	}	?>
				</optgroup>
			</select>

			<input type=checkbox id=sortby_multiple disabled onClick="update(document.getElementById('sortby'));">



			<select id=ascdesc style="width: 100px;" onChange="update(document.getElementById('sortby'));">
				<option value="" selected id=asc-label>ascending</option>
				<option value="-" id=desc-label>descending</option>
			</select>
			<!-- <span>Bibliographic (sort by first value only)</span> -->

			<button type="button" style="visibility:visible; float: right;" onClick="add_to_search('sortby');" class="button" title="Add to Search">Add</button>
		</div>


		<div class="advanced-search-row">
			<label for="type">Record type:</label>
			<?php
				$res = mysql_query("select distinct rty_ID,rty_Name,rty_Description, rtg_Name
				from defRecTypes left join defRecTypeGroups on rtg_ID = rty_RecTypeGroupID
				where rty_ShowInLists = 1 order by rtg_Order, rtg_Name, rty_OrderInGroup, rty_Name");
			?>
			<select name="type" id="type" onChange="update(this);">
				<option selected="selected" value="">(select record type)</option>
				<?php
					$section = "";
					while ($row = mysql_fetch_assoc($res)) {
						if ($row["rtg_Name"] != $section) {
							if ($section) print "</optgroup>\n";
							$section = $row["rtg_Name"];
							print '<optgroup label="' . htmlspecialchars($section) . ' types">';
						}
					?>
					<option value="<?= $row["rty_ID"] ?>" title="<?= htmlspecialchars($row["rty_Description"]) ?>"><?= htmlspecialchars($row["rty_Name"]) ?></option>
					<?php
						}
				?>
				</optgroup>
			</select>

			<button type="button" style="visibility:visible; float: right;" onClick="add_to_search('type');" class="button" title="Add to Search">Add</button>
		</div>

		<div class="advanced-search-row">
			<label for="tag">Tags:</label>
			<input name=tag id=tag onChange="update(this);" onKeyPress="return keypress(event);">
			<?php
				$res = mysql_query('select concat('.GROUPS_NAME_FIELD.', "\\\\", tag_Text) from '.DATABASE.'.usrTags, '.USER_GROUPS_TABLE.', '.GROUPS_TABLE.' where tag_UGrpID='.USER_GROUPS_GROUP_ID_FIELD.' and '.USER_GROUPS_GROUP_ID_FIELD.'='.GROUPS_ID_FIELD.' and '.USER_GROUPS_USER_ID_FIELD.'=' . get_user_id() . ' order by '.GROUPS_NAME_FIELD.', tag_Text');
				if (mysql_num_rows($res) > 0) {
				?>
				<span style="padding-left:28px;">or</span>
				<select id="wgtag" onChange="if (selectedIndex) add_tag(options[selectedIndex].value);" style="width: 200px;">
					<option value="" selected disabled>(select...)</option>
					<?php		while ($row = mysql_fetch_row($res)) {	?>
						<option value="<?= htmlspecialchars($row[0]) ?>"><?= htmlspecialchars($row[0]) ?></option>
						<?php		}	?>
				</select>
				<?php
					} else {
				?>
				<?php
					}
			?>
			<button type="button" style="visibility:visible; float: right;" onClick="add_to_search('tag');" class="button" title="Add to Search">Add</button>
		</div>

		<div class="advanced-search-row">
			<label>Fields:</label>
			<div style="width:200px;display:inline-block;text-align:right;">Title (the full title of the record):</div>
			<span>contains</span><input name=title id=title onChange="update(this);" onKeyPress="return keypress(event);">
			<button type="button" style="visibility:visible; float: right;" onClick="add_to_search('title');" class="button" title="Add to Search">Add</button>
		</div>
		<div class="advanced-search-row">
			<div style="width:313px;display:inline-block;text-align:right;">URL (the hyperlink for the record):</div>
			<span>contains</span><input name=url id=url onChange="update(this);" onKeyPress="return keypress(event);">
			<button type="button" style="visibility:visible; float: right;" onClick="add_to_search('url');" class="button" title="Add to Search">Add</button>
		</div>

		<div class="advanced-search-row">
			<label>&nbsp;</label>
			<select name=fieldtype id=fieldtype onChange="update(this);">
					<option value="" style="font-weight: bold;">Any field</option>
					<optgroup id="rectype-specific-fields" label="rectype specific fields" style="display: none;"></optgroup>
					<optgroup label="Generic fields">
						<?php
							$res = mysql_query('select dty_ID, dty_Name from '.DATABASE.'.defDetailTypes order by dty_Name');
							while ($row = mysql_fetch_assoc($res)) {
							?>          <option value="&quot;<?= htmlspecialchars($row['dty_Name']) ?>&quot;"><?= htmlspecialchars($row['dty_Name']) ?></option>
							<?php	}	?>
					</optgroup>
			</select>
			<span>contains</span><input id=field name=field onChange="update(this);" onKeyPress="return keypress(event);" style="width: 200px;">
			<button type="button" style="visibility:visible; float: right;" onClick="add_to_search('field');" class="button" title="Add to Search">Add</button>
		</div>

<!--
		<div class="advanced-search-row">
			<label for="notes">Notes:</label>
			<input id=notes name=notes onChange="update(this);" onKeyPress="return keypress(event);">
		</div>
-->

		<?php
				$groups = mysql__select_assoc(USERS_DATABASE.".".USER_GROUPS_TABLE." left join ".USERS_DATABASE.".".GROUPS_TABLE." on ".USER_GROUPS_GROUP_ID_FIELD."=".GROUPS_ID_FIELD, GROUPS_ID_FIELD, GROUPS_NAME_FIELD, USER_GROUPS_USER_ID_FIELD."=".get_user_id()." and ".GROUPS_TYPE_FIELD."='workgroup' order by ".GROUPS_NAME_FIELD);
				if ($groups  &&  count($groups) > 0) {
				?>
		<div class="advanced-search-row">
				<label for="user">Owned&nbsp;by:</label>
				<select name="owner" id="owner" onChange="update(this);" style="width:200px;">
					<option value="" selected="selected">(any owner or ownergroup)</option>
					<option value="&quot;<?= get_user_username()?>&quot;"><?= get_user_name()?></option>
					<?php	foreach ($groups as $id => $name) { ?>
						<option value="&quot;<?= htmlspecialchars($name) ?>&quot;"><?= htmlspecialchars($name) ?></option>
						<?php	} ?>
				</select>
				<button type="button" style="visibility:visible; float: right;" onClick="add_to_search('owner');" class="button" title="Add to Search">Add</button>
		</div>
				<?php
					}
		?>


		<div class="advanced-search-row">
			<label for="user">Bookmarked&nbsp;by:</label>
			<input onChange="refilter_usernames()" onKeyPress="invoke_refilter()" value="(search for a user)" id=user_search onFocus="if (value == defaultValue) { value = ''; }">
			<span style="padding-left:15px;">then</span>
			<select name="user" id="user" onChange="style.color = 'black'; update(this);" onkeypress="return keypressRedirector(event)">
				<option value="" selected="selected">(matching users)</option>
			</select>
			<select name="users_all" id="users_all" style="display: none;">
				<?php
					$res = mysql_query('select '.USERS_ID_FIELD.', concat('.USERS_FIRSTNAME_FIELD.'," ",'.USERS_LASTNAME_FIELD.') as fullname from '.USERS_TABLE.' left join '.USER_GROUPS_TABLE.' on '.USER_GROUPS_USER_ID_FIELD.'='.USERS_ID_FIELD.' where '.USERS_ACTIVE_FIELD.' = "Y" and '.USER_GROUPS_GROUP_ID_FIELD.'=2 order by fullname');
					while ($row = mysql_fetch_row($res)) {
						print '<option value="&quot;'.htmlspecialchars($row[1]).'&quot;">'.htmlspecialchars($row[1]).'</option>'."\n";
					}
				?>
			</select>
			<button type="button" style="visibility:visible; float:right;" onClick="add_to_search('user');" class="button" title="Add to Search">Add</button>
			<!--
			<img src=<?=HEURIST_URL_BASE?>common/images/leftarrow.gif>
			<span style="color: green;">Type name to find users</span>
			-->
		</div>

	</body>
</html>
