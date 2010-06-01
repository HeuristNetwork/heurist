<?php

require_once('class.geometry.php');

class Biblio {
	var $rec_id;
	var $rec_title;
	var $rec_url;
	var $rec_type;
	var $depth;
	var $start;
	var $end;
	var $geometry;
	var $description;

	var $minX, $minY, $maxX, $maxY;

	function Biblio ($rec_id) {
		$this->geometry  = array();

		$this->minX = null; $this->minY = null; $this->maxX = null; $this->maxY = null;

		if ($rec_id) {
			$res = mysql_query('select rec_id, rec_title, rec_url, rec_type from records where rec_id='.$rec_id);
			$row = mysql_fetch_assoc($res);
			if (!$row) return false;
			$this->rec_id = $row['rec_id'];
			$this->rec_title = htmlentities($row['rec_title']);
			$this->rec_url = htmlentities($row['rec_url']);
			$this->rec_type = $row['rec_type'];

			$details = mysql__select_assoc('rec_details', 'rd_type', 'rd_val', 'rd_rec_id='.$rec_id);

			if (array_key_exists('177', $details)) {
				$this->start = $details['177'];
			}
			if (array_key_exists('178', $details)) {
				$this->end   = $details['178'];
			}

			$thumb_url = null;
			// 223  Thumbnail
			// 222  Logo image
			// 224  Images
			$res = mysql_query("select files.*
								  from rec_details
							 left join files on file_id = rd_file_id
								 where rd_rec_id = $rec_id
								   and rd_type in(223,222,224,221,231)
								   and file_mimetype like 'image%'
							  order by rd_type = 223 desc, rd_type = 222 desc, rd_type = 224 desc, rd_type
								 limit 1");
			if (mysql_num_rows($res) !== 1) {
				$res = mysql_query("select files.*
				                      from rec_details a, rec_detail_types, records, rec_details b, files
				                     where a.rd_rec_id = $rec_id
				                       and a.rd_type = rdt_id
				                       and rdt_type = 'resource'
				                       and rec_id = a.rd_val
				                       and b.rd_rec_id = rec_id
				                       and file_id = b.rd_file_id
				                       and file_mimetype like 'image%'
				                     limit 1;");
			}
			if (mysql_num_rows($res) == 1) {
				$file = mysql_fetch_assoc($res);
				$thumb_url = "../../common/lib/resize_image.php?file_id=".$file['file_nonce'];
			}

			$text = @$details['191'] ? '191' : (@$details['303'] ? '303' : null);
			$this->description = "";
			if ($thumb_url) {
				$this->description .= "<img" . ($text ? " style='float: right;'" : "") . " src='" . $thumb_url . "'/>";
			}
			if ($text) {
				$this->description .= $details[$text];
			}

			$res = mysql_query('SELECT AsText(rd_geo) geo, rd_val, astext(envelope(rd_geo)) as rect
			                      FROM rec_details
			                     WHERE NOT IsNULL(rd_geo)
			                       AND rd_rec_id = ' . $rec_id);

			if (mysql_num_rows($res) < 1  &&  $this->rec_type != 52  &&  $details['177']) {
				// Special case behaviour!
				// If a record has time data but not spatial data,
				// and it points to record(s) with spatial data, use that.
				// Although this is written in a general fashion it was
				// created for Event records, which may point to Site records
				$res = mysql_query('select astext(g.rd_geo) geo, g.rd_val, astext(envelope(g.rd_geo)) as rect
				                      from rec_details p
				                 left join rec_detail_types on rdt_id = p.rd_type
				                 left join records on rec_id = p.rd_val
				                 left join rec_details g on g.rd_rec_id = rec_id
				                     where p.rd_rec_id = ' . $rec_id . '
				                       and rdt_type = "resource"
				                       and g.rd_geo is not null');
			}

			while ($row = mysql_fetch_assoc($res)) {
				$geometry = new Geometry($row['geo'], $row['rd_val'], $row['rect']);
				if ($this->minX === null  ||  $geometry->minX < $this->minX) $this->minX = $geometry->minX;
				if ($this->maxX === null  ||  $geometry->maxX > $this->maxX) $this->maxX = $geometry->maxX;
				if ($this->minY === null  ||  $geometry->minY < $this->minY) $this->minY = $geometry->minY;
				if ($this->maxY === null  ||  $geometry->maxY > $this->maxY) $this->maxY = $geometry->maxY;

				$this->geometry[] = $geometry;
			}
		}
	}

	function hasGeometry() {
		return count($this->geometry) > 0;
	}

	function getGeometry() {
		return $this->geometry;
	}

}

?>
