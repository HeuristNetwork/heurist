-- HEURIST Vsn 3 Build: Relational Constraints Addition

-- Ian Johnson 5 Nov 2010  Last updated 7pm 17/2/11

-- Added additional constraints on defTerms and defRelationshipConstraints 24 Feb
-- TO DO: Need to check through this file that we have covered all required constraints
-- with all the recent changes

-- Can be run from command line logged in as root with
-- mysql -u root -ppassword hdb_databasename < /var/www/htdocs/h3-ij/admin/setup/addReferentialConstraints.sql

-- ----------------------------------------------------------------------------

ALTER TABLE `defTranslations`
ADD CONSTRAINT defTranslations_ibfk_1 FOREIGN KEY (trn_LanguageCode3)
REFERENCES defLanguages (lng_NISOZ3953) ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------

ALTER TABLE `Records`
  ADD CONSTRAINT FOREIGN KEY (rec_RecTypeID) REFERENCES defRecTypes (rty_ID) ON DELETE RESTRICT,
  ADD CONSTRAINT FOREIGN KEY (rec_AddedByUGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE SET NULL,
  ADD CONSTRAINT FOREIGN KEY (rec_OwnerUGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE RESTRICT;

-- ADD CONSTRAINT FOREIGN KEY (rec_RecTypeID) REFERENCES def_RecTypes (rty_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (rec_AddedByUGrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (rec_OwnerUGrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,


ALTER TABLE defDetailTypes
  ADD CONSTRAINT FOREIGN KEY (dty_DetailTypeGroupID) REFERENCES  defDetailTypeGroups (dtg_ID) ON DELETE RESTRICT;
ALTER TABLE defDetailTypes
  ADD CONSTRAINT FOREIGN KEY (dty_DetailTypeGroupID) REFERENCES defDetailTypeGroups (dtg_ID) ON UPDATE CASCADE;


-- ---------------------------------------------------------------------------

-- Next may fail on term IDs pointed to by trm_InverseTermID. Use this query and set missining
-- inverse term IDs to NULL (NOT to 0). Also beware multiple recs with same inverse term
-- none in sandpit4
SELECT `trm_InverseTermId` from defTerms where NOT `trm_InverseTermId` in (select `trm_ID` from defTerms);


-- If this fails due to constraint failure you may need to use this first
-- delete from defTerms where NOT trm_ParentTermId in (select trm_ID from defTerms);
ALTER TABLE defTerms
  ADD CONSTRAINT FOREIGN KEY (trm_ParentTermID) REFERENCES defTerms(trm_ID) ON DELETE RESTRICT;


-- delete from defTerms where NOT trm_InverseTermId in (select trm_ID from defTerms);
ALTER TABLE defTerms
    ADD CONSTRAINT FOREIGN KEY (trm_InverseTermId) REFERENCES defTerms (trm_ID) ON DELETE SET NULL;

-- ADD CONSTRAINT FOREIGN KEY (trm_VocabID) REFERENCES defVocabularies (vcb_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (trm_InverseTermId) REFERENCES defTerms (trm_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE defRecStructure
  ADD CONSTRAINT FOREIGN KEY (rst_RecTypeID) REFERENCES defRecTypes (rty_ID) ON DELETE Cascade,
  ADD CONSTRAINT FOREIGN KEY (rst_DetailtypeID) REFERENCES defDetailTypes (dty_ID) ON DELETE Cascade;

-- ADD CONSTRAINT FOREIGN KEY (rst_RecTypeID) REFERENCES defRecTypes (rty_ID) ON UPDATE CASCADE,-- Fails
-- ADD CONSTRAINT FOREIGN KEY (rst_DetailtypeID) REFERENCES defDetailtypes (dty_ID) ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------

ALTER TABLE  defRelationshipConstraints
  ADD CONSTRAINT FOREIGN KEY (rcs_TermID) REFERENCES defTerms (trm_ID) ON DELETE CASCADE;

Update defRelationshipConstraints Set rcs_SourceRecTypeID=NULL Where rcs_SourceRecTypeID=0;
Update defRelationshipConstraints Set rcs_TargetRecTypeID=NULL Where rcs_TargetRecTypeID=0;
ALTER TABLE  defRelationshipConstraints
  ADD CONSTRAINT FOREIGN KEY (rcs_SourceRecTypeID) REFERENCES defRecTypes (rty_ID) ON DELETE CASCADE,
  ADD CONSTRAINT FOREIGN KEY (rcs_TargetRecTypeID) REFERENCES defRecTypes (rty_ID) ON DELETE CASCADE;

-- NOW USES MULTI-VALUE FIELDS
--  ADD CONSTRAINT FOREIGN KEY (rcs_VocabID) REFERENCES defVocabularies (vcb_ID) ON DELETE RESTRICT;

-- ADD CONSTRAINT FOREIGN KEY (rcs_DetailTypeID) REFERENCES defDetailTypes (dty_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (rcs_SourceRecTypeID) REFERENCES defRecTypes (rty_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (rcs_TargetRecTypeID) REFERENCES defRecTypes (rty_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (rcs_VocabID) REFERENCES defVocabularies (vcb_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE recForwarding
  ADD CONSTRAINT FOREIGN KEY (rfw_NewRecID) REFERENCES Records (rec_ID) ON DELETE RESTRICT;

-- ADD CONSTRAINT FOREIGN KEY (rfw_NewRecID) REFERENCES Records (rec_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE recRelationshipsCache
  ADD CONSTRAINT FOREIGN KEY (rrc_SourceRecID) REFERENCES Records (rec_ID) ON DELETE CASCADE,
  ADD CONSTRAINT FOREIGN KEY (rrc_TargetRecID) REFERENCES Records (rec_ID) ON DELETE CASCADE;

-- ADD CONSTRAINT FOREIGN KEY (rrc_SourcePtr) REFERENCES Records (rec_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (rrc_TargetPtr) REFERENCES Records (rec_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

-- If this fails due to constraint failure you may need to use this first
-- delete from recDetails where NOT dtl_RecID in (select rec_ID from Records);
-- 8 in sandpit
ALTER TABLE recDetails
  ADD CONSTRAINT FOREIGN KEY (dtl_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE;

ALTER TABLE recDetails
  ADD CONSTRAINT FOREIGN KEY (dtl_DetailTypeID) REFERENCES defDetailTypes (dty_ID) ON DELETE RESTRICT;

ALTER TABLE  `recUploadedFiles` ENGINE = INNODB;
ALTER TABLE recDetails
  ADD CONSTRAINT FOREIGN KEY (dtl_UploadedFileID) REFERENCES recUploadedFiles (ulf_ID) ON DELETE RESTRICT;
-- RESTRICT b/c there might be more than one detail pointign to an uploaded file

-- ADD CONSTRAINT FOREIGN KEY (dtl_RecID) REFERENCES Records (rec_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (dtl_DetailTypeID) REFERENCES defDetailTypes (dty_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (dtl_UploadedFileID) REFERENCES recUploadedFiles (ulf_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE recThreadedComments
  ADD CONSTRAINT FOREIGN KEY (cmt_OwnerUgrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE;

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
  ADD CONSTRAINT FOREIGN KEY (cmt_ParentCmtID) REFERENCES recThreadedComments (cmt_ID) ON UPDATE CASCADE,
  ADD CONSTRAINT FOREIGN KEY (cmt_ParentCmtID) REFERENCES recThreadedComments (cmt_ID) ON DELETE RESTRICT,
  ADD CONSTRAINT FOREIGN KEY (cmt_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE;

-- ADD CONSTRAINT FOREIGN KEY (cmt_OwnerUgrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (cmt_RecID) REFERENCES Records (rec_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE recUploadedFiles
  ADD CONSTRAINT FOREIGN KEY (ulf_UploaderUGrpID) REFERENCES sysUGrps (ugr_ID)  ON DELETE RESTRICT;


ALTER TABLE recUploadedFiles
  ADD CONSTRAINT FOREIGN KEY (ulf_MimeExt) REFERENCES defFileExtToMimetype (fxm_Extension) ON UPDATE RESTRICT,
  ADD CONSTRAINT FOREIGN KEY (ulf_MimeExt) REFERENCES defFileExtToMimetype (fxm_Extension) ON DELETE RESTRICT;

    -- ADD CONSTRAINT FOREIGN KEY (ulf_UploaderUGrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

 ALTER TABLE sysUsrGrpLinks
  ADD CONSTRAINT FOREIGN KEY (ugl_UserID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,
  ADD CONSTRAINT FOREIGN KEY (ugl_GroupID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE;

-- ADD CONSTRAINT FOREIGN KEY (ugl_UserID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (ugl_GroupID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE usrBookmarks
  ADD CONSTRAINT FOREIGN KEY (bkm_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE;


-- If this fails due to constraint failure you may need to use this first
-- 133 in sandpit @ 4/4/11
-- delete from usrBookmarks where NOT bkm_RecId in (select rec_ID from Records);

ALTER TABLE usrBookmarks
  ADD CONSTRAINT FOREIGN KEY (bkm_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE;
-- while this does allow someone to delete other people's bookmarks, record deletion is only available to
-- system adminstrators, and the function checks to see if the record has been bookmarked and warns

-- ADD CONSTRAINT FOREIGN KEY (bkm_UGrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (bkm_RecID) REFERENCES Records (rec_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE usrHyperlinkFilters
  ADD CONSTRAINT FOREIGN KEY (hyf_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE;

-- ADD CONSTRAINT FOREIGN KEY (hyf_UGrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE usrRecTagLinks
  ADD CONSTRAINT FOREIGN KEY (rtl_TagID) REFERENCES usrTags (tag_ID) ON DELETE CASCADE;


-- If this fails due to constraint failure you may need to use this first
-- 14 in sandpit @ 4/4/11
-- delete from usrRecTagLinks where NOT rtl_RecId in (select rec_ID from Records);

ALTER TABLE usrRecTagLinks
  ADD CONSTRAINT FOREIGN KEY (rtl_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE;
-- as with bookmarks, only sysadmins can delete records, but they can delete records others have tagged
-- and at vsn 3.0 there is only a check on bookmarking, not on tagging; that works for individual
-- users but might allow deletion of workgroup-only tagged records without warning

-- ADD CONSTRAINT FOREIGN KEY (rtl_TagID) REFERENCES usrTags (tag_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (rtl_RecID) REFERENCES Records (rec_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE usrRecentRecords
  ADD CONSTRAINT FOREIGN KEY (rre_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,
  ADD CONSTRAINT FOREIGN KEY (rre_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE;

-- ADD CONSTRAINT FOREIGN KEY (rre_UGrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (rre_RecID) REFERENCES Records (rec_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE usrReminders
  ADD CONSTRAINT FOREIGN KEY (rem_RecID) REFERENCES Records (rec_ID) ON DELETE CASCADE,
  ADD CONSTRAINT FOREIGN KEY (rem_OwnerUGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE;

ALTER TABLE usrReminders
  ADD CONSTRAINT FOREIGN KEY (rem_ToWorkgroupID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE,
  ADD CONSTRAINT FOREIGN KEY (rem_ToUserID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE;
-- Steve wants reminders retained even if the recipient is deleted so they can be reallocated to a new one
-- Ian thinks this is  not a good idea, for the one or two people who might ever use this we will need a
-- to write a reallocation interface and/or cleanout routine. In fact, reallocating the user to the
-- new person is no less obscure a methodology and takes no programming

-- ADD CONSTRAINT FOREIGN KEY (rem_RecID) REFERENCES Records (rec_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY rem_OwnerID() REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (rem_ToWorkgroupID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (rem_ToUserID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE usrRemindersBlockList
  ADD CONSTRAINT FOREIGN KEY (rbl_RemID) REFERENCES usrReminders (rem_ID) ON DELETE CASCADE,
  ADD CONSTRAINT FOREIGN KEY (rbl_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE;

-- ADD CONSTRAINT FOREIGN KEY (rbl_RemID) REFERENCES usrReminders (rem_ID) ON UPDATE CASCADE,
-- ADD CONSTRAINT FOREIGN KEY (rbl_UGrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE usrSavedSearches
  ADD CONSTRAINT FOREIGN KEY (svs_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE;

-- ADD CONSTRAINT FOREIGN KEY (svs_UGrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,

-- ---------------------------------------------------------------------------

ALTER TABLE usrTags
  ADD CONSTRAINT FOREIGN KEY (tag_UGrpID) REFERENCES sysUGrps (ugr_ID) ON DELETE CASCADE;

-- ADD CONSTRAINT FOREIGN KEY (tag_UGrpID) REFERENCES sysUGrps (ugr_ID) ON UPDATE CASCADE,

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

