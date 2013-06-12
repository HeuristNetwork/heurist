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
*  Generate javascript arrays for given type (parameter r) for browse page
*  They are used in browse.js render
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/


require_once("incvars.php");
require_once("Record.php");
require_once("getRecordInfo.php");
//require_once(dirname(__FILE__)."/php/utilsMakes.php");

if(!isset($type)){
	$type = @$_REQUEST['r'];
	if($type==null) return; //type not found @todo WORKAROUND
}

if(!$db_selected){
    $db_selected = mysql_connection_select();
}

$entities = array();
$orderedEntities = array();

$order_stm = "TRIM( LEADING 'a ' FROM TRIM( LEADING 'an ' FROM TRIM( LEADING 'the ' FROM LOWER( replace(rec_Title, '\'', '') ) ) ) )";

if ( $type == RT_ENTRY ||
     $type == RT_MAP ||
     $type == RT_MEDIA ||
     $type == RT_CONTRIBUTOR
    ){

	$query = "select rec_ID,
					 rec_Title
	            from Records
	           where rec_RecTypeID = ".$type."
	        order by ".$order_stm;

            //and rec_NonOwnerVisibility = 'public'
	        //2674 - internal record
}
else if ( $type == RT_TERM ) {

    //entry IDs that relate to this term as  'hasPrimarySubject', 'hasSubject'
	$query = "select rec_ID,
	                 rec_Title,
	                 null,
	                 group_concat(distinct d.dtl_Value) ".  //group_concat(rc.rrc_SourceRecID) ".
					 " from Records
					        left join recRelationshipsCache rc on rc.rrc_TargetRecID = rec_ID
					        left join recDetails d on rc.rrc_RecID = d.dtl_RecID and d.dtl_DetailTypeID = ".DT_RELATION_TYPE."
					 			 and d.dtl_Value in (3344, 3343)
                     where rec_RecTypeID = ".RT_TERM."
                     group by rec_ID
                     order by rec_Title";

} else if ( $type == RT_ROLE ) {  //only 3324=occupations!!
	$query = "select rec_ID,
	                 rec_Title
	            from Records,
	                 recDetails
	           where rec_RecTypeID = ".RT_ROLE."
	             and dtl_RecID = rec_ID
	             and dtl_DetailTypeID = ".DT_ROLE_TYPE."
	             and dtl_Value = 3324
  			 order by ".$order_stm;
/* NOT USED
} else if ($type == "Person") {
	$query = "select type.rd_rec_id,
	                 title.rd_val,
	                 null,
	                 group_concat(rel_ptr_2.rd_val)
	            from rec_details type
	      inner join rec_details title
	       left join rec_details rel_ptr_1
	                          on rel_ptr_1.rd_val = type.rd_rec_id
	                         and rel_ptr_1.rd_type = 199
	       left join rec_details rel_type
	                          on rel_type.rd_rec_id = rel_ptr_1.rd_rec_id
	                         and rel_type.rd_type = 200
	                         and rel_type.rd_val = 'isAbout'
	       left join rec_details rel_ptr_2
	                          on rel_ptr_2.rd_rec_id = rel_type.rd_rec_id
	                         and rel_ptr_2.rd_type = 202
	           where type.rd_type = 523
	             and type.rd_val = '$type'
	             and title.rd_rec_id = type.rd_rec_id
	             and title.rd_type = 160
	        group by type.rd_rec_id
	        order by if (title.rd_val like 'the %', substr(title.rd_val, 5), replace(title.rd_val, '\'', ''))";
*/

} else { //entities

/*
	$query = "select type.dtl_RecID,
	                 title.dtl_Value,
	                 group_concat(distinct subtype.dtl_RecID),
	                 group_concat(distinct rc.rrc_SourceRecID)
	            from recDetails type
	      inner join recDetails title
	      inner join recDetails factoid_src_ptr
	      inner join recDetails factoid_type
	      inner join recDetails factoid_role_ptr
	      inner join recDetails subtype

                     left join recRelationshipsCache rc on rc.rrc_TargetRecID = type.dtl_RecID
                     left join recDetails d on rc.rrc_RecID = d.dtl_RecID and d.dtl_DetailTypeID = ".DT_RELATION_TYPE.
                                 " and d.dtl_Value = 3341

	           where type.dtl_DetailTypeID = ".DT_ENTITY_TYPE."
	             and type.dtl_Value = $type

	             and title.dtl_RecID = type.dtl_RecID
	             and title.dtl_DetailTypeID = ".DT_NAME."

	             and factoid_src_ptr.dtl_Value = title.dtl_RecID
	             and factoid_src_ptr.dtl_DetailTypeID = ".DT_FACTOID_SOURCE."
	             and factoid_type.dtl_RecID = factoid_src_ptr.dtl_RecID
	             and factoid_type.dtl_DetailTypeID = ".DT_FACTOID_TYPE."
	             and factoid_type.dtl_Value = 3314 ".   // 'Type'
	             " and factoid_role_ptr.dtl_RecID = factoid_src_ptr.dtl_RecID
	             and factoid_role_ptr.dtl_DetailTypeID = ".DT_FACTOID_ROLE."

	             and subtype.dtl_RecID = factoid_role_ptr.dtl_Value
	             and subtype.dtl_DetailTypeID = ".DT_NAME."
	             and subtype.dtl_Value != 'Generic'
	        group by type.dtl_RecID
	        order by if (title.dtl_Value like 'the %', substr(title.dtl_Value, 5), replace(title.dtl_Value, '\'', ''))";
*/

    // 3314='Type'

    //working with types - IT WROKS!!!!
    /*
    $query = "select type.dtl_RecID,
                     title.dtl_Value,
                     group_concat(distinct factoid_role_ptr.dtl_Value),
                     group_concat(distinct rc.rrc_SourceRecID)
                from recDetails type
          inner join recDetails title
          inner join recDetails factoid_src_ptr
          inner join recDetails factoid_type
          inner join recDetails factoid_role_ptr

                     left join recRelationshipsCache rc on rc.rrc_TargetRecID = type.dtl_RecID
                     left join recDetails d on rc.rrc_RecID = d.dtl_RecID and d.dtl_DetailTypeID = ".DT_RELATION_TYPE.   //isAbout = 3341
                                 " and d.dtl_Value = 3341

               where type.dtl_DetailTypeID = ".DT_ENTITY_TYPE."
                 and type.dtl_Value = $type

                 and title.dtl_RecID = type.dtl_RecID
                 and title.dtl_DetailTypeID = ".DT_NAME."

                 and factoid_src_ptr.dtl_Value = title.dtl_RecID
                 and factoid_src_ptr.dtl_DetailTypeID = ".DT_FACTOID_SOURCE."
                 and factoid_type.dtl_RecID = factoid_src_ptr.dtl_RecID
                 and factoid_type.dtl_DetailTypeID = ".DT_FACTOID_TYPE."
                 and factoid_type.dtl_Value = 3314
                 and factoid_role_ptr.dtl_RecID = factoid_src_ptr.dtl_RecID
                 and factoid_role_ptr.dtl_DetailTypeID = ".DT_FACTOID_ROLE."

            group by type.dtl_RecID
order by TRIM( LEADING 'a ' FROM TRIM( LEADING 'an ' FROM TRIM( LEADING 'the ' FROM LOWER( replace(title.dtl_Value, '\'', '')))))";
    */

    $query = "select type.dtl_RecID,
                     title.dtl_Value,
                     null,
                     group_concat(distinct d.dtl_Value) ".  //group_concat(distinct rc.rrc_SourceRecID)
                " from recDetails type
          inner join recDetails title

                     left join recRelationshipsCache rc on rc.rrc_TargetRecID = type.dtl_RecID
                     left join recDetails d on rc.rrc_RecID = d.dtl_RecID and d.dtl_DetailTypeID = ".DT_RELATION_TYPE.   //isAbout = 3341
                                 " and d.dtl_Value = 3341

               where type.dtl_DetailTypeID = ".DT_ENTITY_TYPE."
                 and type.dtl_Value = $type

                 and title.dtl_RecID = type.dtl_RecID
                 and title.dtl_DetailTypeID = ".DT_NAME."

            group by type.dtl_RecID
order by TRIM( LEADING 'a ' FROM TRIM( LEADING 'an ' FROM TRIM( LEADING 'the ' FROM LOWER( replace(title.dtl_Value, '\'', '')))))";

}

// 0 - id
// 1- title
// 2 - type
// 3 - ref to entry

//error_log($query);

$res = mysql_query($query);
while ($row = mysql_fetch_row($res)) {
	$entity = array($row[1]);
	$types = @$row[2] ? split(",", $row[2]) : null;
	$entries = @$row[3] ? split(",", $row[3]) : null;
	if ($types || $entries) {
		array_push($entity, $types);
	}
	if ($entries) {
		array_push($entity, $entries);
	}
	$entities[$row[0]] = $entity;
	array_push($orderedEntities, $row[0]);
}

$subtypes = array();
$orderedSubtypes = array();


// find subtypes
$query = null;

if ( $type == RT_ENTRY ){ //find entry types

	$query = "select distinct if (entity_type.dtl_Value is null, 'Thematic', entity_type.dtl_Value),
                    if (entity_type.dtl_Value is null, 'Thematic Entries',
                         concat(
                                'Entries about ',
                                if (terms.trm_Label = 'Person', 'People', concat(terms.trm_Label, 's'))
                         )
                     ),
                        entry.rec_id
	            from Records entry

                     left join recRelationshipsCache rc on rc.rrc_SourceRecID = entry.rec_ID
                     left join recDetails d on rc.rrc_RecID = d.dtl_RecID and d.dtl_DetailTypeID = ".DT_RELATION_TYPE.   //isAbout = 3341
                                 " and d.dtl_Value = 3341
                     left join recDetails entity_type
                              on entity_type.dtl_RecID = rc.rrc_TargetRecID
                             and entity_type.dtl_DetailTypeID = ".DT_ENTITY_TYPE."
                     left join defTerms terms
                              on entity_type.dtl_Value = terms.trm_ID

               where rec_RecTypeID = ".RT_ENTRY."
            order by terms.trm_Label, ".$order_stm;

//and rec_NonOwnerVisibility = 'public'

} else if ($type == RT_CONTRIBUTOR) {
/* NOT USED
	$query = "select rd_val, ".
					"if (rd_val = 'author', 'Authors', ".
					"if (rd_val = 'institution', 'Institutions and Collections', ".
					"if (rd_val = 'public', 'Public', ".
					"if (rd_val = 'supporter', 'Supporters', 'Other'))),
	                 rec_id
	            from records,
	                 rec_details
	           where rec_type = 153
	             and rd_rec_id = rec_id
	             and rd_type = 568
	        order by rd_val, if (rec_title like 'the %', substr(rec_title, 5), replace(rec_title, '\'', ''))";
*/
} else {  //@todo for entities only

    if($use_pointer_cache){

    $query = "select distinct  factoid_cache.rfc_RoleRecID,
                     factoid_role_name.rec_Title,
                     type.dtl_RecID

                from recDetails type
          inner join recDetails title
          inner join recFacctoidsCache factoid_cache
          inner join recDetails factoid_type

          left join Records factoid_role_name
                 on factoid_role_name.rec_ID = factoid_cache.rfc_RoleRecID

               where type.dtl_DetailTypeID = ".DT_ENTITY_TYPE."
                 and type.dtl_Value = $type

                 and title.dtl_RecID = type.dtl_RecID
                 and title.dtl_DetailTypeID = ".DT_NAME."

                 and factoid_cache.rfc_SourceRecID = title.dtl_RecID

                 and factoid_type.dtl_RecID = factoid_cache.rfc_RecID
                 and factoid_type.dtl_DetailTypeID = ".DT_FACTOID_TYPE."
                 and factoid_type.dtl_Value = 3314

order by factoid_role_name.rec_Title, TRIM( LEADING 'a ' FROM TRIM( LEADING 'an ' FROM TRIM( LEADING 'the ' FROM LOWER( replace(title.dtl_Value, '\'', '')))))";

    }else{

    $query = "select distinct factoid_role_ptr.dtl_Value,
                     factoid_role_name.rec_Title,
                     type.dtl_RecID

                from recDetails type
          inner join recDetails title
          inner join recDetails factoid_src_ptr
          inner join recDetails factoid_type
          inner join recDetails factoid_role_ptr

          left join Records factoid_role_name
                 on factoid_role_name.rec_ID = factoid_role_ptr.dtl_Value

               where type.dtl_DetailTypeID = ".DT_ENTITY_TYPE."
                 and type.dtl_Value = $type

                 and title.dtl_RecID = type.dtl_RecID
                 and title.dtl_DetailTypeID = ".DT_NAME."

                 and factoid_src_ptr.dtl_Value = title.dtl_RecID
                 and factoid_src_ptr.dtl_DetailTypeID = ".DT_FACTOID_SOURCE."

                 and factoid_type.dtl_RecID = factoid_src_ptr.dtl_RecID
                 and factoid_type.dtl_DetailTypeID = ".DT_FACTOID_TYPE."
                 and factoid_type.dtl_Value = 3314

                 and factoid_role_ptr.dtl_RecID = factoid_src_ptr.dtl_RecID
                 and factoid_role_ptr.dtl_DetailTypeID = ".DT_FACTOID_ROLE."

order by factoid_role_name.rec_Title, TRIM( LEADING 'a ' FROM TRIM( LEADING 'an ' FROM TRIM( LEADING 'the ' FROM LOWER( replace(title.dtl_Value, '\'', '')))))";
    }

}

if($query){
$res = mysql_query($query);
while ($row = mysql_fetch_row($res)) {
	if (! @$subtypes[$row[0]]) {
		$subtypes[$row[0]] = array($row[1], array());
		array_push($orderedSubtypes, $row[0]);
	}
	array_push($subtypes[$row[0]][1], $row[2]);
}
}

if ( $type == RT_ENTRY ) {
	$licenceTypes = array();
	$orderedLicenceTypes = array();

    $query = "select distinct if (licence.dtl_Value is null, 'other', terms.trm_Label) as lictype,
                        entry.rec_id
                from Records entry
                     left join recDetails licence on entry.rec_ID = licence.dtl_RecID and licence.dtl_DetailTypeID = ".DT_TYPE_LICENSE."
                     left join defTerms terms on licence.dtl_Value = terms.trm_ID
               where rec_RecTypeID = ".RT_ENTRY."
            order by lictype, ".$order_stm;

	$res = mysql_query($query);
	while ($row = mysql_fetch_row($res)) {
		if (! @$licenceTypes[$row[0]]) {
			$licenceTypes[$row[0]] = array();
			array_push($orderedLicenceTypes, $row[0]);
		}
		array_push($licenceTypes[$row[0]], $row[1]);
	}

}

print "if (! window.DOS) { DOS = {}; }\n";
print "if (! DOS.Browse) { DOS.Browse = {}; }\n";
if ($is_generation) { print "DOS.Browse.pathBase = " . json_encode(getNameByCode($type)) . ";\n"; }
print "DOS.Browse.entities = " . json_encode($entities) . ";\n";
print "DOS.Browse.orderedEntities = " . json_encode($orderedEntities) . ";\n";
print "DOS.Browse.subtypes = " . json_encode($subtypes) . ";\n";
print "DOS.Browse.orderedSubtypes = " . json_encode($orderedSubtypes) . ";\n";
if ($type == RT_ENTRY) {
	print "DOS.Browse.licenceTypes = " . json_encode($licenceTypes) . ";\n";
	print "DOS.Browse.orderedLicenceTypes = " . json_encode($orderedLicenceTypes) . ";\n";
}
print "$(DOS.Browse.render);";
?>
