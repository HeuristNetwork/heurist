# Directory: import/biblio

## Overview
This directory contains files related to synchronizing bibliographic data with Zotero.

## Key files
- `syncZotero.php`: Handles synchronization with Zotero (zotero.org). Syncs Heurist database with Zotero group or user items. 
- `zoteroMap.xml`: XML file that defines the mapping between Zotero fields and Heurist fields for the synchronization process handled by syncZotero.php.

# Corrections suggested by ChatGPT  5.6 Sol lite 19/7/26

All 15 active record types now include every field in Zotero’s current API schema. 
Existing numeric Heurist IDs were preserved; 
120 currently unmapped fields/creator roles now use xxx-xxxx.

I also corrected obvious errors such as:

abstract → abstractNote
accessed → accessDate
titleSeries → series
medium → artworkMedium
library Catalog → libraryCatalog
incorrect uses of url for Rights
duplicate fields and repeated Item Type entries
missing/current creator roles

Remaining questionable legacy entries, retained because they may reflect intentional Heurist behaviour:

version is not a current Zotero field in several types.
Email title is invalid; Zotero uses subject.
Invalid creator roles:
Conference Paper: publisher
Artwork: organisation
Document: publisher
Letter and Manuscript: editor

Conference Paper has non-standard cslType="book_chapter"; current CSL normally uses paper-conference.

The header still identifies Zotero 3.0.11 and January 2013.

Only 15 Zotero types are active. Another 23 current types are absent or only represented in the large commented-out legacy section, including Dataset, Dictionary Entry, Interview, Map, Patent, Preprint, Presentation and Standard.

The XML parses successfully and git diff --check reports no formatting errors.
