-- Created by Artem Osmakov 2014-11-12
-- Last updated 

DROP TABLE IF EXISTS recLinks;

CREATE TABLE recLinks (
  rl_ID   int(10) unsigned NOT NULL auto_increment COMMENT 'Primary key',
  rl_SourceID int(10) unsigned NOT NULL COMMENT 'Source record ID',
  rl_TargetID int(10) unsigned NOT NULL COMMENT 'Target record ID',
  rl_RelationID int(10) unsigned        COMMENT 'Realtionship record ID',
  rl_RelationTypeID int(10) unsigned    COMMENT 'Realtionship type - defTerms.trm_ID',
  rl_DetailTypeID int(10) unsigned      COMMENT 'Record Pointer detail type ID',
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

-- IMPORTANT: recreate triggers!!!
 