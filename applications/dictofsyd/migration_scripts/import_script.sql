use `hdb_dos_3`;

DROP TRIGGER IF EXISTS `insert_record_trigger`;
DROP TRIGGER IF EXISTS `update_record_trigger`;
DROP TRIGGER IF EXISTS `usrRecentRecords_updater`;
DROP TRIGGER IF EXISTS `delete_record_trigger`;
DROP TRIGGER IF EXISTS `insert_Details_precis_trigger`;
DROP TRIGGER IF EXISTS `pre_update_Details_trigger`;
DROP TRIGGER IF EXISTS `update_Details_trigger`;

SET FOREIGN_KEY_CHECKS=0; 
ALTER TABLE Records DISABLE KEYS;
ALTER TABLE recDetails DISABLE KEYS;
ALTER TABLE recUploadedFiles DISABLE KEYS;

insert into Records (`rec_ID`, `rec_URL`, `rec_Added`, `rec_Modified`, `rec_Title`, `rec_ScratchPad`, `rec_RecTypeID`, `rec_AddedByUGrpID`, `rec_OwnerUGrpID`)
SELECT `rec_id`, `rec_url`, `rec_added`, `rec_modified`, 
`rec_title`, `rec_scratchpad`, `rec_type`, 2, 1 
FROM `heuristdb-dos`.`records`
WHERE rec_type in (1,52,74,91,98,99,103,150,151,152,153,165,168);

update Records set rec_RecTypeID=2 where rec_RecTypeID=1;
update Records set rec_RecTypeID=1 where rec_RecTypeID=52;
update Records set rec_RecTypeID=5 where rec_RecTypeID=74;
update Records set rec_RecTypeID=27 where rec_RecTypeID=91;
update Records set rec_RecTypeID=13 where rec_RecTypeID=98;
update Records set rec_RecTypeID=15 where rec_RecTypeID=99;
update Records set rec_RecTypeID=28 where rec_RecTypeID=103;
update Records set rec_RecTypeID=26 where rec_RecTypeID=150;
update Records set rec_RecTypeID=25 where rec_RecTypeID=151;
update Records set rec_RecTypeID=29 where rec_RecTypeID=152;
update Records set rec_RecTypeID=24 where rec_RecTypeID=153;
update Records set rec_RecTypeID=23 where rec_RecTypeID=165;
update Records set rec_RecTypeID=11 where rec_RecTypeID=168;

update Records r1,`heuristdb-dos`.`rec_details` rd 
set r1.rec_URL=rd.rd_val where r1.rec_id=rd.rd_rec_id and rd.rd_type=198;

insert into recUploadedFiles (`ulf_ID`, `ulf_OrigFileName`, `ulf_UploaderUGrpID`, `ulf_Added`, `ulf_Modified`, `ulf_ObfuscatedFileID`, `ulf_PreferredSource`, `ulf_Thumbnail`, `ulf_Description`, `ulf_MimeExt`, `ulf_FileSizeKB`, `ulf_FilePath`, `ulf_FileName`)
SELECT `file_id`, `file_orig_name`, 2, current_timestamp, current_timestamp, `file_nonce`, 'local',
`file_thumbnail`, `file_description`, LOWER(SUBSTRING_INDEX(`file_orig_name`,'.',-1)) AS ext,
round(SUBSTRING_INDEX(`file_size`,' ',1)) as fsize, '/var/www/htdocs/uploaded-heurist-files/dos/' as path, `file_id` as fname
FROM `heuristdb-dos`.`files` where 1;

insert into recDetails (`dtl_ID`, `dtl_RecID`, `dtl_DetailTypeID`, `dtl_Value`, `dtl_UploadedFileID`, `dtl_Geo`, `dtl_ValShortened`)
SELECT `rd_id`, `rd_rec_id`, `rd_type`, `rd_val`, `rd_file_id`, `rd_geo`, `rd_val_precis` FROM `heuristdb-dos`.`rec_details` 
WHERE rd_type not in (158,159,175,190,198,201,214,215,258,374,491);

update recDetails set dtl_DetailTypeID=1 where dtl_DetailTypeID=160;
update recDetails set dtl_DetailTypeID=9 where dtl_DetailTypeID=166;
update recDetails set dtl_DetailTypeID=76 where dtl_DetailTypeID=171;
update recDetails set dtl_DetailTypeID=2 where dtl_DetailTypeID=173;
update recDetails set dtl_DetailTypeID=2 where dtl_DetailTypeID=174;
update recDetails set dtl_DetailTypeID=77 where dtl_DetailTypeID=176;
update recDetails set dtl_DetailTypeID=10 where dtl_DetailTypeID=177;
update recDetails set dtl_DetailTypeID=11 where dtl_DetailTypeID=178;
update recDetails set dtl_DetailTypeID=97 where dtl_DetailTypeID=179;
update recDetails set dtl_DetailTypeID=4 where dtl_DetailTypeID=191;
update recDetails set dtl_DetailTypeID=78 where dtl_DetailTypeID=193;
update recDetails set dtl_DetailTypeID=79 where dtl_DetailTypeID=197;
update recDetails set dtl_DetailTypeID=5 where dtl_DetailTypeID=199;
update recDetails set dtl_DetailTypeID=6 where dtl_DetailTypeID=200;
update recDetails set dtl_DetailTypeID=7 where dtl_DetailTypeID=202;
update recDetails set dtl_DetailTypeID=80 where dtl_DetailTypeID=218;
update recDetails set dtl_DetailTypeID=38 where dtl_DetailTypeID=221;
update recDetails set dtl_DetailTypeID=39 where dtl_DetailTypeID=223;
update recDetails set dtl_DetailTypeID=28 where dtl_DetailTypeID=230;
update recDetails set dtl_DetailTypeID=81 where dtl_DetailTypeID=231;
update recDetails set dtl_DetailTypeID=17 where dtl_DetailTypeID=256;

update recDetails set dtl_DetailTypeID=29 where dtl_DetailTypeID=289;
update recDetails set dtl_DetailTypeID=35 where dtl_DetailTypeID=290;
update recDetails set dtl_DetailTypeID=3 where dtl_DetailTypeID=303;
update recDetails set dtl_DetailTypeID=82 where dtl_DetailTypeID=304;
update recDetails set dtl_DetailTypeID=42 where dtl_DetailTypeID=322;
update recDetails set dtl_DetailTypeID=44 where dtl_DetailTypeID=329;
update recDetails set dtl_DetailTypeID=45 where dtl_DetailTypeID=330;
update recDetails set dtl_DetailTypeID=34 where dtl_DetailTypeID=339;
update recDetails set dtl_DetailTypeID=96 where dtl_DetailTypeID=359;
update recDetails set dtl_DetailTypeID=83 where dtl_DetailTypeID=365;
update recDetails set dtl_DetailTypeID=84 where dtl_DetailTypeID=368;
update recDetails set dtl_DetailTypeID=20 where dtl_DetailTypeID=399;
update recDetails set dtl_DetailTypeID=61 where dtl_DetailTypeID=508;
update recDetails set dtl_DetailTypeID=75 where dtl_DetailTypeID=523;
update recDetails set dtl_DetailTypeID=85 where dtl_DetailTypeID=526;
update recDetails set dtl_DetailTypeID=86 where dtl_DetailTypeID=527;
update recDetails set dtl_DetailTypeID=87 where dtl_DetailTypeID=528;
update recDetails set dtl_DetailTypeID=88 where dtl_DetailTypeID=529;
update recDetails set dtl_DetailTypeID=89 where dtl_DetailTypeID=531;
update recDetails set dtl_DetailTypeID=90 where dtl_DetailTypeID=538;
update recDetails set dtl_DetailTypeID=46 where dtl_DetailTypeID=539;
update recDetails set dtl_DetailTypeID=47 where dtl_DetailTypeID=540;

update recDetails set dtl_DetailTypeID=91 where dtl_DetailTypeID=564;
update recDetails set dtl_DetailTypeID=74 where dtl_DetailTypeID=568;
update recDetails set dtl_DetailTypeID=92 where dtl_DetailTypeID=569;
update recDetails set dtl_DetailTypeID=31 where dtl_DetailTypeID=585;
update recDetails set dtl_DetailTypeID=32 where dtl_DetailTypeID=586;
update recDetails set dtl_DetailTypeID=33 where dtl_DetailTypeID=587;
update recDetails set dtl_DetailTypeID=93 where dtl_DetailTypeID=588;
update recDetails set dtl_DetailTypeID=94 where dtl_DetailTypeID=590;
update recDetails set dtl_DetailTypeID=95 where dtl_DetailTypeID=591;
update recDetails set dtl_DetailTypeID=30 where dtl_DetailTypeID=618;

update recDetails set dtl_DetailTypeID=13 where dtl_DetailTypeID=5 and dtl_RecID in
(select distinct rec_ID from Records where rec_RecTypeID=15);


insert into recRelationshipsCache (`rrc_RecID`, `rrc_SourceRecID`, `rrc_TargetRecID`) 
select d1.dtl_RecID, d1.dtl_Value, d2.dtl_value 
FROM recDetails d1 left join recDetails d2 on d1.dtl_RecID=d2.dtl_RecID and d2.dtl_DetailTypeID=5
WHERE d1.dtl_DetailTypeID=7;

update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=20 
and trm_ParentTermID=513;
update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=29 
and trm_ParentTermID=533;
update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=30 
and trm_ParentTermID=543;
update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=31 
and trm_ParentTermID=546;
update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=74
and trm_ParentTermID=3285;
update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=75
and trm_ParentTermID=3290;
update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=85
and trm_ParentTermID=3306;
update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=89
and trm_ParentTermID=3315;
update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=94
and trm_ParentTermID=3318;
update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=95
and trm_ParentTermID=3321;
update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=96
and trm_ParentTermID=3328;
update recDetails, defTerms set dtl_value=trm_ID where trm_Label=dtl_Value and dtl_DetailTypeID=6
and trm_ParentTermID=3338;

ALTER TABLE recUploadedFiles ENABLE KEYS;
ALTER TABLE recDetails ENABLE KEYS;
ALTER TABLE Records ENABLE KEYS;
SET FOREIGN_KEY_CHECKS=1;