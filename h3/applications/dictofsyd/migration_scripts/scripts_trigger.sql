-- Trigger DDL Statements
USE `hdb_dos_3`;

DROP TRIGGER IF EXISTS `insert_record_trigger`;
DROP TRIGGER IF EXISTS `update_record_trigger`;
DROP TRIGGER IF EXISTS `usrRecentRecords_updater`;
DROP TRIGGER IF EXISTS `delete_record_trigger`;
DROP TRIGGER IF EXISTS `insert_Details_trigger`;
DROP TRIGGER IF EXISTS `insert_Details_precis_trigger`;
DROP TRIGGER IF EXISTS `pre_update_Details_trigger`;
DROP TRIGGER IF EXISTS `update_Details_trigger`;

DELIMITER $$

CREATE
DEFINER=`root`@`localhost`
TRIGGER `hdb_dos_3`.`insert_record_trigger`
AFTER INSERT ON `hdb_dos_3`.`Records`
FOR EACH ROW
begin
	
	
	insert into usrRecentRecords (rre_UGrpID, rre_RecID, rre_Time)
								values (@logged_in_user_id, NEW.rec_ID, now());
	set @rec_id := last_insert_id(NEW.rec_ID);
		if NEW.rec_RecTypeID = 1 then
			
			insert into recRelationshipsCache (rrc_RecID, rrc_SourceRecID, rrc_TargetRecID) values (NEW.rec_ID,NEW.rec_ID,NEW.rec_ID);
		end if;
	end$$

CREATE
DEFINER=`root`@`localhost`
TRIGGER `hdb_dos_3`.`update_record_trigger`
BEFORE UPDATE ON `hdb_dos_3`.`Records`
FOR EACH ROW
begin
		if @suppress_update_trigger is null then
			set @rec_version := last_insert_id();
		end if;
		if NEW.rec_URL != OLD.rec_URL OR NEW.rec_URL is null then
			set NEW.rec_URLLastVerified := NULL;
		end if;
	end$$

CREATE
DEFINER=`root`@`localhost`
TRIGGER `hdb_dos_3`.`usrRecentRecords_updater`
AFTER UPDATE ON `hdb_dos_3`.`Records`
FOR EACH ROW
begin
		declare srcRecID integer;
		declare trgRecID integer;
		if @suppress_update_trigger is null then
			insert into usrRecentRecords (rre_UGrpID, rre_RecID, rre_Time)
				values (@logged_in_user_id, NEW.rec_ID, now())
				on duplicate key update rre_Time = now();
		end if;
		
		
		if NEW.rec_RecTypeID = 1 AND NOT OLD.rec_RecTypeID = 1 then
			select dtl_Value into srcRecID
				from recDetails
				
				where dtl_DetailTypeID=7 and OLD.rec_ID=dtl_RecID order by dtl_Value desc limit 1;
			if srcRecID is null then
				set srcRecID = NEW.rec_ID;
			end if;
			select dtl_Value into trgRecID
				from recDetails
				
				where dtl_DetailTypeID=5 and OLD.rec_ID=dtl_RecID order by dtl_Value desc limit 1;
			if trgRecID is null then
				set trgRecID = NEW.rec_ID;
			end if;
			insert into recRelationshipsCache (rrc_RecID, rrc_SourceRecID, rrc_TargetRecID) values (NEW.rec_ID,srcRecID,trgRecID);
		end if;
		
	if OLD.rec_RecTypeID = 1 AND NOT NEW.rec_RecTypeID = 1 then
			delete ignore from recRelationshipsCache where rrc_RecID = OLD.rec_ID;
		end if;
	end$$

CREATE
DEFINER=`root`@`localhost`
TRIGGER `hdb_dos_3`.`delete_record_trigger`
AFTER DELETE ON `hdb_dos_3`.`Records`
FOR EACH ROW
begin
		set @rec_version := last_insert_id();
	
		if OLD.rec_RecTypeID = 1 then
			delete ignore from recRelationshipsCache where rrc_RecID = OLD.rec_ID;
		end if;
	end$$

CREATE
DEFINER=`root`@`localhost`
TRIGGER `hdb_dos_3`.`insert_Details_precis_trigger`
BEFORE INSERT ON `hdb_dos_3`.`recDetails`
FOR EACH ROW
begin set NEW.dtl_ValShortened = ifnull(liposuction(NEW.dtl_Value), ''); end$$

CREATE
DEFINER=`root`@`localhost`
TRIGGER `hdb_dos_3`.`insert_Details_trigger`
AFTER INSERT ON `hdb_dos_3`.`recDetails`
FOR EACH ROW
begin
		if NEW.dtl_DetailTypeID=5 then 
			update recRelationshipsCache
			
				set rrc_TargetRecID = NEW.dtl_Value
				where rrc_RecID=NEW.dtl_RecID;
		elseif NEW.dtl_DetailTypeID=7 then 
			update recRelationshipsCache
			
				set rrc_SourceRecID = NEW.dtl_Value
				where rrc_RecID=NEW.dtl_RecID;
		end if;
	end$$

CREATE
DEFINER=`root`@`localhost`
TRIGGER `hdb_dos_3`.`pre_update_Details_trigger`
BEFORE UPDATE ON `hdb_dos_3`.`recDetails`
FOR EACH ROW
begin
		if asbinary(NEW.dtl_Geo)=asbinary(OLD.dtl_Geo) then
			set NEW.dtl_Geo = OLD.dtl_Geo;
		end if;
		set NEW.dtl_ValShortened = ifnull(liposuction(NEW.dtl_Value), '');
	end$$

CREATE
DEFINER=`root`@`localhost`
TRIGGER `hdb_dos_3`.`update_Details_trigger`
AFTER UPDATE ON `hdb_dos_3`.`recDetails`
FOR EACH ROW
begin
		if NEW.dtl_DetailTypeID=5 then 
			update recRelationshipsCache
			
				set rrc_TargetRecID = NEW.dtl_Value
				where rrc_RecID=NEW.dtl_RecID;
		elseif NEW.dtl_DetailTypeID=7 then 
		update recRelationshipsCache
				set rrc_SourceRecID = NEW.dtl_Value
			
				where rrc_RecID=NEW.dtl_RecID;
		end if;
		
	end$$

DELIMITER ;