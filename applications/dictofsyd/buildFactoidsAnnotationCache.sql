        use hdb_DoS3;
        DROP TABLE IF EXISTS `recFacctoidsCache`;
        CREATE TABLE `recFacctoidsCache` (

        `rfc_RecID` int(10) unsigned NOT NULL,
        `rfc_SourceRecID` int(10) unsigned,
        `rfc_TargetRecID` int(10) unsigned,
        `rfc_RoleRecID` int(10) unsigned,

        PRIMARY KEY  (`rfc_RecID`),
        KEY `rfc_sourcePtrKey` (`rfc_SourceRecID`),
        KEY `rfc_TargetPtrKey` (`rfc_TargetRecID`),
        KEY `rfc_RolePtrKey` (`rfc_RoleRecID`)
        );
        delete from `recFacctoidsCache` where rfc_RecID>0;
        INSERT INTO `recFacctoidsCache`
        select distinct r.rec_ID, d1.dtl_Value as src, d2.dtl_Value as trg, d3.dtl_Value as role
        from Records r
        left join recDetails d1
        on r.rec_ID=d1.dtl_RecID and d1.dtl_DetailTypeID = 87
        left join recDetails d2
        on r.rec_ID=d2.dtl_RecID and d2.dtl_DetailTypeID = 86
        left join recDetails d3
        on r.rec_ID=d3.dtl_RecID and d3.dtl_DetailTypeID = 88
        where r.rec_RecTypeID = 26 and r.rec_NonOwnerVisibility='public';

        DROP TABLE IF EXISTS `recAnnotationCache`;
        CREATE TABLE `recAnnotationCache` (

        `rac_ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `rac_RecID` int(10) unsigned NOT NULL,
        `rac_EntryRecID` int(10) unsigned,
        `rac_TargetRecID` int(10) unsigned,

        PRIMARY KEY  (`rac_ID`),
        KEY `rac_EntryPtrKey` (`rac_EntryRecID`),
        KEY `rac_TargetPtrKey` (`rac_TargetRecID`)
        );
        delete from `recAnnotationCache` where rac_ID>0;
        INSERT INTO `recAnnotationCache` (rac_RecID,rac_EntryRecID,rac_TargetRecID)
        select distinct r.rec_ID, d1.dtl_Value as entry, d2.dtl_Value as entity
        from Records r
        left join recDetails d1
        on r.rec_ID=d1.dtl_RecID and d1.dtl_DetailTypeID = 42
        left join recDetails d2
        on r.rec_ID=d2.dtl_RecID and d2.dtl_DetailTypeID = 13
        where r.rec_RecTypeID = 15 and r.rec_NonOwnerVisibility='public';
