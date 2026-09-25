-- Source version: 1.3.19
-- Target version: 1.4.0
-- Safety rating: SAFE
-- Description: Advance the stable 1.3.19 schema past the unused 1.4 holding file.

-- The generic DBUpgrade_1.3.0_to_1.4.0.sql says it is a holding pen and must
-- not be processed. Version 1.5.0 needs a deterministic, no-surprise route from
-- the currently required 1.3.19 schema, so this bridge changes version only.
UPDATE sysIdentification
   SET sys_dbVersion=1, sys_dbSubVersion=4, sys_dbSubSubVersion=0
 WHERE 1=1;
