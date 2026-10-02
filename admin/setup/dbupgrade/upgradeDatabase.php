<?php
/**
* upgradeDatabase.php - Manages the user interface and process for upgrading a single Heurist database.
*
* @fileOverview This script provides an HTML interface for upgrading a Heurist database.
*               It detects if the current database's schema version is older than the
*               minimum required version (`HEURIST_MIN_DBVERSION`). If an upgrade is needed
*               and the user is an administrator, it allows them to trigger the upgrade process.
*               The upgrade itself involves applying a series of SQL or PHP scripts to update
*               the database schema incrementally. It displays information about the safety
*               and description of each upgrade script.
*
* @project     Heurist academic knowledge management system
* @package Admin/dbupgrade
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Stephen White
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       3.2
*/

if(!defined('PDIR')){
    define('PDIR','../../../');//need for proper path to js and css
    require_once dirname(__FILE__).'/../../../hclient/framecontent/initPageMin.php';
}
    require_once dirname(__FILE__).'/DBUpgrade.php';
    require_once dirname(__FILE__).'/DBUpgrade_1.4.php';

    $src_maj = intval( $system->settings->get('sys_dbVersion') );
    $src_min = intval( $system->settings->get('sys_dbSubVersion') );
    $src_sub = intval( $system->settings->get('sys_dbSubSubVersion') );

    $trg_maj = intval(explode(".", HEURIST_MIN_DBVERSION)[0]);

    if( @$_REQUEST['mode']!='action'
        && version_compare("$src_maj.$src_min.$src_sub", HEURIST_MIN_DBVERSION) >= 0){ //versions are ok redirect to main page
        redirectURL(HEURIST_BASE_URL . '?db=' . $_REQUEST['db']);
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en" xml:lang="en">
    <head>
        <title>Heurist database version upgrade</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8">
        <meta name="robots" content="noindex,nofollow">

        <link rel=icon href="<?php echo PDIR?>favicon.ico" type="image/x-icon">

<?php
        includeJQuery();
?>

        <!-- CSS -->
        <?php include_once dirname(__FILE__).'/../../../hclient/framecontent/initPageCss.php';?>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/detectHeurist.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_msg.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/utils_ui.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/hapi.js"></script>
        <script type="text/javascript" src="<?php echo PDIR;?>hclient/core/HSystemMgr.js"></script>

        <script type="text/javascript" src="<?php echo PDIR;?>hclient/widgets/profile/profileLogin.js"></script>
        <script>
            $(document).ready(function() {

                if(!window.hWin.HAPI4){
                    window.hWin.HAPI4 = new hAPI('<?php echo htmlspecialchars($_REQUEST['db'])?>', onHapiInit);
                }else{
                    onHapiInit(true);
                }

                function onHapiInit(success){

                    var prefs = window.hWin.HAPI4.get_prefs();
                    if(!window.hWin.HR){
                        //loads localization
                        window.hWin.HR = window.hWin.HAPI4.setLocale(prefs['layout_language']);
                    }

                    $('button').button();

                    $(window.hWin.document).on(
                        window.hWin.HAPI4.Event.ON_CREDENTIALS,
                        function(){
                            if(window.hWin.HAPI4.has_access()){
                                window.location.reload()
                            }
                        });

                }

            });
        </script>
    </head>

    <body class="ui-heurist-header1" style="font-size:0.7em">
        <div class="ui-corner-all ui-widget-content"
            style="text-align:left; min-width:220px; padding: 0.5em; position:absolute; top:30px; bottom:30px; left:60px;right:60px">

            <div class="logo" style="background-color:#2e3e50;width:100%"></div>

            <div style="margin-top:24px;">
                <h2>Heurist Database Version upgrade</h2>
            </div>
            <div style="position:absolute;top:100px;bottom:10px;width: 99%;overflow-y:auto;text-align:left;">

                <?php

                    if($system->isAdmin() && @$_REQUEST['mode']=='action' && $src_maj==$trg_maj){ //upgrade minor versions
                    //2d iteration ACTION!!!
                        $upgrade_res = doUpgradeDatabase($system, null, true, true);

                        if($upgrade_res['status']=='upgraded' || $upgrade_res['status']=='uptodate'){
                            print "<p>Upgrade was successeful.&nbsp;&nbsp;<a href='".HEURIST_BASE_URL."?db=".$system->dbname()."'>Return to main page</a></p>";
                        }

                    }
                    else{
                    //1d itweration INFORMATION!!!
                    ?>

                    <p>Your database <b> <?=$system->dbname()?> </b> currently uses database format version
                        <b><?=$src_maj.'.'.$src_min.'.'.$system->settings->get('sys_dbSubSubVersion')?> </b>
                        <br>(this is distinct from the program version # listed below)</p>

                    <p>This version of the software <b>(Vsn <?=HEURIST_VERSION?>)</b>
                        requires upgrade of your database to format version <b><?=HEURIST_MIN_DBVERSION?></b>
                        <hr>
                    </p>

                    <?php
                        //automatic upgrade failed (see initPage.php)
                        if(isset($upgrade_res) && $upgrade_res['status']=='error'){
                            print errorDiv('Automatic upgrade failed: '.end($upgrade_res['report']));
                        }

                        if($system->isAdmin()){

                            if($src_maj!=$trg_maj){
                                print '<p style="font-weight:bold">Automatic upgrade applies to minor version updates only (ie. within database version 1, 2 etc.).'.CONTACT_HEURIST_TEAM_PLEASE.' to upgrade major version (1 => 2, 2 => 3)</p>';
                            }else{

                                //verification that all scripts exist and get safety rating from these scrips

                                $scripts_info = "";
                                $is_allfind = true;
                                //DBUpgrade_1.1.0_to_1.2.0.sql etc.
                                $dir = HEURIST_DIR.'admin/setup/dbupgrade/';
                                while ($src_min<3) {
                                    $filename = "DBUpgrade_$src_maj.$src_min.0_to_$trg_maj.".($src_min+1).".0.sql";
                                    if( file_exists($dir.$filename) ){

                                        $safety = "";
                                        $description = "";

                                        $file = fopen($dir.$filename, "r");
                                        if($file){
                                            while(!feof($file)){
                                                $line = fgets($file);

                                                if(strpos($line,"-- Safety rating")===0){

                                                    $safety = substr($line,17);

                                                }elseif(strpos($line,"-- Description")===0){

                                                    $description = substr($line,15);
                                                }

                                                if($safety && $description){
                                                    break;
                                                }
                                            }
                                            fclose($file);
                                        }else{
                                            print "<p style='font-weight:bold'>Cannot read the upgrade script '".$filename."'."
                                            .CONTACT_HEURIST_TEAM_PLEASE."</p>";
                                            $is_allfind = false;
                                            break;
                                        }

                                        $scripts_info  = $scripts_info."<tr><td width='130'>".
                                        $src_maj.".".$src_min.".0 to ".$src_maj.".".($src_min+1).".0</td><td>".$safety
                                        ." <i>".$description."</i></td></tr>";

                                        $src_min++;
                                        $src_sub = 0;
                                    }else{
                                        print "<p style='font-weight:bold'>Cannot find the upgrade script '".$filename
                                        ."'.".CONTACT_HEURIST_TEAM_PLEASE."</p>";
                                        $is_allfind = false;
                                        break;
                                    }
                                }

                                if($is_allfind && $src_min==3 && $src_sub<19){

$description = 'Modify tables:  defRecStructure(rst_SemanticReferenceURL,rst_TermsAsButtons,rst_PointerMode,rst_NonOwnerVisibility),   recUploadedFiles(ulf_PreferredSource), defTerms (trm_OrderInBranch), recDetails(dtl_HideFromPublic),sysIdentification(sys_NakalaKey), sysUGrps(usr_ExternalAuthentication)   Add tables:sysWorkflowRules,defTranslations,recDetailsDateIndex';
                                    if($src_sub<14){
                                        $description .= '. Date index will be rebuilt - it may take considerable time';
                                    }

                                    $scripts_info  = $scripts_info
                                        .'<tr><td width="130">1.3.'.$src_sub.' to 1.3.19</td><td> SAFE '
                                        ." <i>".$description."</i></td></tr>";
                                    $src_sub = 19;
                                }

                                if($is_allfind && $src_min==3 && $src_sub>=19){
                                    $scripts_info  = $scripts_info
                                        .'<tr><td width="130">1.3.19 to 1.4.0</td><td> SAFE '
                                        ." <i>".DBUPGRADE_1_4_DESCRIPTION."</i></td></tr>";
                                }

                                if($is_allfind)    {
                                ?>

                                <p>Upgrades marked SAFE are unlikely to harm your database but will make it incompatible with older versions of the code.<br>
                                    Upgrades marked DANGEROUS involve major changes to your database structure and could cause data loss.<br>
                                    For these upgrades we strongly recommend cloning your database first with an older version of the software<br>
                                    or asking your system adminstrator to backup the MySQL database and database file directory.<br>
                                    <br>
                                    If you need a partial upgrade eg. to an intermediate version, you will need to run an older version of the software -
                                    please consult Heurist support.
                                </p>
                                <p>
                                    Please determine whether you need backward compatibility and/or clone your database with an older version before proceeding.
                                    <br>
                                    Specific changes in each version upgrade are listed below:
                                </p>

                                <table role="presentation">
                                    <?php echo $scripts_info;?>
                                </table>
                                <br>
                                <p>
                                    <form action="<?php echo HEURIST_BASE_URL?>admin/setup/dbupgrade/upgradeDatabase.php">
                                        <input type="hidden" name="db" value="<?=$system->dbname()?>">
                                        <input type="hidden" name="mode" value="action">
                                        <button value="submit">Upgrade database</button>
                                    </form>
                                </p>
                                <?php
                                }
                            }

                        }else{ //not admin
                        ?>
                <div class="ui-state-error" style="width:90%;margin:auto;margin-top:10px;padding:10px;">
                    <span class="ui-icon ui-icon-alert" style="float: left; margin: .3em;"></span>
                    You must be logged in as database owner to upgrade the database structure
                    <button onclick="showLoginDialog(true)">Login</button>
                </div>
                        <?php
                        }
                    }
                ?>
<br><br>
<a href="<?=HEURIST_BASE_URL?>">Select different database</a>
            </div>
        </div>
    </body>
</html>
