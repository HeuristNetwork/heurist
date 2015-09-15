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
* Dictionary of Sydney Heurist - Utilities to load data from database to Record object
*
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://sydney.edu.au/heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  applications
*/


$stime = 0;

/**
* Load entire info from database and return record object
*
* @param mixed $rec_id
* @return Record
*/
function getInfo($rec_id){
    global $stime, $query_times, $use_pointer_cache, $db_selected;

    if(!$db_selected){
        $db_selected = mysql_connection_select();
    }

    $record = getRecordFull($rec_id);

    if($record){

        if($record->type()==RT_CONTRIBUTOR){
            //find contributor records


            $ids = getRecordsForIn('select group_concat(distinct rd.dtl_RecID) from recDetails rd where '.
                ' rd.dtl_DetailTypeID='.DT_CONTRIBUTOR_REF.' and rd.dtl_Value='.$rec_id);
            if($ids){

                $query = 'select d.dtl_RecID as rec_id, r.rec_RecTypeID as rectype, d.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue from Records r, recDetails d '.
                ' where r.rec_RecTypeID in ('.RT_ENTRY.','.RT_MEDIA.','.RT_TILEDIMAGE.')
                and r.rec_ID=d.dtl_RecID and r.rec_ID in ('.$ids.') and d.dtl_DetailTypeID in ('.DT_NAME.','.DT_TYPE_MIME.','.DT_TILEDIMAGE_TYPE.')';

                addRelations($record, false, $query);
            }

            return $record;

        }else if($record->type()==RT_TERM){

            // narrow terms

            $ids = getRecordsForIn('SELECT group_concat(r1.rrc_SourceRecID) FROM recRelationshipsCache r1 where r1.rrc_TargetRecID='.$rec_id);
            if($ids){
                $query = 'select d.dtl_RecID as rec_id, '.RT_TERM.' as rectype, '.
                'd.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue, 2 as ref from Records r, recDetails d '.
                'where r.rec_RecTypeID='.RT_TERM.' and r.rec_ID=d.dtl_RecID and r.rec_ID in ('.$ids.') and d.dtl_DetailTypeID='.DT_NAME;
                addRelations($record, false, $query);
            }

            // broader terms
            $ids = getRecordsForIn('SELECT group_concat(r1.rrc_TargetRecID) FROM recRelationshipsCache r1 where r1.rrc_SourceRecID='.$rec_id);
            if($ids){
                $query = 'select d.dtl_RecID as rec_id, '.RT_TERM.' as rectype, '.
                'd.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue, 1 as ref from Records r, recDetails d '.
                'where r.rec_RecTypeID='.RT_TERM.' and r.rec_ID=d.dtl_RecID and r.rec_ID in ('.$ids.') and d.dtl_DetailTypeID='.DT_NAME;
                addRelations($record, false, $query);
            }

            //related features
            $ids = getRecordsForIn('SELECT r1.rrc_SourceRecID as recid FROM recRelationshipsCache r1 where r1.rrc_TargetRecID='.$rec_id.' union '.
                'SELECT r2.rrc_TargetRecID as recid FROM recRelationshipsCache r2 where r2.rrc_SourceRecID='.$rec_id);
            if($ids){
                $query = 'select r.rec_RecTypeID as rectype, d.dtl_RecID as rec_id, '.
                'd.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue from Records r, recDetails d '.
                'where r.rec_RecTypeID in ('.RT_MEDIA.','.RT_ENTRY.','.RT_ENTITY.','.RT_MAP.','.RT_TILEDIMAGE.') and r.rec_ID=d.dtl_RecID and r.rec_ID in
                ('.$ids.') and d.dtl_DetailTypeID in ('.DT_NAME.','.DT_TYPE_MIME.','.DT_TILEDIMAGE_TYPE.','.DT_ENTITY_TYPE.')';

                addRelations($record, false, $query);
            }
            return $record;

        }else if($record->type()==RT_ROLE){

            if($use_pointer_cache){
                $query = 'SELECT r1.rfc_RecID as recid FROM recFacctoidsCache r1 where r1.rfc_RoleRecID='.$rec_id;
            }else{
                //slower
                $query = 'select group_concat(distinct rd.dtl_RecID) from recDetails rd where '.
                ' rd.dtl_DetailTypeID='.DT_FACTOID_ROLE.' and rd.dtl_Value='.$rec_id;
            }

            $ids = getRecordsForIn($query);

            if($ids){
                /* old way - it works
                $query = 'select d.dtl_RecID as rec_id, d.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue,  ds1.rec_Title as ref from recDetails d '.
                ' left join Records ds1 on d.dtl_DetailTypeID in ('.DT_FACTOID_TARGET.','.DT_FACTOID_SOURCE.') and d.dtl_Value=ds1.rec_ID'.
                ' where d.dtl_RecID in ('.$ids.') ';
                */
                $query = 'select d.dtl_RecID as rec_id, d.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue,'.
                ' ds1.dtl_Value as ref,  ds2.dtl_Value as ref2 '.
                'from recDetails d '.
                ' left join recDetails ds1 on d.dtl_DetailTypeID in ('.DT_FACTOID_TARGET.','.DT_FACTOID_SOURCE.') and ds1.dtl_RecID=d.dtl_Value and ds1.dtl_DetailTypeID='.DT_NAME.
                ' left join recDetails ds2 on d.dtl_DetailTypeID in ('.DT_FACTOID_TARGET.','.DT_FACTOID_SOURCE.') and ds2.dtl_RecID=d.dtl_Value and ds2.dtl_DetailTypeID='.DT_ENTITY_TYPE.
                ' where d.dtl_RecID in ('.$ids.')';


                addRelations($record, false, $query, RT_FACTOID);
            }
            return $record;
        }

        if($record->type()==RT_ENTITY){

            //get factoids
            /* old way - works
            $query = 'select d.dtl_RecID as rec_id, d.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue,  astext(d.dtl_Geo) as geo, ds1.rec_Title as ref, ds4.dtl_Value as ref2 from Records r, recDetails d '.
            ' left join Records ds1 on d.dtl_DetailTypeID in ('.DT_FACTOID_TARGET.','.DT_FACTOID_SOURCE.','.DT_FACTOID_ROLE.')'.
            ' and d.dtl_Value=ds1.rec_ID and ds1.rec_ID<>'.$rec_id.
            ' left join recDetails ds4 on d.dtl_DetailTypeID='.DT_FACTOID_ROLE.' and d.dtl_Value=ds4.dtl_RecID and ds4.dtl_DetailTypeID='.DT_NAME2.
            ' where r.rec_RecTypeID='.RT_FACTOID.' and r.rec_ID=d.dtl_RecID and r.rec_ID in (select distinct rd.dtl_RecID from recDetails rd where '.
            ' rd.dtl_DetailTypeID in ('.DT_FACTOID_TARGET.','.DT_FACTOID_SOURCE.') and rd.dtl_Value='.$rec_id.') ';
            */

            //SQL_NO_CACHE  group_concat(

            if($use_pointer_cache){
                $query = 'SELECT r1.rfc_RecID as recid FROM recFacctoidsCache r1 where r1.rfc_TargetRecID='.$rec_id.' union '.
                'SELECT r2.rfc_RecID as recid FROM recFacctoidsCache r2 where r2.rfc_SourceRecID='.$rec_id;
            }else{
                $query = 'select distinct r.rec_ID from Records r, recDetails rd where
                r.rec_RecTypeID='.RT_FACTOID.' and r.rec_ID=rd.dtl_RecID and
                (rd.dtl_DetailTypeID='.DT_FACTOID_TARGET.' or rd.dtl_DetailTypeID='.DT_FACTOID_SOURCE.') and rd.dtl_Value='.$rec_id;
            }

            $ids = getRecordsForIn($query);

            $mtime = explode(' ', microtime());
            $query_times .= sprintf('%.3f', $mtime[0] + $mtime[1] - $stime).". ";
            $stime = 0;

            if($ids){

                $query = 'select d.dtl_RecID as rec_id, d.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue,  astext(d.dtl_Geo) as geo, ds1.rec_Title as ref, ds4.dtl_Value as ref2 from recDetails d '.
                ' left join Records ds1 on d.dtl_DetailTypeID in ('.DT_FACTOID_TARGET.','.DT_FACTOID_SOURCE.','.DT_FACTOID_ROLE.')'.
                ' and d.dtl_Value=ds1.rec_ID and ds1.rec_ID<>'.$rec_id.
                ' left join recDetails ds4 on '.
                '(d.dtl_DetailTypeID='.DT_FACTOID_ROLE.' and d.dtl_Value=ds4.dtl_RecID and ds4.dtl_DetailTypeID='.DT_NAME2.') or '.
                '(ds1.rec_ID<>'.$rec_id.' and d.dtl_DetailTypeID in ('.DT_FACTOID_TARGET.','.DT_FACTOID_SOURCE.') and d.dtl_Value=ds4.dtl_RecID and ds4.dtl_DetailTypeID='.DT_ENTITY_TYPE.')'.
                ' where d.dtl_RecID in ('.$ids.') ';


                addRelations($record, true, $query, RT_FACTOID);

            }

        }else if($record->type()==RT_ENTRY){
            //get annotations

            if($use_pointer_cache){
                $query = 'SELECT group_concat(r1.rac_RecID) FROM recAnnotationCache r1 where r1.rac_EntryRecID='.$rec_id;
            }else{
                $query = 'select group_concat(distinct rd.dtl_RecID) from recDetails rd where '.
                ' rd.dtl_DetailTypeID='.DT_ANNOTATION_ENTRY.' and rd.dtl_Value='.$rec_id;
            }


            $ids = getRecordsForIn($query);
            if($ids){

                $query = 'select d.dtl_RecID as rec_id, d.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue, ds1.dtl_Value as ref, ds2.dtl_Value as ref2 from recDetails d '.
                ' left join recDetails ds1 on d.dtl_Value=ds1.dtl_RecID and d.dtl_DetailTypeID = '.DT_ANNOTATION_ENTITY.' and ds1.dtl_DetailTypeID='.DT_NAME.
                ' left join recDetails ds2 on d.dtl_Value=ds2.dtl_RecID and d.dtl_DetailTypeID = '.DT_ANNOTATION_ENTITY.' and ds2.dtl_DetailTypeID='.DT_ENTITY_TYPE.
                ' where d.dtl_RecID in ('.$ids.')';


                addRelations($record, false, $query, RT_ANNOTATION);
            }
        }

        if($record->type()==RT_ENTITY || $record->type()==RT_MEDIA){

            //get annotations : names of entries
            //old way
            /*
            $query = 'select d.dtl_RecID as rec_id, d.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue, ds1.rec_Title as ref from Records r, recDetails d '.
            ' left join Records ds1 on d.dtl_Value=ds1.rec_ID'.
            ' where r.rec_RecTypeID='.RT_ANNOTATION.' and r.rec_ID=d.dtl_RecID and r.rec_ID in (select distinct rd.dtl_RecID from recDetails rd where '.
            ' rd.dtl_DetailTypeID='.DT_ANNOTATION_ENTITY.' and rd.dtl_Value='.$rec_id.') and d.dtl_DetailTypeID='.DT_ANNOTATION_ENTRY;
            */

            if($use_pointer_cache){
                $query = 'SELECT group_concat(r1.rac_RecID) FROM recAnnotationCache r1 where r1.rac_TargetRecID='.$rec_id;
            }else{
                $query = 'select group_concat(distinct rd.dtl_RecID) from recDetails rd where '.
                ' rd.dtl_DetailTypeID='.DT_ANNOTATION_ENTITY.' and rd.dtl_Value='.$rec_id;
            }

            $ids = getRecordsForIn($query);

            if($ids){

                $query = 'select d.dtl_RecID as rec_id, d.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue, ds1.rec_Title as ref from recDetails d '.
                ' left join Records ds1 on d.dtl_Value=ds1.rec_ID'.
                ' where d.dtl_RecID in ('.$ids.') and d.dtl_DetailTypeID='.DT_ANNOTATION_ENTRY;

                addRelations($record, false, $query, RT_ANNOTATION);
            }
        }

        //get relations
        /* old way
        $query = 'select r.rec_RecTypeID as rectype, d.dtl_RecID as rec_id, r.rec_URL as url, '.
        'd.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue, d.dtl_UploadedFileID as dtfile from Records r, recDetails d '.
        'where r.rec_RecTypeID in ('.RT_WEBLINK.','.RT_MEDIA.','.RT_ENTRY.','.RT_ENTITY.','.RT_MAP.','.RT_TERM.') and r.rec_ID=d.dtl_RecID and r.rec_ID in
        (SELECT r1.rrc_SourceRecID as recid FROM recRelationshipsCache r1 where r1.rrc_TargetRecID='.$rec_id.' union '.
        'SELECT r2.rrc_TargetRecID as recid FROM recRelationshipsCache r2 where r2.rrc_SourceRecID='.$rec_id.')';
        */

        $ids = getRecordsForIn('SELECT r1.rrc_SourceRecID as recid FROM recRelationshipsCache r1 where r1.rrc_TargetRecID='.$rec_id.' union '.
            'SELECT r2.rrc_TargetRecID as recid FROM recRelationshipsCache r2 where r2.rrc_SourceRecID='.$rec_id);

        if($ids){

            $query = 'select r.rec_RecTypeID as rectype, d.dtl_RecID as rec_id, r.rec_URL as url, '.
            'd.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue, d.dtl_UploadedFileID as dtfile from Records r, recDetails d '.
            'where r.rec_ID in ('.$ids.') and r.rec_RecTypeID in ('.RT_WEBLINK.','.RT_MEDIA.','.RT_ENTRY.','.RT_ENTITY.','.RT_MAP.','.RT_TERM.') and r.rec_ID=d.dtl_RecID';

            //get related records
            $res2 = mysql_query($query);
            //perhaps $dttypes = array(DT_NAME, DT_DESCRIPTION, DT_DATE, DT_CONTRIBUTOR_REF, DT_ENTITY_TYPE, DT_TYPE_MIME, DT_TYPE_LICENSE);

            $f_record = null;
            $entity_ids = array();
            while (($row2 = mysql_fetch_assoc($res2))) {

                if($f_record==null || $f_record->id() != $row2['rec_id']){
                    $f_record = new Record();
                    //$f_record->init( array("rec_id"=>$row2['rec_id'], "rectype"=>$row2['rectype']) );
                    $f_record->init2( $row2 );
                    $record->addRelation($f_record);
                    if($row2['rectype']==RT_ENTITY){
                        array_push($entity_ids, $row2['rec_id']);
                    }
                }

                $f_record->addDetail($row2);
            }

        }

        $mtime = explode(' ', microtime());
        $query_times .= sprintf('%.3f', $mtime[0] + $mtime[1] - $stime).". ";
        $stime = 0;

        if($record->type()==RT_MAP){

            //find TimePlace factoids
            if($use_pointer_cache){
                $query = 'SELECT group_concat(r1.rfc_RecID) as recid FROM recFacctoidsCache r1 where r1.rfc_SourceRecID in ('.implode(',', $entity_ids).')';
            }else{
                $query = 'select group_concat(distinct rd.dtl_RecID) from recDetails rd where '.
                ' rd.dtl_DetailTypeID='.DT_FACTOID_SOURCE.' and rd.dtl_Value in ('.implode(',', $entity_ids).')';
            }

            $ids = getRecordsForIn($query);

            if($ids){
                $query = 'select d.dtl_RecID as rec_id, d.dtl_DetailTypeID as dttype, d.dtl_Value as dtvalue,  astext(d.dtl_Geo) as geo '.
                ' from recDetails d where d.dtl_RecID in ('.$ids.')';

                //' from Records r, recDetails d '.
                //'where r.rec_RecTypeID='.RT_FACTOID.' and r.rec_ID=d.dtl_RecID and r.rec_ID in ('.$ids.')';


                addRelations($record, false, $query, RT_FACTOID);
            }
        }


    }else{
        //echo "not found";
    }

    return $record;
}

/**
* Loads header and details
*
* @param mixed $rec_id
* @param mixed $dttypes
*/
function getRecordFull($rec_id, $dttypes=null){

    $record = null;
    $query = 'select rec_ID as rec_id, rec_RecTypeID as rectype, rec_URL as url from Records where rec_ID='.$rec_id;
    $res = mysql_query($query);
    if($res){
        $row = mysql_fetch_assoc($res);
        if($row){
            $record = new Record();
            $record->init($row);
            $record->setDetails(getRecordDetails($rec_id, $dttypes));

            }
    }

    return $record;
}

/**
* Loads details
*
* @param mixed $rec_id
* @param mixed $dttypes
*/
function getRecordDetails($rec_id, $dttypes=null)
{

    $query = 'select dtl_DetailTypeID as dttype, dtl_Value as dtvalue, dtl_UploadedFileID as dtfile, astext(dtl_Geo) as geo from recDetails where dtl_RecID='.$rec_id;
    if($dttypes && count($dttypes)>0){
        $query = $query.' and dtl_DetailTypeID in ('.implode(',',$dttypes).')';
    }

    $res2 = mysql_query($query);
    $details = array();
    while (($row2 = mysql_fetch_assoc($res2))) {
        //$details[$row2['dttype']] = $row2['dtvalue'];
        array_push($details, $row2);
    }

    return $details;
}

/**
* Return CS list of record ids for given query
*
* @param mixed $query
*/
function getRecordsForIn($query){
    global $stime;

    $stime = explode(' ', microtime());
    $stime = $stime[1] + $stime[0];

    $no_loop = (strpos($query, "group_concat")>0);

    $res2 = mysql_query($query);
    if(!$res2){ // not found
    }else
        if($no_loop){
            $row = mysql_fetch_row($res2);
            if($row){
                return $row[0];
            }
    }else{
        $ids = array();
        while (($row = mysql_fetch_row($res2))) {
            array_push($ids, $row[0]);
        }
        if(count($ids)>0){
            return implode(',',$ids);
        }
    }
    return null;
}

/**
* Add related records for given record
*
* @param Record $record
* @param mixed $parseRef - remove entity type from tile
* @param mixed $query
* @param mixed $rectype
*/
function addRelations(Record $record, $parseRef, $query, $rectype=null){

    global $query_times, $stime;

    if($stime==0){
        $stime = explode(' ', microtime());
        $stime = $stime[1] + $stime[0];
    }


    $res2 = mysql_query($query);

    $f_record = null;
    while (($row2 = mysql_fetch_assoc($res2))) {

        if($f_record==null || $f_record->id() != $row2['rec_id']){
            $f_record = new Record();
            $f_record->init( array("rec_id"=>$row2['rec_id'], "rectype"=>($rectype?$rectype:$row2['rectype']) ) );
            $record->addRelation($f_record);
        }

        if($parseRef && @$row2['ref'] ){//remove enity type name from recTitle
            $lst = strrpos($row2['ref'], "(");
            if($lst>0){
                $row2['ref'] = substr($row2['ref'], 0, $lst);
            }
        }

        $f_record->addDetail($row2);

    }

    $mtime = explode(' ', microtime());
    $query_times .= sprintf('%.3f', $mtime[0] + $mtime[1] - $stime).". ";
    $stime = 0;
}


?>
