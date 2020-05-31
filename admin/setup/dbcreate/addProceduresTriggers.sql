-- Created by Steve White 2010-10-23
-- Last updated 2 April 2011 Ian Johnson - removed deprecated archive triggers
-- 2013-05-13 Arjen Lentz - added replacement functions for levenshtein.c and liposuction.c UDFs


-- This file contains the stored procedures and triggers for Heurist databases

-- RUN FROM COMMAND LINE LOGGED IN AS ROOT IN DIRECTORY /var/www/html/h5-xx WITH:
--   mysql -u root -ppassword hdb_databasename < admin/setup/dbcreate/addProceduresTriggers.sql
-- Note: this file cannot be run in PHPMySQL because it doesn't recognise the delimiter changes

-- MAY NOT REPORT ERRORS, POSSIBLE NEED TO SET STDOUT FIRST AND/OR USE TEE TO WRITE TO OUTPUT FILE
-- AND INSPECT

-- Stored Procedures
-- ------------------------------------------------------------------------------

-- TODO: 5/9/11 Replace the clunky use of the sys_TableLastUpdated table to record the date of last update of defionition
-- tables, involving a complex set of triggers on add/update/delete (the latter not working in any case)
-- with a simple max(xxx_Modified) - takes 0.3 millisecs on the Records table with 56K records, so practically instant
-- tlu_ fields are only accessed in three places - loadCommonInfo.php, getRecordInfoLibrary.php and getRectypesAsJSON.php
-- ------------------------------------------------------------------------------

DROP function IF EXISTS `hhash`;
DROP function IF EXISTS `simple_hash`;
DROP procedure IF EXISTS `set_all_hhash`;
DROP FUNCTION IF EXISTS NEW_LEVENSHTEIN ;
DROP FUNCTION IF EXISTS NEW_LIPOSUCTION;

DELIMITER $$


DROP function IF EXISTS `getTemporalDateString`$$

CREATE DEFINER=CURRENT_USER FUNCTION `getTemporalDateString`(strDate varchar(4095)) RETURNS varchar(4095) CHARSET utf8
	DETERMINISTIC
	begin
			declare temporalType char;
			declare iBegin integer;
			declare iEnd integer;
			declare dateString varchar(4095);
-- find the temporal type might not be a temporal format, see else below
		set @iBegin := LOCATE('TYP=',strDate);
		if iBegin = 0 THEN
			RETURN strDate;
		else
			set @iBegin := @iBegin + 4;
		end if;
		set @temporalType = SUBSTRING(strDate,@iBegin,1);
		CASE @temporalType
			WHEN 's' THEN
-- Simple Date
				begin
					set @iBegin := INSTR(strDate,'DAT=');
					if @iBegin = 0 THEN
-- no Date field send empty string
						RETURN '';
					else
						set @iBegin := @iBegin + 4;
					end if;
					set @iEnd := LOCATE('|', strDate, @iBegin);
					if @iEnd = 0 THEN
-- no other properties so goto end of string
						begin
							set @dateString =  substring(strDate,@iBegin);
						end;
					else
-- use iEnd to calc substring length
						begin
							set @dateString =  substring(strDate,@iBegin, @iEnd - @iBegin);
						end;
					end if;
				end;
			WHEN 'f' THEN
-- Fuzzy Date
				begin
					set @iBegin := INSTR(strDate,'DAT=');
					if @iBegin = 0 THEN
-- no Date field send empty string
						RETURN '';
					else
						set @iBegin := @iBegin + 4;
					end if;
					set @iEnd := LOCATE('|', strDate, @iBegin);
					if @iEnd = 0 THEN
-- no other properties so goto end of string
						begin
							set @dateString =  substring(strDate,@iBegin);
						end;
					else
-- use iEnd to calc substring length
						begin
							set @dateString =  substring(strDate,@iBegin, @iEnd - @iBegin);
						end;
					end if;
				end;
			WHEN 'c' THEN
-- Carbon14 Date
				begin
					set @iBegin := INSTR(strDate,'BPD=');
					if @iBegin = 0 THEN
-- no Date field send empty string
						set @iBegin := INSTR(strDate,'BCE=');
					end if;
					if @iBegin = 0 THEN
-- no Date field send empty string
						RETURN '';
					else
						set @iBegin := @iBegin + 4;
					end if;
					set @iEnd := LOCATE('|', strDate, @iBegin);
					if @iEnd = 0 THEN
-- no other properties so goto end of string
						begin
							set @dateString =  substring(strDate,@iBegin);
						end;
					else
-- use iEnd to calc substring length
						begin
							set @dateString =  substring(strDate,@iBegin, @iEnd - @iBegin);
						end;
					end if;
				end;
			WHEN 'p' THEN
-- Probable Date
				begin
					set @iBegin := INSTR(strDate,'TPQ=');
					if @iBegin = 0 THEN
-- no TPQ field try PDB
						set @iBegin := INSTR(strDate,'PDB=');
					end if;
					if @iBegin = 0 THEN
-- no PDB field try PDE
						set @iBegin := INSTR(strDate,'PDE=');
					end if;
					if @iBegin = 0 THEN
-- no PDE field try TAQ
						set @iBegin := INSTR(strDate,'TAQ=');
					end if;
					if @iBegin = 0 THEN
-- no Date field send empty string
						RETURN '';
					else
						set @iBegin := @iBegin + 4;
					end if;
					set @iEnd := LOCATE('|', strDate, @iBegin);
					if @iEnd = 0 THEN
-- no other properties so goto end of string
						begin
							set @dateString =  substring(strDate,@iBegin);
						end;
					else
-- use iEnd to calc substring length
						begin
							set @dateString =  substring(strDate,@iBegin, @iEnd - @iBegin);
						end;
					end if;
				end;
			ELSE
				set @dateString = strDate;
		END CASE;

		return @dateString;
	end$$

DELIMITER ;

--  **********************   Triggers   ***************************
-- ------------------------------------------------------------------------------

-- ------------------------------------------------------------------------------
-- --------recDetails



	DROP TRIGGER IF EXISTS insert_Details_precis_trigger;
-- DELIMITER $$

-- 	CREATE
-- 	DEFINER=CURRENT_USER 
-- 	TRIGGER `insert_Details_precis_trigger`
-- 	BEFORE INSERT ON `recDetails`
-- 	FOR EACH ROW
--           begin set NEW.dtl_ValShortened = ifnull(NEW_LIPOSUCTION(NEW.dtl_Value), ''); end$$
-- 
-- DELIMITER ;

DELIMITER $$

    DROP TRIGGER IF EXISTS insert_Details_trigger$$

    CREATE
    DEFINER=CURRENT_USER 
    TRIGGER `insert_Details_trigger`
    AFTER INSERT ON `recDetails`
    FOR EACH ROW
    begin
        declare dtType varchar(20);
        select dty_Type into dtType
            from defDetailTypes
            where dty_ID=NEW.dtl_DetailTypeID
            limit 1;
    
--        declare relSrcDT integer;
--        declare relTrgDT integer;
--        select dty_ID into relSrcDT
--            from defDetailTypes
--            where dty_OriginatingDBID = 3 and dty_IDInOriginatingDB = 202
--            order by dty_ID desc limit 1;
--        select dty_ID into relTrgDT
--            from defDetailTypes
--            where dty_OriginatingDBID = 3 and dty_IDInOriginatingDB = 199
--            order by dty_ID desc limit 1;
--        if NEW.dtl_DetailTypeID=relTrgDT then
        if NEW.dtl_DetailTypeID=5 then -- linked resource pointer
        begin
            update recRelationshipsCache
-- need to also save the RecTypeID for the record to help with constraint checking
                set rrc_TargetRecID = NEW.dtl_Value
                where rrc_RecID=NEW.dtl_RecID;
                
            update recLinks
                set rl_TargetID = NEW.dtl_Value
                where rl_RelationID=NEW.dtl_RecID;
        end;        
--        elseif NEW.dtl_DetailTypeID=relSrcDT then
        elseif NEW.dtl_DetailTypeID=7 then -- primary resource pointer
        begin
            update recRelationshipsCache
-- need to also save the RecTypeID for the record to help with constraint checking
                set rrc_SourceRecID = NEW.dtl_Value
                where rrc_RecID=NEW.dtl_RecID;

            update recLinks
                set rl_SourceID = NEW.dtl_Value
                where rl_RelationID=NEW.dtl_RecID;
        end;
        elseif NEW.dtl_DetailTypeID=6 then -- relationship type
            update recLinks
                set rl_RelationTypeID = NEW.dtl_Value
                where rl_RelationID=NEW.dtl_RecID;
                
        elseif dtType='resource' then
            insert into recLinks (rl_SourceID, rl_TargetID, rl_DetailTypeID, rl_DetailID)
            values (NEW.dtl_RecID, NEW.dtl_Value, NEW.dtl_DetailTypeID, NEW.dtl_ID);
        end if;
-- legacy databases: need to add update for 200 to save the termID to help with constraint checking
-- new databases: need to add update for detail 200, now 5, to save the termID
    end$$
    

	DROP TRIGGER IF EXISTS pre_update_Details_trigger$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `pre_update_Details_trigger`
	BEFORE UPDATE ON `recDetails`
	FOR EACH ROW
	begin

        if @suppress_update_trigger is null then
        -- archive previous version into sysArchive
SELECT CONCAT_WS(',',COALESCE(CONCAT('"',dtl_ID,'"'),'NULL'),COALESCE(CONCAT('"',dtl_RecID,'"'),'NULL'),COALESCE(CONCAT('"',dtl_DetailTypeID,'"'),'NULL'),COALESCE(CONCAT('"',dtl_Value,'"'),'NULL'),COALESCE(CONCAT('"',dtl_AddedByImport,'"'),'NULL'),COALESCE(CONCAT('"',dtl_UploadedFileID,'"'),'NULL'),COALESCE(CONCAT('"',ST_AsText(dtl_Geo),'"'),'NULL'),COALESCE(CONCAT('"',dtl_ValShortened,'"'),'NULL'),COALESCE(CONCAT('"',dtl_Modified,'"'),'NULL'),COALESCE(CONCAT('"',dtl_Certainty,'"'),'NULL'),COALESCE(CONCAT('"',dtl_Annotation,'"'),'NULL')) 
FROM recDetails where dtl_ID=OLD.dtl_ID INTO @raw_detail;       
        insert into sysArchive (arc_Table, arc_PriKey, arc_ChangedByUGrpID, arc_OwnerUGrpID, arc_RecID, arc_DataBeforeChange, arc_ContentType)
            values ('dtl', OLD.dtl_ID, COALESCE(@logged_in_user_id,0), NULL, OLD.dtl_RecID, @raw_detail, 'raw');
        end if;
    
		if ST_AsBinary(NEW.dtl_Geo)=ST_AsBinary(OLD.dtl_Geo) then
			set NEW.dtl_Geo = OLD.dtl_Geo;
		end if;
-- set NEW.dtl_ValShortened = ifnull(NEW_LIPOSUCTION(NEW.dtl_Value), '');
	end$$

DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS update_Details_trigger$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `update_Details_trigger`
	AFTER UPDATE ON `recDetails`
	FOR EACH ROW
	begin
        declare dtType varchar(20);
        select dty_Type into dtType
            from defDetailTypes
            where dty_ID=NEW.dtl_DetailTypeID
            limit 1;
    
    
--		declare relSrcDT integer;
--		declare relTrgDT integer;
--		select dty_ID into relSrcDT
--			from defDetailTypes
--			where dty_OriginatingDBID = 3 and dty_IDInOriginatingDB = 202
--			order by dty_ID desc limit 1;
--		select dty_ID into relTrgDT
--			from defDetailTypes
--			where dty_OriginatingDBID = 3 and dty_IDInOriginatingDB = 199
--			order by dty_ID desc limit 1;
--		if NEW.dtl_DetailTypeID=relTrgDT then
		if NEW.dtl_DetailTypeID=5 then -- linked resource pointer
        begin
            update recRelationshipsCache
            -- need to also save teh RecTypeID for the record
                set rrc_TargetRecID = NEW.dtl_Value
                where rrc_RecID=NEW.dtl_RecID;
                
            update recLinks
                set rl_TargetID = NEW.dtl_Value
                where rl_RelationID=NEW.dtl_RecID;
        end;
--		elseif NEW.dtl_DetailTypeID=relSrcDT then
		elseif NEW.dtl_DetailTypeID=7 then -- primary resource pointer
        begin
            update recRelationshipsCache
                set rrc_SourceRecID = NEW.dtl_Value
-- need to also save teh RecTypeID for the record
                where rrc_RecID=NEW.dtl_RecID;
            update recLinks
                set rl_SourceID = NEW.dtl_Value
                where rl_RelationID=NEW.dtl_RecID;
        end;
        elseif NEW.dtl_DetailTypeID=6 then -- relationship type
            update recLinks
                set rl_RelationTypeID = NEW.dtl_Value
                where rl_RelationID=NEW.dtl_RecID;
                
        elseif dtType='resource' then
            update recLinks set rl_TargetID=NEW.dtl_Value, rl_DetailTypeID=NEW.dtl_DetailTypeID
            where rl_DetailID=NEW.dtl_ID;
        end if;
-- need to add update for detail 200, now 5, to save the termID
	end$$

    DROP TRIGGER IF EXISTS delete_detail_trigger$$

    CREATE
    DEFINER=CURRENT_USER 
    TRIGGER `delete_detail_trigger`
    BEFORE DELETE ON `recDetails`
    FOR EACH ROW
    begin
    
        if @suppress_update_trigger is null then
        -- archive previous version into sysArchive
SELECT CONCAT_WS(',',COALESCE(CONCAT('"',dtl_ID,'"'),'NULL'),COALESCE(CONCAT('"',dtl_RecID,'"'),'NULL'),COALESCE(CONCAT('"',dtl_DetailTypeID,'"'),'NULL'),COALESCE(CONCAT('"',dtl_Value,'"'),'NULL'),COALESCE(CONCAT('"',dtl_AddedByImport,'"'),'NULL'),COALESCE(CONCAT('"',dtl_UploadedFileID,'"'),'NULL'),COALESCE(CONCAT('"',ST_AsText(dtl_Geo),'"'),'NULL'),COALESCE(CONCAT('"',dtl_ValShortened,'"'),'NULL'),COALESCE(CONCAT('"',dtl_Modified,'"'),'NULL'),COALESCE(CONCAT('"',dtl_Certainty,'"'),'NULL'),COALESCE(CONCAT('"',dtl_Annotation,'"'),'NULL')) 
FROM recDetails where dtl_ID=OLD.dtl_ID INTO @raw_detail;       
        insert into sysArchive (arc_Table, arc_PriKey, arc_ChangedByUGrpID, arc_OwnerUGrpID, arc_RecID, arc_DataBeforeChange, arc_ContentType)
            values ('dtl', OLD.dtl_ID, COALESCE(@logged_in_user_id,0), NULL, OLD.dtl_RecID, @raw_detail, 'raw');
        end if;
    
        delete ignore from recLinks where rl_DetailID=OLD.dtl_ID;
    end$$
    
    
DELIMITER ;

-- ------------------------------------------------------------------------------
-- --------Records

DELIMITER $$

	DROP TRIGGER IF EXISTS insert_record_trigger$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `insert_record_trigger`
	AFTER INSERT ON `Records`
	FOR EACH ROW
	begin
--		declare relRT integer;
--		select rty_ID into relRT
--			from defRecTypes
--			where rty_OriginatingDBID = 3 and rty_IDInOriginatingDB = 52 order by rty_ID desc limit 1;
-- need to change this to check the rectype's type = relationship
-- 1 = record relationship
    if @suppress_update_trigger is null then
	    insert into usrRecentRecords (rre_UGrpID, rre_RecID, rre_Time)
								    values (@logged_in_user_id, NEW.rec_ID, now());
	    set @rec_id := last_insert_id(NEW.rec_ID);
    --		if NEW.rec_RecTypeID = relRT then
		if NEW.rec_RecTypeID = 1 then
        begin
--  need to also save relationship records RecTypeID
            insert into recRelationshipsCache (rrc_RecID, rrc_SourceRecID, rrc_TargetRecID) values (NEW.rec_ID,NEW.rec_ID,NEW.rec_ID);
            
            insert into recLinks (rl_SourceID, rl_TargetID, rl_RelationID)
            values (NEW.rec_ID, NEW.rec_ID, NEW.rec_ID);
        end;    
		end if;
    end if;
	end$$

DELIMITER ;
DELIMITER $$

-- dynamic expression is not allowed in triggers
--    DROP PROCEDURE IF EXISTS logRecord$$
--    CREATE PROCEDURE logRecord(rec_ID INT)
--    BEGIN
--        SET @colnames = (SELECT GROUP_CONCAT('COALESCE(CONCAT(\'"\',',COLUMN_NAME,',\'"\'),\'NULL\')') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Records' AND TABLE_SCHEMA = (SELECT DATABASE()));
--        SET @exp = CONCAT('SELECT CONCAT_WS(\',\',', @colnames, ') FROM Records where rec_ID=', @rec_ID, ' INTO @raw_record');
--
--        PREPARE stmt FROM @exp;
--        EXECUTE stmt;
--        DEALLOCATE PREPARE stmt;  
            
--        insert into sysArchive (arc_Table, arc_PriKey, arc_ChangedByUGrpID, arc_OwnerUGrpID, arc_RecID, arc_DataBeforeChange, arc_ContentType)
--            values ('rec', rec_ID, COALESCE(@logged_in_user_id,0), OLD.rec_OwnerUGrpID, rec_ID, @raw_record, 'raw');

--    END$$

--    DROP PROCEDURE IF EXISTS logRecordDetail$$
--    CREATE PROCEDURE logRecordDetail(dtl_RecID INT, dtl_RecID INT)
--    
--        SET @colnames = (SELECT GROUP_CONCAT('COALESCE(CONCAT(\'"\',',COLUMN_NAME,',\'"\'),\'NULL\')') FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'recDetails' AND TABLE_SCHEMA = (SELECT DATABASE()));
--        SET @exp = CONCAT('SELECT CONCAT_WS(\',\',', @colnames, ') FROM Records where rec_ID=', @rec_ID, ' INTO @raw_record');
--
--        PREPARE stmt FROM @exp;
--        EXECUTE stmt;
--        DEALLOCATE PREPARE stmt;  
            
--        insert into sysArchive (arc_Table, arc_PriKey, arc_ChangedByUGrpID, arc_OwnerUGrpID, arc_RecID, arc_DataBeforeChange, arc_ContentType)
--            values ('dtl', dtl_ID, COALESCE(@logged_in_user_id,0), NULL, dtl_RecID, @raw_record, 'raw');

--    END$$


    DROP TRIGGER IF EXISTS update_record_trigger$$

    CREATE
    DEFINER=CURRENT_USER 
    TRIGGER `update_record_trigger`
    BEFORE UPDATE ON `Records`
    FOR EACH ROW
    begin
        if @suppress_update_trigger is null then
        -- archive previous version of record into sysArchive
        SELECT CONCAT_WS(',',COALESCE(CONCAT('"',rec_ID,'"'),'NULL'),COALESCE(CONCAT('"',rec_URL,'"'),'NULL'),COALESCE(CONCAT('"',rec_Added,'"'),'NULL'),COALESCE(CONCAT('"',rec_Modified,'"'),'NULL'),COALESCE(CONCAT('"',rec_Title,'"'),'NULL'),COALESCE(CONCAT('"',rec_ScratchPad,'"'),'NULL'),COALESCE(CONCAT('"',rec_RecTypeID,'"'),'NULL'),COALESCE(CONCAT('"',rec_AddedByUGrpID,'"'),'NULL'),COALESCE(CONCAT('"',rec_AddedByImport,'"'),'NULL'),COALESCE(CONCAT('"',rec_Popularity,'"'),'NULL'),COALESCE(CONCAT('"',rec_FlagTemporary,'"'),'NULL'),COALESCE(CONCAT('"',rec_OwnerUGrpID,'"'),'NULL'),COALESCE(CONCAT('"',rec_NonOwnerVisibility,'"'),'NULL'),COALESCE(CONCAT('"',rec_URLLastVerified,'"'),'NULL'),COALESCE(CONCAT('"',rec_URLErrorMessage,'"'),'NULL'),COALESCE(CONCAT('"',rec_URLExtensionForMimeType,'"'),'NULL'),COALESCE(CONCAT('"',rec_Hash,'"'),'NULL')) 
        FROM Records where rec_ID=OLD.rec_ID INTO @raw_record;
        insert into sysArchive (arc_Table, arc_PriKey, arc_ChangedByUGrpID, arc_OwnerUGrpID, arc_RecID, arc_DataBeforeChange, arc_ContentType)
            values ('rec', OLD.rec_ID, COALESCE(@logged_in_user_id,0), OLD.rec_OwnerUGrpID, OLD.rec_ID, @raw_record, 'raw');

        end if;
        
        if NEW.rec_URL != OLD.rec_URL OR NEW.rec_URL is null then
            set NEW.rec_URLLastVerified := NULL;
        end if;
    end$$

DELIMITER ;

DELIMITER $$

	DROP TRIGGER IF EXISTS usrRecentRecords_updater$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `usrRecentRecords_updater`
	AFTER UPDATE ON `Records`
	FOR EACH ROW
	begin
--		declare relRT integer;
		declare srcRecID integer;
		declare trgRecID integer;
--		declare relSrcDT integer;
--		declare relTrgDT integer;
		if @suppress_update_trigger is null then
			insert into usrRecentRecords (rre_UGrpID, rre_RecID, rre_Time)
				values (@logged_in_user_id, NEW.rec_ID, now())
				on duplicate key update rre_Time = now();
		end if;
--		select dty_ID into relSrcDT
--			from defDetailTypes
--			where dty_OriginatingDBID = 3 and dty_IDInOriginatingDB = 202
--			order by dty_ID desc limit 1;
--		select dty_ID into relTrgDT
--			from defDetailTypes
--			where dty_OriginatingDBID = 3 and dty_IDInOriginatingDB = 199
--			order by dty_ID desc limit 1;
--		select rty_ID into relRT
--			from defRecTypes
--			where rty_OriginatingDBID = 3 and rty_IDInOriginatingDB = 52 order by rty_ID desc limit 1;
-- if change the records type from something else to relation insert cache value
--		if NEW.rec_RecTypeID = relRT AND NOT OLD.rec_RecTypeID = relRT then
--			select dtl_Value into srcRecID
--				from recDetails
--				where dtl_DetailTypeID = relSrcDT and OLD.rec_ID=dtl_RecID order by dtl_Value desc limit 1;
-- record type 1 = relationship record
		if NEW.rec_RecTypeID = 1 AND NOT OLD.rec_RecTypeID = 1 then
			select dtl_Value into srcRecID
				from recDetails
-- primary resource pointer
				where dtl_DetailTypeID=7 and OLD.rec_ID=dtl_RecID order by dtl_Value desc limit 1;
			if srcRecID is null then
				set srcRecID = NEW.rec_ID;
			end if;
			select dtl_Value into trgRecID
				from recDetails
--				where dtl_DetailTypeID = relTrgDT and OLD.rec_ID=dtl_RecID order by dtl_Value desc limit 1;
-- linked resource pointer
				where dtl_DetailTypeID=5 and OLD.rec_ID=dtl_RecID order by dtl_Value desc limit 1;
			if trgRecID is null then
				set trgRecID = NEW.rec_ID;
			end if;
			insert into recRelationshipsCache (rrc_RecID, rrc_SourceRecID, rrc_TargetRecID) values (NEW.rec_ID,srcRecID,trgRecID);
            
            insert into recLinks (rl_SourceID, rl_TargetID, rl_RelationID)
            values (srcRecID, trgRecID, NEW.rec_ID);
		end if;
-- if change the records type from relation to something else remove cache value
--		if OLD.rec_RecTypeID = relRT AND NOT NEW.rec_RecTypeID = relRT then
	    if OLD.rec_RecTypeID = 1 AND NOT NEW.rec_RecTypeID = 1 then
        begin
            delete ignore from recRelationshipsCache where rrc_RecID = OLD.rec_ID;
            delete ignore from recLinks where rl_RelationID=OLD.rec_ID;
        end;    
		end if;
	end$$

DELIMITER ;
DELIMITER $$

	DROP TRIGGER IF EXISTS delete_record_trigger$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `delete_record_trigger`
	BEFORE DELETE ON `Records`
	FOR EACH ROW
	begin 
    
      if @suppress_update_trigger is null then
        -- archive previous version of record into sysArchive
        SELECT CONCAT_WS(',',COALESCE(CONCAT('"',rec_ID,'"'),'NULL'),COALESCE(CONCAT('"',rec_URL,'"'),'NULL'),COALESCE(CONCAT('"',rec_Added,'"'),'NULL'),COALESCE(CONCAT('"',rec_Modified,'"'),'NULL'),COALESCE(CONCAT('"',rec_Title,'"'),'NULL'),COALESCE(CONCAT('"',rec_ScratchPad,'"'),'NULL'),COALESCE(CONCAT('"',rec_RecTypeID,'"'),'NULL'),COALESCE(CONCAT('"',rec_AddedByUGrpID,'"'),'NULL'),COALESCE(CONCAT('"',rec_AddedByImport,'"'),'NULL'),COALESCE(CONCAT('"',rec_Popularity,'"'),'NULL'),COALESCE(CONCAT('"',rec_FlagTemporary,'"'),'NULL'),COALESCE(CONCAT('"',rec_OwnerUGrpID,'"'),'NULL'),COALESCE(CONCAT('"',rec_NonOwnerVisibility,'"'),'NULL'),COALESCE(CONCAT('"',rec_URLLastVerified,'"'),'NULL'),COALESCE(CONCAT('"',rec_URLErrorMessage,'"'),'NULL'),COALESCE(CONCAT('"',rec_URLExtensionForMimeType,'"'),'NULL'),COALESCE(CONCAT('"',rec_Hash,'"'),'NULL')) 
        FROM Records where rec_ID=OLD.rec_ID INTO @raw_record;
        insert into sysArchive (arc_Table, arc_PriKey, arc_ChangedByUGrpID, arc_OwnerUGrpID, arc_RecID, arc_DataBeforeChange, arc_ContentType)
            values ('rec', OLD.rec_ID, COALESCE(@logged_in_user_id,0), OLD.rec_OwnerUGrpID, OLD.rec_ID, @raw_record, 'del');
      end if;  
        
--		declare relRT integer;
--		select rty_ID into relRT
--			from defRecTypes
--			where rty_OriginatingDBID = 3 and rty_IDInOriginatingDB = 52 order by rty_ID desc limit 1;

    delete from usrRecentRecords where rre_RecID = OLD.rec_ID;

-- need to change this to check the rectype's type = relationship
--		if OLD.rec_RecTypeID = relRT then
		if OLD.rec_RecTypeID = 1 then
			delete ignore from recRelationshipsCache where rrc_RecID = OLD.rec_ID;
		end if;
        
        delete ignore from recLinks where rl_RelationID=OLD.rec_ID or rl_SourceID=OLD.rec_ID or rl_TargetID=OLD.rec_ID;
	end$$

DELIMITER ;

-- ------------------------------------------------------------------------------
-- --------usrBookmarks

DELIMITER $$

	DROP TRIGGER IF EXISTS usrBookmarks_update$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `usrBookmarks_update`
	BEFORE UPDATE ON `usrBookmarks`
	FOR EACH ROW
		set NEW.bkm_Modified = now()$$

DELIMITER ;

-- ------------------------------------------------------------------------------
-- --------sysUGrps

DELIMITER $$

DROP TRIGGER IF EXISTS sysUGrps_last_insert$$
DROP TRIGGER IF EXISTS sysUGrps_last_update$$


--  			insert
	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `sysUGrps_last_insert`
	AFTER INSERT ON `sysUGrps`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUGrps"$$


    CREATE
    DEFINER=CURRENT_USER 
    TRIGGER `sysUGrps_last_update`
    AFTER UPDATE ON `sysUGrps`
    FOR EACH ROW
    begin
        update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUGrps";
    end$$
    
DELIMITER ;

-- ------------------------------------------------------------------------------
-- --------sysUsrGrpLinks

DELIMITER $$

DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_insert$$
DROP TRIGGER IF EXISTS sysUsrGrpLinks_last_update$$

    CREATE
    DEFINER=CURRENT_USER 
    TRIGGER `sysUsrGrpLinks_last_insert`
    AFTER INSERT ON `sysUsrGrpLinks`
    FOR EACH ROW
    begin
       update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks";
    end$$

    CREATE
    DEFINER=CURRENT_USER 
    TRIGGER `sysUsrGrpLinks_last_update`
    AFTER UPDATE ON `sysUsrGrpLinks`
    FOR EACH ROW
    begin
       update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="sysUsrGrpLinks";
    end$$

DELIMITER ;

-- ------------------------------------------------------------------------------
-- --------defDetailTypes

DELIMITER $$

--  			insert
	DROP TRIGGER IF EXISTS defDetailTypes_last_insert$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defDetailTypes_last_insert`
	AFTER INSERT ON `defDetailTypes`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypes"$$

DELIMITER ;
DELIMITER $$

--  			update
	DROP TRIGGER IF EXISTS defDetailTypes_last_update$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defDetailTypes_last_update`
	AFTER UPDATE ON `defDetailTypes`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypes"$$

DELIMITER ;
DELIMITER $$

--  			delete
	DROP TRIGGER IF EXISTS defDetailTypes_delete$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defDetailTypes_delete`
	AFTER DELETE ON `defDetailTypes`
	FOR EACH ROW
			update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypes"$$

DELIMITER ;

-- ------------------------------------------------------------------------------
-- --------defRecTypes

DELIMITER $$

--  			insert
	DROP TRIGGER IF EXISTS defRecTypes_last_insert$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defRecTypes_last_insert`
	AFTER INSERT ON `defRecTypes`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypes"$$

DELIMITER ;
DELIMITER $$

--  			update
	DROP TRIGGER IF EXISTS defRecTypes_last_update$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defRecTypes_last_update`
	AFTER UPDATE ON `defRecTypes`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypes"$$

DELIMITER ;
DELIMITER $$

--  			delete
	DROP TRIGGER IF EXISTS defRecTypes_delete$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defRecTypes_delete`
	AFTER DELETE ON `defRecTypes`
	FOR EACH ROW
			update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypes"$$

DELIMITER ;
-- ------------------------------------------------------------------------------
-- --------defRecStructure
DELIMITER $$

--  			insert
	DROP TRIGGER IF EXISTS defRecStructure_last_insert$$

	CREATE DEFINER=CURRENT_USER 
	TRIGGER `defRecStructure_last_insert`
	AFTER INSERT ON `defRecStructure`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure"$$

DELIMITER ;
DELIMITER $$

--  			update
	DROP TRIGGER IF EXISTS defRecStructure_last_update$$

	CREATE DEFINER=CURRENT_USER 
	TRIGGER `defRecStructure_last_update`
	AFTER UPDATE ON `defRecStructure`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure"$$

DELIMITER ;
DELIMITER $$

--  			delete
-- 5/9/11 This trigger gives an error "multiple triggers with the same action time and event for one table" not yet supported
	DROP TRIGGER IF EXISTS defRecStructure_last_delete$$

	CREATE DEFINER=CURRENT_USER 
	TRIGGER `defRecStructure_last_delete`
	AFTER DELETE ON `defRecStructure`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecStructure"$$

DELIMITER ;

-- ------------------------------------------------------------------------------
-- --------defTerms

DELIMITER $$

--  			insert
	DROP TRIGGER IF EXISTS defTerms_last_insert$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defTerms_last_insert`
	AFTER INSERT ON `defTerms`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defTerms"$$

DELIMITER ;
DELIMITER $$

--  			update
	DROP TRIGGER IF EXISTS defTerms_last_update$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defTerms_last_update`
	AFTER UPDATE ON `defTerms`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defTerms"$$

DELIMITER ;
DELIMITER $$

--  			delete
	DROP TRIGGER IF EXISTS defTerms_last_delete$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defTerms_last_delete`
	AFTER DELETE ON `defTerms`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defTerms"$$

DELIMITER ;

-- ------------------------------------------------------------------------------
-- --------defRelationshipConstraints

DELIMITER $$

--  			insert
	DROP TRIGGER IF EXISTS defRelationshipConstraints_last_insert$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defRelationshipConstraints_last_insert`
	AFTER INSERT ON `defRelationshipConstraints`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints"$$

DELIMITER ;
DELIMITER $$

--  			update
	DROP TRIGGER IF EXISTS defRelationshipConstraints_last_update$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defRelationshipConstraints_last_update`
	AFTER UPDATE ON `defRelationshipConstraints`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints"$$

DELIMITER ;
DELIMITER $$

--  			delete
	DROP TRIGGER IF EXISTS defRelationshipConstraints_last_delete$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defRelationshipConstraints_last_delete`
	AFTER DELETE ON `defRelationshipConstraints`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRelationshipConstraints"$$

DELIMITER ;

-- --------------------------------------------------------------------------------
-- --------defRecTypeGroups

-- ** New triggers from Artem 5/4/2011
DELIMITER $$

--  			insert
	DROP TRIGGER IF EXISTS defRecTypeGroups_insert$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defRecTypeGroups_insert`
	AFTER INSERT ON `defRecTypeGroups`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypeGroups"$$

DELIMITER ;
DELIMITER $$

--  			update
	DROP TRIGGER IF EXISTS defRecTypeGroups_update$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defRecTypeGroups_update`
	AFTER UPDATE ON `defRecTypeGroups`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypeGroups"$$

DELIMITER ;
DELIMITER $$

--  			delete
	DROP TRIGGER IF EXISTS defRecTypeGroups_delete$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defRecTypeGroups_delete`
	AFTER DELETE ON `defRecTypeGroups`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defRecTypeGroups"$$

DELIMITER ;

-- --------------------------------------------------------------------------------
-- --------defDetailTypeGroups

DELIMITER $$

--  			insert
	DROP TRIGGER IF EXISTS defDetailTypeGroups_insert$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defDetailTypeGroups_insert`
	AFTER INSERT ON `defDetailTypeGroups`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypeGroups"$$

DELIMITER ;
DELIMITER $$

--  			update
	DROP TRIGGER IF EXISTS defDetailTypeGroups_update$$

	CREATE
	DEFINER=CURRENT_USER 
	TRIGGER `defDetailTypeGroups_update`
	AFTER UPDATE ON `defDetailTypeGroups`
	FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypeGroups"$$

DELIMITER ;
DELIMITER $$

--  			delete
	DROP TRIGGER IF EXISTS defDetailTypeGroups_delete$$

		CREATE
		DEFINER=CURRENT_USER 
	TRIGGER `defDetailTypeGroups_delete`
	AFTER DELETE ON `defDetailTypeGroups`
		FOR EACH ROW
		update sysTableLastUpdated set tlu_DateStamp=now() where tlu_TableName="defDetailTypeGroups"$$

DELIMITER ;
