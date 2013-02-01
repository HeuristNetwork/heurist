<?php

// ARTEM: TO REMOVE

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/

?>

<?php
/**
 * FileName:    class.searchCursor.php
 * Author:      Hanna Chamoun
 *
 * Description: This class is used to perform search action on the heurist
 *              database for the use of accessing Biblio items
 *
 */
require_once('class.record.php');
//require_once(dirname(__FILE__).'/../../common/connect/applyCredentials.php');
require_once(dirname(__FILE__).'/../../common/php/dbMySqlWrappers.php');
require_once(dirname(__FILE__).'/../../search/parseQueryToSQL.php');

class Search{
  var $result;
  var $querry;

  /**
   *  Constructor
   *
   *  @param a valid sql querry
   */
  function Search($querry){
    $this->querry = $querry;
    $this->_dbConnect();
    $this->result = $this->_getDBData();
  }


  /**
   * Connection to the database
   * @return void
   *
   */
  function _dbConnect(){
    mysql_connection_select(DATABASE);
  }


  /**
   * Retrieves the data from the database, executes the sql querry
   *
   * @return Database Result
   *
   */
  function _getDBData(){
    return mysql_query($this->querry);
  }

  /**
   * Returns next records object or false if no biblios available
   *
   * @return array [class.records.php]
   */
  function fetch() {
      if($row = mysql_fetch_array($this->result)) {
        return new Biblio($row['rec_ID']);
      } else {
        return false;
      }
  }

  /**
   * Returns the size of the result
   *
   * @return integer
   */
  function size() {
        return mysql_num_rows($this->result);
  }

  /**
   * Resets the searchresult to the first element
   *
   * @return void
   */
  function reset() {
        mysql_data_seek($this->result,0);
  }
}
?>
