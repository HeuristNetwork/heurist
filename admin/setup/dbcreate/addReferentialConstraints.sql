-- HEURIST Vsn 3 Build: Relational Constraints Addition

-- Ian Johnson 5 Nov 2010  Last updated 7pm 19/9/11

-- Added additional constraints on defTerms and defRelationshipConstraints 24 Feb

-- Named all constraints explicitely to aid diff-ing with reference database

-- Can be run from PHPMyAdmin or command line logged in as root with
-- mysql -u root -ppassword hdb_databasename < /var/www/html/heurist/admin/setup/dbcreate/addReferentialConstraints.sql

-- Additions of CASCADE ON UPDATE 19/9/11
-- These should not restrict any existing actions but allow more flexibility in updating database
--  rst_RectypeID, rst_DetailTypeID
--  rcs_SourceRectypeID, rcs_TargetRectypeID, rcs_TermID
--  trm_InverseTermID, trm_ParentTermID + change to SET NULL ON DELETE for trm_ParentTermID
--  dtl_RecID, dtl_DetailTypeID, dtl_UploadedFileID
-- rfw_NewRecID
-- rec_RectypeID, rec_AddedByUGrpID, rec_OwnerUGrpID
-- rl_SourceID, rl_TargetID
-- cmt_OwnerUGrpID, cmt_RecID
-- ulf_UploaderUGrpID (cascade on update, set null on delete, changed to allow null value and default NULL), ulf_MimeExt
-- ugl_UserID, ugl_GroupID
-- bkm_UGrpID, bkm_RecID
-- hyf_UgrpID
-- rre_UGrpID, rre_RecID
-- rtl_TagID, rtl_RecID
-- rem_RecID, remOwnerUGrpID, rem_ToWorkgroupID, rem_ToUserID
-- rbl_RemID, rbl_UGrpID
-- svs_UGrpID
-- tag_UGrpID

-- ----------------------------------------------------------------------------

ALTER TABLE `defTranslations` ADD CONSTRAINT fk_trn_LanguageCode3 FOREIGN KEY (trn_LanguageCode3) REFERENCES defLanguages (lng_NISOZ3953) ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------

ALTER TABLE `Records`
  ADD CONSTRAINT fk_rec_RecTypeID FOREIGN KEY (rec_RecTypeID) REFERENCES defRecTypes (rty_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT fk_rec_AddedByUGrpID FOREIGN KEY (rec_AddedByUGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT fk_rec_OwnerUGrpID FOREIGN KEY (rec_OwnerUGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE defDetailTypes
  ADD CONSTRAINT fk_dty_DetailTypeGroupID_1 FOREIGN KEY (dty_DetailTypeGroupID) REFERENCES  defDetailTypeGroups (dtg_ID) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_dty_DetailTypeGroupID_2 FOREIGN KEY (dty_DetailTypeGroupID) REFERENCES defDetailTypeGroups (dtg_ID) ON UPDATE CASCADE;

ALTER TABLE defRecTypes
  ADD CONSTRAINT fk_rty_RecTypeGroupID_1 FOREIGN KEY (rty_RecTypeGroupID) REFERENCES  defRecTypeGroups (rtg_ID) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_rty_RecTypeGroupID_2 FOREIGN KEY (rty_RecTypeGroupID) REFERENCES defRecTypeGroups (rtg_ID) ON UPDATE CASCADE;
  
-- ---------------------------------------------------------------------------

-- Next may fail on term IDs pointed to by trm_InverseTermID. Use this query and set missining
-- inverse term IDs to NULL (NOT to 0). Also beware multiple recs with same inverse term
-- none in sandpit4
SELECT `trm_InverseTermId` from defTerms where NOT `trm_InverseTermId` in (select `trm_ID` from defTerms);
-- This is a precaution since some old databases have invalid 0 values instead of null
Update defTerms set trm_ParentTermID=NULL where trm_ParentTermID=0;
Update defTerms set trm_InverseTermID=NULL where trm_InverseTermID=0;

-- If this fails due to constraint failure you may need to use this first
-- delete from defTerms where NOT trm_ParentTermId in (select trm_ID from defTerms);
ALTER TABLE defTerms
  ADD CONSTRAINT fk_trm_ParentTermID FOREIGN KEY (trm_ParentTermID) REFERENCES defTerms(trm_ID) ON DELETE SET NULL ON UPDATE CASCADE,
-- delete from defTerms where NOT trm_InverseTermId in (select trm_ID from defTerms);
    ADD CONSTRAINT fk_trm_InverseTermId FOREIGN KEY (trm_InverseTermId) REFERENCES defTerms (trm_ID) ON DELETE SET NULL ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------

ALTER TABLE defRecStructure
  ADD CONSTRAINT fk_rst_RecTypeID FOREIGN KEY (rst_RecTypeID) REFERENCES defRecTypes (rty_ID) ON DELETE Cascade ON UPDATE CASCADE,
  ADD CONSTRAINT fk_rst_DetailtypeID FOREIGN KEY (rst_DetailtypeID) REFERENCES defDetailTypes (dty_ID) ON DELETE Cascade ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------

Update defRelationshipConstraints Set rcs_SourceRecTypeID=NULL Where rcs_SourceRecTypeID=0;
Update defRelationshipConstraints Set rcs_TargetRecTypeID=NULL Where rcs_TargetRecTypeID=0;

ALTER TABLE  defRelationshipConstraints
  ADD CONSTRAINT fk_rcs_TermID FOREIGN KEY (rcs_TermID) REFERENCES defTerms (trm_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_rcs_SourceRecTypeID FOREIGN KEY (rcs_SourceRecTypeID) REFERENCES defRecTypes (rty_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_rcs_TargetRecTypeID FOREIGN KEY (rcs_TargetRecTypeID) REFERENCES defRecTypes (rty_ID) ON DELETE CASCADE ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------

ALTER TABLE recForwarding
  ADD CONSTRAINT fk_rfw_NewRecID FOREIGN KEY (rfw_NewRecID) REFERENCES Records (rec_ID) ON DELETE RESTRICT  ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------

ALTER TABLE recLinks
  ADD CONSTRAINT fk_rl_SourceID FOREIGN KEY (rl_SourceID) REFERENCES Records (rec_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_rl_TargetID FOREIGN KEY (rl_TargetID) REFERENCES Records (rec_ID) ON DELETE CASCADE ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------

-- If this fails due to constraint failure you may need to use this first
-- delete from recDetails where NOT dtl_RecID in (select rec_ID from Records);
-- 8 in sandpit
-- ALTER TABLE  `recUploadedFiles` ENGINE = INNODB;
ALTER TABLE recDetails
  ADD CONSTRAINT fk_dtl_RecID FOREIGN KEY (dtl_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_dtl_DetailTypeID FOREIGN KEY (dtl_DetailTypeID) REFERENCES defDetailTypes (dty_ID) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT fk_dtl_UploadedFileID FOREIGN KEY (dtl_UploadedFileID) REFERENCES recUploadedFiles (ulf_ID) ON DELETE RESTRICT ON UPDATE CASCADE;
-- RESTRICT b/c there might be more than one detail pointign to an uploaded file

-- ---------------------------------------------------------------------------

-- There are 50 without a valid parent, of which 35 are 0 - should this be null for no parent?
update `recThreadedComments` set `cmt_ParentCmtID`= NULL where `cmt_ParentCmtID`=0;

-- Now there are 15 with invalid parent comment poitners, set these to null
-- can't be done directly by a query, rejects modification to the select-from table
CREATE TEMPORARY TABLE NoParent select * from recThreadedComments
    where not cmt_ParentCmtID in (select cmt_ID from recThreadedComments);
Update NoParent Set cmt_ParentCmtID=Null;

-- If this fails due to constraint failure you may need to use this first
-- delete from recThreadedComments where NOT cmt_ParentCmtId in (select cmt_ID from recThreadedComments);
-- however, unlike the ones above it doesn't seem to allow this so change to select * from ...
-- and then delete the selected records. Then repeat and update the cmt_ParentCmtId to null on any remaining
ALTER TABLE recThreadedComments
  ADD CONSTRAINT fk_cmt_OwnerUgrpID FOREIGN KEY (cmt_OwnerUgrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_cmt_ParentCmtID FOREIGN KEY (cmt_ParentCmtID) REFERENCES recThreadedComments (cmt_ID) ON UPDATE CASCADE ON DELETE RESTRICT,
  ADD CONSTRAINT fk_cmt_RecID FOREIGN KEY (cmt_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------
UPDATE recUploadedFiles left join sysUGrps on ulf_UploaderUGrpID=ugr_ID set ulf_UploaderUGrpID=null where ugr_ID is null  and ulf_ID>0;

ALTER TABLE recUploadedFiles
  ADD CONSTRAINT fk_ulf_UploaderUGrpID FOREIGN KEY (ulf_UploaderUGrpID) REFERENCES sysUGrps (ugr_ID)  ON DELETE set null ON UPDATE CASCADE,
  ADD CONSTRAINT fk_ulf_MimeExt FOREIGN KEY (ulf_MimeExt) REFERENCES defFileExtToMimetype (fxm_Extension) ON DELETE RESTRICT ON UPDATE CASCADE;

  -- ---------------------------------------------------------------------------

 ALTER TABLE sysUsrGrpLinks
  ADD CONSTRAINT fk_ugl_UserID FOREIGN KEY (ugl_UserID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_ugl_GroupID FOREIGN KEY (ugl_GroupID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------
DELETE FROM usrBookmarks where bkm_ID>0 and NOT bkm_UGrpID in (select ugr_ID from sysUGrps);

ALTER TABLE usrBookmarks
  ADD CONSTRAINT fk_bkm_UGrpID FOREIGN KEY (bkm_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE ON UPDATE CASCADE,
-- If this fails due to constraint failure you may need to use this first
-- 133 in sandpit @ 4/4/11
-- delete from usrBookmarks where NOT bkm_RecId in (select rec_ID from Records);
  ADD CONSTRAINT fk_bkm_RecID FOREIGN KEY (bkm_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE ON UPDATE CASCADE;


-- ---------------------------------------------------------------------------

ALTER TABLE usrHyperlinkFilters
  ADD CONSTRAINT fk_hyf_UGrpID FOREIGN KEY (hyf_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------

ALTER TABLE usrRecTagLinks
  ADD CONSTRAINT fk_rtl_TagID FOREIGN KEY (rtl_TagID) REFERENCES usrTags (tag_ID) ON DELETE CASCADE ON UPDATE CASCADE,
-- If this fails due to constraint failure you may need to use this first
-- 14 in sandpit @ 4/4/11
-- delete from usrRecTagLinks where NOT rtl_RecId in (select rec_ID from Records);
  ADD CONSTRAINT fk_rtl_RecID FOREIGN KEY (rtl_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE ON UPDATE CASCADE;
-- as with bookmarks, only sysadmins can delete records, but they can delete records others have tagged
-- and at vsn 3.0 there is only a check on bookmarking, not on tagging; that works for individual
-- users but might allow deletion of workgroup-only tagged records without warning

-- ---------------------------------------------------------------------------

ALTER TABLE usrReminders
  ADD CONSTRAINT fk_rem_RecID FOREIGN KEY (rem_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_rem_OwnerUGrpID FOREIGN KEY (rem_OwnerUGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_rem_ToWorkgroupID FOREIGN KEY (rem_ToWorkgroupID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_rem_ToUserID FOREIGN KEY (rem_ToUserID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE ON UPDATE CASCADE;
-- Steve wants reminders retained even if the recipient is deleted so they can be reallocated to a new one
-- Ian thinks this is  not a good idea, for the one or two people who might ever use this we will need a
-- to write a reallocation interface and/or cleanout routine. In fact, reallocating the user to the
-- new person is no less obscure a methodology and takes no programming

-- ---------------------------------------------------------------------------

ALTER TABLE usrRemindersBlockList
  ADD CONSTRAINT fk_rbl_RemID FOREIGN KEY (rbl_RemID) REFERENCES usrReminders (rem_ID) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT fk_rbl_UGrpID FOREIGN KEY (rbl_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------
DELETE FROM usrSavedSearches where svs_ID>0 AND NOT svs_UGrpID in (select ugr_ID from sysUGrps);
ALTER TABLE usrSavedSearches
  ADD CONSTRAINT fk_svs_UGrpID FOREIGN KEY (svs_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------

ALTER TABLE usrTags
  ADD CONSTRAINT fk_tag_UGrpID FOREIGN KEY (tag_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE ON UPDATE CASCADE;

-- -----------------------------------------------------------------------------

-- TO DO: cannot BE CONSTRAINED UNTIL THE USER AND GROUP IDS ARE UPDATES (+100 AND +10 RESPECTIVELY))
-- not yet done for sandpit 3 @ 21/1/11

-- ALTER TABLE woot_ChunkPermissions
--   ADD CONSTRAINT FOREIGN KEY (wprm_chunkid) REFERENCES woot_Chunks (wchnk_id) ON UPDATE CASCADE,
--   ADD CONSTRAINT FOREIGN KEY (wprm_chunkid) REFERENCES woot_Chunks (wchnk_id) ON DELETE RESTRICT,
-- ADD CONSTRAINT FOREIGN KEY (wprm_UGrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (wprm_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE RESTRICT,
-- ADD CONSTRAINT FOREIGN KEY (wprm_groupid) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (wprm_groupid) REFERENCES sysUGrps (ugr_ID) ON DELETE RESTRICT,
-- ADD CONSTRAINT FOREIGN KEY (wprm_creatorid) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (wprm_creatorid) REFERENCES sysUGrps (ugr_ID) ON DELETE RESTRICT;
--
-- ALTER TABLE woot_Chunks
-- ADD CONSTRAINT FOREIGN KEY (wchnk_woot_id) REFERENCES woot_records (woot_id) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (wchnk_woot_id) REFERENCES woot_records (woot_id) ON DELETE RESTRICT,
-- ADD CONSTRAINT FOREIGN KEY (wchnk_owner) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (wchnk_owner) REFERENCES sysUGrps (ugr_ID) ON DELETE RESTRICT,
-- ADD CONSTRAINT FOREIGN KEY (wchnk_editor) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (wchnk_editor) REFERENCES sysUGrps (ugr_ID) ON DELETE RESTRICT;
--
-- ALTER TABLE woot_record_permissions
-- ADD CONSTRAINT FOREIGN KEY (wrprm_woot_id) REFERENCES woot_records (woot_id) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (wrprm_woot_id) REFERENCES woot_records (woot_id) ON DELETE RESTRICT,
-- ADD CONSTRAINT FOREIGN KEY (wrprm_UGrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (wrprm_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE RESTRICT,
-- ADD CONSTRAINT FOREIGN KEY (wrprm_group_id) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (wrprm_group_id) REFERENCES sysUGrps (ugr_ID) ON DELETE RESTRICT,
-- ADD CONSTRAINT FOREIGN KEY (wrprm_creator) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (wrprm_creator) REFERENCES sysUGrps (ugr_ID) ON DELETE RESTRICT;
--
-- ALTER TABLE woot_records
-- ADD CONSTRAINT FOREIGN KEY (woot_creator) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (woot_creator) REFERENCES sysUGrps (ugr_ID) ON DELETE RESTRICT;
--
-- ALTER TABLE archive_rec_Details
-- ADD CONSTRAINT FOREIGN KEY (ard_record_id) REFERENCES Records (rec_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (ard_record_id) REFERENCES Records (rec_ID) ON DELETE RESTRICT,
-- -- This appears to be the actual primary key of the details table
-- --  ADD CONSTRAINT FOREIGN KEY (ard_id) REFERENCES defRecTypes (rty_ID) ON UPDATE CASCADE,
-- --  ADD CONSTRAINT FOREIGN KEY (ard_id) REFERENCES defRecTypes (rty_ID) ON DELETE RESTRICT,
-- ADD CONSTRAINT FOREIGN KEY (ard_DetailTypeID) REFERENCES defDetailTypes (dty_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (ard_DetailTypeID) REFERENCES defDetailTypes (dty_ID) ON DELETE RESTRICT,
-- ADD CONSTRAINT FOREIGN KEY (ard_file_id) REFERENCES recUploadedFiles (ulf_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (ard_file_id) REFERENCES recUploadedFiles (ulf_ID) ON DELETE RESTRICT;
--
-- ------------------------------------------------------------------------------

-- ARCHIVE RECORDS SHOULD NOT BE CONSTRAINED
-- they are simply a dump of old information and constraing against our live info will just caused headaches.
-- What we do need though is to archive changes to detail types, ontologies, vocabs, terms etc. as well as users,
-- and bookmark information, if we are to have a proper archive

