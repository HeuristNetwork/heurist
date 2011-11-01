INSERT INTO `sysUGrps` ( `ugr_ID` , `ugr_Type` , `ugr_Name` , `ugr_LongName` , `ugr_Description` , `ugr_Password` ,
`ugr_eMail` , `ugr_FirstName` , `ugr_LastName` , `ugr_Department`, `ugr_Organisation` , `ugr_City` , `ugr_State` ,
`ugr_Postcode` , `ugr_Interests` , `ugr_Enabled` , `ugr_LastLoginTime` , `ugr_MinHyperlinkWords` , `ugr_LoginCount` ,
`ugr_IsModelUser` ,`ugr_IncomingEmailAddresses` , `ugr_TargetEmailAddresses` , `ugr_URLs` , `ugr_FlagJT` , `ugr_Modified` )
VALUES (1000 , 'user', 'Johnson', 'Ian Johnson', NULL , 'V74MGtjGpIkS2', 'ian.johnson@sydney.edu.au',
'Ian', 'Johnson', NULL , NULL , NULL , NULL , NULL ,NULL , 'y', NULL , '3', '0', '0', NULL , NULL , NULL , '0', NOW( ));

INSERT INTO `sysUsrGrpLinks` (`ugl_ID`, `ugl_UserID`, `ugl_GroupID`, `ugl_Role`) VALUES (NULL, '1000', '1', 'admin');
