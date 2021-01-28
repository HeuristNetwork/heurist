-- Created by Artem Osmakov 2014-11-12
-- Last updated 

DROP TABLE IF EXISTS recLinks;

CREATE TABLE recLinks (
  rl_ID   int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key',
  rl_SourceID int(10) unsigned NOT NULL COMMENT 'Source record ID',
  rl_TargetID int(10) unsigned NOT NULL COMMENT 'Target record ID',
  rl_RelationID int(10) unsigned        COMMENT 'Realtionship record ID',
  rl_RelationTypeID int(10) unsigned    COMMENT 'Realtionship type - defTerms.trm_ID',
  rl_DetailTypeID int(10) unsigned      COMMENT 'Pointer (Resource) detail type ID',
  rl_DetailID int(10) unsigned          COMMENT 'Pointer Detail type',
  PRIMARY KEY  (rl_ID),
  KEY rl_SourcePtrKey (rl_SourceID),
  KEY rl_TargetPtrKey (rl_TargetID),
  KEY rl_RelationKey (rl_RelationID),
  KEY rl_DetailKey (rl_DetailID)
) ENGINE=InnoDB COMMENT='A cache for records links (pointers and relationships) to speed access';

-- add relationships
insert into recLinks (rl_SourceID, rl_TargetID, rl_RelationID, rl_RelationTypeID)
select d1.dtl_Value, d2.dtl_Value, rd.rec_ID, d3.dtl_Value from Records rd 
left join recDetails d1 on d1.dtl_RecID = rd.rec_ID and d1.dtl_DetailTypeID = 7
left join recDetails d2 on d2.dtl_RecID = rd.rec_ID and d2.dtl_DetailTypeID = 5
left join recDetails d3 on d3.dtl_RecID = rd.rec_ID and d3.dtl_DetailTypeID = 6
where rd.rec_RecTypeID=1 and d1.dtl_Value is not null and d2.dtl_Value is not null;

-- add pointers
insert into recLinks (rl_SourceID, rl_TargetID, rl_DetailTypeID, rl_DetailID)
select rd.rec_ID, bd.dtl_Value, dty_ID, bd.dtl_ID from Records rd, defDetailTypes, recDetails bd 
where rd.rec_RecTypeID!=1 and bd.dtl_RecID=rd.rec_ID and dty_ID=bd.dtl_DetailTypeID and dty_Type="resource" and bd.dtl_Value is not null; 

-- modify triggers
 
DELIMITER $$

--   ON INSERT DETAILS --------------------------------------------

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

--   ON UPDATE DETAILS --------------------------------------------

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
            -- need to also save the RecTypeID for the record
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
                set rrc_SourceRecID = NEW.dtl_Value
-- need to also save the RecTypeID for the record
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

--   ON DELETE DETAILS --------------------------------------------

    DROP TRIGGER IF EXISTS delete_detail_trigger$$

    CREATE
    DEFINER=CURRENT_USER 
    TRIGGER `delete_detail_trigger`
    AFTER DELETE ON `recDetails`
    FOR EACH ROW
    begin
        delete ignore from recLinks where rl_DetailID=OLD.dtl_ID;
    end$$

--   ON INSERT RECORD --------------------------------------------

    DROP TRIGGER IF EXISTS insert_record_trigger$$

    CREATE
    DEFINER=CURRENT_USER 
    TRIGGER `insert_record_trigger`
    AFTER INSERT ON `Records`
    FOR EACH ROW
    begin
--        declare relRT integer;
--        select rty_ID into relRT
--            from defRecTypes
--            where rty_OriginatingDBID = 3 and rty_IDInOriginatingDB = 52 order by rty_ID desc limit 1;
-- need to change this to check the rectype type = relationship
-- 1 = record relationship
    insert into usrRecentRecords (rre_UGrpID, rre_RecID, rre_Time)
                                values (@logged_in_user_id, NEW.rec_ID, now());
    set @rec_id := last_insert_id(NEW.rec_ID);
--        if NEW.rec_RecTypeID = relRT then
        if NEW.rec_RecTypeID = 1 then
        begin
--  need to also save relationship records RecTypeID
            insert into recRelationshipsCache (rrc_RecID, rrc_SourceRecID, rrc_TargetRecID) values (NEW.rec_ID,NEW.rec_ID,NEW.rec_ID);
            
            insert into recLinks (rl_SourceID, rl_TargetID, rl_RelationID)
            values (NEW.rec_ID, NEW.rec_ID, NEW.rec_ID);
        end;    
        end if;
    end$$

--   ON DELETE RECORD --------------------------------------------

    DROP TRIGGER IF EXISTS delete_record_trigger$$

    CREATE
    DEFINER=CURRENT_USER 
    TRIGGER `delete_record_trigger`
    AFTER DELETE ON `Records`
    FOR EACH ROW
    begin
--        insert into archiveRecords (arec_ID, arec_UGrpID, arec_Date)
--            values (OLD.rec_ID, @logged_in_user_id, now());

-- 14/2/11 Ian: Do we need this value set by the previous insert?
        set @rec_version := last_insert_id();
--        declare relRT integer;
--        select rty_ID into relRT
--            from defRecTypes
--            where rty_OriginatingDBID = 3 and rty_IDInOriginatingDB = 52 order by rty_ID desc limit 1;

    delete from usrRecentRecords where rre_RecID = OLD.rec_ID;

-- need to change this to check the rectype type = relationship
--        if OLD.rec_RecTypeID = relRT then
        if OLD.rec_RecTypeID = 1 then
            delete ignore from recRelationshipsCache where rrc_RecID = OLD.rec_ID;
        end if;
        
        delete ignore from recLinks where rl_RelationID=OLD.rec_ID or rl_SourceID=OLD.rec_ID or rl_TargetID=OLD.rec_ID;
    end$$

--   ON AFTER UPDATE RECORD --------------------------------------------
    
    DROP TRIGGER IF EXISTS usrRecentRecords_updater$$

    CREATE
    DEFINER=CURRENT_USER 
    TRIGGER `usrRecentRecords_updater`
    AFTER UPDATE ON `Records`
    FOR EACH ROW
    begin
--        declare relRT integer;
        declare srcRecID integer;
        declare trgRecID integer;
--        declare relSrcDT integer;
--        declare relTrgDT integer;
        if @suppress_update_trigger is null then
            insert into usrRecentRecords (rre_UGrpID, rre_RecID, rre_Time)
                values (@logged_in_user_id, NEW.rec_ID, now())
                on duplicate key update rre_Time = now();
        end if;
--        select dty_ID into relSrcDT
--            from defDetailTypes
--            where dty_OriginatingDBID = 3 and dty_IDInOriginatingDB = 202
--            order by dty_ID desc limit 1;
--        select dty_ID into relTrgDT
--            from defDetailTypes
--            where dty_OriginatingDBID = 3 and dty_IDInOriginatingDB = 199
--            order by dty_ID desc limit 1;
--        select rty_ID into relRT
--            from defRecTypes
--            where rty_OriginatingDBID = 3 and rty_IDInOriginatingDB = 52 order by rty_ID desc limit 1;
-- if change the records type from something else to relation insert cache value
--        if NEW.rec_RecTypeID = relRT AND NOT OLD.rec_RecTypeID = relRT then
--            select dtl_Value into srcRecID
--                from recDetails
--                where dtl_DetailTypeID = relSrcDT and OLD.rec_ID=dtl_RecID order by dtl_Value desc limit 1;
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
--                where dtl_DetailTypeID = relTrgDT and OLD.rec_ID=dtl_RecID order by dtl_Value desc limit 1;
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
--        if OLD.rec_RecTypeID = relRT AND NOT NEW.rec_RecTypeID = relRT then
        if OLD.rec_RecTypeID = 1 AND NOT NEW.rec_RecTypeID = 1 then
        begin
            delete ignore from recRelationshipsCache where rrc_RecID = OLD.rec_ID;
            delete ignore from recLinks where rl_RelationID=OLD.rec_ID;
        end;    
        end if;
    end$$
    
DELIMITER ;
