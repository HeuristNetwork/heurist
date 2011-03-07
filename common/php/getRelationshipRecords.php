<?php

	/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

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

	function reltype_inverse ($relTermID) {	//saw Enum change - find inverse as an id instead of a string
	global $inverses;
		if (!$relTermID) return;
	if (! $inverses) {
			//		$inverses = mysql__select_assoc("defTerms A left join defTerms B on B.trm_ID=A.trm_InverseTermID", "A.trm_Label", "B.trm_Label", "A.rdl_rdt_id=200 and A.trm_Label is not null");
			$inverses = mysql__select_assoc("defTerms A left join defTerms B on B.trm_ID=A.trm_InverseTermID", "A.trm_ID", "B.trm_ID", "A.trm_Label is not null and B.trm_Label is not null");
	}

		$inverse = @$inverses[$relTermID];
	if (!$inverse)
		$inverse = array_search($relTermID, $inverses);
	if (!$inverse)
		$inverse = 'Inverse of '.$relTermID;

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


	function fetch_relation_details($recID, $i_am_primary) {
	/* Raid recDetails for the given link resource and extract all the necessary values */

		$res = mysql_query('select * from recDetails where dtl_RecID = ' . $recID);
	$bd = array(
		'recID' => $recID
	);
	while ($row = mysql_fetch_assoc($res)) {
		switch ($row['dtl_DetailTypeID']) {
		    case 200:	//saw Enum change - added RelationValue for UI
			if ($i_am_primary) {
						$bd['relTermID'] = $row['dtl_Value'];
			}else{
						$bd['relTermID'] = reltype_inverse($row['dtl_Value']);
					}
					$relval = mysql_fetch_assoc(mysql_query('select trm_Label, trm_ParentTermID from defTerms where trm_ID = ' .  intval($bd['RelTermID'])));
					$bd['relTerm'] = $relval['trm_Label'];
					if ($relval['trm_ParentTermID'] ) {
						$bd['parentTermID'] = $relval['trm_ParentTermID'];
			}
			break;

		    case 199:	// linked resource
			if (! $i_am_primary) break;
					$r = mysql_query('select rec_ID as recID, rec_Title as title, rec_RecTypeID as rectype, rec_URL as URL
										from Records where rec_ID = ' . intval($row['dtl_Value']));
					$bd['relatedRecID'] = mysql_fetch_assoc($r);
			break;

		    case 202:
			if ($i_am_primary) break;
					$r = mysql_query('select rec_ID as recID, rec_Title as title, rec_RecTypeID as rectype, rec_URL as URL
										from Records where rec_ID = ' . intval($row['dtl_Value']));
					$bd['relatedRecID'] = mysql_fetch_assoc($r);
			break;

		    case 638:
					$r = mysql_query('select rec_ID as recID, rec_Title as title, rec_RecTypeID as rectype, rec_URL as URL
										from Records where rec_ID = ' . intval($row['dtl_Value']));
					$bd['interpRecID'] = mysql_fetch_assoc($r);
			break;

		    case 201:
					$bd['notes'] = $row['dtl_Value'];
			break;

		    case 160:
					$bd['title'] = $row['dtl_Value'];
			break;

		    case 177:
					$bd['startDate'] = $row['dtl_Value'];
			break;

		    case 178:
					$bd['endDate'] = $row['dtl_Value'];
			break;
		}
	}

	return $bd;
	}


	function getAllRelatedRecords($recID, $relnRecID=0) {
		if (! $recID) return null;
	$query = "select LINK.dtl_DetailTypeID as type, DETAILS.*, DBIB.rec_Title as title, DBIB.rec_RecTypeID as rt, DBIB.rec_URL as url
		from recDetails LINK left join Records LBIB on LBIB.rec_ID=LINK.dtl_RecID, recDetails DETAILS left join Records DBIB on DBIB.rec_ID=DETAILS.dtl_Value and DETAILS.dtl_DetailTypeID in (202, 199, 158)
		where ((LINK.dtl_DetailTypeID in (202, 199) and LBIB.rec_RecTypeID=52) or LINK.dtl_DetailTypeID=158) and LINK.dtl_Value = $recID and DETAILS.dtl_RecID = LINK.dtl_RecID";
		if ($relnRecID) $query .= " and DETAILS.dtl_RecID = $relnRecID";

	$query .= " order by LINK.dtl_DetailTypeID desc, DETAILS.dtl_ID";

		//error_log($query);
	$res = mysql_query($query);	/* primary resources first, then non-primary, then authors */

	$relations = array();
		while ($row = mysql_fetch_assoc($res)) {
			$recID = $row["dtl_RecID"];
			$i_am_primary = ($row["type"] == 202);
			if (! array_key_exists($recID, $relations))
			$relations[$recID] = array();

			if (! array_key_exists("role", $relations[$recID])) {
				if ($row["type"] == 202) {
					$relations[$recID]["role"] = "Primary";
				} else if ($row["type"] == 199) {
					$relations[$recID]["role"] = "Non-primary";
			} else {
					$relations[$recID]["role"] = "Unknown";
			}
		}
			if (! array_key_exists("recID", $relations[$recID])) {
				$relations[$recID]["recID"] = $recID;
		}

			switch ($row["dtl_DetailTypeID"]) {
		case 200:	//saw Enum change - nothing to do since dtl_Value is an id and inverse returns an id
					$relations[$recID]["relTermID"] = $i_am_primary? $row["dtl_Value"] : reltype_inverse($row["dtl_Value"]);
					if($relations[$recID]["relTermID"]) {
						$relval = mysql_fetch_assoc(mysql_query('select trm_Label from defTerms where trm_ID = ' .  intval($relations[$recID]["RelTermID"])));
						$relations[$recID]['relTerm'] = $relval['trm_Label'];
					}
			break;

		case 199:
			if ($i_am_primary)
					$relations[$recID]["relatedRec"] = array("title" => $row["title"], "rectype" => $row["rt"],
																"URL" => $row["url"], "recID" => $row["dtl_Value"]
				);
			break;

		case 202:
			if (! $i_am_primary)
					$relations[$recID]["relatedRec"] = array("title" => $row["title"], "rectype" => $row["rt"],
																"URL" => $row["url"], "recID" => $row["dtl_Value"]
				);
			break;

		case 638:
					$relations[$recID]["interpRec"] = array("title" => $row["title"], "rectype" => $row["rt"],
															 "URL" => $row["url"], "recID" => $row["dtl_Value"]);
			break;

		case 201:
				$relations[$recID]["notes"] = $row["dtl_Value"];
			break;

		case 160:
				$relations[$recID]["title"] = $row["dtl_Value"];
			break;

		case 177:
				$relations[$recID]["startDate"] = $row["dtl_Value"];
			break;

		case 178:
				$relations[$recID]["endDate"] = $row["dtl_Value"];
			break;
		}
	}

	return $relations;
	}

?>
