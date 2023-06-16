<?php
$is_included = (defined('PDIR'));
$cache_missed = false;

if($is_included){

    print '<div style="padding:10px"><h3 id="dateindex_cache_msg">Check Record Details Date Index</h3><br>';
    
}else{
    //own header
    define('PDIR','../../');
    
    require_once (dirname(__FILE__).'/../../hsapi/System.php');
    
    $system = new System();
    if( ! $system->init(@$_REQUEST['db']) ){
        //get error and response
        print $system->getError()['message'];
        return;
    }
    if(!$system->is_admin()){ //  $system->is_dbowner()
        print '<span>You must be logged in as Database Administrator to perform this operation</span>';
    }
    
    
?>    
<html>
    <head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <link rel="stylesheet" type="text/css" href="<?php echo PDIR;?>h4styles.css" />
    </head>
    <body class="popup">
        <div class="banner">
            <h2>Check Record Details Date Index</h2>
        </div>
        <div id="page-inner">
<?php    
}
    $is_ok = true;
    if(@$_REQUEST['fixdateindex']=='1'){
        
        if(recreateRecDetailsDateIndex($system, true, @$_REQUEST['convert_dates']=='1')){
            print '<div><h3 class="res-valid">Record Details Date Index has been successfully recreated</h3></div>';
        }else{
            $response = $system->getError();    
            print '<div><h3 class="error">'.$response['message'].'</h3></div>';
            $is_ok = false;
        }
    }

    if($is_ok){
        $mysqli = $system->get_mysqli();
        
        $index_outdated = false;
    
        $is_table_exist = hasTable($mysqli, 'recDetailsDateIndex');    
        
        if($is_table_exist){
    
            //count of index 
            $query = 'SELECT count(rdi_DetailID) FROM recDetailsDateIndex';
            $cnt_index = mysql__select_value($mysqli, $query);

            //count of missed relations in recLinks                    
            $query = 'SELECT count(dtl_ID) FROM recDetails, defDetailTypes  WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date" AND dtl_Value!=""';
            $cnt_dates = mysql__select_value($mysqli, $query);

            $query = 'SELECT count(rdi_DetailID) FROM recDetailsDateIndex WHERE rdi_estMinDate=0 AND rdi_estMaxDate=0';
            $cnt_empty = mysql__select_value($mysqli, $query);

            print '<div style="padding:5px">Total count of date fields:&nbsp;<b>'.$cnt_dates.'</b>'
                    .($cnt_dates==$cnt_index && $cnt_empty==0?'&nbsp;&nbsp;&nbsp;&nbsp;All date entries are in index. Index values are OK.':'').'</div>';
            
            if($cnt_dates > $cnt_index || $cnt_empty>0){
                if($cnt_dates > $cnt_index){
                    print '<div style="padding:5px;color:red">Missed entries in index:&nbsp;<b>'.($cnt_dates - $cnt_index).'</b></div>';
                }
                if($cnt_empty > 0){
                    print '<div style="padding:5px;color:red">Empty dates in index:&nbsp;<b>'.($cnt_empty).'</b></div>';
                }

                echo '<div><h3 class="error">Recreate Relationship cache to restore missing entries</h3></div>';        
                
                $index_outdated = true;
            }else{
                echo '<div><h3 class="res-valid">Record Details Date Index is valid</h3></div>';        
            }

        }else{
                echo '<div><h3 class="res-valid">Record Details Date Index table does not exist</h3></div>';        
        }
        
        if($system->get_system('sys_dbSubSubVersion')<14){
?>            
            <label>
                <input type="checkbox" id="convert_dates"/>
                Converts old Plain String temporals to new JSON format in record details. (DB version will be upgraded to 1.3.14 and some search function will stop working for code version older than 6.3.16)
            </label>                    
<?php                         
        }else{
            print '<input type="hidden" id="convert_dates"/>';            
        }
?>        
        <div><br><br>
                <button onclick="{var ischk=document.getElementById('convert_dates').checked?'1':'0';window.open('listDatabaseErrors.php?db=<?php echo HEURIST_DBNAME;?>&fixdateindex=1&convert_dates='+ischk,'_self')}">Recreate date index</button>
        </div>        
<?php    
    }//isok            
    
if(!$is_included){    
    print '</div></body></html>';
}else{
    
    if($index_outdated || !$is_table_exist){ //highlight in header of verification
        echo '<script>$(".dateindex").css("background-color", "#E60000");</script>';
    }else{
        echo '<script>$(".dateindex").css("background-color", "#6AA84F");</script>';        
    }
    print '<br /></div>';
}
?>
