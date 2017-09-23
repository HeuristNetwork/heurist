<?php

/*
* Copyright (C) 2005-2016 University of Sydney
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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2016 University of Sydney
* @link        http://HeuristNetwork.org
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  Records/Util 
*/
   

  require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
  require_once(dirname(__FILE__).'/../../common/t1000/t1000.php');
  require_once(dirname(__FILE__).'/../disambig/testSimilarURLs.php');

  if (! is_logged_in()) {
    header('Location: ' . HEURIST_BASE_URL . 'common/connect/login.php?db='.HEURIST_DBNAME);
    return;
  }

  if (! $_REQUEST['bkmk_url']) {
    header('Location: '.HEURIST_BASE_URL.'records/add/addRecordPopup.php?db='.HEURIST_DBNAME);
    return;
  }

  mysql_connection_select(DATABASE);

  $base_url = str_replace('&site_hierarchy', '', $_SERVER['REQUEST_URI']);
  $use_site_hierarchy = array_key_exists('site_hierarchy', $_REQUEST);

?>
<html>
  <head>
    <title>Heurist - Add bookmark (disambiguation)</title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <link rel="stylesheet" type="text/css" href="<?=HEURIST_BASE_URL?>common/css/global.css">
    <link rel=stylesheet type=text/css href='<?=HEURIST_BASE_URL?>common/css/disambiguate.css'>
    <link rel="icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="<?=HEURIST_BASE_URL?>favicon.ico" type="image/x-icon">
  </head>
  <body>
    <a id=home-link href='../../'>
      <div id=logo title="Click the logo at top left of any Heurist page to return to your Favourites"></div>
    </a>
    <div id=page>
      <div class="banner">
        <h2 style="padding:0 10px">Possible duplicate URL</h2>
      </div>
      <?= $page_header ?>
      <div id="main">
        <form action="addRecord.php?db="<?=HEURIST_DBNAME?> method="get" onSubmit="var bid = elements['recID']; for (i=0; i < bid.length; ++i) { if (bid[i].checked) return true; } alert('Please select one of the options'); return false;">
          <input type="hidden" name="bkmrk_bkmk_title" value="<?= htmlspecialchars($_REQUEST['bkmk_title']) ?>">
          <input type="hidden" name="bkmrk_bkmk_url" value="<?= htmlspecialchars($_REQUEST['bkmk_url']) ?>">
          <input type="hidden" name="bkmrk_bkmk_description" value="<?= htmlspecialchars($_REQUEST['bkmk_description']) ?>">
          <input type="hidden" name="ver" value="h3">
          <input type="hidden" name="f" value="<?= @$_REQUEST['f']?htmlspecialchars($_REQUEST['f']):'' ?>">
          <input type="hidden" name="tag" value="<?= @$_REQUEST['tag']?htmlspecialchars($_REQUEST['tag']):'' ?>">
          <input type="hidden" name="rec_rectype" value="<?= htmlspecialchars(@$_REQUEST['rec_rectype']) ?>">
          <div class="">
            <div class="siteHierarchy"><h3>Showing: </h3>
              <?php if ($use_site_hierarchy) { ?>
                <span style="width: 150px;"><b>all URLs from this site</b></span> <span style="width: 150px;"><a href="<?= htmlspecialchars($base_url) ?>">records with similar URL</a></span>
                <?php } else { ?>
                <span style="width: 150px;"><b>records with similar URL</b></span> <span style="width: 150px;"><a href="<?= htmlspecialchars($base_url . '&site_hierarchy') ?>">all URLs from this site</a></span>
                <?php } ?>
            </div>
          </div>
          <?php
            if (! $use_site_hierarchy) {
              $bib_ids = similar_urls($_REQUEST['bkmk_url']);

              if (! $bib_ids) {	/* no similar URLs */

                $noproto_url = preg_replace('!^http://(?:www[.])?([^/]*).*!', '\1', $_REQUEST['bkmk_url']);	// URL minus the protocol + possibly www.
          ?>
              <div><b>No similar URLs have been bookmarked, but there are other URLs on <span>
                    <?= htmlspecialchars($noproto_url) ?>
                  </span>.</b></div>
              <div>You may look at <a href="<?= htmlspecialchars($base_url . '&site_hierarchy') ?>">all known URLs from the same site</a>,<br>
                or <a href="addRecord.php?db=<?=HEURIST_DBNAME?>&recID=-1&bkmrk_bkmk_url=<?= urlencode($_REQUEST['bkmk_url']) ?>&bkmrk_bkmk_title=<?= urlencode($_REQUEST['bkmk_title']) ?>&bkmrk_bkmk_description=<?= urlencode($_REQUEST['bkmk_description']) ?>">add a bookmark</a> for <b><span>
                    <?= htmlspecialchars($_REQUEST['bkmk_url']) ?>
                  </span></b>.</div>
              <?php
              } else {
              ?>
              <div><b>Please choose URL to bookmark, then:&nbsp;&nbsp;</b>
                <input type="submit" value="Save bookmark">
              </div>

              <!-- duplicates table FIXME: convert to DIV with 2-column view-->

              <div id="comparoTable">
                <div class="roundedBoth existingRecords">
                  <h2>URLs already in Heurist:</h2>
                  <?php
                    $bkmk_url = $_REQUEST['bkmk_url'];
                    $bkmk_url_len = strlen($bkmk_url);

                    $res = mysql_query('select * from Records where rec_ID in (' . join(',', $bib_ids) . ') '.
                      'and (rec_OwnerUGrpID in (0'.(get_user_id()?','.get_user_id():'').')'.
                      ' or not rec_NonOwnerVisibility="hidden")');
                    $all_bibs = array();
                    while ($row = mysql_fetch_assoc($res))
                      $all_bibs[$row['rec_ID']] = $row;

                    foreach ($bib_ids as $bib_id) {
                      $row = $all_bibs[$bib_id];
                      $common_url = find_commonality($row['rec_URL'], $bkmk_url);
                    ?>
                    <div class=existingRecordItem>
                      <div class=label>
                        <label>
                          <input type="radio" name="recID" value="<?= $row['rec_ID'] ?>">
                          <?= htmlspecialchars($row['rec_Title']) ?>
                        </label>
                      </div>
                      <div class=url><a class="externalLink" target="_testwindow" href="<?= htmlspecialchars($row['rec_URL']) ?>"
                          onClick="return checkURL(&quot;<?= htmlspecialchars($row['rec_URL']) ?>&quot;);"></a><tt title="<?= htmlspecialchars($row['rec_URL']) ?>">
                          <?php
                            /*
                            if (strlen($row['rec_URL']) < 100)
                            print htmlspecialchars($row['rec_URL']);
                            else
                            print htmlspecialchars(substr($row['rec_URL'], 0, 90) . '...');
                            */
                            $common_url_len = strlen($common_url);
                            print '<span class="common_url" title="'.htmlspecialchars($row['rec_URL']).'">'.htmlspecialchars($common_url).'</span>'.substr($row['rec_URL'], $common_url_len, $bkmk_url_len-$common_url_len + 10);
                            if (strlen($row['rec_URL']) > $bkmk_url_len+10) print '...';
                          ?>
                      </tt> </div>
                    </div>
                    <?php
                    }
                  ?>
                </div>
                <div class="roundedBoth newRecord">
                  <h2>New URL:</h2>
                  <div class=warning>This is the resource you asked to add to Heurist.  If selected it will be added as a new record.</div>
                  <div class=info>If you select an existing record, that record will be opened for editing.</div>
                  <div class=newRecordItem>
                    <div class=label>
                      <label>
                        <input type="radio" name="recID" value="-1">
                        <?= htmlspecialchars($_REQUEST['bkmk_title']) ?>
                      </label>
                    </div>
                    <div class=url><a class="externalLink" target="_testwindow" href="<?= htmlspecialchars($_REQUEST['bkmk_url']) ?>" onClick="return checkURL(&quot;<?= htmlspecialchars($_REQUEST['bkmk_url']) ?>&quot;);"></a><tt title="<?= htmlspecialchars($_REQUEST['bkmk_url']) ?>">
                        <?php /*?><?php
                          if (strlen($_REQUEST['bkmk_url']) < 100)
                          print htmlspecialchars($_REQUEST['bkmk_url']);
                          else
                          print htmlspecialchars(substr($_REQUEST['bkmk_url'], 0, 90) . '...')
                        ?><?php */?>
                        <?php
                          print '<span class="common_url" title="'.htmlspecialchars($bkmk_url).'">'.htmlspecialchars($common_url).'</span>'.substr($bkmk_url, $common_url_len, $bkmk_url_len-$common_url_len + 10);
                        ?>
                    </tt> </div>
                  </div>
                </div>

              </div>
              <?php
              }
            } else {
              $bkmk_url = $_REQUEST['bkmk_url'];
              $bkmk_url_len = strlen($bkmk_url);

              $site = preg_replace('!^http://([^/]+).*!', '$1', $bkmk_url);
            ?>
            <tr>
              <td><b>Please choose URL to bookmark, then:&nbsp;&nbsp;</b>
                <input type="submit" value="Save bookmark"></td>
              <!-- <td colspan="4" style="text-align: right;">
              If choosing an existing URL, you can <label><b>check this box <input type="checkbox" name="force_new" value="1" style="margin: 0px; padding: 0px; border: 0px;"></b></label> to attach an additional bookmark<br>
              (this creates multiple bookmarks attached to one URL - use sparingly)
              </td>   -->
            </tr>
            <tr>
              <td colspan="5">&nbsp;</td>
            </tr>
            </table>
            <div id="comparoTable">
              <div class="roundedBoth existingRecords">
                <h2>URLs already in Heurist:</h2>
                <?php
                  $all_site_urls = site_urls($bkmk_url);
                  foreach ($all_site_urls as $url => $id_title) {
                    $common_url = find_commonality($url, $bkmk_url);
                  ?>
                  <div class=existingRecordItem>
                    <div class=label>
                      <label>
                        <input type="radio" name="recID" value="<?= $id_title[0] ?>">
                        <?= htmlspecialchars($id_title[1]) ?>
                      </label>
                    </div>
                    <div class="url"><a class="externalLink" target="_testwindow" href="<?= htmlspecialchars($url) ?>"
                        onClick="return checkURL(&quot;<?= htmlspecialchars($url) ?>&quot;);"></a><tt>
                        <?php
                          /*
                          if (strlen($url) < 100)
                          print htmlspecialchars($url);
                          else
                          print htmlspecialchars(substr($url, 0, 90) . '...')
                          */
                          $common_url_len = strlen($common_url);
                          print '<span class="common_url" title="'.htmlspecialchars($url).'">'.htmlspecialchars($common_url).'</span>'.substr($url, $common_url_len, $bkmk_url_len-$common_url_len + 10);
                        ?>
                      </tt></div>
                  </div>
                  <?php
                  }
                ?>
              </div>
              <div class="roundedBoth newRecord">
                <h2>New URL:</h2>
                <div class=newRecordItem>
                  <div class=label>
                    <label>
                      <input type="radio" name="recID" value="-1">
                      <b>
                        <?= htmlspecialchars($_REQUEST['bkmk_title']) ?>
                      </b>
                      </td>
                    </label>
                  </div>
                  <div class=url><a class="externalLink" target="_testwindow" href="<?= htmlspecialchars($bkmk_url) ?>" onClick="return checkURL(&quot;<?= htmlspecialchars($bkmk_url) ?>&quot;);"></a><tt title="<?= htmlspecialchars($bkmk_url) ?>">
                      <?php
                        print '<span class="common_url" title="'.htmlspecialchars($bkmk_url).'">'.htmlspecialchars($common_url).'</span>'.substr($bkmk_url, $common_url_len, $bkmk_url_len-$common_url_len + 10);
                      ?>
                  </tt> </div>
                </div>
              </div>
            </div>
            <tr>
              <td><img src="../../common/images/200.gif"></td>
              <td colspan="2"></td>
            </tr>
            </table>
            </td>
            </tr>
            <?php
            }
          ?>
        </form>
      </div>
      <div style="position: absolute; display: none; background: url('<?=HEURIST_BASE_URL?>common/images/100x100-check.gif');" id="popupProtector"></div>
      <div style="background-color: #600000; border: 0px; margin: 0px; padding: 10px; overflow: hidden; display: none; position: absolute; text-align: center;" id="url_checker">
        <table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%" style="font-size: 12px;">
          <tr>
            <td style="color: white; font-weight: bold;">Previewing bookmark</td>
            <td style="width: 150px; text-align: right;"><a style="text-decoration: none; color: white;" onClick="endCheckURL(); return false;" href='<?=HEURIST_BASE_URL?>common/html/blank.html'>[close this window]</a></td>
          </tr>
          <tr>
            <td colspan="2"><iframe style="border: 0px; margin: 0px; padding: 0px; background-color: white;" frameBorder="0" name="uc_frame" id="uc_frame_obj" src='<?=HEURIST_BASE_URL?>common/html/blank.html'></iframe></td>
          </tr>
        </table>
      </div>
    </div>
  </body>
</html>
<?php

  function find_commonality($url1, $url2) {

    $url1_slash_pos = strpos($url1, '/', 7);
    if (! $url1_slash_pos) return $url1;
    $url2_slash_pos = strpos($url2, '/', 7);
    if (! $url2_slash_pos) return $url1;

    $url1_  = substr($url1, $url1_slash_pos);
    $url2_  = substr($url2, $url2_slash_pos);

    $url1_split = preg_split('!(?<=[/?&])!', $url1_);
    $url2_split = preg_split('!(?<=[/?&])!', $url2_);

    $common = substr($url1, 0, $url1_slash_pos);
    $maxi = min(count($url1_split), count($url2_split));
    for ($i=0; $i < $maxi; ++$i)
      if ($url1_split[$i] == $url2_split[$i])
        $common .= $url1_split[$i];
      else
        break;

    return $common;
  }

?>
