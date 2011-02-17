<?php

	/*<!--
 * filename, brief description, date of creation, by whom
 * @copyright (C) 2005-2010 University of Sydney Digital Innovation Unit.
 * @link: http://HeuristScholar.org
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Heurist academic knowledge management system
 * @todo
 -->*/

	function getRecordRequirements($rt_id) {
		// returns [ rst_DetailTypeID => [ rst_RecTypeID, rst_DetailTypeID, rst_DisplayName, rst_DisplayHelpText, rst_DisplayExtendedDescription,
		// rst_DefaultValue, rst_RequirementType, rst_MaxValues, rst_MinValues, rst_DisplayWidth, rst_RecordMatchOrder,
		// rst_DisplayOrder, rst_DisplayDetailTypeGroupID, rst_EnumFilteredIDs, rst_PtrFilteredIDs, rst_CalcFunctionID] ...]
	$rdrs = array();
		$colNames = array("rst_RecTypeID", "rst_DetailTypeID",
							"if(rst_DisplayName,rst_DisplayName,dty_Name) as rst_DisplayName",
							"if(rst_DisplayHelpText,rst_DisplayHelpText,dty_HelpText) as rst_DisplayHelpText",
							"if(rst_DisplayExtendedDescription,rst_DisplayExtendedDescription,dty_ExtendedDescription) as rst_DisplayExtendedDescription",
							"rst_DefaultValue",
							"rst_RequirementType", "rst_MaxValues", "rst_MinValues","rst_DisplayWidth", "rst_RecordMatchOrder",
							"rst_DisplayOrder",
							"if(rst_DisplayDetailTypeGroupID,rst_DisplayDetailTypeGroupID,dty_DetailTypeGroupID) as rst_DisplayDetailTypeGroupID",
							"rst_EnumFilteredIDs", "rst_PtrFilteredIDs", "rst_CalcFunctionID", "rst_PriorityForThumbnail");

		// get rec Structure info ordered by the detailType Group order, then by recStruct display order and then by ID in recStruct incase 2 have the same order
		$res = mysql_query("select ".join(",", $colNames)." from defRecStructure
																left join defDetailTypes on rst_DetailTypeID = dty_ID
																left join defDetailTypeGroups on dtg_ID = if(rst_DisplayDetailTypeGroupID,rst_DisplayDetailTypeGroupID,dty_DetailTypeGroupID)
															where rst_RecTypeID=".$rt_id."
															order by dtg_Order, dtg_Name, rst_DisplayOrder, rst_ID");
	while ($row = mysql_fetch_assoc($res)) {
		$rdrs[$row["rst_DetailTypeID"]] = $row;
	}
	return $rdrs;
	}

	/*no carriage returns after closing script tags please, it breaks xml script genenerator that uses this file as include */
?>
