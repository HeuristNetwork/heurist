<?php
	/*<!-- relationships.php

	Copyright 2005 - 2010 University of Sydney Digital Innovation Unit
	This file is part of the Heurist academic knowledge management system (http://HeuristScholar.org)
	mailto:info@heuristscholar.org

	Concept and direction: Ian Johnson.
	Developers: Tom Murtagh, Kim Jackson, Steve White, Steven Hayes,
				Maria Shvedova, Artem Osmakov, Maxim Nikitin.
	Design and advice: Andrew Wilson, Ireneusz Golka, Martin King.

	Heurist is free software; you can redistribute it and/or modify it under the terms of the
	GNU General Public License as published by the Free Software Foundation; either version 3
	of the License, or (at your option) any later version.

	Heurist is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
	even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.
	If not, see <http://www.gnu.org/licenses/>
	or write to the Free Software Foundation,Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

	-->*/


/*
$inverses = array();
$inverses['Causes'] = 'IsCausedBy';
$inverses['CollaboratesWith'] = 'CollaboratesWith';
$inverses['CooperatesWith'] = 'CooperatesWith';
$inverses['Funds'] = 'IsFundedBy';
$inverses['HasAssociateDirector'] = 'IsAssociateDirectorOf';
$inverses['HasAssociatePartner'] = 'IsAssociatePartnerIn';
$inverses['HasAuthor'] = 'IsAuthorOf';
$inverses['HasCoAuthor'] = 'IsCoAuthorOf';
$inverses['HasCoConvenor'] = 'IsCoConvenerOf';
$inverses['HasConvenor'] = 'IsConvenerOf';
$inverses['HasDirector'] = 'IsDirectorOf';
$inverses['HasHost'] = 'IsHostOf';
$inverses['HasManager'] = 'IsManagerOf';
$inverses['HasMember'] = 'IsMemberOf';
$inverses['HasNodeDirector'] = 'IsNodeDirectorOf';
$inverses['HasPartner'] = 'IsPartnerIn';
$inverses['HasPhotograph'] = 'IsPhotographOf';
$inverses['HasSpeaker'] = 'IsSpeakerAt';
$inverses['HasSubNode'] = 'IsSubNodeOf';
$inverses['IsOwnedBy'] = 'Owns';
$inverses['IsParentOf'] = 'IsPartOf';
$inverses['IsReferencedBy'] = 'References';
$inverses['IsRelatedTo'] = 'IsRelatedTo';
$inverses['IsSameAs'] = 'IsSameAs';
$inverses['IsSimilarTo'] = 'IsSimilarTo';
$inverses['IsUsedBy'] = 'Uses';
*/

$ranks = array();
$ranks['IsAssociateDirectorOf'] = 3;
$ranks['IsAssociatePartnerIn'] = 3;
$ranks['IsDirectorOf'] = 3;
$ranks['IsManagerOf'] = 3;
$ranks['IsMemberOf'] = 3;
$ranks['IsNodeDirectorOf'] = 3;

$ranks['IsFundedBy'] = 2;
$ranks['IsHostOf'] = 2;
$ranks['IsPartOf'] = 2;

$ranks['IsSpeakerAt'] = 1;
$ranks['IsSubNodeOf'] = 1;

/*
$ranks['Causes'] = 0;
$ranks['CollaboratesWith'] = 0;
$ranks['CooperatesWith'] = 0;
$ranks['Funds'] = 0;
$ranks['HasAssociateDirector'] = 0;
$ranks['HasAssociatePartner'] = 0;
$ranks['HasAuthor'] = 0;
$ranks['HasCoAuthor'] = 0;
$ranks['HasCoConvenor'] = 0;
$ranks['HasConvenor'] = 0;
$ranks['HasDirector'] = 0;
$ranks['HasHost'] = 0;
$ranks['HasManager'] = 0;
$ranks['HasMember'] = 0;
$ranks['HasNodeDirector'] = 0;
$ranks['HasPartner'] = 0;
$ranks['HasSpeaker'] = 0;
$ranks['HasSubNode'] = 0;
$ranks['IsAssociateDirectorOf'] = 0;
$ranks['IsAssociatePartnerIn'] = 0;
$ranks['IsAuthorOf'] = 0;
$ranks['IsCausedBy'] = 0;
$ranks['IsCoAuthorOf'] = 0;
$ranks['IsCoConvenerOf'] = 0;
$ranks['IsConvenerOf'] = 0;
$ranks['IsDirectorOf'] = 0;
$ranks['IsFundedBy'] = 0;
$ranks['IsHostOf'] = 0;
$ranks['IsManagerOf'] = 0;
$ranks['IsMemberOf'] = 0;
$ranks['IsNodeDirectorOf'] = 0;
$ranks['IsOwnedBy'] = 0;
$ranks['IsParentOf'] = 0;
$ranks['IsPartnerIn'] = 0;
$ranks['IsPartOf'] = 0;
$ranks['IsReferencedBy'] = 0;
$ranks['IsRelatedTo'] = 0;
$ranks['IsSameAs'] = 0;
$ranks['IsSimilarTo'] = 0;
$ranks['IsSpeakerAt'] = 0;
$ranks['IsSubNodeOf'] = 0;
$ranks['IsUsedBy'] = 0;
$ranks['Owns'] = 0;
$ranks['References'] = 0;
$ranks['Uses'] = 0;
*/

function reltype_inverse ($reltype) {	//saw Enum change - find inverse as an id instead of a string
	global $inverses;
	if (! $inverses) {
//		$inverses = mysql__select_assoc("rec_detail_lookups A left join rec_detail_lookups B on B.rdl_id=A.rdl_related_rdl_id", "A.rdl_value", "B.rdl_value", "A.rdl_rdt_id=200 and A.rdl_value is not null");
		$inverses = mysql__select_assoc("rec_detail_lookups A left join rec_detail_lookups B on B.rdl_id=A.rdl_related_rdl_id", "A.rdl_id", "B.rdl_id", "A.rdl_rdt_id=200 and A.rdl_value is not null and B.rdl_value is not null");
	}

	$inverse = @$inverses[$reltype];
	if (!$inverse)
		$inverse = array_search($reltype, $inverses);
	if (!$inverse)
		$inverse = 'Inverse of '.$reltype;

	return $inverse;
}

function reltype_rank ($reltype) {
	global $ranks;
	$rank = $ranks[$reltype];
	if ($rank)
		return $rank;
	else
		return 0;
}


function fetch_relation_details($rec_id, $i_am_primary) {
	/* Raid rec_details for the given link resource and extract all the necessary values */

	$res = mysql_query('select * from rec_details where rd_rec_id = ' . $rec_id);
	$bd = array(
		'ID' => $rec_id
	);
	while ($row = mysql_fetch_assoc($res)) {
		switch ($row['rd_type']) {
		    case 200:	//saw Enum change - added ReleationValue for UI
			if ($i_am_primary) {
				$bd['RelationType'] = $row['rd_val'];
			}else{
				$bd['RelationType'] = reltype_inverse($row['rd_val']);
			}
			$relval = mysql_fetch_assoc(mysql_query('select rdl_value,rdl_ont_id from rec_detail_lookups where rdl_id = ' .  intval($bd['RelationType'])));
			$bd['RelationValue'] = $relval['rdl_value'];
			$bd['OntologyID'] = $relval['rdl_ont_id'];
			break;

		    case 199:	// linked resource
			if (! $i_am_primary) break;
			$r = mysql_query('select rec_id, rec_title, rec_type, rec_url from records where rec_id = ' . intval($row['rd_val']));
			$bd['OtherResource'] = mysql_fetch_assoc($r);
			break;

		    case 202:
			if ($i_am_primary) break;
			$r = mysql_query('select rec_id, rec_title, rec_type, rec_url from records where rec_id = ' . intval($row['rd_val']));
			$bd['OtherResource'] = mysql_fetch_assoc($r);
			break;

		    case 638:
			$r = mysql_query('select rec_id, rec_title, rec_type, rec_url from records where rec_id = ' . intval($row['rd_val']));
			$bd['InterpResource'] = mysql_fetch_assoc($r);
			break;

		    case 201:
			$bd['Notes'] = $row['rd_val'];
			break;

		    case 160:
			$bd['Title'] = $row['rd_val'];
			break;

		    case 177:
			$bd['StartDate'] = $row['rd_val'];
			break;

		    case 178:
			$bd['EndDate'] = $row['rd_val'];
			break;
		}
	}

	return $bd;
}


function getAllRelatedRecords($rec_id, $relnBibID=0) {
	if (! $rec_id) return null;
	$query = "select LINK.rd_type as type, DETAILS.*, DBIB.rec_title as title, DBIB.rec_type as rt, DBIB.rec_url as url
from rec_details LINK left join records LBIB on LBIB.rec_id=LINK.rd_rec_id, rec_details DETAILS left join records DBIB on DBIB.rec_id=DETAILS.rd_val and DETAILS.rd_type in (202, 199, 158)
where ((LINK.rd_type in (202, 199) and LBIB.rec_type=52) or LINK.rd_type=158) and LINK.rd_val = $rec_id and DETAILS.rd_rec_id = LINK.rd_rec_id";
	if ($relnBibID) $query .= " and DETAILS.rd_rec_id = $relnBibID";

	$query .= " order by LINK.rd_type desc, DETAILS.rd_id";

error_log($query);
	$res = mysql_query($query);	/* primary resources first, then non-primary, then authors */

	$relations = array();
	while ($bd = mysql_fetch_assoc($res)) {
		$rec_id = $bd["rd_rec_id"];
		$i_am_primary = ($bd["type"] == 202);
		if (! array_key_exists($rec_id, $relations))
			$relations[$rec_id] = array();

		if (! array_key_exists("Type", $relations[$rec_id])) {
			if ($bd["type"] == 202) {
				$relations[$rec_id]["Type"] = "Primary";
			} else if ($bd["type"] == 199) {
				$relations[$rec_id]["Type"] = "Non-primary";
			} else {
				$relations[$rec_id]["Type"] = "Author";
			}
		}
		if (! array_key_exists("bibID", $relations[$rec_id])) {
			$relations[$rec_id]["bibID"] = $rec_id;
		}

		switch ($bd["rd_type"]) {
		case 200:	//saw Enum change - nothing to do since rd_val is an id and inverse returns an id
			$relations[$rec_id]["RelationType"] = $i_am_primary? $bd["rd_val"] : reltype_inverse($bd["rd_val"]);
			$relval = mysql_fetch_assoc(mysql_query('select rdl_value from rec_detail_lookups where rdl_id = ' .  intval($relations[$rec_id]["RelationType"])));
			$relations[$rec_id]['RelationValue'] = $relval['rdl_value'];
			break;

		case 199:
			if ($i_am_primary)
				$relations[$rec_id]["OtherResource"] = array(
					"Title" => $bd["title"], "Reftype" => $bd["rt"], "URL" => $bd["url"], "bibID" => $bd["rd_val"]
				);
			break;

		case 202:
			if (! $i_am_primary)
				$relations[$rec_id]["OtherResource"] = array(
					"Title" => $bd["title"], "Reftype" => $bd["rt"], "URL" => $bd["url"], "bibID" => $bd["rd_val"]
				);
			break;

		case 638:
			$relations[$rec_id]["InterpResource"] = array(
				"Title" => $bd["title"], "Reftype" => $bd["rt"], "URL" => $bd["url"], "bibID" => $bd["rd_val"]);
			break;

		case 201:
			$relations[$rec_id]["Notes"] = $bd["rd_val"];
			break;

		case 160:
			$relations[$rec_id]["Title"] = $bd["rd_val"];
			break;

		case 177:
			$relations[$rec_id]["StartDate"] = $bd["rd_val"];
			break;

		case 178:
			$relations[$rec_id]["EndDate"] = $bd["rd_val"];
			break;
		}
	}

	return $relations;
}

?>
