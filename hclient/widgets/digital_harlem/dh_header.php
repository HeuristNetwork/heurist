<?php

/**
* dh_header.php (Digital Harlem): Writes the header with statistical information about the database
*
* @package     Heurist academic knowledge management system
* @link        http://HeuristNetwork.org
* @copyright   (C) 2005-2016 University of Sydney
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @author      Ian Johnson     <ian.johnson@sydney.edu.au>
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @version     4
*/

/*
* Licensed under the GNU License, Version 3.0 (the "License"); you may not use this file except in compliance
* with the License. You may obtain a copy of the License at http://www.gnu.org/licenses/gpl-3.0.txt
* Unless required by applicable law or agreed to in writing, software distributed under the License is
* distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied
* See the License for the specific language governing permissions and limitations under the License.
*/

require_once(dirname(__FILE__)."/../../../hserver/System.php");
require_once(dirname(__FILE__)."/../../../hserver/dbaccess/db_recsearch.php");

$statistics = "";
$system = new System();
// connect to given database
if(@$_REQUEST['db'] && $system->init(@$_REQUEST['db'])){

    $appcode = @$_REQUEST['app'];
    if($appcode=='DigitalHarlem1935'){
        $appcode = 4800; //4751;
        $date_filter = '1934-12-31T23:59:59.999Z<>1936-01-01';
        $explanation = '1935';
    }else if($appcode=='DigitalHarlem'){
        $appcode = 4799; //4750
        $date_filter = '1914-12-31T23:59:59.999Z<>1931-01-01';
        $explanation = '1915-1930';
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
    .'</b> addresses and <b title="Number of documents">'.@$stats[15]
    .'</b> documentary sources related to Harlem, '.$explanation.'</p>';


}
?>
<html>
    <body>
        <div id="topstuff">

            <div  id="header">


                <div id="header_title">
                    <!-- 
                    <div class="home">
                    <p><b><a title="Home - Digital Harlem" href="#" onclick="{location.reload()}">Home</a></b>&nbsp;<b><a title="Focus on Map" href="#contentstart">Center Map</a></b>&nbsp; 
                    <a href="javascript:void(0)" onClick="toggleFullScreen()">X</a></p>
                    </div>
                    -->
                </div>


                <div class="intro">
                    <?=$statistics?>
                </div>

                <div class="spacer"></div>

            </div>

            <div id="searchbar">


                <div class="menubutton"><a class="menuitem" href="javascript:void(0)" onClick="{location.reload(true);}">HOME</a></div>
                <?php
                if(true){
                    // Building query
                    $query = 'SELECT rec_ID id, rec_Title as title, d1.dtl_Value as content, d2.dtl_Value as ord, '
                    .' d3.dtl_Value as btn_title'
                    .' FROM Records '
                    .' left join recDetails d2 on rec_ID=d2.dtl_recID and d2.dtl_DetailTypeID=94 '  //order 
                    .' , recDetails d1, recDetails d3 '   
                    .' WHERE rec_ID=d1.dtl_recID and rec_RecTypeID=25 and d1.dtl_DetailTypeID=4 and ' //content
                    .' rec_ID=d3.dtl_recID and d3.dtl_DetailTypeID=1 '   //title
                    .' ORDER BY d2.dtl_Value';


                    //find Web Content (25) for header buttons ---------------------------------------
                    /*        
                    var query;
                    if(window.hWin.HAPI4.sysinfo['layout']=='DigitalHarlem1935'){
                    //query = {"t":"25","f:154":"4800"};
                    $query = "t:25 f:154:4800 sortby:f:94";
                    }else{
                    //query = {"t":"25","f:154":"4799"};
                    $query = "t:25 sortby:f:94"; //f:154:4799 
                    }
                    */      
                    //@todo all this stuff should be implemented on client side since header is not static content anymore  

                    // Put record types & counts in the table
                    if($system->is_inted()){
                        $res = $system->get_mysqli()->query($query);
                        $stats = array();
                        while($row = $res->fetch_assoc()) { // each loop is a complete table row


                            if($appcode>0){
                                //detect app
                                $list = mysql__select_list($system->get_mysqli(), 'recDetails', 'dtl_Value', 
                                    'dtl_recID='.$row["id"].' and dtl_DetailTypeID=154'); //145
                                $classes = '';    
                                $isNotFound = true;
                                if(is_array($list))
                                    foreach($list as $val){
                                        if($val==$appcode ){
                                            $isNotFound = false;
                                            break;
                                        }
                                }
                                if($isNotFound) continue;
                            }

                            $btn_title = $row['btn_title']?$row['btn_title']:$row['title'];

                            if($row["ord"]>0){
                                print '<div class="menubutton">';
                                print '<a class="menuitem" href="javascript:void(0)" onClick="{ 
                                window.hWin.HEURIST4.msg.showMsgDlg(\'#webcontent'.$row["id"].'\', null,\''.$btn_title.'\');}">'
                                .$btn_title.'</a></div>';
                            }else{
                                //not ordered - means should be auto opened
                                ?>
                                <script>
                                    window.hWin.HEURIST4.msg.showMsgDlg('#webcontent<?=$row["id"]?>', null,'<?=$btn_title?>');
                                </script>
                                <?php
                            }
                            ?>
                            <div style="display:none;" id="webcontent<?=$row["id"]?>">
                                <?php
                                print $row["content"];
                                ?>
                            </div>

                            <?php
                        }//while
                    }
                }
                ?>
                <div class="menubutton"><a class="menuitem" href="javascript:void(0)" onClick="{ window.open('http://digitalharlemblog.wordpress.com/', 'DHBlog'); }">BLOG</a></div>

            </div>

        </div>  <!-- topstuff -->
    </body>
</html>