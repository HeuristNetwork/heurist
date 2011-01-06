<?php
/**
 * FileName:    class.search.php
 * Author:      Hanna Chamoun
 *
 * Description: This class is used to perform search action on the heurist
 *              database for the use of accessing Biblio items
 *
 */
require_once('class.pseudorssbib.php');
//require_once(dirname(__FILE__).'/../../common/connect/cred.php');
require_once(dirname(__FILE__).'/../../common/connect/db.php');

class RssSearch{
  var $result;
  var $feedURL;
  var $rawContents;
  var $simpleXMLDoc;
  var $domDoc;
  var $items;
  var $fetchIndex;
  var $itemCnt;
  var $title;
  var $description;
  /**
   *  Constructor
   *
   *  @param feedURL a valid rss Feed URL
   */
  function RssSearch($feedURL){
    $this->feedURL = $feedURL;
    $this->readFeed();
  }


  /**
   * Connection to the database
   * @return void
   *
   */
  function readFeed(){
	$ch = curl_init($this->feedURL);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);	// follow server header redirects
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);	// don't verify peer cert
	curl_setopt($ch, CURLOPT_TIMEOUT, 100);	// timeout after ten seconds
	curl_setopt($ch, CURLOPT_MAXREDIRS, 5);	// no more than 5 redirections
	curl_setopt($ch, CURLOPT_PROXY, 'www-cache.usyd.edu.au:8080');
	$rawXML = curl_exec($ch);
	curl_close($ch);

	$pat = array(
				'@&\s@i',
				'@&rsquo;@i',
				'@&lsquo;@i',
				'@&rdquo;@i',
				'@&ldquo;@i',
				'@&ndash;@i'
				);
	$repPat = array(
				'&amp;',
				'%92',
				'%91',
				'%94',
				'%93',
				'%97'
				);
	$this->rawContents = preg_replace($pat,$repPat,$rawXML);
	$this->simpleXMLDoc = new SimpleXmlElement($this->rawContents, LIBXML_NOCDATA);
//	$this->domDoc  = new DOMDocument();
//	$this->domDoc->loadXML($this->simpleXMLDoc->asXML());
	$this->title = $this->simpleXMLDoc->channel->title;
	$this->title = (@$this->title ? $this->title : null);
	$this->description = $this->simpleXMLDoc->channel->description;
	$this->description = (@$this->description ? $this->description : null);
	$children = $this->simpleXMLDoc->channel->children();
	$this->items = array();
	$cnt = count($children);
	for($i = 0; $i < $cnt; $i++) {
		if ($children[$i]->getName() == 'item') {
			array_push($this->items,$children[$i]);
		}
	}
	$this->fetchIndex = 0;
	$this->itemCnt = count($this->items);
	error_log("in readFeed with title = ".$this->title." and item count of ".$this->itemCnt);
  }

  /**
   * Returns next records object or false if no biblios available
   *
   * @return array [class.records.php]
   */
  function fetch() {
      if( $this->fetchIndex < $this->itemCnt) { //if the index is not equal to the last
        return new PseudoRssBiblio($this->items[$this->fetchIndex++], $this->fetchIndex);
      }else {
        return false;
      }
  }

  /**
   * Returns the size of the result
   *
   * @return integer
   */
  function size() {
  //count the number of items in the rss feed data
  	return $this->itemCnt;
  }

  /**
   * Resets the searchresult to the first element
   *
   * @return void
   */
  function reset() {
  //set indexPointer to zero
  $this->fetchIndex = 0;
  }
}
?>
