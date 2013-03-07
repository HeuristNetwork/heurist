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
* brief description of file
*
* @author      Tom Murtagh
* @author      Kim Jackson
* @author      Ian Johnson   <ian.johnson@sydney.edu.au>
* @author      Stephen White   <stephen.white@sydney.edu.au>
* @author      Artem Osmakov   <artem.osmakov@sydney.edu.au>
* @copyright   (C) 2005-2013 University of Sydney
* @link        http://Sydney.edu.au/Heurist
* @version     3.1.0
* @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @package     Heurist academic knowledge management system
* @subpackage  !!!subpackagename for file such as Administration, Search, Edit, Application, Library
*/



// ARTEM: TO REMOVE

/**
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 **/


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
