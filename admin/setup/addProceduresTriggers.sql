-- Created by Steve White 2010-10-23
-- This file contains the stored procedures and triggers for h3 database
-- Stored Procedures
-- ------------------------------------------------------------------------------

-- Updated and applied to hdb_sandpit3 @ 21/1/11

-- RUN FROM COMMAND LINE WITH:
--   mysql -u root -psmith18 databasename < /var/www/htdocs/
--           h2-h3-upgrade-scripts-doco/convert_h3_add_procedures_triggers.sql


-- MAY NOT REPORT ERRORS, POSSIBLE NEED TO SET STDOUT FIRST AND/OR USE TEE TO WRITE TOU OUTPUT FILE
-- AND INSPECT

DROP function IF EXISTS `hhash`;

DELIMITER $$

CREATE DEFINER=`root`@`localhost` FUNCTION `hhash`(recID int) RETURNS varchar(4095) CHARSET utf8
	READS SQL DATA
	DETERMINISTIC

	begin
		declare rectype int;
		declare non_resource_fields varchar(4095);
		declare resource_fields varchar(4095);

		select rec_RecTypeID into rectype from Records where rec_ID = recID;

		select group_concat(liposuction(upper(dtl_Value)) order by dty_ID, upper(dtl_Value) separator ';')
			into non_resource_fields
			from Details, Records, defDetailTypes, defRecStructure
			where dtl_RecID=rec_ID and
					dtl_DetailTypeID=dty_ID and
					rec_RecTypeID=rst_RecTypeID and
					rst_DetailTypeID=dty_ID and
					rst_RecordMatchOrder and
					dty_Type != "resource" and
					rec_ID = recID;

		select group_concat(DST.rec_Hhash order by dty_ID, dty_ID, DST.rec_Hhash separator '$^')
			into resource_fields
			from Details, Records SRC, defDetailTypes, defRecStructure, Records DST
			where dtl_RecID=SRC.rec_ID and
					dtl_DetailTypeID=dty_ID and
					SRC.rec_RecTypeID=rst_RecTypeID and
					rst_DetailTypeID=dty_ID and
					rst_RequirementType = 'required' and
					dty_Type = "resource" and
					dtl_Value = DST.rec_ID and
					dtl_RecID=recID;

		return concat(ifnull(rectype,'n'), ':',

		if(non_resource_fields is not null and non_resource_fields != '', concat(non_resource_fields, ';'), ''),

		if(resource_fields is not null and resource_fields != '', concat('^', resource_fields, '$'), ''));
	end
	$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DROP function IF EXISTS `simple_hash`;

DELIMITER $$

	CREATE DEFINER=`root`@`localhost` FUNCTION `simple_hash`(recID int) RETURNS varchar(4095) CHARSET utf8
	READS SQL DATA
	DETERMINISTIC
	begin
		declare rectype int;
		declare non_resource_fields varchar(4095);
		declare author_fields varchar(4095);
		select rec_RecTypeID into rectype from Records where rec_ID = recID;
		select group_concat(liposuction(upper(dtl_Value)) order by dty_ID, upper(dtl_Value) separator ';')
			into non_resource_fields
			from Details, Records, defDetailTypes, defRecStructure
			where dtl_RecID=rec_ID and
					dtl_DetailTypeID=dty_ID and
					rec_RecTypeID=rst_RecTypeID and
					rst_DetailTypeID=dty_ID and
					rst_RecordMatchOrder and
					dty_Type != "resource" and
					rec_ID = recID;
		return concat(ifnull(rectype,'n'), ':',
		if(non_resource_fields is not null and non_resource_fields != '', concat(non_resource_fields, ';'), ''));
	end
	$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DROP procedure IF EXISTS `set_all_hhash`;

DELIMITER $$

CREATE DEFINER=`root`@`localhost` PROCEDURE `set_all_hhash`()

	begin
		create temporary table t (rec_ID int);
		repeat
			begin
			truncate t;
			insert into t select rec_ID
							from Records A
							where A.rec_Hhash is null
								and not exists (select *
												from Details, defDetailTypes, defRecStructure, Records B
												where dtl_RecID=A.rec_ID and
														dtl_DetailTypeID=dty_ID and
														dty_Type="resource" and
														B.rec_ID=dtl_Value and
														B.rec_Hhash is null and
														rst_RecTypeID=A.rec_RecTypeID and
														rst_DetailTypeID=dty_ID and
														rst_RequirementType="Required");
			set @tcount := row_count();
			update Records
				set rec_Hhash = hhash(rec_ID)
				where rec_ID in (select * from t);
			end;
			until @tcount = 0
		end repeat;
		drop table t;
	end
	$$

DELIMITER ;


--  Triggers
-- ------------------------------------------------------------------------------


DELIMITER $$

	DROP TRIGGER IF EXISTS tag_insert_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `tag_insert_trigger`
	AFTER INSERT ON `usrTags`
	FOR EACH ROW
		begin
			if @suppress_update_trigger is null
				then insert into archiveTagLinkCreations (atc_ID, atc_UGrpID, atc_Name, atc_Created, atc_CreatorID)
				values (NEW.tag_ID, NEW.tag_UGrpID, NEW.tag_Text, now(), @logged_in_user_id);
			end if;
		end$$

DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS kwd_delete_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `kwd_delete_trigger`
	BEFORE DELETE ON `usrTags`
	FOR EACH ROW
		begin
			if @suppress_update_trigger is null
				then insert into archiveTagLinkDeletions (atd_ID, atd_UGrpID, atd_Name, atd_Deleted, atd_DeletorID)
				values (OLD.tag_ID, OLD.tag_UGrpID, OLD.tag_Text,  now(), @logged_in_user_id);
			end if;
		end$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS rtl_insert_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `rtl_insert_trigger`
	AFTER INSERT ON `usrRecTagLinks`
	FOR EACH ROW
		begin
			if @suppress_update_trigger is null
				then insert into
					archiveTagLinkCreations (atlc_ID,  atlc_TagID, atlc_RecID, atlc_Created, atlc_CreatorID)
						values (NEW.rtl_ID,
								NEW.rtl_TagID,
								NEW.rtl_RecID,
								now(),
								@logged_in_user_id);
				end if;
		end$$

DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS rtl_delete_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `rtl_delete_trigger`
	BEFORE DELETE ON `usrRecTagLinks`
	FOR EACH ROW
	begin
		if @suppress_update_trigger is null
			then insert into
				archiveTagLinkDeletions (atld_ID, atld_TagID, atld_RecID, atld_Deleted, atld_DeletorID)
					values (OLD.rtl_ID,
							OLD.rtl_TagID,
							OLD.rtl_RecID,
							now(),
							@logged_in_user_id);
		end if;
	end$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS insert_Details_precis_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `insert_Details_precis_trigger`
	BEFORE INSERT ON `recDetails`
	FOR EACH ROW
		begin set NEW.dtl_ValShortened = ifnull(liposuction(NEW.dtl_Value), ''); end$$

DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS insert_Details_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `insert_Details_trigger`
	AFTER INSERT ON `recDetails`
	FOR EACH ROW
	begin
		insert into archiveDetails (ard_RecID, ard_ID, ard_Ver, ard_DetailTypeID, ard_Value, ard_UploadedFileID, ard_Geo)
			values (NEW.dtl_RecID, NEW.dtl_ID, @rec_version, NEW.dtl_DetailTypeID, NEW.dtl_Value, NEW.dtl_UploadedFileID, NEW.dtl_Geo);
		if NEW.dtl_DetailTypeID=199 then
			update recRelationshipsCache
				set rrc_TargetRecID = NEW.dtl_Value
				where rrc_RecID=NEW.dtl_RecID;
		elseif NEW.dtl_DetailTypeID=202 then
			update recRelationshipsCache
				set rrc_SourceRecID = NEW.dtl_Value
				where rrc_RecID=NEW.dtl_RecID;
		end if;
	end$$

DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS pre_update_Details_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `pre_update_Details_trigger`
	BEFORE UPDATE ON `recDetails`
	FOR EACH ROW
	begin
		if asbinary(NEW.dtl_Geo)=asbinary(OLD.dtl_Geo) then
			set NEW.dtl_Geo = OLD.dtl_Geo;
		end if;
		set NEW.dtl_ValShortened = ifnull(liposuction(NEW.dtl_Value), '');
	end$$

DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS update_Details_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `update_Details_trigger`
	AFTER UPDATE ON `recDetails`
	FOR EACH ROW
	begin
		if @suppress_update_trigger is null then
			insert into archiveDetails (ard_RecID, ard_ID, ard_Ver, ard_DetailTypeID, ard_Value, ard_UploadedFileID, ard_Geo)
				values (NEW.dtl_RecID,
						NEW.dtl_ID,
						@rec_version,
						NEW.dtl_DetailTypeID,
						if (NEW.dtl_Value=OLD.dtl_Value, NULL, NEW.dtl_Value),
						if (NEW.dtl_UploadedFileID=OLD.dtl_UploadedFileID, NULL, NEW.dtl_UploadedFileID),
						if (NEW.dtl_Geo=OLD.dtl_Geo, NULL, NEW.dtl_Geo));
		end if;
		if NEW.dtl_DetailTypeID=199 then
			update recRelationshipsCache
				set rrc_TargetRecID = NEW.dtl_Value
				where rrc_RecID=NEW.dtl_RecID;
		elseif NEW.dtl_DetailTypeID=202 then
		update recRelationshipsCache
				set rrc_SourceRecID = NEW.dtl_Value
				where rrc_RecID=NEW.dtl_RecID;
		end if;
	end$$

DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS delete_Details_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `delete_Details_trigger`
	AFTER DELETE ON `recDetails`
	FOR EACH ROW
	begin
	if @suppress_update_trigger is null then
		insert into archiveDetails (ard_RecID, ard_ID, ard_Ver, ard_DetailTypeID)
			values (OLD.dtl_RecID, OLD.dtl_ID, @rec_version, OLD.dtl_DetailTypeID);
	end if;
	end$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS insert_record_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `insert_record_trigger`
	AFTER INSERT ON `Records`
	FOR EACH ROW
	begin
		insert into archiveRecords (arec_ID, arec_UGrpID, arec_Date, arec_URL, arec_Title, arec_ScratchPad, arec_RecTypeID)
			values (NEW.rec_ID, NEW.rec_AddedByUGrpID, now(), NEW.rec_URL, NEW.rec_Title, NEW.rec_ScratchPad, NEW.rec_RecTypeID);
			set @rec_version := last_insert_id();
			insert into usrRecentRecords (rre_UGrpID, rre_RecID, rre_Time)
				values (@logged_in_user_id, NEW.rec_ID, now()); set @rec_id := last_insert_id(NEW.rec_ID);
		if NEW.rec_RecTypeID = 52 then
			insert into recRelationshipsCache (rrc_RecID) values (NEW.rec_ID);
		end if;
	end$$
    
DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS update_record_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `update_record_trigger`
	BEFORE UPDATE ON `Records`
	FOR EACH ROW
	begin
		if @suppress_update_trigger is null then
			insert into archiveRecords (arec_ID, arec_UGrpID, arec_Date, arec_URL, arec_Title, arec_ScratchPad, arec_RecTypeID)
				values (NEW.rec_ID, @logged_in_user_id, now(),
						if (NEW.rec_URL=OLD.rec_URL, NULL, NEW.rec_URL),
						if (NEW.rec_Title=OLD.rec_Title, NULL, NEW.rec_Title),
						if (NEW.rec_ScratchPad=OLD.rec_ScratchPad, NULL, NEW.rec_ScratchPad),
						if (NEW.rec_RecTypeID=OLD.rec_RecTypeID, NULL, NEW.rec_RecTypeID));
			set @rec_version := last_insert_id();
		end if;
		if NEW.rec_URL != OLD.rec_URL OR NEW.rec_URL is null then
			set NEW.rec_URLLastVerified := NULL;
		end if;
	end$$
    
DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS usrRecentRecords_updater$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `usrRecentRecords_updater`
	AFTER UPDATE ON `Records`
	FOR EACH ROW
	begin
		if @suppress_update_trigger is null then
			insert into usrRecentRecords (rre_UGrpID, rre_RecID, rre_Time)
				values (@logged_in_user_id, NEW.rec_ID, now());
		end if;
		if NEW.rec_RecTypeID = 52 then
			insert ignore into recRelationshipsCache (rrc_RecID) values (NEW.rec_ID);
		end if;
	end$$
    
DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS delete_record_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `delete_record_trigger`
	AFTER DELETE ON `Records`
	FOR EACH ROW
	begin
		insert into archiveRecords (arec_ID, arec_UGrpID, arec_Date)
			values (OLD.rec_ID, @logged_in_user_id, now());
		set @rec_version := last_insert_id();
		if OLD.rec_RecTypeID = 52 then
			delete ignore from recRelationshipsCache where rrc_RecID = OLD.rec_ID;
		end if;
	end$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS usrBookmarks_update$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `usrBookmarks_update`
	BEFORE UPDATE ON `usrBookmarks`
	FOR EACH ROW
		set NEW.bkm_Modified = now()$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS sysUGrps_last_insert$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `sysUGrps_last_insert`
	AFTER INSERT ON `sysUGrps`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUGrps"$$
   
DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS sysUGrps_last_update$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `sysUGrps_last_update`
	AFTER UPDATE ON `sysUGrps`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUGrps"$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_insert$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `sysUsrGrpLinks_last_insert`
	AFTER INSERT ON `sysUsrGrpLinks`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks"$$

DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_update$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `sysUsrGrpLinks_last_update`
	AFTER UPDATE ON `sysUsrGrpLinks`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks"$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS defDetailTypes_last_insert$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `defDetailTypes_last_insert`
	AFTER INSERT ON `defDetailTypes`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypes"$$

DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS defDetailTypes_last_update$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `defDetailTypes_last_update`
	AFTER UPDATE ON `defDetailTypes`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypes"$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS defRecTypes_last_insert$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `defRecTypes_last_insert`
	AFTER INSERT ON `defRecTypes`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypes"$$

DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS defRecTypes_last_update$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `defRecTypes_last_update`
	AFTER UPDATE ON `defRecTypes`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypes"$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS defRecStructure_last_insert$$

	CREATE DEFINER=`root`@`localhost`
	TRIGGER `defRecStructure_last_insert`
	AFTER INSERT ON `defRecStructure`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure"$$
    
DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS defRecStructure_last_update$$

	CREATE DEFINER=`root`@`localhost`
	TRIGGER `defRecStructure_last_update`
	AFTER UPDATE ON `defRecStructure`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure"$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS defTerms_last_insert$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `defTerms_last_insert`
	AFTER INSERT ON `defTerms`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defTerms"$$
        
DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS defTerms_last_update$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `defTerms_last_update`
	AFTER UPDATE ON `defTerms`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defTerms"$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS defVocabularies_last_insert$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `defVocabularies_last_insert`
	AFTER INSERT ON `defVocabularies`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defVocabularies"$$
        
DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS defVocabularies_last_update$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `defVocabularies_last_update`
	AFTER UPDATE ON `defVocabularies`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defVocabularies"$$

DELIMITER ;

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS defRelationshipConstraints_last_insert$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `defRelationshipConstraints_last_insert`
	AFTER INSERT ON `defRelationshipConstraints`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints"$$
        
DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS defRelationshipConstraints_last_update$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `defRelationshipConstraints_last_update`
	AFTER UPDATE ON `defRelationshipConstraints`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints"$$

DELIMITER ;

