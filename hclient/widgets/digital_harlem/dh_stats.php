<?php

/**
* dh_stats.php (Digital Harlem): code spnipper to gather statistical information about the database
*
* @package     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2020 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at https://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/
if(function_exists('recordSearch')) {

    $appcode = @$_REQUEST['app'];
    if($appcode=='DigitalHarlem1935'){
        $appcode = 4800; //4751;
        $date_filter = '1934-12-31T23:59:59.999Z<>1936-01-01';
        $explanation = '1935';
    }else if($appcode=='DigitalHarlem'){
        $appcode = 4799; //4750
        $date_filter = '1914-12-31T23:59:59.999Z<>1931-01-01';
        $explanation = '1915-1930';
    }else{
        $appcode = 0;
    }  


    $stats = array();

    //search for PERSONS INVOLVED INTO EVENTS
    $records = recordSearch($system, array(
        'q'=>'[{"t":"10"},{"relatedfrom:14":[{"t":"14"},{"f:10":"'.$date_filter.'"}]}]',
        'detail'=>'count'));
    //"1914-12-31T23:59:59.999Z<>1931-01-01"

    if(@$records['status']!='ok'){
        $stats['10'] = 'XXX';
    }else{
        $stats['10'] = $records['data']['count'];
    }


    //EVENTS
    $records = recordSearch($system, array(
        'q'=>'[{"t":"14"},{"f:10":"'.$date_filter.'"}]',
        'detail'=>'count'));

    if(@$records['status']!='ok'){
        $stats['14'] = 'XXX';
    }else{
        $stats['14'] = $records['data']['count'];
    }


    list($dmin, $dmax) = explode('<>',$date_filter);

    $query = "SELECT count(distinct d2.dtl_Value) as count 
    FROM Records, recDetails d1, recDetails d2 WHERE rec_RecTypeID=16 and 
    d1.dtl_RecID = rec_ID and d2.dtl_RecID = rec_ID and     
    d1.dtl_DetailTypeID=10 and d1.dtl_Value between '$dmin' and '$dmax' and
    d2.dtl_DetailTypeID=90 ";

    /* alternative query          
    "SELECT count(distinct rl_TargetID) as count 
    FROM Records, recLinks, recDetails WHERE rec_RecTypeID=16 and 
    rl_SourceID = rec_ID and rl_DetailTypeID=90 and
    dtl_RecID = rec_ID and     
    dtl_DetailTypeID=10 and dtl_Value between '1934-12-31' and '1936-01-01'"          
    */        
    $res = $system->get_mysqli()->query($query);
    if($res){
        $row = $res->fetch_assoc();
        $stats['16']= $row["count"];   //USED ADDRESSES (PLACE FUNCTIONS)
    }


    //DOCUMENTS
    $records = recordSearch($system, array(
        'q'=>'[{"t":"15"},{"f:9":"'.$date_filter.'"}]',
        'detail'=>'count'));

    if(@$records['status']!='ok'){
        $stats['15'] = 'XXX';
    }else{
        $stats['15'] = $records['data']['count'];
    }

    $statistics = '<p>Currently presenting '// .'<b title="Number of people">'.@$stats[10].'</b> people, 
    .'<b title="Number of events">'.@$stats[14]
    .'</b> events, <b title="Number of addresses">'.@$stats[16]
    .'</b> addresses and <b title="Number of documents">'.@$stats[15].'</b> sources';
    //.'</b> documentary sources related to Harlem, '.$explanation.'</p>';
    
}
?>
