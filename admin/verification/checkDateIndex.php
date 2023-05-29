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
        
        if(recreateRecDetailsDateIndex($system, true, @$_REQUEST['details']=='1')){
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
            $query = 'SELECT count(dtl_ID) FROM recDetails, defDetailTypes  WHERE dtl_DetailTypeID=dty_ID AND dty_Type="date"';
            $cnt_dates = mysql__select_value($mysqli, $query);
            

            print '<div style="padding:5px">Total count of date fields:&nbsp;<b>'.$cnt_dates.'</b>'
                    .($cnt_dates==$cnt_index?'':'&nbsp;&nbsp;&nbsp;&nbsp;All relationships are in cache. Cache is OK.').'</div>';
            
            if($cnt_dates > $cnt_index){
                print '<div style="padding:5px;color:red">Missed dates in index:&nbsp;<b>'.($cnt_dates - $cnt_index).'</b></div>';

                echo '<div><h3 class="error">Recreate Relationship cache to restore missing entries</h3></div>';        
                
                $index_outdated = true;
            }else{
                echo '<div><h3 class="res-valid">Record Details Date Index is valid</h3></div>';        
            }

        }else{
                echo '<div><h3 class="res-valid">Record Details Date Index tabale does not exist</h3></div>';        
        }
    
?>        
        <div><br><br>
                <button onclick="{window.open('listDatabaseErrors.php?db=<?php echo HEURIST_DBNAME;?>&fixdateindex=1','_self')}">Recreate date index</button>
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
