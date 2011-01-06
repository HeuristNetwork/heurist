<?php
/**
 * FileName:    class.search.php
 * Author:      Hanna Chamoun
 *
 * Description: This class is used to perform search action on the heurist
 *              database for the use of accessing Biblio items
 *
 */
require_once('class.biblio.php');
//require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');
require_once(dirname(__FILE__).'/../../search/advanced/adv-search.php');

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
    mysql_connection_db_select(DATABASE);
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
