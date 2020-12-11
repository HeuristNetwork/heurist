<?php
// Be sure to use this quote  `  not the upright quote, or you will jsut get field names in place of data
$flds = "`trm_ID`, `trm_Label`, `trm_InverseTermId`,
            `trm_Description`, `trm_Status`,
            `trm_OriginatingDBID`,`trm_NameInOriginatingDB`,`trm_IDInOriginatingDB`,
            `trm_AddedByImport`,`trm_IsLocalExtension`, `trm_Domain`, `trm_OntID`,
            `trm_ChildCount`, `trm_ParentTermID`, `trm_Depth`,`trm_Modified`, `trm_LocallyModified`, `trm_Code`,
            `trm_SemanticReferenceURL`, `trm_IllustrationURL`"; //, `trm_VocabularyGroupID` 
            // 20/1/14: Order aligned with order in database tables since 2012 to resolve problems of order incompatibility 
            // between versions. Also updating delivery/expectation order in all VS2 versions of H3 (h3, h3-alpha, h3-beta)
            
            // in waiting 1/10/13 for DB version 1.2.0 for alignment with FAIMS
            // trm_ReferenceURL`, `trm_IllustrationURL` "; 
            
            // Note: You must also add any new fields to the print format statement in getDBStructureAsSQL

?>