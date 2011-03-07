-- Created by Steve White 2010-10-23
-- Last updated 14th Feb 2011 Ian Johnson - removed deprecated archive triggers


-- This file contains the stored procedures and triggers for h3 databases

-- RUN FROM COMMAND LINE LOGGED IN AS ROOT IN DIRECTORY /var/www/htdocs/h3-xx WITH:
--   mysql -u root -ppassword databasename < admin/setup/addProceduresTriggers.sql
-- Note: this file cannot be run in PHPMySQL because it doesn't recognise the delimiter changes

-- MAY NOT REPORT ERRORS, POSSIBLE NEED TO SET STDOUT FIRST AND/OR USE TEE TO WRITE TO OUTPUT FILE
-- AND INSPECT



-- Stored Procedures
-- ------------------------------------------------------------------------------

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

-- ------------------------------------------------------------------------------

DELIMITER $$

	DROP TRIGGER IF EXISTS insert_record_trigger$$

	CREATE
	DEFINER=`root`@`localhost`
	TRIGGER `insert_record_trigger`
	AFTER INSERT ON `Records`
	FOR EACH ROW
	begin
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
--			insert into archiveRecords (arec_ID, arec_UGrpID, arec_Date, arec_URL, arec_Title, arec_ScratchPad, arec_RecTypeID)
--				values (NEW.rec_ID, @logged_in_user_id, now(),
--						if (NEW.rec_URL=OLD.rec_URL, NULL, NEW.rec_URL),
--						if (NEW.rec_Title=OLD.rec_Title, NULL, NEW.rec_Title),
--						if (NEW.rec_ScratchPad=OLD.rec_ScratchPad, NULL, NEW.rec_ScratchPad),
--						if (NEW.rec_RecTypeID=OLD.rec_RecTypeID, NULL, NEW.rec_RecTypeID));

-- 14/2/11 Ian: Do we need this value set by the previous insert?
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
--		insert into archiveRecords (arec_ID, arec_UGrpID, arec_Date)
--			values (OLD.rec_ID, @logged_in_user_id, now());

-- 14/2/11 Ian: Do we need this value set by the previous insert?
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

-- --------------------------------------------------------------------------------

