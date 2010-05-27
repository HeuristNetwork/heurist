<?php

define('dirname(__FILE__)', dirname(__FILE__));	// this line can be removed on new versions of PHP as dirname(__FILE__) is a magic constant
require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__).'/../../data/records/TitleMask.php');
require_once(dirname(__FILE__).'/../../common/lib/fetch_bib_details.php');

if (! is_logged_in()  ||  ! is_admin()) return;

session_start();

$do_merge_details = false;

if (@$_REQUEST['keep']  &&  @$_REQUEST['duplicate']){  //user has select master and dups- time to merge details
   $do_merge_details = true;
   $_REQUEST['bib_ids'] = join(',',array_merge($_REQUEST['duplicate'],array($_REQUEST['keep']))); //copy only the selected items

}elseif(@$_REQUEST['commit']){
   do_fix_dupe();
   return;
}

$finished_merge = false;
if (@$_SESSION['finished_merge']){
   unset($_SESSION['finished_merge']);
   $finished_merge = true;
}

if (@$_REQUEST['keep'])  $_SESSION['master_rec_id'] = $master_rec_id = $_REQUEST['keep']; //store the master record id in the session

if (! @$_REQUEST['bib_ids']) return;

mysql_connection_db_select(DATABASE);
//mysql_connection_db_select("`heuristdb-nyirti`");   //for debug

$bdts = mysql__select_assoc('rec_detail_types', 'rdt_id', 'rdt_name', '1');
$reference_bdts = mysql__select_assoc('rec_detail_types', 'rdt_id', 'rdt_name', 'rdt_type="resource"');

?>

<html>
<head>
 <style type="text/css">
 * { font-size: 100%; font-family: verdana;}
 body { font-size: 0.7em; }
 td { vertical-align: top; }
 </style>
 <script type="text/javascript">
 <!--
	function keep_bib(rec_id) {
		e = document.getElementById('tb');
		if (!e) return;
		for (var i = 0; i < e.childNodes.length; ++i) {
			row = e.childNodes[i];
            if (row.nodeName == "TR" && row.id){
                id = row.id.replace(/row/,'');
                d = document.getElementById('duplicate' + id);
                if (id == rec_id){
                    row.style.backgroundColor = '#bbffbb';
                    if(d){
                        d.style.display = "none";
                        d.nextSibling.style.display = "none";
                    }
                }else{
                    if (d) {
                        d.style.display = "block";
                        d.nextSibling.style.display = "block";
                    }
                    if (d && d.checked){
                        row.style.backgroundColor = '#ffbbbb';
                    }else{
                        row.style.backgroundColor = '';
                    }
                }
            }
		}
		e = document.getElementById('keep'+rec_id);
		if (e) e.checked = true;
		e = document.getElementById('duplicate'+rec_id);
		if (e) e.checked = false;
	}
	function delete_bib(rec_id) {
		e = document.getElementById('row'+rec_id);
		if (e) e.style.backgroundColor = '#ffbbbb';
	}
	function undelete_bib(rec_id) {
		e = document.getElementById('row'+rec_id);
		if (e) e.style.backgroundColor = '';
	}
 -->
 </script>
</head>
<body>
<form>

<div style="width: 500px;">
<?php
 if (! @$do_merge_details){
     print 'This function combines duplicate records. One record MUST be selected as a master record'.
 ' and there must be at least one duplicate selected. Processing duplicates allows you to merge,'.
 'data with the master record.<br/><br/>'.
 'Bookmarks, Tags and Relationships from deleted records are added to the master record.'.
 'None of these data are duplicated if they already exist in the master record.';
} else{
    print 'Select the data items which should be retained, added or replaced in the master records.'.
    ' Repeatable (multi-valued)fields are indicated by checkboxes and single value fields are '.
    ' indicated by radio buttons. Pressing the "commit changes" button will start the process to'.
    ' save the changes to the master record. You will be able to view the master record to verify '.
    'the changes.';
}
?>
 </div><br/><hr/>

<table><tbody id="tb">

<?php

print '<input type="hidden" name="bib_ids" value="'.$_REQUEST['bib_ids'].'">';

$rfts = array();
$res = mysql_query('select rt_id, rt_name from records left join rec_types on rt_id=rec_type where rec_id in ('.$_REQUEST['bib_ids'].')');
//FIXME add code to pprint cross type matching  header " Cross Type - Author Editor with Person with Book"
while ($row = mysql_fetch_assoc($res)) $rfts[$row['rt_id']]= $row['rt_name'];

$temptypes = '';
if (count($rfts) > 0) {
    foreach ($rfts as $rft){
        if (!$temptypes) $temptypes = $rft;
        else $temptypes .= '/'.$rft;
    }
    print '<tr><td colspan="3" style="text-align: center; font-weight: bold;">'.$temptypes.'</td></tr>';
}
//save rec type for merging code
if (!@$_SESSION['rec_type_id']) $_SESSION['rt_id'] = @$rfts[0]['rt_id'];
//get requirements for details
$res = mysql_query('select rdr_rec_type,rdr_rdt_id, rdr_name, rdr_required, rdr_repeatable from rec_detail_requirements where rdr_rec_type in ('.join(',',array_keys($rfts)).')');
$rec_requirements =  array();
while ($req = mysql_fetch_assoc($res)) $rec_requirements[$req['rdr_rec_type']][$req['rdr_rdt_id']]= $req;
// get overrides - this will potentially overwrite the main requirements
$wg_ids_list = join(',',array_keys($_SESSION['heurist']['user_access']));
$res = mysql_query('select rdr_rec_type, rdr_rdt_id, rdr_name, rdr_required, rdr_repeatable from rec_detail_requirements_overrides where rdr_rec_type in ('.join(',',array_keys($rfts)).') AND rdr_wg_id in ('.$wg_ids_list.')');
$precedence = array( "Y"=> 4, "R"=> 3, "O"=> 2, "X"=> 1 );

while ($req = mysql_fetch_assoc($res)) {
    $rdt_id = $req['rdr_rdt_id'];
    $type = $req['rdr_rec_type'];
    if (!$rec_requirements[$type][$rdt_id]) $rec_requirements[$type][$rdt_id] = $req; //if it doesn't exist then add it
    else {  // if name doesn't exist append it and select max required and max repeatable
        $name = $req['rdr_name'];
        $required = $req['rdr_required'];
        $repeatable = $req['rdr_repeatable'];
        if (strpos($rec_requirements[$type][$rdt_id]['rdr_name'],$name) === false) $rec_requirements[$type][$rdt_id]['rdr_name'] .= '/'.$name ;
        if ( $precedence[$rec_requirements[$type][$rdt_id]['rdr_required']] < $precedence[$required])
            $rec_requirements[$type][$rdt_id]['rdr_required'] = $required;
        if ( intval($rec_requirements[$type][$rdt_id]['rdr_repeatable']) < intval($repeatable))
            $rec_requirements[$type][$rdt_id]['rdr_repeatable'] = intval($repeatable);
    }
}

$res = mysql_query('select * from records where rec_id in ('.$_REQUEST['bib_ids'].') order by find_in_set(rec_id, "'.$_REQUEST['bib_ids'].'")');
$records = array();
$counts = array();
$rec_references = array();
$invalid_rec_references = array();
while ($rec = mysql_fetch_assoc($res)) $records[$rec['rec_id']] = $rec;
foreach($records as $index => $record){
    $rec_references = mysql__select_array('rec_details', 'rd_rec_id', 'rd_val='.$records[$index]['rec_id'].' and rd_type in ('.join(',', array_keys($reference_bdts)).')');
    if ($rec_references){
        // only store the references that are actually records
         $records[$index]["refs"] =  mysql__select_array('records', 'rec_id', 'rec_id in ('.join(',', $rec_references).')');
         $records[$index]["ref_count"] = count($records[$index]["refs"]);
         $counts[$index] = $records[$index]["ref_count"];
         $invalid_rec_references += array_diff($rec_references,$records[$index]["refs"]);
    } else{
        array_push($counts,0);
    }
    $details = array();
    $res = mysql_query('select rd_type, rd_val, rd_id, rd_file_id, if(rd_geo is not null, astext(rd_geo), null) as rd_geo
                          from rec_details
                         where rd_rec_id = ' . $records[$index]['rec_id'] . '
                      order by rd_type, rd_id');
    $records[$index]['details'] = array();
    while ($row = mysql_fetch_assoc($res)) {

        $type = $row['rd_type'];

        if (! array_key_exists($type, $records[$index]['details'])) {
            $records[$index]['details'][$type] = array();
        }
        array_push($records[$index]['details'][$type], $row);
    }
}
//FIXME place results into array and output record with most references and/or date rule first - not sure what to do here
$rec_keys = array_keys($records);
if (! @$master_rec_id){
    array_multisort($counts,SORT_NUMERIC, SORT_DESC, $rec_keys );
    $master_rec_id = $rec_keys[0];
}
if (! @$do_merge_details){  // display a page to user for selecting which record should be the master record
//    foreach($records as $index) {
    foreach($records as $record) {
      //  $record = $records[$index];
        $is_master = ($record['rec_id']== $master_rec_id);
	    print '<tr'. ($is_master && !$finished_merge ? ' style="background-color: #bbffbb;" ': '').' id="row'.$record['rec_id'].'">';
	    $checkKeep =  $is_master? "checked" : "";
        $disableDup = $is_master? "none" : "block";
	    if (!$finished_merge) print '<td><input type="checkbox" name="duplicate[]" '.
              ' value="'.$record['rec_id'].
              '" title="Check to mark this as a duplicate record for deletion"'.
              '  id="duplicate'.$record['rec_id'].'" style="display:'.$disableDup.
              '" onclick="if (this.checked) delete_bib('.$record['rec_id'].'); else undelete_bib('.$record['rec_id'].
              ');"><div style="font-size: 70%; display:'.$disableDup.';">DUPLICATE</div></td>';
	    print '<td style="width: 500px;">';
	    if (!$finished_merge) print '<input type="radio" name="keep" '.$checkKeep.
              ' value="'.$record['rec_id'].
              '" title="Click to select this record as the Master record"'.
              ' id="keep'.$record['rec_id'].
              '" onclick="keep_bib('.$record['rec_id'].');">';
        print '<span style="font-size: 120%;"><a target="edit" href="'.HEURIST_URL_BASE.'data/records/editrec/heurist-edit.html?bib_id='.$record['rec_id'].'">'.$record['rec_id'] . ' ' . '<b>'.$record['rec_title'].'</b></a> - <span style="background-color: #FFDDDD;">'. $rfts[$record['rec_type']].'</span></span>';
	    print '<table>';
	    foreach ($record['details'] as $rd_type => $detail) {
		    if (! $detail) continue;    //FIXME  check if required and mark it as missing and required
            $reqmnt = $rec_requirements[$record['rec_type']][$rd_type]['rdr_required'];
            $color = ($reqmnt == 'Y' ? 'red': ($reqmnt == 'R'? 'black':'grey'));
		    print '<tr><td style=" color: '.$color .';">'.$bdts[$rd_type].'</td>';
		    print '<td style="padding-left:10px;">';
            foreach($detail as $i => $rg){
                if ($rg['rd_val']) {
                    if ($rg['rd_geo']) $rd_temp = $rg['rd_geo'];
                    else $rd_temp =$rg['rd_val'];
                }elseif ($rg['rd_file_id']) {
                    $rd_temp = mysql_fetch_array(mysql_query('select file_orig_name from files where file_id ='.$rg['rd_file_id']));
                    $rd_temp = $rd_temp[0];
                }
                if(! @$temp) $temp=$rd_temp;
                elseif(!is_array($temp)){
                  $temp = array($temp,$rd_temp);
                }else array_push($temp,$rd_temp);
            }
            $detail = detail_str($rd_type, $temp);
            unset($temp);
            if (is_array($detail)) {
                 if (intval($rec_requirements[$record['rec_type']][$rd_type]['rdr_repeatable'])>0){
                     foreach ($detail as $val) {
                       print '<div>'. $val . '</div>';
                     }
                 } else{
                    print '<div>'. $detail[0] . '</div>';
                    //FIXME  add code to remove the extra details that are not supposed to be there
                 }
            } else{
                   print '<div>'. $detail . '</div>';
            }

            print '</td>';
	    }

	    if ($record['rec_url']) print '<tr><td>URL</td><td><a href="'.$record['rec_url'].'">'.$record['rec_url'].'</a></td></tr>';

	    if ($record['rec_added']) print '<tr><td>Added</td><td style="padding-left:10px;">'.substr($record['rec_added'], 0, 10).'</td></tr>';
	    if ($record['rec_modified']) print '<tr><td>Modifed</td><td style="padding-left:10px;">'.substr($record['rec_modified'], 0, 10).'</td></tr>';


	    print '</table></td><td>';

	    print '<table>';

	    if ($record["refs"]) {
		    print '<tr><td>References</td><td>';
		    $i = 1;
		    foreach ($record["refs"] as $ref) {  //FIXME  check for reference to be a valid record else mark detail for delete and don't print
			    print '<a target="edit" href="'.HEURIST_URL_BASE.'data/records/editrec/heurist-edit.html?bib_id='.$ref.'">'.$i++.'</a> ';
		    }
		    print '</td></tr>';
	    }

	    $bkmk_count = mysql_fetch_array(mysql_query('select count(distinct pers_id) from personals where pers_rec_id='.$record['rec_id']));
	    if ($bkmk_count[0]) print '<tr><td>Bookmarks</td><td>'.$bkmk_count[0].'</td></tr>';
	    $kwd_count = mysql_fetch_array(mysql_query('select count(distinct kwl_id) from personals left join keyword_links on kwl_pers_id=pers_id where pers_rec_id='.$record['rec_id'].' and kwl_id is not null'));
	    if ($kwd_count[0]) print '<tr><td>Tags</td><td>'.$kwd_count[0].'</td></tr>';

	    $res2 = mysql_query('select concat('.USERS_FIRSTNAME_FIELD.'," ",'.USERS_LASTNAME_FIELD.') as name, rem_freq, rem_startdate from reminders left join '.USERS_DATABASE.'.'.USERS_TABLE.' on '.USERS_ID_FIELD.'=rem_owner_id where rem_rec_id='.$record['rec_id']);
	    $rems = Array();
	    while ($rem = mysql_fetch_assoc($res2))
		    $rems[] = $rem['name'].' '.$rem['rem_freq'].($rem['rem_freq']=='once' ? ' on ' : ' from ').$rem['rem_startdate'];
	    if (count($rems))
		    print '<tr><td>Reminders</td><td>' . join(', ', $rems) . '</td></tr>';

	    print '</table>';

	    print '</td></tr>';
	    print '<tr><td colspan=3><hr /></td></tr>';
	    print "</tr>\n\n";
    }
}else{  //display page for the user to select the set of details to keep for this record  - this is the basic work for the merge
    $master_index = array_search($master_rec_id, $rec_keys);
    if ($master_index === FALSE){  // no master selected we can't do a merge
        return;
    } elseif ($master_index > 0){  // rotate the keys so the master is first
        $temp = array_slice($rec_keys, 0,$master_index);
        $rec_keys = array_merge(array_slice($rec_keys,$master_index),$temp);
        $master_rec_type = $records[$master_rec_id]['rec_type'];
    }
    foreach($rec_keys as $index) {
        $record = $records[$index];
        $is_master = ($record['rec_id']== $master_rec_id);
        print '<tr id="row'.$record['rec_id'].'">';
        if ($is_master) print '<td><div><b>MASTER</b></div></td>';
        else print '<td><div><b>Duplicate</b></div></td>';
        print '<td style="width: 500px;">';
        print '<div style="font-size: 120%;"><a target="edit" href="'.HEURIST_URL_BASE.'data/records/editrec/heurist-edit.html?bib_id='.$record['rec_id'].'">'.$record['rec_id'] . ' ' . '<b>'.$record['rec_title'].'</b></a> - <span style="background-color: #FFDDDD;">'. $rfts[$record['rec_type']].'</span></div>';
        print '<table>';
        if ($is_master) $_SESSION['master_details']=$record['details']; // save master details for processing - signals code to do_fix_dupe
        foreach ($record['details'] as $rd_type => $detail) {
            if (! $detail) continue;    //FIXME  check if required and mark it as missing and required
            // check to see if the master record already has the same detail with the identical value ignoring leading and trailing spaces
            $removeIndices = array();
            if (! $is_master && $_SESSION['master_details'][$rd_type]){
                $master_details =  $_SESSION['master_details'][$rd_type];
                foreach ($detail as $i => $d_detail){
                    foreach ($master_details as $m_detail){
                        if (($m_detail['rd_val'] && trim($d_detail['rd_val']) == trim($m_detail['rd_val'])) ||
                            ($m_detail['rd_geo'] && trim($d_detail['rd_geo']) == trim($m_detail['rd_geo'])) ||
                            ($m_detail['rd_file_id'] && trim($d_detail['rd_file_id']) == trim($m_detail['rd_file_id']))){
                            //mark this detail for removal
                            array_push($removeIndices,$i);
                        }
                    }
                }
            }
            foreach ($removeIndices as $i){
                unset($detail[$i]);
            }
            if (count($detail) == 0) continue;
            $reqmnt = $rec_requirements[$master_rec_type][$rd_type]['rdr_required'];
            $color = ($reqmnt == 'Y' ? 'red': ($reqmnt == 'R'? 'black':'grey'));
            print '<tr><td style=" color: '.$color .';">'.$bdts[$rd_type].'</td>';
            //FIXME place a keep checkbox on values for repeatable fields , place a radio button for non-repeatable fields with
            //keep_dt_### where ### is detail Type id and mark both "checked" for master record
            print '<td style="padding-left:10px;">';
            $is_type_repeatable =  intval($rec_requirements[$master_rec_type][$rd_type]['rdr_repeatable']) > 0 ;
            $detail = detail_get_html_input_str( $detail, $is_type_repeatable, $is_master );
            if (is_array($detail)) {
                 if ($is_type_repeatable){
                     foreach ($detail as $val) {
                       print '<div>'. $val . '</div>';
                     }
                 } else{
                    print '<div>'. $detail[0] . '</div>';
                    //FIXME  add code to remove the extra details that are not supposed to be there
                 }
            } else{
                   print '<div>'. $detail . '</div>';
            }

            print '</td>';
        }

        if ($record['rec_url']) print '<tr><td>URL</td><td><input type="radio" name="URL" '.($is_master?"checked=checked":"").
                                                      ' title="'.($is_master?"Click to keep URL with Master record":"Click to replace URL in Master record (overwrite)").
                                                      '" value="'.$record['rec_url'].
                                                      '" id="URL'.$record['rec_id'].
                                                      '"><a href="'.$record['rec_url'].'">'.$record['rec_url'].'</a></td></tr>';

        if ($record['rec_added']) print '<tr><td>Add &nbsp;&nbsp;&nbsp;'.substr($record['rec_added'], 0, 10).'</td></tr>';
        if ($record['rec_modified']) print '<tr><td>Mod &nbsp;&nbsp;&nbsp;'.substr($record['rec_modified'], 0, 10).'</td></tr>';


        print '</table></td><td>';

        print '<table>';

        if ($record["refs"]) {
            print '<tr><td>References</td><td>';
            $i = 1;
            foreach ($record["refs"] as $ref) {
                print '<a target="edit" href="'.HEURIST_URL_BASE.'data/records/editrec/heurist-edit.html?bib_id='.$ref.'">'.$i++.'</a> ';
            }
            print '</td></tr>';
        }

        $bkmk_count = mysql_fetch_array(mysql_query('select count(distinct pers_id) from personals where pers_rec_id='.$record['rec_id']));
        if ($bkmk_count[0]) print '<tr><td>Bookmarks</td><td>'.$bkmk_count[0].'</td></tr>';
        $kwd_count = mysql_fetch_array(mysql_query('select count(distinct kwl_id) from personals left join keyword_links on kwl_pers_id=pers_id where pers_rec_id='.$record['rec_id'].' and kwl_id is not null'));
        if ($kwd_count[0]) print '<tr><td>Tags</td><td>'.$kwd_count[0].'</td></tr>';

        $res2 = mysql_query('select concat('.USERS_FIRSTNAME_FIELD.'," ",'.USERS_LASTNAME_FIELD.') as name, rem_freq, rem_startdate from reminders left join '.USERS_DATABASE.'.'.USERS_TABLE.' on '.USERS_ID_FIELD.'=rem_owner_id where rem_rec_id='.$record['rec_id']);
        $rems = Array();
        while ($rem = mysql_fetch_assoc($res2))
            $rems[] = $rem['name'].' '.$rem['rem_freq'].($rem['rem_freq']=='once' ? ' on ' : ' from ').$rem['rem_startdate'];
        if (count($rems))
            print '<tr><td>Reminders</td><td>' . join(', ', $rems) . '</td></tr>';

        print '</table>';

        print '</td></tr>';
        print '<tr><td colspan=3><hr /></td></tr>';
        print "</tr>\n\n";
    }
}
?>

</tbody></table>
<?php
    if (! $finished_merge) {
       print '<input type="submit" name="'.($do_merge_details? "commit":"merge").'" style="background-color:#fbb;" value="'. ($do_merge_details? "commit&nbsp;changes":"merge&nbsp;duplicates").'" >';
    } else{
       print '<div> Changes were commited </div>';
       print '<input type="button" name="close_window" id="close_window" value="Close Window"   title="Cick here to close this window" onclick="window.close();">';
    }
?>
</form>
</body>
</html>

<?php

function detail_get_html_input_str( $detail, $is_type_repeatable, $is_master ) {
     foreach($detail as $rg){
        $detail_id = $rg['rd_id'];
        $detail_type = $rg['rd_type'];
        if ($rg['rd_val']) {
            if ($rg['rd_geo']) $detail_val = $rg['rd_geo'];
            else $detail_val = $rg['rd_val'];
        }elseif ($rg['rd_file_id']) {
            $rd_temp = mysql_fetch_array(mysql_query('select file_orig_name from files where file_id ='.$rg['rd_file_id']));
            $detail_val = $rd_temp[0];
        }

        $input = '<input type="'.($is_type_repeatable? "checkbox":"radio").
                '" name="'.($is_type_repeatable? ($is_master?"keep":"add").$detail_type.'[]':"update".$detail_type).
                '" title="'.($is_type_repeatable?($is_master?"check to Keep value in Master record - uncheck to Remove value from Master record":"Check to Add value to Master record"):
                                        ($is_master?  "Click to Keep value in Master record": "Click to Replace value in Master record")).
                '" '.($is_master?"checked=checked":"").
                ' value="'.($is_type_repeatable?  $detail_id :($is_master? "master":$detail_id)).
                '" id="'.($is_type_repeatable? ($is_master?"keep_detail_id":"add_detail_id"):"update").$detail_id.
                '">'.detail_str($detail_type,$detail_val).'';
       $rv[]= $input;
     }
    return $rv;
}
/*      master
        print '<input type="radio"    name="update'.$detail_type.'"   checked=checked value="master" id="update'.$detail_id.'><div style="font-size: 70%;">'.detail_str($detail_type,$detail_val).'</div>';
rep     print '<input type="checkbox" name="keep'.$detail_type.'[]" checked=checked value="'.$detail_id.'" id="keep_detail_id'.$detail_id.'" ><div style="font-size: 70%;">'.detail_str($detail_type,$detail_val).'</div>';
      dup
        print '<td><input type="radio" name="update" '.$detail_type.' value="'.$detail_id.'" id="update'.$detail_id.'"><div style="font-size: 70%;">'.detail_str($detail_type,$detail_val).'</div>';
rep        print '<input type="checkbox" name="add'.$detail_type.'[]"  value="'.$detail_id.'" id="add_detail_id'.$detail_id.'"><div style="font-size: 70%;">'.detail_str($detail_type,$detail_val).'</div></td>';
 *
 *"<input type=\"checkbox\" name=\"keep158[]\"checked=checkedvalue=\"250491\" id=\"keep_detail_id158[]\"><a target=\"edit\" href=\"../edit?bib_id=61985\">Fajsák, G</a>"
 "<input type=\"checkbox\" name=\"keep158[]\"checked=checkedvalue=\"250492\" id=\"keep_detail_id158[]\"><a target=\"edit\" href=\"../edit?bib_id=61986\">Renner, G</a>"
 "<input type=\"radio\" name=\"update159\"checked=checkedvalue=\"250494\" id=\"update159\">1996"

 */

function detail_str($rd_type, $rd_val) {
	global $reference_bdts;
	if (in_array($rd_type, array_keys($reference_bdts))) {
		if (is_array($rd_val)) {
			foreach ($rd_val as $val){
                $title = mysql_fetch_assoc(mysql_query('select rec_title from records where rec_id ='.$val));
                $rv[] = '<a target="edit" href="'.HEURIST_URL_BASE.'data/records/editrec/heurist-edit.html?bib_id='.$val.'">'.$title['rec_title'].'</a>';
            }
			return $rv;
		}
		else {
            $title = mysql_fetch_assoc(mysql_query('select rec_title from records where rec_id ='.$rd_val));
			return '<a target="edit" href="'.HEURIST_URL_BASE.'data/records/editrec/heurist-edit.html?bib_id='.$rd_val.'">'.$title['rec_title'].'</a>';
		}
	}
	/*
	else if ($rd_type == 158) {
		if (is_array($rd_val[0])) {
			foreach ($rd_val as $val)
				$rv[] = $val['per_citeas'];
			return $rv;
		}
		else {
			return $rd_val['per_citeas'];
		}
	}
	*/
	else
		return $rd_val;
}

// ---------------------------------------------- //
// function to actually fix stuff on form submission
function do_fix_dupe() {
	$master_rec_id = $_SESSION['master_rec_id'];
    $master_details = $_SESSION['master_details'];
    unset($_SESSION['master_details']); //clear master_details so we don't re-enter this code
    unset($_SESSION['master_rec_id']);
    $_SESSION['finished_merge'] = 1;  // set state variable for next loop
    $dup_rec_ids=array();
    if(in_array($master_rec_id,explode(',',$_REQUEST['bib_ids'])))
	    $dup_rec_ids = array_diff(explode(',',$_REQUEST['bib_ids']),array($master_rec_id) );
	$dup_rec_list = '(' . join(',', $dup_rec_ids) . ')';
    $add_dt_ids = array();   // array of detail ids to insert for the master record grouped by detail type is
    $update_dt_ids = array(); // array of detail ids to get value for updating the master record
    $keep_dt_ids = array();   // array of master record repeatable detail ids to keep grouped by detail type id- used to find master details to remove

    //parse form data
    foreach($_REQUEST as $key => $value){
     preg_match('/(add|update|keep)(\d+)/',$key,$matches);
     if (! $matches) continue;

     switch (strtolower($matches[1])){
         case 'add':
            $add_dt_ids[$matches[2]] = $value;
            break;
         case 'update':
            if ($value != "master")$update_dt_ids[$matches[2]] = $value;
            break;
         case 'keep':
            $keep_dt_ids[$matches[2]] = $value;
            break;
     }
    }

//   mysql_connection_db_overwrite("`heuristdb-nyirti`");   //for debug
	mysql_connection_db_overwrite(DATABASE);
//    mysql_query('set @suppress_update_trigger:=1'); // shut off update triggers to let us munge the records with out worrying about the archive.

// set modified on master so the changes will stick  aslo update url if there is one.
     $now = date('Y-m-d H:i:s');
     $pairs =(@$_REQUEST['URL']? array("rec_url" =>$_REQUEST['URL'], "rec_modified" => $now): array("rec_modified" => $now));
     mysql__update("records", "rec_id=$master_rec_id", $pairs );
//process keeps - which means find repeatables in master record to delete  all_details - keeps = deletes
//get array of repeatable detail ids for master
    $master_rep_dt_ids = array();
    $res = mysql_query('select rdr_rdt_id from rec_detail_requirements where rdr_repeatable = 1 and rdr_rec_type = '.$_SESSION['rt_id']);
    while ($row = mysql_fetch_array( $res)) {
            array_push($master_rep_dt_ids, $row[0]);
    }
    $master_rep_detail_ids = array();
    foreach($master_rep_dt_ids as $rep_dt_id ){
       if (array_key_exists($rep_dt_id,$master_details)){
           foreach ($master_details[$rep_dt_id]as $detail){
                array_push($master_rep_detail_ids, $detail['rd_id']);
           }
       }
    }

//get flat array of keep detail ids
    if ($keep_dt_ids && count($keep_dt_ids)){
        $master_keep_ids = array();
        foreach($keep_dt_ids as $dt_id => $details){
            foreach($details as $detail)
            array_push($master_keep_ids,$detail);
        }
    }
//diff the arrays  don't delet yet as the user might be adding an existing value
   $master_delete_dt_ids = array();
   if($master_rep_detail_ids) $master_delete_dt_ids = array_diff($master_rep_detail_ids,$master_keep_ids);
//FIXME add code to remove any none repeatable extra details
 //for each update
   if ($update_dt_ids){
       $update_detail=array();
       foreach($update_dt_ids as $rdt_id => $rd_id){
           //look up data for detail and
             $update_detail = mysql_fetch_assoc(mysql_query('select * from rec_details where rd_id='.$rd_id));
            // if exist in master details  update val
            if(in_array($rdt_id,array_keys($master_details))){
                mysql__update("rec_details", "rd_id=".$master_details[$rdt_id][0]['rd_id'], array( "rd_val" => $update_detail['rd_val']));

            // else  insert the data as detail for master record
            }else {
                   unset($update_detail['rd_id']);         //get rid of the detail id the insert will create a new one.
                   $update_detail['rd_rec_id'] = $master_rec_id;   // set this as a detail of the master record
                   mysql__insert('rec_details',$update_detail);
            }
       }
   }
//process adds
  if($add_dt_ids){
    $add_details = array();
    // for each add detail
    foreach($add_dt_ids as $key => $detail_ids){
       foreach($detail_ids as $detail_id){
       // since adds are only for repeatables check if it exist in delete array ?yes - remove from delete list if there
           if ($key_remove = array_search($detail_id, $master_delete_dt_ids)!== FALSE){      //FIXME need to compare teh value not the rd_id (they will always be diff)
               //remove from array
               unset($master_delete_dt_ids[$key_remove]);
           }else{ //no  then lookup data for detail and insert the data as detail under the master rec id
               $add_detail = mysql_fetch_assoc(mysql_query('select * from rec_details where rd_id='.$detail_id));
               unset($add_detail['rd_id']); //the id is auto set during insert
               $add_detail['rd_rec_id'] = $master_rec_id;
               mysql__insert('rec_details',$add_detail);
           }
       }
    }
  }

	foreach ($dup_rec_ids as $dup_rec_id) {
        //FIXME we should be updating the chain of links
		mysql_query('insert into aliases (old_rec_id, new_rec_id) values ('.$dup_rec_id.', '.$master_rec_id.')');
        //FIXME  we should update the relationship table on both rr_rec_idxxx  fields
	}

// move dup bookmarks and tags to master unless they are already there
//get bookmarkid =>userid for bookmarks of master record
    $master_pers_usr_ids = mysql__select_assoc('personals', 'pers_id','pers_usr_id', 'pers_rec_id = '.$master_rec_id);
//get kwids for  all bookmarks of master record
    $master_kwd_ids = mysql__select_array('keyword_links', 'kwl_kwd_id', 'kwl_rec_id = '.$master_rec_id);
    if ($master_kwd_ids) $master_kwd_cnt = count($master_kwd_ids);
//get bookmarkid => userid for bookmarks in dup records
    $dup_pers_usr_ids = mysql__select_assoc('personals','pers_id', 'pers_usr_id', 'pers_rec_id in'. $dup_rec_list);


// if dup userid already has a bookmark on master record then add pers_id to delete_pers_ids_list else add to  update_pers_ids
    $update_pers_ids  = array();
    $delete_pers_ids = array();
    $dup_delete_pers_id_to_master_per_id = array();
    foreach ($dup_pers_usr_ids as $dup_pers_id => $dup_pers_usr_id){
        if ($master_pers_usr_ids && $matching_master_pers_id = array_search($dup_pers_usr_id,$master_pers_usr_ids)){
            array_push($delete_pers_ids, $dup_pers_id);
            $dup_delete_pers_id_to_master_per_id[$dup_pers_id] = $matching_master_pers_id;
        }else{
            array_push($update_pers_ids, $dup_pers_id);
        }
    }
//move duplicate record bookmarks for users without bookmarks on the master record
    $update_pers_ids_list  = '('.join(',',$update_pers_ids). ")";
    $delete_pers_ids_list  = '('.join(',',$delete_pers_ids). ")";

    if (strlen($update_pers_ids_list)>2) { // update the bookmarks and tags that are not in the master
        mysql_query('update personals set pers_rec_id='.$master_rec_id.' where pers_id in '.$update_pers_ids_list);
        mysql_query('update keyword_links set kwl_rec_id='.$master_rec_id.' where kwl_pers_id in '.$update_pers_ids_list);
    }
// process to be deleted dup bookmarks and their kwd links
    foreach ($delete_pers_ids as $delete_dup_pers_id) {
        //copy soon to be deleted dup bookmark data to master record bookmark  by concat notes and pwd_reminder, max of ratings and copy zotero if non existant
        $master_pers_id = $dup_delete_pers_id_to_master_per_id[$delete_dup_pers_id];
        $master_pers_record = mysql_fetch_assoc(mysql_query('select * from personals where pers_id='.$master_pers_id));
        $delete_dup_pers_record = mysql_fetch_assoc(mysql_query('select * from personals where pers_id='.$delete_dup_pers_id));
        $master_pers_record['pers_notes'] .= $delete_dup_pers_record['pers_notes'];
        $master_pers_record['pers_pwd_reminder'] .= $delete_dup_pers_record['pers_pwd_reminder'];
        $master_pers_record['pers_content_rating'] = max($master_pers_record['pers_content_rating'],$delete_dup_pers_record['pers_content_rating']);
        $master_pers_record['pers_quality_rating'] = max($master_pers_record['pers_quality_rating'],$delete_dup_pers_record['pers_quality_rating']);
        $master_pers_record['pers_interest_rating'] = max($master_pers_record['pers_interest_rating'],$delete_dup_pers_record['pers_interest_rating']);
        if (!$master_pers_record['pers_zotero_id']) $master_pers_record['pers_zotero_id']= $delete_dup_pers_record['pers_zotero_id'];
        unset($master_pers_record['pers_id']);
        mysql__update('personals','pers_id='.$master_pers_id,$master_pers_record);
        //for every delete dup kwd link whoses kwd id is not already linked to the master record change the record id to master and the pers_id to the mapped master_pers_id
        //get kwd links for the soon to be deleted bookmark
        $delete_dup_kwl_kwd_ids = mysql__select_assoc('keyword_links','kwl_id', 'kwl_kwd_id', 'kwl_pers_id ='. $delete_dup_pers_id);
        foreach ($delete_dup_kwl_kwd_ids as $kwl_id => $kwd_id) {
            if ($master_kwd_cnt && array_search($kwd_id,$master_kwd_ids)){ //if it's already linked to the master delete it
                mysql_query('delete from keyword_links where kwl_id = '.$kwl_id);  //FIXME add error code
            }else{ // otherwise point it to the master record and the users bookmark that is already attached to the master
                mysql_query('update keyword_links set kwl_rec_id='.$master_rec_id.',kwl_pers_id='.$master_pers_id.' where kwl_id = '.$kwl_id);
            }
        }
    }
// move reminders to master
    mysql_query('update reminders set rem_rec_id='.$master_rec_id.' where rem_rec_id in '.$dup_rec_list);   //?FIXME  do we need to check reminders like we checked personals
//delete master details
   if($master_delete_dt_ids && count($master_delete_dt_ids)){
        $master_detail_delete_list = '('.join(',',$master_delete_dt_ids).')';
        mysql_query('delete from rec_details where rd_id in '.$master_detail_delete_list);  //FIXME add error code
   }
//delete dup details
    mysql_query('delete from rec_details where rd_rec_id in '.$dup_rec_list);
//delete dup personals
    if (strlen($delete_pers_ids_list)>2) {
        mysql_query('delete from personals where pers_id in '.$delete_pers_ids_list);
    }

 // move dup record pointers to master record
    mysql_query('update rec_details left join rec_detail_types on rdt_id=rd_type set rd_val='.$master_rec_id.
                 ' where rd_val in '.$dup_rec_list.' and rdt_type="resource"');

//delete dups
    mysql_query('delete from records where rec_id in '.$dup_rec_list);

//delete unwanted details in master
 //if ($master_delete_dt_ids && $master_delete_dt_ids[0]){
 //    $master_delete_dt_ids_list = '('.join(',',$master_delete_dt_ids). ')' ;
 //    mysql_query('delete from rec_details where rd_id in '.$master_delete_dt_ids_list);
 // }

 //try to get the record to update title and hash
    // calculate title, do an update
    $type =  $_SESSION['rt_id'];
    $mask = mysql__select_array("rec_types", "rt_title_mask", "rt_id=".$type);  $mask = $mask[0];
    $title = fill_title_mask($mask, $master_rec_id, $type);
    if ($title) {
        mysql_query("update records set rec_title = '" . addslashes($title) . "' where rec_id = $master_rec_id");
    }
    mysql_query('update records set rec_hhash = hhash(rec_id), rec_simple_hash = simple_hash(rec_id) where rec_id='.$master_rec_id);


 	header('Location: fix_dupes.php?bib_ids='.$_REQUEST['bib_ids']);
}

?>
